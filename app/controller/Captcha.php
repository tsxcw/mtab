<?php

namespace app\controller;

use app\BaseController;
use think\facade\Cache;
use think\response\Json;

/**
 * 滑块拼图验证码控制器
 * 服务端用 GD 现场生成背景图与拼图块，只把图片下发给前端，缺口位置留在服务端
 * 一次一码：同一张图只能提交一次，无论对错都立即作废
 */
class Captcha extends BaseController
{
    /** 验证码在缓存中的有效期（秒） */
    public const CAPTCHA_TTL = 300;

    /** 旧版字符验证码的有效期（秒），与旧实现保持一致 */
    private const LEGACY_TTL = 300;

    /** 旧版字符验证码的字符集，去掉易混淆的 0/1/i/l/o */
    private const LEGACY_CHARS = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';

    /** 同一 IP 每分钟最多获取的验证码数量 */
    public const GENERATE_LIMIT_PER_MINUTE = 30;

    /** 同一 IP 每小时最多获取的验证码数量 */
    public const GENERATE_LIMIT_PER_HOUR = 300;

    /** 同一 IP 在失败窗口内允许的最大校验失败次数 */
    public const CHECK_FAIL_LIMIT = 8;

    /** 校验失败次数的统计窗口（秒） */
    public const CHECK_FAIL_WINDOW = 600;

    /** 验证码画布宽度（像素） */
    private const IMAGE_WIDTH = 300;

    /** 验证码画布高度（像素） */
    private const IMAGE_HEIGHT = 150;

    /** 拼图块边长（像素） */
    private const PIECE_SIZE = 44;

    /** 拼图块与画布边缘的最小间距（像素） */
    private const PIECE_MARGIN = 6;

    /** 允许的横向对齐误差（像素） */
    private const PIECE_TOLERANCE = 3;

    /** 干扰缺口与真实缺口的最小水平间距（像素） */
    private const DECOY_GAP = 72;

    /** 干扰缺口与真实缺口的最小垂直间距（像素） */
    private const DECOY_VERTICAL_GAP = 26;

    /** 背景平均亮度下限（0-255），低于该值整体提亮 */
    private const MIN_AVERAGE_LUMA = 105;

    /** 背景平均亮度上限（0-255），高于该值整体压暗 */
    private const MAX_AVERAGE_LUMA = 190;

    /** 背景素材目录（相对 public 目录），每次随机取一张 */
    private const BACKGROUND_DIR = 'static/captcha-backgrounds';

    /**
     * 旧版图形验证码（字符型）
     * 浏览器扩展自动更新有滞后，未更新的客户端仍在请求本接口，因此保留旧协议：
     * 返回 captcha_key 与 captcha_img，用户登录时提交 4 位字符
     */
    public function generate(): Json
    {
        // 新旧接口共用同一套获取限流
        if (($limited = $this->guardGenerateLimit()) !== null) {
            return $limited;
        }

        // 旧协议 key 形如 captcha_6af3cce1689087.75585697，未更新客户端按此格式回传
        $key = uniqid('captcha_', true);
        // 随机 4 位字符验证码
        $code = $this->randomCode(4);
        // 旧实现直接以 key 本身作为缓存键，这里保持一致，滚动发布期间旧缓存依然有效
        Cache::set($key, strtolower($code), self::LEGACY_TTL);

        return $this->success('ok', [
            'captcha_key' => $key,
            'captcha_img' => $this->toDataUri($this->createLegacyImage($code)),
        ]);
    }

    /**
     * 生成滑块拼图验证码（新版前端）
     * 返回 captcha_key、captcha_nonce 与两张 base64 图片（带缺口的背景图、拼图块），答案只存在服务端
     */
    public function puzzle(): Json
    {
        // 新旧接口共用同一套获取限流
        if (($limited = $this->guardGenerateLimit()) !== null) {
            return $limited;
        }

        // 验证码标识：随机且不可枚举，前端回传后用于查找答案
        $key = bin2hex(random_bytes(16));

        // 画布与拼图块尺寸
        $width = self::IMAGE_WIDTH;
        $height = self::IMAGE_HEIGHT;
        $pieceSize = self::PIECE_SIZE;

        // 缺口可出现的横向范围：左侧至少留一个块宽，保证必须拖动才可能对齐
        $minHoleX = $pieceSize + self::PIECE_MARGIN;
        // 右侧同样留边，保证拼图块完整落在画布内
        $maxHoleX = $width - $pieceSize - self::PIECE_MARGIN;
        // 缺口左上角横坐标，这就是本张验证码的答案
        $holeX = random_int($minHoleX, $maxHoleX);
        // 缺口可出现的纵向范围：上下各留一个边距，保证拼图块完整落在画布内
        $minHoleY = self::PIECE_MARGIN;
        $maxHoleY = $height - $pieceSize - self::PIECE_MARGIN;
        // 缺口左上角纵坐标，只用于让拼图块与缺口处于同一高度
        $holeY = random_int($minHoleY, $maxHoleY);

        // 本次使用的拼图形状：五角星或三角形，随机二选一
        $shape = random_int(0, 1) === 1 ? 'star' : 'triangle';
        // 形状的顶点坐标，拼图块与缺口共用同一套
        $polygon = $this->shapePolygon($pieceSize, $shape);
        // 干扰缺口的横坐标，与正确答案保持明显间距
        $decoyX = $this->pickDecoyX($holeX, $minHoleX, $maxHoleX);
        // 干扰缺口的纵坐标，与真实缺口错开高度，避免两个洞排在同一水平线上
        $decoyY = $this->pickDecoyY($holeY, $minHoleY, $maxHoleY);

        // 先生成背景，再从背景取块、最后挖洞，保证块里的图案和缺口处完全一致
        $background = $this->createBackground($width, $height);
        // 拼图块（透明底，带压暗轮廓）
        $piece = $this->createPiece($background, $holeX, $holeY, $pieceSize, $polygon);
        // 在背景上把真实缺口位置压暗
        $this->carveHole($background, $holeX, $holeY, $pieceSize, $polygon);
        // 干扰缺口：同形状、不同位置，纵坐标也错开，避免和真实缺口排成一条线
        $this->carveHole($background, $decoyX, $decoyY, $pieceSize, $polygon);

        // 拼图块可移动的最大横向距离，用于把像素坐标换算成比例
        $travel = $width - $pieceSize;
        // 正确答案（万分比），前端缩放显示时像素宽度会变，比例不会变
        $expected = (int)round($holeX / $travel * 10000);
        // 允许误差（万分比），由 3 像素误差换算而来
        $tolerance = (int)ceil(self::PIECE_TOLERANCE / $travel * 10000);
        // 一次性混淆盐：前端提交的位置要和它异或，服务端凭它还原，抓包看不到明文位置
        $nonce = bin2hex(random_bytes(8));
        // 只把「正确比例|允许误差|混淆盐」存服务端，前端拿不到答案
        Cache::set(self::cacheKeyOf($key), $expected . '|' . $tolerance . '|' . $nonce, self::CAPTCHA_TTL);

        return $this->success('ok', [
            'captcha_key' => $key,
            'captcha_nonce' => $nonce,
            'captcha_bg' => $this->toDataUri($background),
            'captcha_piece' => $this->toDataUri($piece),
            'captcha_width' => $width,
            'captcha_height' => $height,
            'captcha_piece_size' => $pieceSize,
            'captcha_piece_y' => $holeY,
        ]);
    }

    /**
     * 校验验证码
     * 登录、注册、找回密码、获取邮箱验证码时统一调用本方法
     * 同时兼容新版滑块拼图与旧版字符验证码，扩展更新完成后可只保留拼图分支
     *
     * @param mixed $key  前端回传的 captcha_key
     * @param mixed $code 新版为混淆后的位置串，旧版为 4 位字符
     * @return string|null 错误提示；校验通过返回 null
     */
    public static function check($key, $code): ?string
    {
        // 只接受字符串，数组等异常输入统一按空值处理，避免类型错误
        $key = is_string($key) ? trim($key) : '';
        $code = is_string($code) ? trim($code) : '';

        // 客户端 IP，作为失败次数的限流维度
        $ip = \RateLimiter::clientIp();
        // 连续失败过多直接拒绝，防止靠小范围枚举横向坐标的方式撞答案
        if (\RateLimiter::tooMany('captcha_fail', $ip, self::CHECK_FAIL_LIMIT, self::CHECK_FAIL_WINDOW)) {
            return '图形验证码错误次数过多，请稍后再试';
        }

        if ($key === '' || $code === '') {
            return '图形验证码不能为空';
        }
        // 旧版字符验证码：key 形如 captcha_<十六进制>.<数字>，按字符比对
        // 十六进制段长度随 PHP 版本略有差异（13 或 14 位），这里不写死长度
        if (preg_match('/^captcha_[0-9a-f]+\.[0-9]+$/', $key)) {
            return self::checkLegacy($key, $code, $ip);
        }
        // 新版滑块拼图：只接受自己签发的 32 位十六进制标识，避免拼出其他业务缓存键
        if (!preg_match('/^[0-9a-f]{32}$/', $key)) {
            return '图形验证码无效，请刷新';
        }

        // 缓存键
        $cacheKey = self::cacheKeyOf($key);
        // 缓存中保存的答案，格式为「正确比例|允许误差|混淆盐」
        $cached = Cache::get($cacheKey);
        if (!is_string($cached) || $cached === '') {
            return '图形验证码已过期，请刷新';
        }
        // 一次性：校验前就删除，杜绝同一张图被反复试错
        Cache::delete($cacheKey);

        // 拆分缓存中的答案、误差与混淆盐
        $parts = explode('|', $cached);
        if (count($parts) < 2) {
            return '图形验证码已失效，请刷新';
        }
        // 正确位置（万分比）
        $expected = (int)$parts[0];
        // 允许误差（万分比）
        $tolerance = (int)$parts[1];
        // 混淆盐；滚动发布期间产生的两段式缓存没有盐，此时退回纯数字校验
        $nonce = $parts[2] ?? '';

        // 还原前端提交的位置，格式不对说明被篡改
        $plain = self::decodePosition($code, $nonce);
        if ($plain === null) {
            \RateLimiter::hit('captcha_fail', $ip, self::CHECK_FAIL_WINDOW);
            return '图形验证码错误';
        }
        // 用户拖动后的位置（万分比）
        $submitted = (int)$plain;
        // 未拖动滑块时不允许通过，且不计入失败次数，避免正常用户被误惩罚
        if ($submitted <= 0) {
            return '请拖动滑块完成验证码拼图';
        }
        // 位置比对只有整数区间判断，不存在逐位猜解的时序问题
        if (abs($submitted - $expected) > $tolerance) {
            \RateLimiter::hit('captcha_fail', $ip, self::CHECK_FAIL_WINDOW);
            return '拼图未对齐，请重新拖动验证码';
        }

        // 通过后清空该 IP 的失败计数
        \RateLimiter::reset('captcha_fail', $ip, self::CHECK_FAIL_WINDOW);
        return null;
    }

    /**
     * 校验旧版字符验证码（兼容未更新的浏览器扩展）
     *
     * @param string $key  旧版 captcha_key
     * @param string $code 用户输入的 4 位字符
     * @param string $ip   客户端 IP，用于失败计数
     * @return string|null 错误提示；校验通过返回 null
     */
    private static function checkLegacy(string $key, string $code, string $ip): ?string
    {
        // 旧实现直接把 captcha_key 当缓存键
        $cached = Cache::get($key);
        if (!is_string($cached) || $cached === '') {
            return '图形验证码已过期，请刷新';
        }
        // 一次性：先作废再比对，避免同一张图被反复试错
        Cache::delete($key);

        // 字符验证码统一转小写比较，其余长度/字符不合法直接判错
        if (!preg_match('/^[a-z0-9]{4}$/i', $code) || strtolower($code) !== $cached) {
            \RateLimiter::hit('captcha_fail', $ip, self::CHECK_FAIL_WINDOW);
            return '图形验证码错误';
        }

        \RateLimiter::reset('captcha_fail', $ip, self::CHECK_FAIL_WINDOW);
        return null;
    }

    /**
     * 还原前端混淆后的位置数值
     * 前端把位置十进制字符与混淆盐按字节异或后转成十六进制，再加 p1: 前缀
     *
     * @param string $code  前端提交的混淆串
     * @param string $nonce 服务端签发的一次性混淆盐（十六进制）
     * @return string|null 还原出的位置数字串；格式非法返回 null
     */
    private static function decodePosition(string $code, string $nonce): ?string
    {
        // 没有盐的历史缓存：直接接受纯数字
        if ($nonce === '') {
            return preg_match('/^\d{1,5}$/', $code) ? $code : null;
        }
        // 新版必须带版本前缀
        if (strpos($code, 'p1:') !== 0) {
            return null;
        }
        // 去掉前缀后的十六进制正文
        $hex = substr($code, 3);
        if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            return null;
        }
        // 密文与盐的原始字节
        $raw = hex2bin($hex);
        $keyBytes = hex2bin($nonce);
        if ($raw === false || $keyBytes === false || $keyBytes === '') {
            return null;
        }
        // 逐字节异或还原明文
        $plain = '';
        $keyLength = strlen($keyBytes);
        for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
            $plain .= chr(ord($raw[$i]) ^ ord($keyBytes[$i % $keyLength]));
        }
        return preg_match('/^\d{1,5}$/', $plain) ? $plain : null;
    }

    /**
     * 获取验证码接口的限流检查
     * 新旧接口共用同一个计数桶，避免绕过其中一个接口无限刷图
     *
     * @return Json|null 超限时返回错误响应，正常时返回 null
     */
    private function guardGenerateLimit(): ?Json
    {
        // 客户端 IP，作为获取验证码的限流维度
        $ip = \RateLimiter::clientIp();

        // 分钟级 + 小时级双重限流，阻止脚本无限刷图
        if (\RateLimiter::tooMany('captcha_gen', $ip, self::GENERATE_LIMIT_PER_MINUTE, 60)
            || \RateLimiter::tooMany('captcha_gen', $ip, self::GENERATE_LIMIT_PER_HOUR, 3600)) {
            return $this->error('验证码获取过于频繁，请稍后再试');
        }
        \RateLimiter::hit('captcha_gen', $ip, 60);
        \RateLimiter::hit('captcha_gen', $ip, 3600);
        return null;
    }

    /**
     * 生成随机字符验证码
     *
     * @param int $length 字符个数
     * @return string 随机验证码
     */
    private function randomCode(int $length = 4): string
    {
        // 字符集最大下标
        $max = strlen(self::LEGACY_CHARS) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::LEGACY_CHARS[random_int(0, $max)];
        }
        return $code;
    }

    /**
     * 绘制旧版字符验证码图片（只用 GD 内置字体，任何环境都能出图）
     * 字符先画在小画布上再放大贴回，保证在 120x42 的图里依然够大够清晰
     *
     * @param string $code 4 位字符验证码
     * @return resource|\GdImage 验证码图片
     */
    private function createLegacyImage(string $code)
    {
        // 画布尺寸（与旧版展示容器 120x45 接近）
        $width = 120;
        $height = 42;
        $image = imagecreatetruecolor($width, $height);

        // 浅色背景，保证字符对比度
        $bgColor = imagecolorallocate($image, random_int(235, 250), random_int(235, 250), random_int(235, 250));
        imagefill($image, 0, 0, $bgColor);

        // 干扰弧线
        for ($i = 0; $i < 4; $i++) {
            $color = imagecolorallocate($image, random_int(170, 210), random_int(170, 210), random_int(170, 210));
            imagearc($image, random_int(0, $width), random_int(0, $height), random_int(30, 80), random_int(15, 50), random_int(0, 180), random_int(0, 360), $color);
        }

        // 干扰直线
        for ($i = 0; $i < 5; $i++) {
            $color = imagecolorallocate($image, random_int(180, 230), random_int(180, 230), random_int(180, 230));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
        }

        // 干扰点
        for ($i = 0; $i < 60; $i++) {
            $color = imagecolorallocate($image, random_int(140, 220), random_int(140, 220), random_int(140, 220));
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $color);
        }

        // GD 内置字体 5 的点阵尺寸（宽 x 高）
        $charWidth = 9;
        $charHeight = 15;
        // 字符间距
        $charGap = 2;
        // 放大倍数，放大后字符实际约 18x30，与旧版字号接近
        $scale = 2;
        // 字符个数
        $length = strlen($code);
        // 原始小画布宽度
        $textWidth = $length * $charWidth + ($length - 1) * $charGap;
        // 字符先画在小画布上，避免放大时把噪点一起放大
        $textImage = imagecreatetruecolor($textWidth, $charHeight);
        imagefill($textImage, 0, 0, $bgColor);

        // 逐字符绘制到小画布
        for ($i = 0; $i < $length; $i++) {
            $color = imagecolorallocate($textImage, random_int(30, 60), random_int(80, 130), random_int(120, 180));
            imagestring($textImage, 5, $i * ($charWidth + $charGap), 0, $code[$i], $color);
        }

        // 放大后贴到画布中央：横向居中、纵向居中
        $dstWidth = $textWidth * $scale;
        $dstHeight = $charHeight * $scale;
        imagecopyresized(
            $image,
            $textImage,
            (int)(($width - $dstWidth) / 2),
            (int)(($height - $dstHeight) / 2),
            0,
            0,
            $dstWidth,
            $dstHeight,
            $textWidth,
            $charHeight
        );
        imagedestroy($textImage);

        // 外边框
        imagerectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 210, 210, 210));
        return $image;
    }

    /**
     * 验证码答案的缓存键
     *
     * @param string $key 验证码标识
     * @return string 缓存键
     */
    private static function cacheKeyOf(string $key): string
    {
        return 'captcha_' . $key;
    }

    /**
     * 把 GD 图片编码成可直接放进 img src 的 data URI，并释放图片资源
     *
     * @param resource|\GdImage $image 待输出的图片
     * @return string data URI
     */
    private function toDataUri($image): string
    {
        // 用输出缓冲捕获 PNG 二进制
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);
        return 'data:image/png;base64,' . base64_encode($data);
    }

    /**
     * 生成验证码背景图
     * 优先从背景素材目录随机取一张真实图片并裁切，取不到素材时退回渐变背景
     *
     * @param int $width  画布宽度
     * @param int $height 画布高度
     * @return resource|\GdImage 背景图
     */
    private function createBackground(int $width, int $height)
    {
        // 真实图片背景
        $image = $this->createImageBackground($width, $height);
        if ($image !== null) {
            // 亮度归一化：过暗的素材整体提亮，过亮的整体压暗，保证拼块与缺口看得清
            $this->normalizeBrightness($image, $width, $height);
            return $image;
        }
        // 素材缺失或读取失败时的兜底背景
        return $this->createGradientBackground($width, $height);
    }

    /**
     * 背景亮度归一化
     * 统计整张图的平均亮度，过暗时整体提亮、过亮时整体压暗
     *
     * @param resource|\GdImage $image  背景图（原地修改）
     * @param int               $width  画布宽度
     * @param int               $height 画布高度
     */
    private function normalizeBrightness($image, int $width, int $height): void
    {
        // 当前平均亮度，取值 0-255
        $average = $this->averageLuma($image, $width, $height);

        if ($average < self::MIN_AVERAGE_LUMA) {
            // 偏暗：按差距提亮，单次最多提 80，避免把夜景直接拉成灰白
            $delta = (int)round(min(80, (self::MIN_AVERAGE_LUMA - $average) * 0.9));
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $delta);
            return;
        }

        if ($average > self::MAX_AVERAGE_LUMA) {
            // 偏亮：轻微压暗，保证压暗后的缺口仍能与背景区分开
            $delta = (int)round(min(45, ($average - self::MAX_AVERAGE_LUMA) * 0.6));
            imagefilter($image, IMG_FILTER_BRIGHTNESS, -$delta);
        }
    }

    /**
     * 计算图片的平均亮度（按亮度公式加权，每 4 像素采样一次）
     *
     * @param resource|\GdImage $image  待统计的图片
     * @param int               $width  画布宽度
     * @param int               $height 画布高度
     * @return float 平均亮度，0-255
     */
    private function averageLuma($image, int $width, int $height): float
    {
        // 亮度累加值
        $sum = 0.0;
        // 采样点数量
        $count = 0;
        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                // 采样点的颜色值
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                // 人眼感知加权亮度
                $sum += 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * 列出背景素材目录下的全部图片
     * 不用 glob 的花括号写法（部分运行环境没有 GLOB_BRACE 常量），改为 scandir 加后缀过滤
     *
     * @return string[] 图片绝对路径列表，目录不可用时返回空数组
     */
    private function backgroundFiles(): array
    {
        // 素材目录绝对路径（public_path 会自动补目录分隔符）
        $dir = public_path(self::BACKGROUND_DIR);
        if (!is_dir($dir)) {
            return [];
        }
        // 目录下的全部文件名
        $names = scandir($dir) ?: [];
        // 允许的图片后缀
        $allow = ['jpg', 'jpeg', 'png', 'webp'];
        // 过滤后的图片路径集合
        $files = [];
        foreach ($names as $name) {
            // 跳过当前目录与上级目录
            if ($name === '.' || $name === '..') {
                continue;
            }
            // 文件后缀（统一小写后比较）
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $allow, true) && is_file($dir . $name)) {
                $files[] = $dir . $name;
            }
        }
        return $files;
    }

    /**
     * 从背景素材目录随机取一张图片，按"铺满画布"的方式随机裁切
     * 图片是完整的真实照片，拼图块与缺口能从图案细节上对应上
     *
     * @param int $width  画布宽度
     * @param int $height 画布高度
     * @return resource|\GdImage|null 生成失败时返回 null，由调用方走兜底逻辑
     */
    private function createImageBackground(int $width, int $height)
    {
        // 素材目录下的全部可用图片
        $files = $this->backgroundFiles();
        if (empty($files)) {
            return null;
        }

        // 随机挑一张素材，保证每次验证码的底图都不同
        $file = $files[random_int(0, count($files) - 1)];
        // 图片基础信息，用于判断格式与原始尺寸
        $info = @getimagesize($file);
        if (!$info) {
            return null;
        }
        // 按图片格式创建 GD 资源
        $source = $this->loadImage($file, (int)$info[2]);
        if ($source === null) {
            return null;
        }

        // 素材原始宽高
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        // 铺满画布所需的最小缩放倍数（取宽高比例中较大的那个）
        $scale = max($width / $srcWidth, $height / $srcHeight);
        // 缩放后参与绘制的原始区域尺寸，多出来的部分被裁掉
        $cropWidth = max(1, (int)round($width / $scale));
        $cropHeight = max(1, (int)round($height / $scale));
        // 随机裁切起点，避免同一张素材每次都取到完全相同的画面
        $srcX = random_int(0, max(0, $srcWidth - $cropWidth));
        $srcY = random_int(0, max(0, $srcHeight - $cropHeight));

        // 目标画布
        $image = imagecreatetruecolor($width, $height);
        imagecopyresampled($image, $source, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);
        // 释放素材资源
        imagedestroy($source);

        return $image;
    }

    /**
     * 按图片类型加载成 GD 资源
     *
     * @param string $file 图片绝对路径
     * @param int    $type IMAGETYPE_* 常量值
     * @return resource|\GdImage|null 不支持的格式或读取失败时返回 null
     */
    private function loadImage(string $file, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($file) ?: null;
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($file) ?: null;
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($file) ?: null) : null;
            default:
                return null;
        }
    }

    /**
     * 兜底背景图：渐变底色 + 随机色块 + 细线 + 噪点
     * 素材目录不可用（如未部署图片）时使用，保证验证码始终能生成
     *
     * @param int $width  画布宽度
     * @param int $height 画布高度
     * @return resource|\GdImage 背景图
     */
    private function createGradientBackground(int $width, int $height)
    {
        // 新建真彩色画布
        $image = imagecreatetruecolor($width, $height);

        // 渐变起始色
        $startR = random_int(50, 150);
        $startG = random_int(50, 150);
        $startB = random_int(60, 180);
        // 渐变结束色：在起始色基础上随机偏移，避免整图过暗或过亮
        $endR = $this->clampColor($startR + random_int(-40, 90));
        $endG = $this->clampColor($startG + random_int(-40, 90));
        $endB = $this->clampColor($startB + random_int(-40, 90));

        // 逐行画线性渐变（纵向），每行一条水平线，比逐像素更省时间
        for ($y = 0; $y < $height; $y++) {
            // 当前行在渐变中的位置比例
            $ratio = $height > 1 ? $y / ($height - 1) : 0;
            $color = imagecolorallocate(
                $image,
                (int)round($startR + ($endR - $startR) * $ratio),
                (int)round($startG + ($endG - $startG) * $ratio),
                (int)round($startB + ($endB - $startB) * $ratio)
            );
            imageline($image, 0, $y, $width - 1, $y, $color);
        }

        // 随机色块：让背景具备局部图案，提升拼图块的辨识度
        for ($i = 0; $i < 5; $i++) {
            $blobColor = imagecolorallocate(
                $image,
                $this->clampColor($startR + random_int(-60, 60)),
                $this->clampColor($startG + random_int(-60, 60)),
                $this->clampColor($startB + random_int(-60, 60))
            );
            imagefilledellipse(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(40, 130),
                random_int(30, 90),
                $blobColor
            );
        }

        // 随机细线：打破色块的规整边缘
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate(
                $image,
                $this->clampColor($startR + random_int(-70, 70)),
                $this->clampColor($startG + random_int(-70, 70)),
                $this->clampColor($startB + random_int(-70, 70))
            );
            // 线宽 1-2 像素
            imagesetthickness($image, random_int(1, 2));
            imageline(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $lineColor
            );
        }
        // 线宽恢复默认，避免影响后续绘制
        imagesetthickness($image, 1);

        // 细噪点：低对比度噪点，人工看图不受影响，但能干扰像素级的颜色匹配
        for ($i = 0; $i < 150; $i++) {
            $dotColor = imagecolorallocate(
                $image,
                $this->clampColor($startR + random_int(-24, 24)),
                $this->clampColor($startG + random_int(-24, 24)),
                $this->clampColor($startB + random_int(-24, 24))
            );
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
        }

        return $image;
    }

    /**
     * 从背景图上截取拼图块
     * 块内像素与背景完全一致，块外像素透明；边缘像素压暗以便用户看清块轮廓
     *
     * @param resource|\GdImage $background 背景图
     * @param int               $holeX      缺口左上角横坐标
     * @param int               $holeY      缺口左上角纵坐标
     * @param int               $size       拼图块边长
     * @param array             $polygon    块形状的顶点坐标
     * @return resource|\GdImage 透明底的拼图块
     */
    private function createPiece($background, int $holeX, int $holeY, int $size, array $polygon)
    {
        // 拼图块画布
        $piece = imagecreatetruecolor($size, $size);
        // 关闭混色，保证写入的颜色（含透明度）原样保存
        imagealphablending($piece, false);
        // 保留 alpha 通道，输出 PNG 时块外才是透明
        imagesavealpha($piece, true);
        imagefill($piece, 0, 0, imagecolorallocatealpha($piece, 0, 0, 0, 127));

        // 逐像素拷贝背景对应区域
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                // 形状外的像素保持透明
                if (!$this->inMask($x, $y, $polygon)) {
                    continue;
                }
                // 背景上对应位置的原始颜色
                $rgb = imagecolorat($background, $holeX + $x, $holeY + $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // 轮廓像素描白，拖动时能看出块的边界，也让对齐更直观
                if ($this->isMaskEdge($x, $y, $polygon)) {
                    $r = 255;
                    $g = 255;
                    $b = 255;
                }

                imagesetpixel($piece, $x, $y, imagecolorallocatealpha($piece, $r, $g, $b, 0));
            }
        }

        return $piece;
    }

    /**
     * 在背景图上把缺口区域压暗
     *
     * @param resource|\GdImage $image  背景图（原地修改）
     * @param int               $holeX  缺口左上角横坐标
     * @param int               $holeY  缺口左上角纵坐标
     * @param int               $size   缺口边长
     * @param array             $polygon 缺口形状的顶点坐标
     */
    private function carveHole($image, int $holeX, int $holeY, int $size, array $polygon): void
    {
        // 逐像素压暗，保留原图纹理，缺口看起来像被"挖走"的一块阴影
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$this->inMask($x, $y, $polygon)) {
                    continue;
                }
                // 缺口位置的原始颜色
                $rgb = imagecolorat($image, $holeX + $x, $holeY + $y);
                $r = (int)((($rgb >> 16) & 0xFF) * 0.42);
                $g = (int)((($rgb >> 8) & 0xFF) * 0.42);
                $b = (int)(($rgb & 0xFF) * 0.42);
                imagesetpixel($image, $holeX + $x, $holeY + $y, imagecolorallocate($image, $r, $g, $b));
            }
        }
    }

    /**
     * 判断块内某个像素是否落在多边形形状里（射线法 + 奇偶规则）
     *
     * @param int   $x       块内横坐标
     * @param int   $y       块内纵坐标
     * @param array $polygon 形状顶点坐标
     * @return bool true 表示该像素属于拼图块
     */
    private function inMask(int $x, int $y, array $polygon): bool
    {
        // 是否落在多边形内部
        $inside = false;
        // 顶点数量
        $count = count($polygon);
        // 从当前点向右发射水平射线，统计与各条边的交点个数
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            // 当前边的两个端点
            [$xi, $yi] = $polygon[$i];
            [$xj, $yj] = $polygon[$j];
            // 边跨越射线高度且交点在当前点右侧时，内外状态翻转
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    /**
     * 判断块内某个像素是否为形状边缘（四邻中有一个在形状外）
     *
     * @param int   $x       块内横坐标
     * @param int   $y       块内纵坐标
     * @param array $polygon 形状顶点坐标
     * @return bool true 表示该像素位于轮廓上
     */
    private function isMaskEdge(int $x, int $y, array $polygon): bool
    {
        return !$this->inMask($x - 1, $y, $polygon)
            || !$this->inMask($x + 1, $y, $polygon)
            || !$this->inMask($x, $y - 1, $polygon)
            || !$this->inMask($x, $y + 1, $polygon);
    }

    /**
     * 生成拼图块形状的顶点坐标（五角星 / 三角形）
     *
     * @param int    $size  块边长
     * @param string $shape 形状标识：star / triangle
     * @return array 顶点坐标列表，按顺序首尾相连
     */
    private function shapePolygon(int $size, string $shape): array
    {
        // 顶点与画布边缘的内缩距离，避免贴边被裁掉
        $pad = 1.0;

        if ($shape === 'triangle') {
            // 正三角形：顶点朝上，底边贴住画布底部
            return [
                [$size / 2, $pad],
                [$size - $pad, $size - $pad],
                [$pad, $size - $pad],
            ];
        }

        // 五角星外接圆圆心
        $cx = $size / 2;
        $cy = $size / 2;
        // 外接圆半径
        $outer = $size / 2 - $pad;
        // 内接圆半径，0.45 倍外径接近标准五角星
        $inner = $outer * 0.45;
        // 交替的外角/内角顶点
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            // 当前顶点角度，从正上方开始顺时针排列
            $angle = -M_PI / 2 + $i * M_PI / 5;
            // 偶数下标是尖角，奇数下标是凹角
            $radius = $i % 2 === 0 ? $outer : $inner;
            $points[] = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
        }
        return $points;
    }

    /**
     * 选取干扰缺口的横坐标，保证与真实缺口有明显间距
     *
     * @param int $holeX 真实缺口横坐标
     * @param int $minX  可用范围左边界
     * @param int $maxX  可用范围右边界
     * @return int 干扰缺口横坐标
     */
    private function pickDecoyX(int $holeX, int $minX, int $maxX): int
    {
        // 多次随机取点，尽量让干扰缺口落得自然一些
        for ($i = 0; $i < 20; $i++) {
            // 候选横坐标
            $candidate = random_int($minX, $maxX);
            if (abs($candidate - $holeX) >= self::DECOY_GAP) {
                return $candidate;
            }
        }
        // 兜底：按真实缺口的位置向另一侧偏移固定间距
        if ($holeX < ($minX + $maxX) / 2) {
            return min($maxX, $holeX + self::DECOY_GAP);
        }
        return max($minX, $holeX - self::DECOY_GAP);
    }

    /**
     * 选取干扰缺口的纵坐标，保证与真实缺口在垂直方向错开
     *
     * @param int $holeY 真实缺口纵坐标
     * @param int $minY  可用范围上边界
     * @param int $maxY  可用范围下边界
     * @return int 干扰缺口纵坐标
     */
    private function pickDecoyY(int $holeY, int $minY, int $maxY): int
    {
        // 多次随机取点，尽量让干扰缺口落得自然一些
        for ($i = 0; $i < 20; $i++) {
            // 候选纵坐标
            $candidate = random_int($minY, $maxY);
            if (abs($candidate - $holeY) >= self::DECOY_VERTICAL_GAP) {
                return $candidate;
            }
        }
        // 兜底：按真实缺口的位置向空间更大的一侧偏移固定间距
        if ($holeY < ($minY + $maxY) / 2) {
            return min($maxY, $holeY + self::DECOY_VERTICAL_GAP);
        }
        return max($minY, $holeY - self::DECOY_VERTICAL_GAP);
    }

    /**
     * 把颜色分量限制在 0-255 之间
     *
     * @param int $value 原始颜色分量
     * @return int 合法颜色分量
     */
    private function clampColor(int $value): int
    {
        return max(0, min(255, $value));
    }
}

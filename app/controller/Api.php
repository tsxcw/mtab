<?php

namespace app\controller;

use app\BaseController;
use app\model\FileModel;
use app\model\LinkModel;
use app\model\SettingModel;
use DOMDocument;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Cache;
use think\facade\Filesystem;
use think\facade\View;
use think\helper\Str;

class Api extends BaseController
{

    public function site(): \think\response\Json
    {

        return $this->success("ok", SettingModel::siteConfig());
    }

    public function background(): \think\response\File
    {
        return download('static/background.jpeg', 'background.jpeg')->mimeType(\PluginStaticSystem::mimeType('static/background.jpeg'))->force(false)->expire(60 * 60 * 24 * 3);
    }

    //获取默认壁纸
    function DefBg(): \think\response\Json
    {
        $config = $this->systemSetting('defaultTab', 'static/defaultTab.json', true);
        if ($config) {
            $fp = public_path() . $config;
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                if (isset($json['config']['theme']['backgroundImage'])) {
                    $bg = $json['config']['theme']['backgroundImage'];
                    $bgMime = $json['config']['theme']['backgroundMime'] ?? 0;
                    return $this->success("ok", ['background' => $bg, "mime" => $bgMime]);
                }
            }
        }
        return $this->success("ok", ['background' => "static/background.jpeg", "mime" => 0]);
    }

    function globalNotify(): \think\response\Json
    {
        $info = SettingModel::Config("globalNotify", false);
        if ($info) {
            $info = json_decode($info, true);
            $info['html'] = modifyImageUrls($info['html'], request()->root(true));
            if (isset($info['status']) && $info['status'] == 1) {
                return $this->success('ok', json_encode($info, JSON_UNESCAPED_UNICODE));
            }
        }
        return $this->error('empty');
    }

    //获取邮件验证码
    function getMailCode(): \think\response\Json
    {
        $mail = $this->request->post("mail", false);
        $captchaKey = $this->request->post('captcha_key', '');
        $captchaCode = $this->request->post('captcha_code', '');

        // 滑块拼图验证码强制校验：防止脚本绕过验证码直接刷邮件接口；
        // 只有后台显式配置 captcha_check='1'（关闭验证码）才旁路
        if ($this->systemSetting("captcha_check", '0', true) !== '1') {
            // 校验失败时的错误提示，null 表示通过
            $captchaError = Captcha::check($captchaKey, $captchaCode);
            if ($captchaError !== null) {
                return $this->error($captchaError);
            }
        }

        $code = rand(100000, 999999);
        if ($mail) {
            if (Cache::get('code' . $mail)) {
                return $this->success("请勿频繁获取验证码");
            }
            $k = SettingModel::Config('smtp_code_template', false);
            if ($k === false || mb_strlen(trim($k)) == 0) {
                $k = '
                        <div style="border:1px #DEDEDE solid;border-top:3px #009944 solid;padding:25px;background-color:#FFF;">
                            <div style="font-size:17px;font-weight:bold;">邮箱验证码</div>
                            <div style="font-size:14px;line-height:36px;padding-top:15px;padding-bottom:15px;">
                                尊敬的用户，您好！<br>
                                您的验证码是：<b style="color: #1e9fff">{$code}</b>。5分钟内有效，请尽快验证。
                            </div>
                            <div style="line-height:15px;">
                                此致
                            </div>
                        </div>
                ';
            }
            $html = View::display($k, ['time' => date('Y-m-d H:i:s'), 'code' => $code]);
            try {
                $status = \Mail::send($mail, $html);
                if ($status) {
                    Cache::set('code' . $mail, $code, 300);
                    return $this->success('发送成功');
                }
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            }
        }
        return $this->error('发送失败');
    }

    const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    // 远程抓取图标时允许的类型 => 落盘扩展名（svg 落盘前必须经过 SvgSanitizer 净化）
    const ALLOWED_MIMES = [
        'image/png'                => 'png',
        'image/jpeg'               => 'jpg',
        'image/webp'               => 'webp',
        'image/gif'                => 'gif',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml'            => 'svg',
    ];

    // 普通图片上传允许的真实类型 => 落盘扩展名（扩展名完全由文件内容决定）
    const IMAGE_MIME_MAP = [
        'image/png'                => 'png',
        'image/jpeg'               => 'jpg',
        'image/webp'               => 'webp',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml'            => 'svg',
    ];

    // 后台上传允许的真实类型 => 落盘扩展名（图片 + 视频）
    const ADMIN_UPLOAD_MIME_MAP = [
        'image/png'                => 'png',
        'image/jpeg'               => 'jpg',
        'image/webp'               => 'webp',
        'image/gif'                => 'gif',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        // svg 为矢量图标常用格式（后台桌面分类图标），落盘前会做白名单净化
        'image/svg+xml'            => 'svg',
        'video/mp4'                => 'mp4',
        'video/webm'               => 'webm',
        'video/ogg'                => 'ogg',
        'video/quicktime'          => 'mov',
    ];

    const ICON_RELS = [
        'icon',
        'shortcut icon',
        'apple-touch-icon',
        'apple-touch-icon-precomposed',
        'mask-icon',
    ];

    /**
     * 获取带统一 UA 的 HTTP 客户端（跳过 SSL 验证）
     */
    private function httpClient(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'verify'  => false,
            'headers' => ['User-Agent' => self::UA],
            // 统一的超时时间，避免远端不响应时请求被长期挂住
            'timeout'         => 10,
            // 连接超时时间
            'connect_timeout' => 5,
            // 限制重定向次数，并对每一次重定向目标重新做内网地址校验，防止 302 跳转到内网形成 SSRF
            'allow_redirects' => [
                'max'         => 3,
                'strict'      => true,
                'referer'     => false,
                'protocols'   => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri) {
                    if (!$this->isSafeRemoteUrl((string)$uri)) {
                        throw new \RuntimeException('非法的重定向地址');
                    }
                },
            ],
        ]);
    }

    /**
     * 判断远程地址是否为可安全请求的公网地址（仅 http/https，且域名解析结果不能落在内网）
     * @param string $url 待请求的远程地址
     * @return bool 安全返回 true，非法或不安全返回 false
     */
    private function isSafeRemoteUrl($url): bool
    {
        // 解析出的 URL 结构
        $parts = parse_url((string)$url);
        if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        // 协议白名单，避免 file://、gopher:// 等协议被利用
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        // 目标主机名（IPv6 会带方括号，需要去掉）
        $host = strtolower(trim($parts['host'], '[]'));
        // 域名解析出的所有 IP
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            // IPv4 记录
            $v4 = gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
            // IPv6 记录
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }
        // 域名无法解析时按不安全处理
        if (empty($ips)) {
            return false;
        }
        // 解析结果中只要有一个不是公网 IP 就拒绝（防止 DNS 指向内网）
        foreach ($ips as $ip) {
            // IPv4 映射的 IPv6 地址（如 ::ffff:127.0.0.1）先还原成 IPv4，避免绕过内网判断
            if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = substr($ip, 7);
            }
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 规范化 URL：确保有 scheme 和 host，去除 path/query/fragment
     */
    private function normalizeBaseUrl($url): string
    {
        $parts = parse_url($url);

        if (empty($parts['scheme'])) {
            $scheme = (isset($parts['host']) && strpos($url, '//') === 0) ? 'http:' : 'http://';
            $url = $scheme . ltrim($url, '/');
            $parts = parse_url($url);
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        return $base;
    }

    /**
     * 判断 URL 是否仅为路径（无 scheme 和 host）
     */
    private function isRelativePath($url): bool
    {
        if (empty($url)) {
            return false;
        }
        $parts = parse_url($url);
        return !empty($parts['path']) && empty($parts['host']) && empty($parts['scheme']);
    }

    /**
     * 解析绝对 URL：若 href 为相对路径则拼接 baseUrl
     */
    private function resolveUrl($href, $baseUrl): string
    {
        if ($this->isRelativePath($href)) {
            return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        }
        return $href;
    }

    function getIcon(): \think\response\Json
    {
        $avatar = $this->request->post('avatar');
        if ($avatar) {
            $remote_avatar = $this->systemSetting('remote_avatar', 'https://avatar.mtab.cc/6.x/icons/png?seed=', true);
            $str = $this->downloadFile($remote_avatar . $avatar, md5($avatar) . '.png');
            return $this->success(['src' => $str]);
        }

        $url = $this->request->post('url', false);
        if (!$url) {
            return $this->error('没有抓取到图标');
        }

        $baseUrl = $this->normalizeBaseUrl($url);
        $cdn = $this->systemSetting('assets_host', '');

        try {
            $pageInfo = $this->fetchPageInfo($baseUrl);
            if ($pageInfo === null) {
                return $this->error('没有抓取到图标');
            }

            $title = $pageInfo['title'];
            $icon = '';

            foreach ($pageInfo['icons'] as $iconHref) {
                $icon = $this->downloadIcon($iconHref, $baseUrl, $cdn);
                if ($icon) {
                    break;
                }
            }

            if (empty($icon)) {
                $icon = $this->fetchFavicon($baseUrl, $cdn);
            }

            if ($icon) {
                return $this->success(['src' => $icon, 'name' => $title]);
            }
        } catch (\Exception $e) {
            return $this->error('没有抓取到图标');
        }

        return $this->error('没有抓取到图标');
    }

    /**
     * 抓取页面 HTML，提取 title 和所有 icon 链接（按尺寸从大到小排序）
     */
    private function fetchPageInfo($baseUrl): ?array
    {
        // 只允许抓取公网地址，避免该接口被当作探测内网的 SSRF 跳板
        if (!$this->isSafeRemoteUrl($baseUrl)) {
            return null;
        }

        // 流式获取响应，避免远端返回超大页面时被一次性读入内存
        $response = $this->httpClient()->get($baseUrl, ['stream' => true]);

        if ($response->getStatusCode() !== 200) {
            $response->getBody()->close();
            return null;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (stripos($contentType, 'text/html') === false) {
            $response->getBody()->close();
            return null;
        }

        // 只读前 1MB，足够解析 <title> 与 <link rel="icon">，同时限制内存占用
        $body = $response->getBody()->read(1024 * 1024);
        $response->getBody()->close();
        $dom = new DOMDocument();
        @$dom->loadHTML($body);

        $title = '';
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $title = trim($titles->item(0)->textContent);
        }

        $icons = $this->findIcons($dom, $baseUrl);

        return ['title' => $title, 'icons' => $icons];
    }

    /**
     * 从 DOM 中提取 icon 链接，按尺寸降序排列（大尺寸优先）
     */
    private function findIcons($dom, $baseUrl): array
    {
        $icons = [];

        foreach ($dom->getElementsByTagName('link') as $link) {
            $rel = trim($link->getAttribute('rel'));
            if (!in_array($rel, self::ICON_RELS, true)) {
                continue;
            }

            $href = $link->getAttribute('href');
            if (empty($href)) {
                continue;
            }

            $href = $this->resolveUrl($href, $baseUrl);

            $size = 0;
            $sizes = $link->getAttribute('sizes');
            if ($sizes && preg_match('/(\d+)x\1/', $sizes, $m)) {
                $size = (int) $m[1];
            }

            $icons[] = ['href' => $href, 'size' => $size];
        }

        usort($icons, function ($a, $b) {
            return $b['size'] - $a['size'];
        });

        return array_column($icons, 'href');
    }

    /**
     * 下载单个 icon 并返回 CDN 路径
     */
    private function downloadIcon($iconHref, $baseUrl, $cdn): string
    {
        try {
            // 图标地址同样必须是公网地址，否则跳过（防止伪造页面里的内网链接）
            if (!$this->isSafeRemoteUrl($iconHref)) {
                return '';
            }

            // 这里只需要响应头判断类型，用流式请求并立即关闭，避免把图标内容重复读进内存
            $response = $this->httpClient()->get($iconHref, ['stream' => true]);
            $contentType = strtolower($response->getHeaderLine('Content-Type'));
            $contentType = explode(';', $contentType)[0];
            $response->getBody()->close();

            if (!isset(self::ALLOWED_MIMES[$contentType])) {
                return '';
            }

            $fileExt = self::ALLOWED_MIMES[$contentType];
            $iconPath = $this->downloadFile($iconHref, md5($baseUrl) . '.' . $fileExt);
            // 下载或内容校验失败时返回空串，交给上层继续尝试其它图标
            if (!$iconPath) {
                return '';
            }
            return $cdn . $iconPath;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * 回退方案：尝试抓取站点根目录的 favicon（支持 .ico / .png，不再抓取 svg）
     */
    private function fetchFavicon($baseUrl, $cdn): string
    {
        $faviconPaths = [
            '/favicon.ico',
            '/favicon.png',
            '/apple-touch-icon.png',
        ];

        foreach ($faviconPaths as $path) {
            $faviconUrl = rtrim($baseUrl, '/') . $path;
            try {
                // 候选地址必须是公网地址，避免访问内网服务
                if (!$this->isSafeRemoteUrl($faviconUrl)) {
                    continue;
                }
                // 只需响应头判断类型，流式请求后立即关闭，避免下载超大内容占用内存
                $response = $this->httpClient()->get($faviconUrl, ['stream' => true]);
                if ($response->getStatusCode() !== 200) {
                    $response->getBody()->close();
                    continue;
                }
                $contentType = strtolower($response->getHeaderLine('Content-Type'));
                $contentType = explode(';', $contentType)[0];
                $response->getBody()->close();

                $extMap = [
                    'image/x-icon'             => 'ico',
                    'image/vnd.microsoft.icon' => 'ico',
                    'image/png'                => 'png',
                ];
                $ext = $extMap[$contentType] ?? null;
                if ($ext === null) {
                    continue;
                }

                $iconPath = $this->downloadFile($faviconUrl, md5($baseUrl) . '.' . $ext);
                // 下载或内容校验失败时继续尝试下一个候选地址
                if (!$iconPath) {
                    continue;
                }
                return $cdn . $iconPath;
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    private function downloadFile($url, $name)
    {
        // 只允许下载公网地址，防止接口被用来访问内网服务（SSRF）
        if (!$this->isSafeRemoteUrl($url)) {
            return false;
        }
        // 当前用户，未登录为 null（抓取图标允许游客使用）
        $user = $this->getUser();
        // 相对存储目录，按年月日分组
        $path = '/images/' . date('Y/m/d/');
        // 服务器上的绝对目录
        $remotePath = public_path() . $path;
        // 最终写入的绝对路径
        $downloadPath = $remotePath . $name;
        if (!is_dir($remotePath)) {
            mkdir($remotePath, 0755, true);
        }
        try {
            $this->httpClient()->request('GET', $url, ['sink' => $downloadPath, 'timeout' => 10]);
            // 限制下载体积（8MB），防止被利用写入超大文件占满磁盘
            if (!is_file($downloadPath) || filesize($downloadPath) > 8 * 1024 * 1024) {
                if (is_file($downloadPath)) {
                    @unlink($downloadPath);
                }
                return false;
            }
            // 校验下载内容的真实类型，防止远端伪造 Content-Type 把非图片内容写进站点目录
            $realMime = FileModel::detectMimeType($downloadPath);
            if (!isset(self::ALLOWED_MIMES[$realMime])) {
                @unlink($downloadPath);
                return false;
            }
            // 抓取到的 svg 同样要净化，避免远程站点把脚本写进本站目录
            if ($realMime === 'image/svg+xml' && !\SvgSanitizer::sanitizeFile($downloadPath)) {
                @unlink($downloadPath);
                return false;
            }
            return FileModel::addFile($path . $name, $user['user_id'] ?? null);
        } catch (\Throwable $e) {
            // 连接失败/超时等异常同样要清掉残留的半成品文件
            if (is_file($downloadPath)) {
                @unlink($downloadPath);
            }
        }
        return false;
    }

    function renderIco(): \think\Response
    {
        // 未传 seed 时 get() 返回 null，PHP 8.1+ 下传给 urlencode 会触发弃用错误
        $send = (string)$this->request->get('seed');
        $client = new Client();
        $remote_avatar = $this->systemSetting('remote_avatar', 'https://avatar.mtab.cc/6.x/icons/png?seed=', true);
        $response = $client->get($remote_avatar . urlencode($send), [
            'stream' => true,
            'timeout' => 10,
        ]);
        return response($response->getBody(), 200, ['Content-Type' => $response->getHeader("content-type")[0]]);
    }

    /**
     * 通过文件真实内容（magic bytes）识别类型，并映射为安全的落盘扩展名
     * @param \think\file\UploadedFile $file 上传的文件对象
     * @param array $mimeMap 允许的 MIME => 扩展名 映射表
     * @return string 匹配到的扩展名，未匹配返回空字符串
     */
    private function detectRealExtension($file, array $mimeMap): string
    {
        // 上传文件的临时路径，用于读取真实内容
        $realPath = $file->getPathname();
        // 通过文件头魔数识别真实 MIME（纯 PHP 实现，不依赖 fileinfo 扩展）
        $mime = FileModel::detectMimeType($realPath);
        return $mimeMap[$mime] ?? '';
    }

    /**
     * 校验图片像素总数，拦截“文件很小、尺寸极大”的解压炸弹图片
     * @param \think\file\UploadedFile $file 上传的图片文件
     * @param int $maxPixels 允许的最大像素数（默认 3000 万）
     * @return bool 校验通过返回 true
     */
    private function checkImagePixels($file, int $maxPixels = 30000000): bool
    {
        // getimagesize 解析出的图片信息，解析失败说明不是有效图片
        $info = @getimagesize($file->getPathname());
        if (!$info) {
            return false;
        }
        // 图片总像素数
        $pixels = (int)$info[0] * (int)$info[1];
        return $pixels > 0 && $pixels <= $maxPixels;
    }

    /**
     * 解析并校验裁剪尺寸参数（格式：宽x高，如 500x0）
     * @param string $calc 前端传入的 Calc 请求头
     * @return array|null 合法返回 [宽, 高]，非法或不需要裁剪返回 null
     */
    private function parseCalcSize($calc): ?array
    {
        if (!$calc || !preg_match('/^(\d{1,4})x(\d{1,4})$/', strtolower(trim((string)$calc)), $m)) {
            return null;
        }
        // 目标宽度（必须大于 0，否则 ImageBack 内部会除零报错）
        $width = (int)$m[1];
        // 目标高度（0 表示按原比例自适应）
        $height = (int)$m[2];
        if ($width < 1 || $width > 4096 || $height > 4096) {
            return null;
        }
        return [$width, $height];
    }

    function upload(): \think\response\Json
    {
        // 当前登录用户，未登录为 null（游客上传场景）
        $user = $this->getUser();
        if (!$user) {
            if ($this->systemSetting('touristUpload') !== '1') {
                //如果没有开启游客上传
                return $this->error('管理员已关闭游客上传！请登录后使用');
            }
        }
        // 上传用途：icon/avatar 需要压缩，AdminBackground 需要生成缩略图
        $type = $this->request->header("Up-Type", '');
        // 上传的文件对象
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('not File');
        }

        // 裁剪参数，格式为 宽x高（如 500x0）
        $calc = $this->request->header("Calc");
        // 后台配置的允许上传体积（MB）
        $maxSize = (float)$this->systemSetting('upload_size', '2');
        if ($file->getSize() > 1024 * 1024 * $maxSize) {
            $limit = $maxSize < 1 ? ($maxSize * 1000) . 'KB' : ($maxSize) . 'MB';
            return $this->error("文件最大$limit,请压缩后再试");
        }
        // 按文件真实内容识别类型，落盘扩展名由 MIME 决定：不再信任客户端扩展名，杜绝 php/html 等可执行文件
        $ext = $this->detectRealExtension($file, self::IMAGE_MIME_MAP);
        if ($ext === '') {
            return $this->error('仅支持 png/jpg/webp/ico/svg 格式的图片');
        }
        // 图片像素总数上限，防止解压炸弹耗尽内存；ico 结构特殊、svg 为矢量格式，均不做该检查
        if (!in_array($ext, ['ico', 'svg'], true) && !$this->checkImagePixels($file)) {
            return $this->error('图片尺寸过大，请压缩后再试');
        }
        // 验证文件并保存
        try {
            // 构建保存路径
            $savePath = '/images/' . date('Y/m/d');
            // 随机文件名，避免重名覆盖与路径猜测
            $hash = Str::random(32);
            // 最终文件名：随机串 + 真实类型对应的扩展名
            $fileName = $hash . '.' . $ext;
            $filePath = Filesystem::disk('images')->putFileAs($savePath, $file, $fileName);
            $minPath = '';
            if ($ext === 'svg') {
                // svg 是文本矢量格式，不做 GD 重编码，改为白名单净化，剥离脚本/事件/外链
                if (!\SvgSanitizer::sanitizeFile(joinPath(public_path(), $filePath))) {
                    // 净化失败说明文件存在无法安全处理的内容，删除后拒绝上传
                    @unlink(joinPath(public_path(), $filePath));
                    return $this->error('SVG 内含不安全内容，请处理后重新上传');
                }
                // 后台背景图无法生成缩略图，直接用原图地址填充，避免前端取不到图
                if ($type == 'AdminBackground') {
                    $minPath = $filePath;
                }
            } else {
                if ($type == 'icon' || $type == 'avatar') {
                    // 头像/图标强制重编码，顺带清除文件中可能夹带的多余数据
                    $fp = joinPath(public_path(), $filePath);
                    $image = new \ImageBack($fp);
                    $image->resize(400, 0)->save($fp);
                } else if ($type == 'AdminBackground') {
                    // 后台背景图额外生成一张缩略图
                    $minPath = joinPath($savePath, "/min_$fileName");
                    $fp = joinPath(public_path(), $filePath);
                    $image = new \ImageBack($fp);
                    $image->resize(400, 0)->save(joinPath(public_path(), $minPath));
                    $minPath = FileModel::addFile($minPath, $user['user_id'] ?? null);
                }
                //如果包含裁剪头就裁剪图片（尺寸已做合法性校验，避免超大尺寸拖垮内存）
                $calcSize = $this->parseCalcSize($calc);
                if ($calcSize) {
                    $fp = joinPath(public_path(), $filePath);
                    $image = new \ImageBack($fp);
                    $image->resize($calcSize[0], $calcSize[1])->save($fp);
                }
            }
            $filePath = FileModel::addFile($filePath, $user['user_id'] ?? null);
            return $this->success(['url' => $filePath, "minUrl" => $minPath, 'filename' => $fileName]);
        } catch (\Throwable $e) {
            // 图片处理库在 PHP8 下可能抛 Error/TypeError，统一按失败处理
            return $this->error($e->getMessage());
        }
    }

    function AdminUpload(): \think\response\Json
    {
        // 管理员信息，非管理员会直接终止请求
        $user = $this->getAdmin();
        // 上传的文件对象
        $file = $this->request->file('file');
        // 目标磁盘名，只允许 images/static（根目录都在 public 下）
        $disk = $this->request->post("disk", "images");
        // 目标子目录，由前端传入，必须严格校验防止目录穿越
        $dir = $this->request->post("dir", "");
        if (empty($file)) {
            return $this->error('not File');
        }
        if ($file->getSize() > 1024 * 1024 * 8) {
            return $this->error('文件最大8MB,请压缩后再试');
        }
        if (!in_array($disk, ['images', 'static'], true)) {
            return $this->error('非法的存储位置');
        }
        // 目录只允许字母、数字、下划线、中划线组成的多级路径，禁止 . 与 ..
        if ($dir !== '' && !preg_match('#^/?([A-Za-z0-9_\-]+/)*[A-Za-z0-9_\-]+/?$#', $dir)) {
            return $this->error('非法的目录参数');
        }
        // 按真实内容识别类型，落盘扩展名由 MIME 决定，避免上传 php/html 等可执行文件
        $ext = $this->detectRealExtension($file, self::ADMIN_UPLOAD_MIME_MAP);
        if ($ext === '') {
            return $this->error('仅支持 png/jpg/webp/gif/ico/svg 图片或 mp4/webm/ogg/mov 视频');
        }
        // 验证文件并保存
        try {
            // 构建保存路径
            $savePath = "/$disk/";
            if ($dir) {
                $savePath = joinPath($savePath, $dir);
            } else {
                $savePath .= date('Y/m/d');
            }
            // 随机文件名，避免重名覆盖
            $hash = Str::random(32);
            // 最终文件名：随机串 + 真实类型对应的扩展名
            $fileName = $hash . '.' . $ext;
            $filePath = Filesystem::disk($disk)->putFileAs($savePath, $file, $fileName);
            // svg 是文本矢量格式，不做重编码，改为白名单净化，剥离脚本/事件/外链
            if ($ext === 'svg' && !\SvgSanitizer::sanitizeFile(joinPath(public_path(), $filePath))) {
                // 净化失败说明含无法安全处理的内容，删除已落盘文件后拒绝
                @unlink(joinPath(public_path(), $filePath));
                return $this->error('SVG 内含不安全内容，请处理后重新上传');
            }
            $path = FileModel::addFile($filePath, $user['user_id'] ?? null);
            return $this->success(['url' => $path]);
        } catch (\Throwable $e) {
            // 把底层异常抛给前端，避免只提示笼统的“上传失败”而无法定位原因
            return $this->error('上传失败：' . $e->getMessage());
        }
    }

    function refresh(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = [];
            $data['link_update_time'] = LinkModel::where("user_id", $user['user_id'])->value("update_time");
            return $this->success("ok", $data);
        }
        return $this->error("not login");
    }

    function cardImages(): \think\response\Json
    {
        $list = [];
        $webPath = '/static/CardBackground/bg/';
        $dirPath = public_path($webPath);
        $dir = opendir($dirPath);
        while (($file = readdir($dir)) !== false) {
            if ($file != "." && $file != "..") {
                if (is_file(joinPath($dirPath, $file))) {
                    $list[] = [
                        "thumbor" => joinPath($webPath, $file),
                        "url" => joinPath($webPath, $file),
                    ];
                }
            }
        }
        //针对文件的创建时间排序
        usort($list, function ($a, $b) {
            return filemtime(joinPath(public_path(), $b['thumbor'])) - filemtime(joinPath(public_path(), $a['thumbor']));
        });
        return $this->success($list);
    }

    function moveFile(): \think\response\Json
    {
        $this->getAdmin();
        $old = $this->request->post("old");
        $new = $this->request->post("new");
        FileModel::moveFile($old, $new);
        return $this->success("文件移动成功");
    }

    function delImages(): \think\response\Json
    {
        $this->getAdmin();
        $url = $this->request->post('url');
        $p = joinPath(public_path(), $url);
        if (file_exists($p)) {
            FileModel::delFile($url);
        }
        return $this->success("删除完毕");
    }

    function wx_login_info(): \think\response\Json
    {
        $data = [
            'appid' => $this->systemSetting('wx_login_appid'),
            'redirect_uri' => $this->request->scheme() . '://' . $this->request->host(true)
        ];
        return $this->success('ok', $data);
    }
}

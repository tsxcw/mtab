<?php

use think\facade\Cache;

/**
 * 简易限流器
 *
 * 基于缓存做固定窗口计数，用于验证码这类低成本高频接口的防刷。
 * 计数不落库，窗口切换后自动使用新键，旧键靠过期时间回收。
 */
class RateLimiter
{
    /**
     * 获取用于限流的客户端 IP
     *
     * X-Forwarded-For 里客户端自己填的段一定在左侧，右侧是各级代理追加的，
     * 所以从右往左取第一个公网地址：既挡住"自带 XFF 伪造 IP"的绕过，
     * 也能跳过本机/内网代理地址。不使用公共的 getRealIp()，因为它取最左一段，
     * 攻击者只要自己带一个 x-forwarded-for 就能让每次请求落进不同的限流桶。
     *
     * @return string 合法的客户端 IP，取不到时返回 unknown（此时所有请求共用一个计数桶）
     */
    public static function clientIp(): string
    {
        // 原始 X-Forwarded-For 请求头，可能有多段逗号分隔的地址
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            // 反转后的地址列表，从最靠近服务端的一段开始判断
            $parts = array_reverse(explode(',', $forwarded));
            foreach ($parts as $part) {
                // 待判断的单个地址
                $candidate = trim($part);
                // 只接受公网 IPv4/IPv6，跳过内网与保留地址（例如代理自身）
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }
            // 全部是内网地址时（纯内网部署），退化为取最右侧的合法地址
            foreach ($parts as $part) {
                // 待判断的单个地址
                $candidate = trim($part);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        // 无代理头时使用 TCP 连接的对端地址
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
    }

    /**
     * 判断某标识在指定窗口内是否已达次数上限（只读，不累加）
     *
     * @param string $name   限流维度名称，如 captcha_fail
     * @param string $id     限流对象标识，如客户端 IP
     * @param int    $limit  窗口内允许的最大次数
     * @param int    $window 窗口长度（秒）
     * @return bool true 表示已超限，调用方应直接拒绝本次请求
     */
    public static function tooMany(string $name, string $id, int $limit, int $window): bool
    {
        return self::count($name, $id, $window) >= $limit;
    }

    /**
     * 读取某标识在指定窗口内的当前次数（只读，不累加）
     *
     * @param string $name   限流维度名称
     * @param string $id     限流对象标识
     * @param int    $window 窗口长度（秒）
     * @return int 当前窗口内的累计次数
     */
    public static function count(string $name, string $id, int $window): int
    {
        return (int)Cache::get(self::buildKey($name, $id, $window), 0);
    }

    /**
     * 清零某标识在指定窗口内的计数（用于验证通过后解除惩罚）
     *
     * @param string $name   限流维度名称
     * @param string $id     限流对象标识
     * @param int    $window 窗口长度（秒）
     */
    public static function reset(string $name, string $id, int $window): void
    {
        Cache::delete(self::buildKey($name, $id, $window));
    }

    /**
     * 记录一次命中，返回当前窗口内的累计次数
     *
     * @param string $name   限流维度名称
     * @param string $id     限流对象标识
     * @param int    $window 窗口长度（秒）
     * @return int 当前窗口内的累计次数
     */
    public static function hit(string $name, string $id, int $window): int
    {
        // 当前窗口对应的缓存键
        $key = self::buildKey($name, $id, $window);
        // 累计次数加一
        $times = (int)Cache::get($key, 0) + 1;
        // 每次写入都带上过期时间：既让旧窗口自动回收，也避开 File 驱动 inc 不续期的问题
        Cache::set($key, $times, $window);
        return $times;
    }

    /**
     * 生成限流缓存键
     *
     * @param string $name   限流维度名称
     * @param string $id     限流对象标识
     * @param int    $window 窗口长度（秒）
     * @return string 形如 rl_{name}_{id哈希}_{窗口序号} 的缓存键
     */
    private static function buildKey(string $name, string $id, int $window): string
    {
        // 当前时间所属的窗口序号，窗口切换后键随之变化，实现固定窗口计数
        $slot = (int)floor(time() / $window);
        return 'rl_' . $name . '_' . md5($id) . '_' . $slot;
    }
}

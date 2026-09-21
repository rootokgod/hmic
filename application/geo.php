<?php
declare(strict_types=1);

/**
 * 离线 GeoIP（ip2region v4 xdb，结构 3.x） 与访问设备识别
 * - IP 解析结果缓存在 data/iploc.json
 * - 依赖站点根 common.php 的 DATA_DIR 常量
 * 用法：geo_lookup($ip) 返回 ['country','country_code','province','city']
 */

if (!defined('DATA_DIR')) {
    define('DATA_DIR', dirname(__DIR__) . '/data');
}
const GEO_XDB_FILE   = DATA_DIR . '/ip2region_v4.xdb';
const GEO_XDB_FILE_V6 = DATA_DIR . '/ip2region_v6.xdb';
const GEO_CACHE_FILE = DATA_DIR . '/iploc.json';
const GEO_CACHE_MAX  = 80000;

function geo_whitelist(): array
{
    return [
        '255.32.250.239' => ['中国', '广西', '梧州'],
        '254.112.137.83' => ['中国', '广西', '梧州'],
        '117.183.71.000' => ['中国', '广西', '梧州'],
    ];
}

function geo_is_whitelisted(string $ip): bool
{
    return isset(geo_whitelist()[$ip]);
}

/** 一次性把 xdb 读进内存并缓存 */
function geo_xdb_buffer(bool $v6 = false): ?string
{
    static $buf4 = null;
    static $loaded4 = false;
    static $buf6 = null;
    static $loaded6 = false;
    if ($v6) {
        if ($loaded6) {
            return $buf6;
        }
        $loaded6 = true;
        $buf6 = is_file(GEO_XDB_FILE_V6) ? @file_get_contents(GEO_XDB_FILE_V6) : false;
        return $buf6 === false ? null : $buf6;
    }
    if ($loaded4) {
        return $buf4;
    }
    $loaded4 = true;
    $buf4 = is_file(GEO_XDB_FILE) ? @file_get_contents(GEO_XDB_FILE) : false;
    return $buf4 === false ? null : $buf4;
}

/** 读取 iploc.json 缓存（静态内存 + 文件） */
function geo_cache_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = @file_get_contents(GEO_CACHE_FILE);
    $d = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $cache = is_array($d) ? $d : [];
    return $cache;
}

/** 将内存缓存写回 iploc.json */
function geo_cache_save(): void
{
    $cache = geo_cache_load();
    if (count($cache) > GEO_CACHE_MAX) {
        $cache = array_slice($cache, -intval(GEO_CACHE_MAX * 0.7), null, true);
    }
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    @file_put_contents(GEO_CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** 用本地 xdb 解析 IP，返回原始 region 字符串（如 中国|山西省|太原市|电信|CN） */
function geo_raw_lookup(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return '';
    }
    $byteLen = strlen($bin);
    if ($byteLen === 16) {
        // IPv6 兼容映射 ::ffff:a.b.c.d 转 IPv4
        if (substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            $bin = substr($bin, 12);
            $byteLen = 4;
        }
    }
    if ($byteLen !== 4 && $byteLen !== 16) {
        return '';
    }
    $raw = geo_xdb_buffer($byteLen === 16);
    if ($raw === null || strlen($raw) < 256) {
        return '';
    }
    // 结构版本：2 / 3
    if (in_array(unpack('v', substr($raw, 0, 2))[1], [2, 3], true) === false) {
        return '';
    }
    // 段索引：IPv4 为 14 字节（4+4+2+4），IPv6 为 38 字节（16+16+2+4）
    $entrySize = $byteLen === 16 ? 38 : 14;

    $b0 = ord($bin[0]);
    $b1 = ord($bin[1]);
    $idx = 256 + (($b0 * 256) + $b1) * 8;
    $sptr = unpack('V', substr($raw, $idx, 4))[1];
    $eptr = unpack('V', substr($raw, $idx + 4, 4))[1];
    if ($sptr === 0 || $eptr === 0) {
        return '';
    }
    $lo = 0;
    $hi = intdiv($eptr - $sptr, $entrySize);
    $dataPtr = 0;
    $dataLen = 0;
    while ($lo <= $hi) {
        $m = intdiv($lo + $hi, 2);
        $p = $sptr + ($m * $entrySize);
        $seg = substr($raw, $p, $entrySize);
        if (strlen($seg) < $entrySize) {
            break;
        }
        $cmpStart = strcmp($bin, substr($seg, 0, $byteLen));
        $cmpEnd = strcmp($bin, substr($seg, $byteLen, $byteLen));
        if ($cmpStart < 0) {
            $hi = $m - 1;
        } elseif ($cmpEnd > 0) {
            $lo = $m + 1;
        } else {
            $dataLen = unpack('v', substr($seg, $byteLen * 2, 2))[1];
            $dataPtr = unpack('V', substr($seg, ($byteLen * 2) + 2, 4))[1];
            break;
        }
    }
    if ($dataLen === 0 || $dataPtr === 0) {
        return '';
    }
    return substr($raw, $dataPtr, $dataLen);
}

/** 获取 IP 的解析结果（带缓存；$write=false 时不落盘） */
function geo_lookup(string $ip, bool $write = true): array
{
    static $mem = [];
    if (isset($mem[$ip])) {
        return $mem[$ip];
    }
    $wl = geo_whitelist();
    if (isset($wl[$ip])) {
        $r = ['country' => $wl[$ip][0], 'country_code' => '', 'province' => $wl[$ip][1], 'city' => $wl[$ip][2]];
        $mem[$ip] = $r;
        return $r;
    }
    $cache = geo_cache_load();
    // IPv6 命中整段 /64 覆盖记录（隐私地址后缀会变，前缀不变）
    if (strpos($ip, ':') !== false && strpos($ip, '::ffff:') !== 0) {
        $k64 = geo_v6_prefix64($ip);
        if ($k64 !== '' && isset($cache[$k64]) && is_array($cache[$k64])) {
            $mem[$ip] = $cache[$k64];
            return $cache[$k64];
        }
    }
    if (isset($cache[$ip]) && is_array($cache[$ip]) && ($cache[$ip]['country'] ?? null) !== null) {
        $mem[$ip] = $cache[$ip];
        return $cache[$ip];
    }
    $raw = geo_raw_lookup($ip);
    if ($raw === '') {
        $r = ['country' => '', 'country_code' => '', 'province' => '0', 'city' => '0'];
    } else {
        $parts = explode('|', $raw);
        $r = [
            'country'      => (string) ($parts[0] ?? ''),
            'country_code' => (string) ($parts[4] ?? ''),
            'province'     => (string) ($parts[1] ?? '0'),
            'city'         => (string) ($parts[2] ?? '0'),
        ];
    }
    $mem[$ip] = $r;
    if ($write) {
        $cache[$ip] = $r;
        geo_cache_save();
    }
    return $r;
}

/** 判断 IP 是否为内网 / 保留地址（本机、私网段、多播、保留段） */
function geo_ip_class(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return 'local';
    }
    if (strlen($bin) === 16) {
        if (substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            $bin = substr($bin, 12);
        } else {
            // IPv6：回环 ::1、ULA fc00::/7、链路本地 fe80::/10、多播 ff00::/8 视为本机/内网
            $b0 = ord($bin[0]);
            if ($b0 === 0) {
                $tail = rtrim(substr($bin, 15, 1), "\x00");
                if (substr($bin, 0, 15) === str_repeat("\x00", 15) && $tail === "\x01") {
                    return 'local';
                }
            } elseif ($b0 === 0xff) {
                return 'local';
            }
            if (($b0 & 0xfe) === 0xfc || ($b0 & 0xff) === 0xfe) {
                return 'local';
            }
            return 'public';
        }
    }
    $b0 = ord($bin[0]);
    $b1 = ord($bin[1]);
    if ($b0 === 10 || $b0 === 127 || $b0 === 0 || $b0 === 255) {
        return 'local';
    }
    if ($b0 === 172 && ($b1 & 0xf0) === 16) {
        return 'local';
    }
    if ($b0 === 192 && $b1 === 168) {
        return 'local';
    }
    if ($b0 === 169 && $b1 === 254) {
        return 'local';
    }
    if ($b0 >= 224) {
        return 'local';
    }
    return 'public';
}

/** 省份简称 → 地图全称（与 DataV / ECharts 中国地图片名一致） */
function geo_province_map_name(string $province): string
{
    static $map = [
        '内蒙古' => '内蒙古自治区',
        '广西'   => '广西壮族自治区',
        '西藏'   => '西藏自治区',
        '宁夏'   => '宁夏回族自治区',
        '新疆'   => '新疆维吾尔自治区',
        '香港'   => '香港特别行政区',
        '澳门'   => '澳门特别行政区',
        '台湾'   => '台湾省',
    ];
    if (isset($map[$province])) {
        return $map[$province];
    }
    return $province;
}

/** 省级全称 → 简称（缓存存储用；已是简称则原样返回） */
function geo_province_short_name(string $province): string
{
    static $map = [
        '内蒙古自治区'  => '内蒙古',
        '广西壮族自治区' => '广西',
        '西藏自治区'   => '西藏',
        '宁夏回族自治区' => '宁夏',
        '新疆维吾尔自治区' => '新疆',
        '香港特别行政区' => '香港',
        '澳门特别行政区' => '澳门',
        '台湾省'       => '台湾',
        '北京市'       => '北京',
        '天津市'       => '天津',
        '上海市'       => '上海',
        '重庆市'       => '重庆',
        '黑龙江省'     => '黑龙江',
        '河北省'       => '河北',
        '山西省'       => '山西',
        '辽宁省'       => '辽宁',
        '吉林省'       => '吉林',
        '江苏省'       => '江苏',
        '浙江省'       => '浙江',
        '安徽省'       => '安徽',
        '福建省'       => '福建',
        '江西省'       => '江西',
        '山东省'       => '山东',
        '河南省'       => '河南',
        '湖北省'       => '湖北',
        '湖南省'       => '湖南',
        '广东省'       => '广东',
        '海南省'       => '海南',
        '四川省'       => '四川',
        '贵州省'       => '贵州',
        '云南省'       => '云南',
        '陕西省'       => '陕西',
        '甘肃省'       => '甘肃',
        '青海省'       => '青海',
    ];
    return $map[$province] ?? $province;
}

/** 取出 IPv6 的 /64 前缀（隐私地址后缀变化不敏感；非 v6 返回空串） */
function geo_v6_prefix64(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) {
        return '';
    }
    $hex = bin2hex(substr($bin, 0, 8));
    return 'v6#' . substr($hex, 0, 4) . ':' . substr($hex, 4, 4)
        . ':' . substr($hex, 8, 4) . ':' . substr($hex, 12, 4);
}

/** 将客户端验证过的地理信息写入 iploc.json（覆盖旧缓存；IPv6 按 /64 前缀存储） */
function geo_override(string $ip, string $country, string $province, string $city): void
{
    $key = $ip;
    if (strpos($ip, ':') !== false && strpos($ip, '::ffff:') !== 0) {
        $k64 = geo_v6_prefix64($ip);
        if ($k64 !== '') {
            $key = $k64;
        }
    }
    $cache = geo_cache_load();
    $cache[$key] = [
        'country'      => $country,
        'country_code' => '',
        'province'     => $province,
        'city'         => $city,
    ];
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    @file_put_contents(GEO_CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** 从 UA 识别 设备类型 与 操作系统 */
function device_info(string $ua): array
{
    $s = strtolower($ua);
    if (strpos($s, 'iphone') !== false || strpos($s, 'ipod') !== false) {
        return ['device' => '手机', 'os' => 'iOS'];
    }
    if (strpos($s, 'ipad') !== false) {
        return ['device' => '平板', 'os' => 'iOS'];
    }
    if (strpos($s, 'android') !== false) {
        $dev = (strpos($s, 'tablet') !== false) ? '平板' : '手机';
        return ['device' => $dev, 'os' => 'Android'];
    }
    if (strpos($s, 'windows') !== false) {
        return ['device' => '电脑', 'os' => 'Windows'];
    }
    if (strpos($s, 'macintosh') !== false || strpos($s, 'mac os') !== false) {
        return ['device' => '电脑', 'os' => 'macOS'];
    }
    if (strpos($s, 'linux') !== false) {
        return ['device' => '电脑', 'os' => 'Linux'];
    }
    return ['device' => '其他', 'os' => '其他'];
}
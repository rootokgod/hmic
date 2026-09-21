<?php
/**
 * 安装向导 - 基础工具
 */
if (!defined('IW_ROOT')) {
    // 站点根目录（install/ 的上一级）
    define('IW_ROOT', dirname(__DIR__, 2));
    define('IW_DIR', dirname(__DIR__)); // install/ 目录（本文件位于 install/lib/ 下）
    define('IW_DATA', IW_ROOT . '/data');
    define('IW_CFG', IW_DATA . '/Configuration');

    // 安装向导入口 URL（始终指向 install/index.php）：
    // 无论运行目录是「站点根」还是「/public」，都可通过该地址回到向导，
    // 避免完成安装前切换运行目录后表单无处提交。站点部署于子目录亦可正确计算。
    $__iwSn  = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $__iwDir = rtrim(dirname($__iwSn), '/');
    if (substr($__iwDir, -8) !== '/install') {
        $__iwDir .= '/install';
    }
    define('IW_BASE', $__iwDir . '/index.php');
}

function iw_lock_file(): string
{
    return IW_CFG . '/install.lock';
}

function iw_installed(): bool
{
    $f = iw_lock_file();
    return is_file($f) && (filesize($f) ?: 0) > 0;
}

function iw_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @ini_set('session.use_strict_mode', '1');
        @session_name('iwz');
        @session_start();
    }
}

function iw_h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function iw_self_url(): string
{
    $u = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($u === '') {
        return '/';
    }
    $p = strpos($u, '?');
    return $p === false ? $u : substr($u, 0, $p);
}

function iw_redirect(string $target): void
{
    if (str_starts_with($target, '/') || preg_match('#^https?://#i', $target)) {
        $url = $target;
    } else {
        $url = IW_BASE . $target;
    }
    if (!headers_sent()) {
        @header('Location: ' . $url, true, 302);
    } else {
        echo '<meta http-equiv="refresh" content="0;url=' . iw_h($url) . '">';
    }
    exit;
}

function iw_post(string $key, string $def = ''): string
{
    $v = $_POST[$key] ?? null;
    return is_string($v) ? trim($v) : $def;
}

function iw_sess_set(string $key, mixed $value): void
{
    if (!isset($_SESSION['iwz']) || !is_array($_SESSION['iwz'])) {
        $_SESSION['iwz'] = [];
    }
    $_SESSION['iwz'][$key] = $value;
}

function iw_sess_get(string $key, mixed $def = null): mixed
{
    $s = isset($_SESSION['iwz']) && is_array($_SESSION['iwz']) ? $_SESSION['iwz'] : [];
    return array_key_exists($key, $s) ? $s[$key] : $def;
}
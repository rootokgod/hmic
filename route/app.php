<?php
// +----------------------------------------------------------------------
// | 路由定义（动态后台前缀 ）
// +----------------------------------------------------------------------
use think\facade\Route;
use think\middleware\SessionInit;
use app\middleware\AdminAuth;
use app\middleware\AdminAccessLog;

// 后台前缀源自设置（默认 admin），修改后旧地址立即失效
require_once dirname(__DIR__) . '/common.php';
$ADMIN = admin_base();

// -------- 首页 / 数据 API --------
Route::get('/', 'Index/index');
Route::get('index.php', 'Index/index');

// -------- 下载（令牌模式 + 旧签名模式）--------
Route::get('download.php', 'Down/index');
Route::post('download.php', 'Down/index');
Route::get('d/:token', 'Down/index');

// -------- 图片令牌代理 --------
Route::get('img.php', 'Img/index');

// -------- 贴纸图片公开服务 --------
Route::get('stk/:file', 'Sticker/index');

// -------- MAS 激活脚本 --------
Route::get('mas.ps1', 'Mas/index');
Route::get('mas_ps1.php', 'Mas/index');

// -------- 密码保护解锁（POST JSON/表单）--------
Route::post('api/unlock', 'Index/unlock');

// -------- 后台管理（动态前缀，需登录 + 访问日志）--------
Route::group($ADMIN, function () {
    Route::get('', 'Admin/index');
    Route::get('api/dashboard', 'Admin/dashboard');
    Route::get('api/links', 'Admin/links');
    Route::get('api/logs', 'Admin/logs');
    Route::get('api/visitmap', 'Admin/visitmap');
    Route::post('api/report-ip', 'Admin/reportIp');
    Route::get('api/cache', 'Admin/cache');
    Route::post('api/cache', 'Admin/cache');
    Route::get('home', 'Admin/home');
    Route::get('home-style', 'Admin/homeStyle');
    Route::get('visual', 'Admin/visual');
    Route::post('api/layout', 'Admin/layoutSave');
    Route::get('sticker', 'Admin/sticker');
    Route::get('api/stickers', 'Admin/stickers');
    Route::post('api/sticker/upload', 'Admin/stickerUpload');
    Route::post('api/sticker/delete', 'Admin/stickerDelete');
    Route::get('notice', 'Admin/notice');
    Route::post('api/notice', 'Admin/noticeSave');
    Route::get('files', 'Admin/files');
    Route::post('api/files/upload', 'Admin/filesUpload');
    Route::post('api/files/rename', 'Admin/filesRename');
    Route::post('api/files/meta', 'Admin/filesMeta');
    Route::post('api/files/lock', 'Admin/filesLock');
    Route::post('api/files/mkdir', 'Admin/filesMkdir');
    Route::post('api/files/bulk-link', 'Admin/filesBulkLink');
    Route::post('api/files/delete', 'Admin/filesDelete');
    Route::post('api/files/size', 'Admin/filesSize');
    Route::post('api/home', 'Admin/homeSave');
    Route::post('api/feat', 'Admin/featSave');
    Route::get('password', 'Admin/password');
    Route::post('password', 'Admin/password');
    Route::get('system-logs', 'Admin/systemLogs');
    Route::get('api/system-logs', 'Admin/systemLogsApi');
    Route::get('about', 'Admin/about');
    Route::get('about/framework', 'Admin/aboutFramework');
    Route::get('settings', 'Admin/settings');
    Route::post('api/settings/account', 'Admin/settingsAccount');
    Route::post('api/settings/path', 'Admin/settingsPath');
    Route::post('api/settings/gen-path', 'Admin/settingsPathGen');
})->middleware([SessionInit::class, AdminAccessLog::class, AdminAuth::class]);

// -------- 后台登录 / 验证码 / 退出（无需登录）--------
Route::group($ADMIN, function () {
    Route::get('login', 'Admin/login');
    Route::post('login', 'Admin/login');
    Route::get('captcha', 'Admin/captcha');
    Route::get('logout', 'Admin/logout');
})->middleware([SessionInit::class, AdminAccessLog::class]);

// -------- 兜底 404（记录访问后台的未匹配请求）--------
Route::miss(function () {
    require_once dirname(__DIR__) . '/common.php';
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '') {
        $uri = '/';
    }
    $base = admin_base();
    if (str_starts_with($uri, '/' . $base) || ($base !== 'admin' && str_starts_with($uri, '/admin'))) {
        admin_access_log();
    }
    $ct   = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $acc  = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $jsonReq = str_contains($ct, 'json') || str_contains($acc, 'json');
    if ($jsonReq) {
        return json(['ok' => false, 'code' => 404, 'msg' => '接口不存在'], 404, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }
    $file = app()->getRootPath() . 'application/view/error/error.php';
    if (is_file($file)) {
        $code   = 404;
        $title  = '页面不存在';
        $msg    = '您访问的地址不存在或已被移除，请检查地址后重试。';
        $detail = '';
        ob_start();
        include $file;
        return response(ob_get_clean(), 404);
    }
    return response('Not Found', 404);
});

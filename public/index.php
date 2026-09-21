<?php
// +----------------------------------------------------------------------
// | ThinkPHP 8 单入口
// | 应用目录指向站点根 application/（避开现有 app/ 资源目录）
// +----------------------------------------------------------------------
namespace think;

require __DIR__ . '/../vendor/autoload.php';

// 程序安装向导：探针 + 未安装兜底。
// 运行目录被切换至 /public 且尚未完成安装时，引导进入安装向导；
// 安装完成后（存在 data/Configuration/install.lock）则正常进入应用。
$__iwPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($__iwPath === '/__install__/ping') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'pkg' => 'install-wizard-public', 't' => time()]);
    exit;
}
$__iwLock = dirname(__DIR__) . '/data/Configuration/install.lock';
if (!is_file($__iwLock) || (filesize($__iwLock) ?: 0) <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>尚未安装</title></head>'
        . '<body style="font-family:system-ui;background:#f1f3f6;color:#111;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0"><div style="text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px 36px;max-width:440px">'
        . '<div style="font-size:24px;font-weight:700;margin-bottom:10px">系统尚未完成安装</div>'
        . '<p style="color:#555;font-size:13.5px;line-height:1.7;margin:0 0 20px">程序安装向导未完成初始化配置。请点击下方按钮进入安装向导继续完成安装。</p>'
        . '<a href="/install/" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-size:14px">前往安装向导</a></div></body></html>';
    exit;
}

// nginx 采用 try_files 转发（未设置 PATH_INFO），此处依据 REQUEST_URI 补全，
// 使 ThinkPHP 能正确解析路由，无需修改 nginx 配置
if (empty($_SERVER['PATH_INFO'])) {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $_SERVER['PATH_INFO'] = rawurldecode($requestPath === null || $requestPath === '' ? '/' : $requestPath);
}

$app = new App(dirname(__DIR__));
$app->setAppPath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);

// 全局异常处理：任何错误都渲染为体面错误页 / JSON，绝不出现空白页
$app->bind(\think\exception\Handle::class, \app\ExceptionHandle::class);

$http = $app->http;
try {
    $response = $http->run();
    $response->send();
    $http->end($response);
} catch (\Throwable $e) {
    // 极少数在“发送/收尾阶段”再次出错的情况：兜底输出内联错误页，确保永不空白
    $code = 500;
    if ($e instanceof \think\exception\HttpException) {
        $c = (int) $e->getStatusCode();
        if ($c >= 400 && $c <= 599) {
            $code = $c;
        }
    }
    $errLog = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'errors-dispatch.log';
    @file_put_contents(
        $errLog,
        date('Y-m-d H:i:s') . ' [fatal-dispatch] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $code . ' - 服务器错误</title></head><body style="font-family:system-ui;background:#f7f8fa;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0"><div style="text-align:center;padding:48px 44px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;max-width:460px"><h1 style="font-size:64px;margin:0 0 8px;color:#dc2626">' . $code . '</h1><p style="color:#6b7280;font-size:14px;line-height:1.7;margin:0 0 22px">服务器内部错误，请稍后重试。</p><a href="./" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:9px 22px;border-radius:8px;font-size:14px">返回首页</a></div></body></html>';
}
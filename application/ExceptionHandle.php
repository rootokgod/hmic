<?php
declare(strict_types=1);

namespace app;

use think\exception\Handle;
use think\exception\HttpException;
use think\Request;
use think\Response;
use Throwable;

/**
 * 全局异常处理：任何错误都渲染为体面错误页 / JSON，绝不出现空白页或裸文本。
 * 渲染过程不使用模板引擎、不访问 Session，确保自身永不二次抛错。
 */
class ExceptionHandle extends Handle
{
    protected const GUIDE = [
        400 => ['请求不正确', '抱歉，您的请求有误，请返回重试。'],
        401 => ['未登录或登录已过期', '请重新登录后继续操作。'],
        403 => ['没有访问权限', '您没有权限访问该资源，如有疑问请联系管理员。'],
        404 => ['页面不存在', '您访问的地址不存在或已被移除，请检查地址后重试。'],
        405 => ['请求方式不支持', '该地址不接受此种请求方式，请返回重试。'],
        406 => ['无法按要求返回', '服务器无法根据请求生成可接受的响应。'],
        410 => ['链接已过期', '该链接已过期，请返回站点刷新页面重新获取。'],
        413 => ['请求内容过大', '请求内容超出大小限制，请精简后重试。'],
        415 => ['内容类型不支持', '请求的内容类型不受支持，请检查后重试。'],
        422 => ['参数校验失败', '提交的参数不正确，请检查后重试。'],
        429 => ['请求过于频繁', '操作过于频繁，请稍后再试。'],
        500 => ['服务器开小差了', '服务器内部错误，请稍后重试。'],
        502 => ['网关错误', '上游服务暂不可用，请稍后重试。'],
        503 => ['服务暂时不可用', '服务正在维护或过载，请稍后再试。'],
        504 => ['网关超时', '上游服务响应超时，请稍后重试。'],
    ];

    public function render(Request $request, Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        $this->logError($request, $e, $status);

        if ($request->isJson()) {
            [$title, $msg] = $this->guide($status);
            return Response::create([
                'ok'   => false,
                'code' => $status,
                'msg'  => $status === 500 ? $msg : ($this->safeMsg($e->getMessage()) ?: $msg),
            ], 'json', $status);
        }

        return Response::create($this->renderHtml($status, $e), 'html', $status);
    }

    protected function renderHtml(int $status, Throwable $e): string
    {
        [$title, $msg] = $this->guide($status);
        if ($status !== 404 && $status !== 500) {
            $msg = $this->safeMsg($e->getMessage()) ?: $msg;
        }

        $file = $this->app->getAppPath() . 'view/error/error.php';

        if (!is_file($file)) {
            // 极端兜底：模板缺失时输出内联页面，保证永不空白
            return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $status . ' - ' . $title . '</title></head><body style="font-family:system-ui;background:#f7f8fa;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0"><div style="text-align:center;padding:48px 44px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;max-width:460px"><h1 style="font-size:64px;margin:0 0 8px;color:#dc2626">' . $status . '</h1><p style="color:#6b7280;font-size:14px;line-height:1.7;margin:0 0 22px">' . $title . '</p><a href="./" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:9px 22px;border-radius:8px;font-size:14px">返回首页</a></div></body></html>';
        }

        $code   = $status;
        $detail = $this->app->isDebug() ? $this->formatDetail($e) : '';
        ob_start();
        include $file;
        return ob_get_clean();
    }

    protected function formatDetail(Throwable $e): string
    {
        $out = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        $prev = $e->getPrevious();
        if ($prev !== null) {
            $out .= "\ncaused by " . get_class($prev) . ': ' . $prev->getMessage() . ' @ ' . $prev->getFile() . ':' . $prev->getLine();
        }
        return $out;
    }

    protected function guide(int $status): array
    {
        return self::GUIDE[$status] ?? [$status . ' 错误', '抱歉，操作未能完成，请稍后重试。'];
    }

    protected function safeMsg(string $raw): string
    {
        $raw = trim($raw);
        return $raw === '' ? '' : $raw;
    }

    protected function logError(Request $request, Throwable $e, int $status): void
    {
        $logDir = $this->app->getRuntimePath() . 'errors';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $id  = bin2hex(random_bytes(4));
        $uri = $request->url(true);
        $tel = '(no trace)';
        try {
            $tel = $e->getTraceAsString();
        } catch (Throwable $ignored) {
        }
        $line = sprintf(
            "{%s} %s %d | %s %s | %s: %s @ %s:%d",
            date('Y-m-d H:i:s'),
            $id,
            $status,
            $request->method(),
            $uri,
            get_class($e),
            str_replace("\n", ' ', $e->getMessage()),
            $e->getFile(),
            $e->getLine()
        );
        @file_put_contents(
            $logDir . '/errors-' . date('Y-m-d') . '.log',
            $line . PHP_EOL . $tel . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
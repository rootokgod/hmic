<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 后台访问日志中间件
 * 记录所有进入后台前缀的请求（IP / 路径 / 时间 / User-Agent），登录页与未匹配路径也不例外。
 */
class AdminAccessLog
{
    public function handle(Request $request, Closure $next): Response
    {
        require_once dirname(__DIR__, 2) . '/common.php';
        admin_access_log();

        return $next($request);
    }
}

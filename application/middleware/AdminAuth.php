<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Session;

/**
 * 后台登录校验中间件
 * 未登录一律重定向到当前后台前缀下的 /login
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        require_once dirname(__DIR__, 2) . '/common.php';

        if ((string) Session::get('admin_user', '') === '') {
            return redirect('/' . admin_base() . '/login');
        }

        return $next($request);
    }
}

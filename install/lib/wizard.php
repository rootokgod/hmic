<?php
/**
 * 安装向导 - 逻辑（路由 / 校验 / 环境检测 / 创建配置 / 完成标记）
 */

/** 后台路径保留字（与 common.php 中 admin_path_valid 保持一致并扩充向导相关目录） */
function iw_admin_reserved(): array
{
    return [
        'd', 'stk', 'img', 'download.php', 'mas_ps1.php', 'mas.ps1', 'index.php', 'api',
        'assets', 'networkpanel', 'data', 'app', 'vendor', 'runtime', 'install',
        'public', 'application', 'config', 'route', 'config.php', 'common.php', 'files.php',
    ];
}

/** 后台安全路径格式校验 */
function iw_valid_admin_path(string $p): bool
{
    $p = trim($p);
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,31}$/', $p)) {
        return false;
    }
    return !in_array(strtolower($p), iw_admin_reserved(), true);
}

/** 管理员账号 / 密码校验：返回错误信息（空串表示通过） */
function iw_validate_account(string $user, string $pass, string $pass2): string
{
    if ($user === '' || $pass === '' || $pass2 === '') {
        return '请完整填写管理员用户名、密码与确认密码。';
    }
    if (mb_strlen($user, 'UTF-8') < 2 || mb_strlen($user, 'UTF-8') > 32) {
        return '管理员用户名长度需在 2~32 个字符之间。';
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $user)) {
        return '管理员用户名仅允许使用字母、数字、下划线（_）与连字符（-）。';
    }
    if (strlen($pass) < 6) {
        return '管理员密码长度不能少于 6 位。';
    }
    if (strlen($pass) > 64) {
        return '管理员密码长度不能超过 64 位。';
    }
    if ($pass !== $pass2) {
        return '两次输入的密码不一致，请重新确认。';
    }
    if (strtolower($pass) === strtolower($user)) {
        return '密码不能与用户名相同，请更换更安全的密码。';
    }
    return '';
}

/** GET 分发 */
function iw_dispatch(): void
{
    $step = (string) ($_GET['step'] ?? 'welcome');
    switch ($step) {
        case 'env':
            iw_render_env(iw_check_env(), iw_sess_get('env_rewrite', false));
            break;
        case 'path':
            iw_render_path('', (string) iw_sess_get('admin_path', 'admin'), (string) iw_sess_get('path_mode', 'default'));
            break;
        case 'account':
            iw_render_account('', (string) iw_sess_get('admin_user', ''));
            break;
        case 'created':
            iw_render_created();
            break;
        case 'finish':
            iw_render_finish();
            break;
        default:
            iw_render_welcome();
    }
}

/** POST 处理 */
function iw_handle_post(): void
{
    $act = iw_post('act');

    switch ($act) {
        case 'begin':
        case 'rescan':
            iw_redirect('?step=env');

        case 'env_next':
            iw_sess_set('env_rewrite', iw_post('rewrite_ok') === '1');
            iw_redirect('?step=path');

        case 'path_next':
            $mode = iw_post('path_mode', 'default');
            $raw  = iw_post('admin_path', 'admin');
            $path = $raw !== '' ? $raw : 'admin';
            if ($mode === 'custom') {
                $err = iw_valid_admin_path($path)
                    ? ''
                    : '安全路径格式不正确：需以字母开头，仅包含字母、数字、-、_，长度 2~32；且不能与系统保留关键字冲突。';
                if ($err !== '') {
                    iw_render_path($err, $raw, $mode);
                    return;
                }
            } else {
                $path = 'admin';
            }
            iw_sess_set('path_mode', $mode);
            iw_sess_set('admin_path', $path);
            iw_redirect('?step=account');

        case 'account_next':
            $user  = iw_post('admin_user');
            $pass  = (string) ($_POST['admin_pass'] ?? '');
            $pass2 = (string) ($_POST['admin_pass2'] ?? '');
            $err   = iw_validate_account($user, $pass, $pass2);
            if ($err !== '') {
                iw_render_account($err, $user);
                return;
            }
            iw_sess_set('admin_user', $user);
            iw_sess_set('admin_pass', $pass);

            // 第 5 步：创建 json 与 app 基础配置
            $results = iw_build_base_config();
            iw_sess_set('create_results', $results);
            iw_redirect('?step=created');

        case 'created_next':
            iw_redirect('?step=finish');

        case 'finish':
            if (!iw_write_lock()) {
                iw_render_error('安装完成标记写入失败，请检查 data/Configuration 目录写入权限后重试。');
                return;
            }
            iw_redirect('/');

        default:
            iw_render_welcome();
    }
}

/** 环境检测 */
function iw_check_env(): array
{
    $items = [];

    // PHP 版本（推荐 ~8.5，最低 8.1）
    if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
        $state = 'ok';
        $text  = 'PHP ' . PHP_VERSION . '（推荐版本，兼容性优秀）';
    } elseif (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        $state = 'ok';
        $text  = 'PHP ' . PHP_VERSION . '（可用，建议升级至 PHP 8.5 左右的高版本）';
    } else {
        $state = 'danger';
        $text  = 'PHP ' . PHP_VERSION . '（版本过旧，推荐 PHP 8.5 左右的高版本）';
    }
    $items[] = ['key' => 'php', 'label' => 'PHP 版本', 'state' => $state, 'text' => $text];

    // 关键扩展
    $extMap = [
        'json'     => 'json（JSON 数据）',
        'pcre'     => 'pcre（正则匹配）',
        'mbstring' => 'mbstring（多字节字符）',
        'openssl'  => 'openssl（密码哈希 / 随机数）',
        'session'  => 'session（向导会话）',
    ];
    foreach ($extMap as $ext => $label) {
        $loaded = extension_loaded($ext);
        $items[] = [
            'key'   => 'ext_' . $ext,
            'label' => '扩展 ' . $label,
            'state' => $loaded ? 'ok' : 'danger',
            'text'  => $loaded ? '已启用' : '未安装，请安装并启用该扩展',
        ];
    }

    // 目录写入权限（缺失目录由向导自动创建）
    $dirs = [
        'data'                => IW_DATA,
        'data/Configuration'  => IW_CFG,
        'data/jpg/sticker'    => IW_ROOT . '/data/jpg/sticker',
        'data/logs'           => IW_ROOT . '/data/logs',
        'data/mas'            => IW_ROOT . '/data/mas',
        'app'                 => IW_ROOT . '/app',
        'runtime'             => IW_ROOT . '/runtime',
    ];
    $bad = [];
    foreach ($dirs as $label => $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        $probe = $d . '/._iwt';
        if (!@file_put_contents($probe, '1')) {
            $bad[] = $label;
        }
        @unlink($probe);
    }
    $items[] = [
        'key'   => 'writable',
        'label' => '数据目录写入权限',
        'state' => $bad === [] ? 'ok' : 'danger',
        'text'  => $bad === []
            ? 'data / app / runtime 等目录可正常写入'
            : '以下目录不可写：' . implode('、', $bad) . '（请设置为 www 用户可写，如 755/775）',
    ];

    // 伪静态 / 友好路由（可选优化项，非安装必需）：由前端通过 /__install__/ping 探针在线测定
    $items[] = [
        'key'     => 'rewrite',
        'label'   => '伪静态 / 友好路由（可选）',
        'state'   => 'pending',
        'text'    => '正在检测伪静态规则…（未配置也不影响安装与使用）',
        'rewrite' => true,
    ];

    return $items;
}

/** 创建基础配置（第 5 步）：app 目录 + json 数据文件；返回逐项结果 */
function iw_build_base_config(): array
{
    $rows = [];

    // 目录
    $dirs = [
        'app 目录'                => IW_ROOT . '/app',
        'data 目录'               => IW_DATA,
        'data/Configuration 目录' => IW_CFG,
        'data/jpg/sticker 目录'   => IW_ROOT . '/data/jpg/sticker',
        'data/logs 目录'          => IW_ROOT . '/data/logs',
        'data/mas 目录'           => IW_ROOT . '/data/mas',
    ];
    foreach ($dirs as $label => $d) {
        if (is_dir($d)) {
            $rows[] = ['status' => 'ok', 'label' => $label, 'note' => '已存在'];
            continue;
        }
        $rows[] = @mkdir($d, 0755, true)
            ? ['status' => 'ok', 'label' => $label, 'note' => '已创建']
            : ['status' => 'err', 'label' => $label, 'note' => '创建失败，请检查权限'];
    }

    // 附属防护 / 占位文件
    $keepFiles = [
        IW_ROOT . '/app/.htaccess'             => "Require all denied\n",
        IW_DATA . '/.htaccess'                 => "Require all denied\n",
        IW_ROOT . '/app/.gitkeep'              => '',
        IW_ROOT . '/data/jpg/sticker/.gitkeep' => '',
    ];
    foreach ($keepFiles as $f => $content) {
        if (is_file($f)) {
            continue;
        }
        $rel = str_replace(IW_ROOT . '/', '', $f);
        $rows[] = @file_put_contents($f, $content) !== false
            ? ['status' => 'ok', 'label' => $rel, 'note' => '已创建']
            : ['status' => 'err', 'label' => $rel, 'note' => '写入失败'];
    }

    // 基础 json 数据（不存在才创建；若已存在则保留站点已有运行数据）
    $baseJson = [
        'data/index.json'               => [],
        'data/active.json'              => [],
        'data/login_guard.json'         => new stdClass(),
        'data/filemap.json'             => new stdClass(),
        'data/img_tokens.json'          => new stdClass(),
        'data/visitmap.json'            => new stdClass(),
        'data/links.json'               => [],
        'data/Configuration/items.json' => [],
        'data/Configuration/locks.json' => new stdClass(),
    ];
    foreach ($baseJson as $rel => $def) {
        $abs = IW_ROOT . '/' . $rel;
        if (is_file($abs)) {
            $rows[] = ['status' => 'ok', 'label' => $rel, 'note' => '已存在'];
            continue;
        }
        $json = json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $ok   = @file_put_contents($abs, $json, LOCK_EX) !== false;
        $rows[] = $ok
            ? ['status' => 'ok', 'label' => $rel, 'note' => '已创建']
            : ['status' => 'err', 'label' => $rel, 'note' => '写入失败'];
    }

    // 统计日志占位
    $stat = IW_DATA . '/stats.log';
    if (is_file($stat)) {
        $rows[] = ['status' => 'ok', 'label' => 'data/stats.log', 'note' => '已存在'];
    } else {
        $rows[] = @file_put_contents($stat, '') !== false
            ? ['status' => 'ok', 'label' => 'data/stats.log', 'note' => '已创建']
            : ['status' => 'err', 'label' => 'data/stats.log', 'note' => '写入失败'];
    }

    // 运行期配置（由安装向导写入）
    $path = (string) iw_sess_get('admin_path', 'admin');
    $user = (string) iw_sess_get('admin_user', '');
    $pass = (string) iw_sess_get('admin_pass', '');

    $settings = [
        'v4_url'           => 'https://ipv4.20070622.xyz',
        'v6_url'           => 'https://ipv6.20070622.xyz',
        'site_name'        => 'God Supremus 的工具站',
        'site_slogan'      => '杀毒 · 安全 · 应急工具分享',
        'alt_enabled'      => true,
        'donate_enabled'   => true,
        'donate_image'     => '',
        'netpanel_enabled' => true,
        'mas_enabled'      => true,
        'recent_enabled'   => true,
        'home_enabled'     => false,
        'admin_path'       => $path,
        'layout'           => [
            'support' => ['show' => true, 'label' => '支持作者（赞助）'],
            'speed'   => ['show' => true, 'label' => '测速面板'],
            'mas'     => ['show' => true, 'label' => 'Windows激活工具'],
            'stats'   => ['show' => true],
            'ip'      => ['show' => true],
            'theme'   => ['show' => true],
        ],
        'stickers'         => new stdClass(),
    ];
    $ok = @file_put_contents(
        IW_DATA . '/settings.json',
        json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
    $rows[] = ['status' => $ok ? 'ok' : 'err', 'label' => 'data/settings.json', 'note' => $ok ? '已创建' : '写入失败'];

    // 管理员账号（bcrypt 哈希，登录走 password_verify）
    $hash = $pass !== '' ? password_hash($pass, PASSWORD_BCRYPT) : '';
    $ok   = @file_put_contents(
        IW_DATA . '/admin_hash.json',
        json_encode(['user' => $user, 'hash' => $hash], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
    $rows[] = ['status' => $ok ? 'ok' : 'err', 'label' => 'data/admin_hash.json', 'note' => $ok ? '已创建' : '写入失败'];

    // 首页 / 公告配置（兜底默认值）
    $cfgDefault = [
        'data/Configuration/home.json'   => ['enabled' => true, 'font_scale' => 1.0, 'menu_round' => 0],
        'data/Configuration/notice.json' => [
            'enabled' => true,
            'text'    => '针对银狐病毒导致无法连接 360 官网的情况,请直接从本站下载急救箱与银狐清理脚本',
        ],
    ];
    foreach ($cfgDefault as $rel => $def) {
        $abs = IW_ROOT . '/' . $rel;
        if (is_file($abs)) {
            $rows[] = ['status' => 'ok', 'label' => $rel, 'note' => '已存在'];
            continue;
        }
        $ok = @file_put_contents(
            $abs,
            json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
        $rows[] = $ok
            ? ['status' => 'ok', 'label' => $rel, 'note' => '已创建']
            : ['status' => 'err', 'label' => $rel, 'note' => '写入失败'];
    }

    // 清理会话中的明文密码
    unset($_SESSION['iwz']['admin_pass']);

    return $rows;
}

/** 写入安装完成标记 */
function iw_write_lock(): bool
{
    if (!is_dir(IW_CFG)) {
        @mkdir(IW_CFG, 0755, true);
    }
    $lockData = [
        'installed_at' => date('Y-m-d H:i:s'),
        'php_version'  => PHP_VERSION,
        'sapi'         => PHP_SAPI,
        'admin_path'   => (string) iw_sess_get('admin_path', 'admin'),
        'admin_user'   => (string) iw_sess_get('admin_user', ''),
        'installer'    => 'InstallWizard/1.0.0',
        'host'         => (string) ($_SERVER['HTTP_HOST'] ?? '-'),
    ];
    $json = json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return @file_put_contents(iw_lock_file(), $json, LOCK_EX) !== false;
}
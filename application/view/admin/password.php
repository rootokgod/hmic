<?php
/** @var string $msg */
/** @var bool   $ok */
/** @var string $user */
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>修改密码</title>
<?php include __DIR__ . '/partials/theme-head.php'; ?>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;
background:var(--bg);color:var(--ink);display:flex;align-items:center;justify-content:center;min-height:100vh}
.page-toggle{position:fixed;top:16px;right:16px;z-index:5}
.box{width:min(92vw,380px);background:var(--card);border:1px solid var(--line);border-radius:10px;padding:32px 30px}
h1{font-size:18px;font-weight:600;margin:0 0 6px}
.sub{color:var(--ink3);font-size:13px;margin:0 0 22px}
label{display:block;font-size:13px;color:var(--ink2);margin-bottom:14px}
input{width:100%;margin-top:7px;padding:10px 12px;border-radius:8px;border:1px solid var(--line);
background:var(--card);color:var(--ink);font-size:14px;outline:none;transition:border-color .15s}
input:focus{border-color:var(--ink3)}
button{width:100%;padding:11px;border:0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;
background:var(--btn);color:var(--btn-ink);transition:filter .15s}
button:hover{filter:brightness(.92)}
.msg{margin-bottom:16px;font-size:13px}
.msg.ok{color:var(--ok)}
.msg.bad{color:var(--err)}
.back{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--ink2);text-decoration:none}
.back:hover{color:var(--ink)}
</style>
</head>
<body>
<div class="page-toggle"><?php include __DIR__ . '/partials/theme-toggle.php'; ?></div>
<div class="box">
  <h1>修改登录密码</h1>
  <p class="sub">当前用户：<?= $h($user) ?></p>
  <?php if ($msg !== ''): ?>
    <div class="msg <?= $ok ? 'ok' : 'bad' ?>"><?= $h($msg) ?></div>
  <?php endif; ?>
  <form method="post" action="/admin/password" autocomplete="off">
    <label>新密码
      <input type="password" name="new1" minlength="6" required autofocus autocomplete="new-password">
    </label>
    <label>确认新密码
      <input type="password" name="new2" minlength="6" required autocomplete="new-password">
    </label>
    <button type="submit">保存新密码</button>
  </form>
  <a class="back" href="/admin">返回后台首页</a>
</div>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
</body>
</html>

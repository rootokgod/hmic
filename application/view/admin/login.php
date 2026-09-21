<?php
/** @var string $error */
/** @var string $site */
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>后台登录</title>
<?php include __DIR__ . '/partials/theme-head.php'; ?>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;
background:var(--bg);color:var(--ink);display:flex;align-items:center;justify-content:center;min-height:100vh}
.page-toggle{position:fixed;top:16px;right:16px;z-index:5}
.box{width:min(92vw,360px);background:var(--card);border:1px solid var(--line);border-radius:10px;padding:32px 30px}
h1{font-size:18px;font-weight:600;margin:0 0 6px}
.sub{color:var(--ink3);font-size:13px;margin:0 0 24px}
label{display:block;font-size:13px;color:var(--ink2);margin-bottom:14px}
input{width:100%;margin-top:7px;padding:10px 12px;border-radius:8px;border:1px solid var(--line);
background:var(--card);color:var(--ink);font-size:14px;outline:none;transition:border-color .15s}
input:focus{border-color:var(--ink3)}
button[type=submit]{width:100%;margin-top:6px;padding:11px;border:0;border-radius:8px;cursor:pointer;
background:var(--btn);color:var(--btn-ink);font-size:14px;font-weight:500;transition:filter .15s}
button[type=submit]:hover{filter:brightness(.92)}
.cap{display:flex;gap:10px;align-items:stretch;margin-top:7px}
.cap input{flex:1;margin-top:0}
.cap img{width:120px;height:42px;flex:none;border:1px solid var(--line);border-radius:8px;
background:var(--hover);cursor:pointer;display:block}
.err{margin-bottom:16px;font-size:13px;color:var(--err)}
</style>
</head>
<body>
<div class="page-toggle"><?php include __DIR__ . '/partials/theme-toggle.php'; ?></div>
<div class="box">
  <h1>后台管理登录</h1>
  <p class="sub"><?= $h($site ?? '') ?></p>
  <?php if (!empty($error)): ?>
    <div class="err"><?= $h($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/admin/login" autocomplete="off">
    <label>用户名
      <input type="text" name="user" required autofocus autocomplete="username">
    </label>
    <label>密码
      <input type="password" name="pass" required autocomplete="current-password">
    </label>
    <label>验证码
      <div class="cap">
        <input type="text" name="captcha" maxlength="4" required autocomplete="off"
               inputmode="text" autocapitalize="characters" spellcheck="false" placeholder="请输入右侧字符">
        <img src="/admin/captcha" alt="验证码" title="点击刷新"
             onclick="this.src='/admin/captcha?t='+Date.now()">
      </div>
    </label>
    <button type="submit">登 录</button>
  </form>
</div>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
</body>
</html>

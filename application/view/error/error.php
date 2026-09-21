<?php
declare(strict_types=1);
/** @var int    $code
 *  @var string $title
 *  @var string $msg
 *  @var string $detail
 */
$code   = (int) ($code ?? 500);
$title  = (string) ($title ?? '错误');
$msg    = (string) ($msg ?? '');
$detail = (string) ($detail ?? '');
$t = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
$m = htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8');
$d = htmlspecialchars((string) $detail, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $code ?> - <?= $t ?></title>
<style>
body{font-family:system-ui,"PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fa;color:#1f2937;
display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center;padding:48px 44px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;
max-width:480px;width:90%;box-shadow:0 8px 30px rgba(31,41,55,.06)}
.code{font-size:64px;font-weight:700;color:#dc2626;line-height:1;margin-bottom:6px}
h1{font-size:20px;color:#111827;margin:0 0 10px}
p{color:#6b7280;font-size:14px;line-height:1.7;margin:0 0 24px}
a{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:9px 22px;
border-radius:8px;font-size:14px;line-height:1.5}
pre.detail{margin:22px auto 0;max-width:100%;overflow:auto;text-align:left;background:#111827;color:#9ca3af;
font-size:12px;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-all}
</style>
</head>
<body>
<div class="box">
<div class="code"><?= $code ?></div>
<h1><?= $t ?></h1>
<p><?= $m ?></p>
<a href="./">返回首页</a>
<?php if ($d !== ''): ?><pre class="detail"><?= $d ?></pre><?php endif; ?>
</div>
</body>
</html>
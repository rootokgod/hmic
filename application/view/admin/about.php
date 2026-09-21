<?php
/**
 * 关于程序 - 作者信息
 * @var string $user
 * @var string $pageTitle
 * @var string $activeNav
 */
include __DIR__ . '/partials/head.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
  <div class="panel">
    <h2>作者信息</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">作者</div>
          <div class="set-d">God Supremus</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">QQ</div>
          <div class="set-d">3089337655</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">邮箱</div>
          <div class="set-d">godsupremus@gmail.com</div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>赞助作者</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">如果觉得好用，欢迎赞助支持</div>
          <div class="set-d">扫描下方二维码即可赞助，感谢你的支持</div>
          <div class="spon-img">
            <img src="/data/jpg/background/Sponsorship.png" alt="赞助二维码" loading="lazy">
          </div>
        </div>
      </div>
    </div>
  </div>

<style>
  .spon-img{margin-top:14px}
  .spon-img img{width:min(300px,100%);max-width:300px;height:auto;border-radius:10px;
    border:1px solid var(--line);box-shadow:0 4px 18px var(--shadow);background:#fff}
</style>

<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
<?php
/**
 * 后台侧边栏导航（公共）
 * 需要变量：$activeNav、$user、$site
 * 以后新增页面：在 $navItems 数组里加一项即可
 */
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$__nav   = (string)($activeNav ?? '');
$__site  = (string)($site ?? '后台管理');
$navItems = [
    ['key' => 'dashboard', 'href' => '/admin', 'label' => '仪表盘'],
];
$homeOn  = in_array($__nav, ['home', 'homeStyle', 'notice', 'visual'], true);
$styleOn = in_array($__nav, ['homeStyle', 'visual'], true);
$aboutOn = in_array($__nav, ['about', 'about-framework'], true);
?><aside class="side">
  <div class="side-title">菜单</div>
  <nav>
<?php foreach ($navItems as $it): ?>
    <a class="<?= $__nav === $it['key'] ? 'on' : '' ?>" href="<?= $h($it['href']) ?>"><?= $h($it['label']) ?></a>
<?php endforeach; ?>
    <div class="nav-group">
      <span class="nav-parent<?= $homeOn ? ' on' : '' ?>" id="homeNavParent">
        <span>首页管理</span>
        <span class="caret" aria-hidden="true"></span>
      </span>
      <div class="nav-children<?= $homeOn ? ' open' : '' ?>" id="homeNavChildren">
        <a class="<?= $__nav === 'home' ? 'on' : '' ?>" href="/admin/home">首页管理</a>
        <div class="nav-group">
          <span class="nav-parent<?= $styleOn ? ' on' : '' ?>" id="styleNavParent">
            <span>个性化</span>
            <span class="caret" aria-hidden="true"></span>
          </span>
          <div class="nav-children<?= $styleOn ? ' open' : '' ?>" id="styleNavChildren">
            <a class="<?= $__nav === 'homeStyle' ? 'on' : '' ?>" href="/admin/home-style">外观设置</a>
            <a class="<?= $__nav === 'visual' ? 'on' : '' ?>" href="/admin/visual">可视化编辑</a>
            <a class="<?= $__nav === 'notice' ? 'on' : '' ?>" href="/admin/notice">跑马灯</a>
          </div>
        </div>
      </div>
    </div>
    <a class="<?= $__nav === 'files' ? 'on' : '' ?>" href="/admin/files">文件管理</a>
    <a class="<?= $__nav === 'system-logs' ? 'on' : '' ?>" href="/admin/system-logs">系统日志</a>
    <a class="<?= $__nav === 'settings' ? 'on' : '' ?>" href="/admin/settings">系统设置</a>
    <div class="nav-group">
      <span class="nav-parent<?= $aboutOn ? ' on' : '' ?>" id="aboutNavParent">
        <span>关于程序</span>
        <span class="caret" aria-hidden="true"></span>
      </span>
      <div class="nav-children<?= $aboutOn ? ' open' : '' ?>" id="aboutNavChildren">
        <a class="<?= $__nav === 'about' ? 'on' : '' ?>" href="/admin/about">关于程序</a>
        <a class="<?= $__nav === 'about-framework' ? 'on' : '' ?>" href="/admin/about/framework">框架与引用</a>
      </div>
    </div>
  </nav>
  <div class="side-bottom">
    <button type="button" class="clear-cache" id="clearCacheBtn">清除缓存</button>
    <div class="side-sep" aria-hidden="true"></div>
    <a class="logout" href="/admin/logout">退出登录</a>
  </div>
</aside>
<script>
(function () {
  var p = document.getElementById('homeNavParent');
  var c = document.getElementById('homeNavChildren');
  if (p && c) {
    p.addEventListener('click', function () {
      var open = c.classList.toggle('open');
      p.classList.toggle('on', open);
    });
  }
  var sp = document.getElementById('styleNavParent');
  var sc = document.getElementById('styleNavChildren');
  if (sp && sc) {
    sp.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = sc.classList.toggle('open');
      sp.classList.toggle('on', open);
    });
  }
  (function () {
    var ap = document.getElementById('aboutNavParent');
    var ac = document.getElementById('aboutNavChildren');
    if (ap && ac) {
      ap.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = ac.classList.toggle('open');
        ap.classList.toggle('on', open);
      });
    }
  })();
})();
</script>
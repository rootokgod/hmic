<?php
/**
 * 个性化定制页（内容，布局由 partials/head.php + foot.php 提供）
 * @var bool   $homeEnabled  首页开关（透传保留）
 * @var float  $fontScale    首页字体缩放（0.5~2.0，默认 1.0）
 * @var int    $menuRound    后台菜单圆润（0=尖角 1=圆润 2=超圆润）
 */
$__mr = (int) ($menuRound ?? 0);
include __DIR__ . '/partials/head.php';
$fontOpts = [
    '0.85' => '小',
    '1'    => '标准',
    '1.15' => '大',
    '1.3'  => '特大',
];
?>
  <div class="panel">
    <h2>个性化</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">首页字体大小</div>
          <div class="set-d">调整首页整体文字/界面大小，保存后对访问者立即生效</div>
          <div id="fontPreview" class="font-preview">
            <div class="fp-title">示例工具标题</div>
            <div class="fp-line">一个普通的工具链接，用于展示调整后的实际比例效果</div>
            <div class="fp-btn">进入工具</div>
          </div>
        </div>
        <select id="fontScale" class="theme-select">
          <?php foreach ($fontOpts as $v => $label): ?>
            <option value="<?= $v ?>" <?= abs((float) $fontScale - (float) $v) < 0.001 ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>后台菜单</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">圆润</div>
          <div class="set-d">将后台侧边栏菜单的尖角改为圆润胶囊效果，三种档位可选</div>
          <div id="mrPreview" class="mr-preview">
            <div class="mr-item">仪表盘</div>
            <div class="mr-item">首页管理</div>
            <div class="mr-item">文件管理</div>
            <div class="mr-item">系统日志</div>
          </div>
        </div>
        <select id="mrRound" class="theme-select">
          <option value="0" <?= $__mr === 0 ? 'selected' : '' ?>>尖角</option>
          <option value="1" <?= $__mr === 1 ? 'selected' : '' ?>>圆润</option>
          <option value="2" <?= $__mr === 2 ? 'selected' : '' ?>>超圆润</option>
        </select>
      </div>
    </div>
  </div>

<style>
  .font-preview{margin-top:12px;padding:14px 16px;width:230px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink)}
  .font-preview .fp-title{font-size:15px;font-weight:600}
  .font-preview .fp-line{font-size:12px;color:var(--ink3);margin:8px 0 12px}
  .font-preview .fp-btn{display:inline-block;padding:6px 14px;border-radius:6px;background:var(--btn);color:var(--btn-ink);font-size:12px}
  .mr-preview{margin-top:12px;display:flex;flex-direction:column;gap:5px;width:210px}
  .mr-preview .mr-item{padding:7px 11px;font-size:12.5px;background:var(--bg);color:var(--ink2);
    border:1px solid var(--line)}
  .mr-preview.mr-1 .mr-item{border-radius:9px;margin-left:7px;margin-right:7px;border-bottom:0}
  .mr-preview.mr-2 .mr-item{border-radius:15px;margin-left:7px;margin-right:7px;border-bottom:0}
</style>

<script>
(function () {
  var sel  = document.getElementById('fontScale');
  var prev = document.getElementById('fontPreview');
  var toast = function (msg, err) {
    if (typeof window.showToast === 'function') window.showToast(msg, err);
  };
  if (!sel || !prev) return;
  prev.style.zoom = parseFloat(sel.value);

  var mrSel = document.getElementById('mrRound');
  if (!mrSel) return;

  var saveAll = function () {
    var body = 'enabled=<?= !empty($homeEnabled) ? '1' : '0' ?>'
      + '&font_scale=' + encodeURIComponent(sel.value)
      + '&menu_round=' + encodeURIComponent(mrSel.value);
    return fetch('/admin/api/home', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
      cache: 'no-store',
      body: body
    });
  };

  sel.addEventListener('change', function () {
    var oldV = sel.value;
    prev.style.zoom = parseFloat(sel.value);
    saveAll()
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) { toast('字体大小已保存'); return; }
        sel.value = oldV; prev.style.zoom = parseFloat(oldV);
        toast('保存失败，请稍后重试', true);
      })
      .catch(function () {
        sel.value = oldV; prev.style.zoom = parseFloat(oldV);
        toast('保存失败，请稍后重试', true);
      });
  });

  var mrPrev = document.getElementById('mrPreview');
  if (mrPrev) mrPrev.className = 'mr-preview mr-' + mrSel.value;
  mrSel.addEventListener('change', function () {
    var oldV = mrSel.value;
    if (mrPrev) mrPrev.className = 'mr-preview mr-' + mrSel.value;
    saveAll()
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          toast('后台菜单样式已保存，侧边栏已生效');
          var a = 'mr-' + mrSel.value;
          var m = document.body.className.match(/(^|\s)(mr-\d)/);
          if (m) document.body.classList.remove(m[2]);
          document.body.classList.add(a);
          return;
        }
        mrSel.value = oldV; if (mrPrev) mrPrev.className = 'mr-preview mr-' + oldV;
        toast('保存失败，请稍后重试', true);
      })
      .catch(function () {
        mrSel.value = oldV; if (mrPrev) mrPrev.className = 'mr-preview mr-' + oldV;
        toast('保存失败，请稍后重试', true);
      });
  });
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
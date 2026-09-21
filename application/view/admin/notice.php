<?php
/**
 * 跑马灯（首页顶部滚动横幅，页面内容，布局由 partials/head.php + foot.php 提供）
 * @var bool   $noticeEnabled  跑马灯开关（默认开）
 * @var string $noticeText     跑马灯文字（<=200 字）
 */
include __DIR__ . '/partials/head.php';
$defaultText = '针对银狐病毒导致无法连接 360 官网的情况,请直接从本站下载急救箱与银狐清理脚本';
?>
  <div class="panel">
    <h2>跑马灯</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">跑马灯开关</div>
          <div class="set-d">开启：首页顶部滚动显示跑马灯；关闭：整条横幅隐藏、不占任何位置</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="noticeSwitch" <?= !empty($noticeEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
      <div class="set-row notice-text-row">
        <div>
          <div class="set-t">公告内容</div>
          <div class="set-d">最长 200 字，单行滚动展示；保存后对访问者立即生效</div>
        </div>
      </div>
      <div class="body-inner">
        <textarea id="noticeText" class="notice-input" rows="2" maxlength="200" placeholder="请输入公告内容"><?= htmlspecialchars((string) $noticeText, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="notice-actions">
          <span class="notice-count" id="noticeCount"><?= mb_strlen((string) $noticeText, 'UTF-8') ?> / 200</span>
          <div class="set-actions">
            <button type="button" class="mbtn" id="noticeReset">恢复默认</button>
            <button type="button" class="mbtn primary" id="noticeSave">保存公告</button>
          </div>
        </div>
      </div>
    </div>
  </div>

<style>
  .notice-text-row{padding-bottom:4px}
  .body-inner{padding:0 17px 18px}
  .notice-input{width:100%;box-sizing:border-box;min-height:64px;resize:vertical;padding:10px 12px;
    border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink);
    font-size:13px;line-height:1.6}
  .notice-input:focus{outline:none;border-color:var(--ink3)}
  .notice-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
  .notice-count{font-size:12px;color:var(--ink3)}
  .set-actions{display:flex;gap:8px}
  .set-actions .mbtn{padding:8px 18px;border-radius:8px;border:1px solid var(--line);
    background:var(--card);color:var(--ink);cursor:pointer;font-size:13px}
  .set-actions .mbtn:hover{background:var(--hover)}
  .set-actions .mbtn.primary{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .set-actions .mbtn.primary:hover{filter:brightness(.92)}
  .set-actions .mbtn:disabled{opacity:.45;cursor:default;filter:none}
</style>

<script>
(function () {
  var sw  = document.getElementById('noticeSwitch');
  var ta  = document.getElementById('noticeText');
  var cnt = document.getElementById('noticeCount');
  var saveBtn = document.getElementById('noticeSave');
  var resetBtn = document.getElementById('noticeReset');
  if (!sw || !ta) return;
  var toast = function (msg, err) {
    if (typeof window.showToast === 'function') window.showToast(msg, err);
  };
  var DEFAULT_TEXT = <?= json_encode($defaultText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  if (cnt) {
    ta.addEventListener('input', function () {
      cnt.textContent = ta.value.length + ' / 200';
    });
  }
  function save() {
    saveBtn.disabled = true;
    fetch('/admin/api/notice', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
      cache: 'no-store',
      body: 'enabled=' + (sw.checked ? 1 : 0) + '&text=' + encodeURIComponent(ta.value)
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        saveBtn.disabled = false;
        if (d && d.ok) { toast('公告已保存'); return; }
        toast('保存失败，请稍后重试', true);
      })
      .catch(function () {
        saveBtn.disabled = false;
        toast('保存失败，请稍后重试', true);
      });
  }
  saveBtn.addEventListener('click', save);
  sw.addEventListener('change', function () {
    saveBtn.disabled = true;
    fetch('/admin/api/notice', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
      cache: 'no-store',
      body: 'enabled=' + (sw.checked ? 1 : 0) + '&text=' + encodeURIComponent(ta.value)
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        saveBtn.disabled = false;
        if (d && d.ok) { toast('公告开关已' + (sw.checked ? '开启' : '关闭')); return; }
        sw.checked = !sw.checked;
        toast('保存失败，请稍后重试', true);
      })
      .catch(function () {
        sw.checked = !sw.checked;
        saveBtn.disabled = false;
        toast('保存失败，请稍后重试', true);
      });
  });
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      ta.value = DEFAULT_TEXT;
      if (cnt) cnt.textContent = ta.value.length + ' / 200';
      save();
    });
  }
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
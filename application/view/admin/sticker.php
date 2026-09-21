<?php
/**
 * 贴纸图库（页面内容，布局由 partials/head.php + foot.php 提供）
 * 列表 GET /admin/api/stickers，上传 POST /admin/api/sticker/upload，删除 POST /admin/api/sticker/delete
 */
include __DIR__ . '/partials/head.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$icoUp = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>';
$icoRef = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>';
$icoTrash = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
?>
  <div class="panel">
    <h2>贴纸图库</h2>
    <div class="fm-bar">
      <div class="fm-bar-row">
        <div class="fm-bar-acts">
          <button type="button" class="fm-btn fm-btn-p" id="stkUpBtn" title="上传贴纸到图库"><?= $icoUp ?>上传贴纸</button>
          <button type="button" class="fm-btn" id="stkRefBtn" title="刷新图库列表"><?= $icoRef ?>刷新</button>
          <input type="file" id="stkUpInput" accept="image/png,image/jpeg,image/webp" multiple hidden>
        </div>
        <span class="fm-count muted" id="stkCount"></span>
        <a class="fm-btn" href="/admin/visual" title="回到可视化面板摆放贴纸">去可视化摆放</a>
        <a class="fm-btn" href="/admin/files" title="返回文件管理">返回文件管理</a>
      </div>
    </div>
    <div class="body">
      <div class="stk-grid" id="stkGrid"></div>
      <div class="stk-empty" id="stkEmpty" style="display:none">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <strong>图库还是空的</strong>
        <span>点击右上角「上传贴纸」添加图片，然后到<a href="/admin/visual">可视化面板 → 贴纸</a>里摆放。</span>
      </div>
    </div>
  </div>

<style>
  .stk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(176px,1fr));gap:14px;padding:16px 17px}
  .stk-card{position:relative;display:flex;flex-direction:column;background:var(--bg);border:1px solid var(--line);
    border-radius:12px;overflow:hidden;transition:border-color .15s,box-shadow .15s,transform .15s}
  .stk-card:hover{border-color:var(--ink3);box-shadow:0 6px 18px var(--shadow);transform:translateY(-1px)}
  .stk-card .stk-box{height:138px;display:flex;align-items:center;justify-content:center;padding:10px;
    background:repeating-conic-gradient(rgba(128,128,128,.09) 0 25%,transparent 0 50%) 0 0/20px 20px;border-bottom:1px solid var(--rowline)}
  .stk-card .stk-box img{max-width:100%;max-height:100%;object-fit:contain}
  .stk-card .stk-meta{display:flex;flex-direction:column;gap:7px;padding:10px 12px}
  .stk-card .stk-name{font-size:12.5px;color:var(--ink);word-break:break-all;line-height:1.4}
  .stk-card .stk-sub{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--ink3)}
  .stk-card .op-btn{margin-left:auto;display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;
    font-size:12px;cursor:pointer;border:1px solid var(--line);background:var(--card);color:var(--err);
    font-family:inherit;transition:background .15s,border-color .15s}
  .stk-card .op-btn:hover{background:var(--hover);border-color:var(--err)}
  .stk-card .stk-used{margin-left:auto;font-size:11px;color:var(--ok);display:inline-flex;align-items:center;gap:3px}
  .stk-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:60px 20px;text-align:center;color:var(--ink3)}
  .stk-empty svg{opacity:.4}
  .stk-empty strong{color:var(--ink);font-size:15px}
  .stk-empty span{font-size:13px;line-height:1.8}
  .stk-empty a{color:var(--blue)}
</style>

<script>
(function () {
  var grid = document.getElementById('stkGrid');
  var empty = document.getElementById('stkEmpty');
  var count = document.getElementById('stkCount');
  var upBtn = document.getElementById('stkUpBtn');
  var upInput = document.getElementById('stkUpInput');
  var refBtn = document.getElementById('stkRefBtn');
  var busy = false;

  function esc(s) {
    return ('' + s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmtSize(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    return (n / 1048576).toFixed(2) + ' MB';
  }

  function render(list) {
    grid.textContent = '';
    empty.style.display = list.length ? 'none' : 'block';
    grid.style.display = list.length ? '' : 'none';
    count.textContent = list.length ? '共 ' + list.length + ' 张' : '图库为空';
    list.forEach(function (it) {
      var card = document.createElement('div');
      card.className = 'stk-card';
      card.innerHTML =
        '<div class="stk-box"><img src="' + esc(it.url) + '" alt="" loading="lazy"></div>' +
        '<div class="stk-meta">' +
          '<div class="stk-name" title="' + esc(it.name) + '">' + esc(it.name) + '</div>' +
          '<div class="stk-sub"><span>' + fmtSize(it.size) + '</span>' +
            '<button type="button" class="op-btn" data-name="' + esc(it.name) + '">删除</button></div>' +
        '</div>';
      grid.appendChild(card);
    });
    grid.querySelectorAll('.op-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        var name = b.getAttribute('data-name');
        window.confirmBox('确定删除贴纸「' + name + '」？删除后需重新上传。', '删除', function () {
          fetch('/admin/api/sticker/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: name })
          }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok) { window.showToast('已删除', name); refresh(); }
            else { window.showToast('删除失败', (d && d.msg) || '', true); }
          }).catch(function () { window.showToast('删除失败', '网络错误', true); });
        });
      });
    });
  }

  function refresh() {
    fetch('/admin/api/stickers', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      render((d && d.list) || []);
    }).catch(function () {
      render([]);
      empty.style.display = 'block';
      grid.style.display = 'none';
    });
  }

  function setBusy(on) {
    busy = on;
    upBtn.disabled = on;
    upBtn.innerHTML = on ? '上传中…' : '<?= $icoUp ?>上传贴纸';
  }

  upBtn.addEventListener('click', function () { if (!busy) upInput.click(); });
  refBtn.addEventListener('click', function () { if (!busy) refresh(); });
  upInput.addEventListener('change', function () {
    var files = upInput.files;
    if (!files || !files.length) return;
    var pending = Array.prototype.slice.call(files);
    var done = 0, fail = 0, jpgs = 0, renames = [], failMsgs = [];
    setBusy(true);
    function next() {
      if (!pending.length) {
        upInput.value = '';
        setBusy(false);
        refresh();
        var extra = renames.length ? '，' + renames.length + ' 张重名已自动改名' : '';
        if (fail === 0) {
          var tip = jpgs ? '。提示：JPG 不含透明通道，透明背景建议用 PNG' : '';
          window.showToast('上传完成', '已添加 ' + done + ' 张贴纸' + extra + tip);
          return;
        }
        var why = failMsgs[0] ? '（' + failMsgs[0] + '）' : '';
        window.showToast('部分上传失败', '成功 ' + done + '，失败 ' + fail + extra + why, true);
        return;
      }
      var f = pending.shift();
      var nm = (f.name || '').toLowerCase();
      if (/\.(jpe?g)$/.test(nm)) jpgs++;
      var fd = new FormData();
      fd.append('file', f);
      fetch('/admin/api/sticker/upload', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            done++;
            if (d.renamed) renames.push(d.name);
          } else {
            fail++;
            if (d && d.msg) failMsgs.push(d.msg);
          }
          next();
        })
        .catch(function () { fail++; failMsgs.push('网络错误'); next(); });
    }
    next();
  });

  refresh();
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
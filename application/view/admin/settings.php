<?php
/**
 * 系统设置（修改登录用户名 / 密码）
 * @var string $user
 * @var string $storedUser
 * @var string $pageTitle
 */
include __DIR__ . '/partials/head.php';
?>
<div class="panel">
  <h2>系统设置</h2>
  <div class="body">
    <div class="set-row">
      <div>
        <div class="set-t">当前登录账号</div>
        <div class="set-d">用户名：<b><?= htmlspecialchars((string) $storedUser, ENT_QUOTES, 'UTF-8') ?></b>（密码以 bcrypt 哈希存储，登录仅校验明文，哈希值无法用于登录）</div>
      </div>
    </div>
    <div class="acc-fields">
      <label>当前密码（必须正确才能保存修改）
        <input type="password" id="curPass" autocomplete="current-password" required>
      </label>
      <label>新用户名（留空保持不变；3-24 位，仅限字母、数字、下划线）
        <input type="text" id="newUser" autocomplete="username" maxlength="24" placeholder="例如 god2">
      </label>
      <label>新密码（留空保持不变；至少 8 位，需同时包含字母和数字，且不能与用户名相同）
        <input type="password" id="newPass" autocomplete="new-password">
      </label>
      <label>确认新密码
        <input type="password" id="newPass2" autocomplete="new-password">
      </label>
      <button type="button" class="mbtn primary" id="accSave">保存修改</button>
    </div>
  </div>
</div>

<div class="panel">
  <h2>安全路径</h2>
  <div class="body">
    <div class="set-row">
      <div>
        <div class="set-t">当前后台地址</div>
        <div class="set-d">后台入口：<b><?= htmlspecialchars((string) $adminBase, ENT_QUOTES, 'UTF-8') ?></b>
          （默认 /admin，修改后旧地址立即失效，可降低默认路径被扫描的风险）</div>
      </div>
    </div>
    <div class="acc-fields path-fields">
      <label>当前密码（必须正确才能保存修改）
        <input type="password" id="pathCurPass" autocomplete="current-password" required>
      </label>
      <label>新后台路径（2-32 位，仅限字母、数字，首字符需为字母）
        <input type="text" id="pathInput" maxlength="32" placeholder="例如 admin">
      </label>
      <div class="path-row">
        <button type="button" class="mbtn primary" id="pathSave">保存路径</button>
      </div>
    </div>
  </div>
</div>

<style>
  .acc-fields{display:flex;flex-direction:column;gap:12px;padding:6px 17px 20px;max-width:420px}
  .acc-fields label{display:block;font-size:12.5px;color:var(--ink2)}
  .acc-fields input{display:block;width:100%;margin-top:6px;padding:9px 11px;border:1px solid var(--line);
    border-radius:8px;background:var(--card);color:var(--ink);font-size:13px;outline:none;box-sizing:border-box;
    transition:border-color .15s}
  .acc-fields input:focus{border-color:var(--ink3)}
  .acc-fields .mbtn{width:130px;margin-top:4px;padding:9px 14px;border-radius:8px;border:1px solid transparent;
    background:var(--btn);color:var(--btn-ink);font-size:13px;font-family:inherit;cursor:pointer;
    transition:filter .15s,opacity .15s}
  .acc-fields .mbtn:hover:not(:disabled){filter:brightness(.92)}
  .acc-fields .mbtn:disabled{opacity:.5;cursor:default}
  .path-fields{margin-top:4px}
  .path-row{display:flex;gap:10px}
  .path-row .mbtn{width:auto;padding:9px 16px}
</style>

<script>
(function () {
  var btn = document.getElementById('accSave');
  btn.addEventListener('click', function () {
    var cur = document.getElementById('curPass').value;
    if (cur) { document.getElementById('pathCurPass').value = cur; }
    var nu = document.getElementById('newUser').value.trim();
    var np = document.getElementById('newPass').value;
    var np2 = document.getElementById('newPass2').value;
    if (!cur) { showToast('请先输入当前密码', '', true); return; }
    if (np && np !== np2) { showToast('两次输入的新密码不一致', '', true); return; }
    var fd = new FormData();
    fd.append('current_pass', cur);
    fd.append('new_user', nu);
    fd.append('new_pass', np);
    fd.append('new_pass2', np2);
    btn.disabled = true;
    btn.textContent = '保存中…';
    fetch('/admin/api/settings/account', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      body: fd
    }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false;
      btn.textContent = '保存修改';
      if (d && d.ok) {
        showToast(d.msg || '已保存');
        document.getElementById('newPass').value = '';
        document.getElementById('newPass2').value = '';
      } else {
        showToast('保存失败：' + ((d && d.msg) || '未知错误'), '', true);
      }
    }).catch(function () {
      btn.disabled = false;
      btn.textContent = '保存修改';
      showToast('保存失败：网络错误', '', true);
    });
  });

  // ---------- 安全路径 ----------
  var pathSave = document.getElementById('pathSave');
  pathSave.addEventListener('click', function () {
    var cur = document.getElementById('pathCurPass').value;
    var np = document.getElementById('pathInput').value.trim();
    if (!cur) { showToast('请先输入当前密码', '', true); return; }
    if (!np) { showToast('请输入新的后台路径', '', true); return; }
    var fd = new FormData();
    fd.append('current_pass', cur);
    fd.append('new_path', np);
    pathSave.disabled = true;
    pathSave.textContent = '保存中…';
    fetch('/admin/api/settings/path', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      body: fd
    }).then(function (r) { return r.json(); }).then(function (d) {
      pathSave.disabled = false;
      pathSave.textContent = '保存路径';
      if (d && d.ok) {
        showToast(d.msg || '后台路径已更新');
        setTimeout(function () {
          window.location.href = (d.base || '/admin') + '/settings';
        }, 1200);
      } else {
        showToast('保存失败：' + ((d && d.msg) || '未知错误'), '', true);
      }
    }).catch(function () {
      pathSave.disabled = false;
      pathSave.textContent = '保存路径';
      showToast('保存失败：网络错误', '', true);
    });
  });
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
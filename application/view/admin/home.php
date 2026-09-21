<?php
/**
 * 首页管理（页面内容，布局由 partials/head.php + foot.php 提供）
 * @var bool   $homeEnabled  首页开关
 * @var string $siteName     全局站点名
 * @var string $siteSlogan   全局标语
 * @var bool   $altEnabled   IPv4/IPv6 跳转开关
 * @var string $v4Url        IPv4 站点地址
 * @var string $v6Url        IPv6 站点地址
 * @var bool   $donateEnabled 支持作者开关
 * @var bool   $netpanelEnabled 测速面板开关
 * @var bool   $netpanelOn   测速面板目录当前是否在线
 * @var bool   $masEnabled   Windows 激活工具开关
 * @var bool   $recentEnabled 首页最近操作卡片开关
 */
include __DIR__ . '/partials/head.php';
?>
<div class="cards">
  <div class="panel">
    <h2>首页开关</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">首页开关</div>
          <div class="set-d">开启：正常显示首页；关闭：访问首页显示维护提示</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="homeSwitch" <?= !empty($homeEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>测速面板</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">测速面板</div>
          <div class="set-d">关闭后该功能入口隐藏</div>
        </div>
        <label class="switch">
          <input type="checkbox" class="feat-chk" id="netpanelSwitch" data-key="netpanel_enabled" <?= !empty($netpanelEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Windows 激活工具</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">Windows 激活工具</div>
          <div class="set-d">关闭后首页入口隐藏</div>
        </div>
        <label class="switch">
          <input type="checkbox" class="feat-chk" id="masSwitch" data-key="mas_enabled" <?= !empty($masEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>IPv4 / IPv6 跳转</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">跳转按钮</div>
          <div class="set-d">开启后访问者可在 IPv4/IPv6 站点间互跳，可自定义两组跳转地址</div>
        </div>
        <label class="switch">
          <input type="checkbox" class="feat-chk" id="altSwitch" data-key="alt_enabled" <?= !empty($altEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
      <div class="fields">
        <div class="fld"><div class="fl">IPv4 站点地址</div><input type="text" id="v4Url" class="txt" value="<?= htmlspecialchars((string) $v4Url, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ipv4.example.com"></div>
        <div class="fld"><div class="fl">IPv6 站点地址</div><input type="text" id="v6Url" class="txt" value="<?= htmlspecialchars((string) $v6Url, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ipv6.example.com"></div>
        <button type="button" class="mbtn primary" id="altSave">保存跳转地址</button>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>全局名称</h2>
    <div class="body">
      <div class="set-d pad">首页站点名与副标题，全局生效（含浏览器标题栏、页脚）</div>
      <div class="fields">
        <div class="fld"><div class="fl">站点名称</div><input type="text" id="siteName" class="txt" value="<?= htmlspecialchars((string) $siteName, ENT_QUOTES, 'UTF-8') ?>" placeholder="God Supremus 的工具站" maxlength="60"></div>
        <div class="fld"><div class="fl">副标题 / 标语</div><input type="text" id="siteSlogan" class="txt" value="<?= htmlspecialchars((string) $siteSlogan, ENT_QUOTES, 'UTF-8') ?>" placeholder="杀毒 · 安全 · 应急工具分享" maxlength="60"></div>
        <button type="button" class="mbtn primary" id="siteSave">保存全局名称</button>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>最近操作</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">首页底部“最近 15 条”卡片</div>
          <div class="set-d">关闭后首页不渲染该卡片</div>
        </div>
        <label class="switch">
          <input type="checkbox" class="feat-chk" id="recentSwitch" data-key="recent_enabled" <?= !empty($recentEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>支持作者（赞助）</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">支持作者按钮</div>
          <div class="set-d">关闭后首页「支持作者」按钮隐藏</div>
        </div>
        <label class="switch">
          <input type="checkbox" class="feat-chk" id="donateSwitch" data-key="donate_enabled" <?= !empty($donateEnabled) ? 'checked' : '' ?>>
          <span class="slider"></span>
        </label>
      </div>
      <div class="set-row donate-row">
        <div>
          <div class="set-t">赞赏码预览</div>
          <div class="set-d">与首页展示同一张图</div>
          <div class="donate-prev"><img id="donatePrev" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="赞赏码预览"></div>
        </div>
        <div class="donate-acts">
          <label class="mbtn upload-lbl" id="donateBtn">选择图片
            <input type="file" id="donateFile" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none">
          </label>
        </div>
      </div>
      <div class="donate-prog" id="donateProg" style="display:none">
        <div class="dp-text" id="donateProgText">准备上传…</div>
        <div class="dp-bar"><i id="donateProgBar"></i></div>
      </div>
    </div>
  </div>
</div>

<style>
  .cards{display:flex;flex-direction:column;gap:14px}
  .pad{padding:14px 17px 0;color:var(--ink3);font-size:12.5px}
  .fields{display:flex;flex-direction:column;gap:10px;padding:6px 17px 18px}
  .fields .fld{display:flex;align-items:center;gap:10px}
  .fields .fl{width:130px;flex:none;font-size:12.5px;color:var(--ink2)}
  .fields .txt{flex:1;min-width:0;padding:8px 11px;border:1px solid var(--line);border-radius:8px;
    background:var(--card);color:var(--ink);font-size:13px;box-sizing:border-box}
  .fields .txt:focus{outline:none;border-color:var(--ink3)}
  .fields .mbtn{width:150px;margin-left:140px;padding:8px 14px;border-radius:8px;border:1px solid var(--line);
    background:var(--card);color:var(--ink2);cursor:pointer;font-size:13px;font-family:inherit}
  .fields .mbtn:hover{background:var(--hover);color:var(--ink)}
  .fields .mbtn.primary{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .fields .mbtn.primary:hover{filter:brightness(.92);color:var(--btn-ink)}
  .fields .mbtn:disabled{opacity:.45;cursor:default;filter:none}
  .donate-row{padding-bottom:14px}
  .donate-prev{margin-top:10px;width:150px;height:150px;border:1px solid var(--line);border-radius:10px;
    background:var(--card);display:flex;align-items:center;justify-content:center;overflow:hidden}
  .donate-prev img{max-width:100%;max-height:100%;object-fit:contain}
  .donate-acts{display:flex;flex-direction:column;gap:8px;flex:none}
  .donate-acts .mbtn{padding:7px 12px;border-radius:8px;border:1px solid var(--line);background:var(--card);
    color:var(--ink2);cursor:pointer;font-size:12.5px;font-family:inherit;text-align:center}
  .donate-acts .mbtn:hover{background:var(--hover);color:var(--ink)}
  .donate-acts .mbtn.primary{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .donate-acts .mbtn.primary:hover{filter:brightness(.92);color:var(--btn-ink)}
  .donate-acts .mbtn:disabled{opacity:.45;cursor:default;filter:none}
  .donate-acts .upload-lbl{display:inline-flex;align-items:center;justify-content:center}
  .donate-acts .busy{opacity:.5;pointer-events:none}
  .donate-prog{padding:2px 17px 18px;max-width:360px}
  .donate-prog .dp-text{font-size:12.5px;color:var(--ink2);margin-bottom:6px}
  .donate-prog .dp-bar{height:8px;border-radius:6px;background:var(--hover);overflow:hidden}
  .donate-prog .dp-bar i{display:block;height:100%;width:0;border-radius:6px;background:var(--ok);transition:width .15s}
</style>

<script>
(function () {
  var toast = function (msg, detail, err) {
    if (typeof detail === 'boolean') { err = detail; detail = ''; }
    if (typeof window.showToast === 'function') window.showToast(msg, detail || '', err);
  };
  var post = function (body, onok) {
    fetch('/admin/api/feat', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      body: body
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok) { if (onok) onok(d); else toast('已保存'); return; }
      toast('保存失败：' + ((d && d.msg) || ''), true);
    }).catch(function () { toast('保存失败：网络错误', true); });
  };
  var fdFor = function (extra) {
    var fd = new FormData();
    Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    return fd;
  };

  /* 赞助图预览：与首页同源，经 /img.php 令牌路由读取。
     token 复用 sessionStorage：未过期时直接显示（近零延迟），过期或刷新时才重新获取。force=true 强制换新 token */
  function refreshSponsor(force) {
    fetch('/img.php?action=get&path=/data/jpg/zs.jpg')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok || !d.url) return;
        try { sessionStorage.setItem('sponsorTok', JSON.stringify({ url: d.url, expires: d.expires })); } catch (err) {}
        var el = document.getElementById('donatePrev');
        if (force) {
          el.removeAttribute('src');
          el.src = d.url + (d.url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
        } else {
          el.src = d.url;
        }
      })
      .catch(function () {});
  }
  function loadSponsor(force) {
    var el = document.getElementById('donatePrev');
    var o = null;
    try { o = JSON.parse(sessionStorage.getItem('sponsorTok') || 'null'); } catch (err) {}
    if (!force && o && o.url && o.expires && o.expires * 1000 > Date.now()) {
      el.src = o.url;
      return;
    }
    el.removeAttribute('src');
    refreshSponsor(!!force);
  }
  loadSponsor(false);

  /* 首页开关 */
  var homeSw = document.getElementById('homeSwitch');
  if (homeSw) homeSw.addEventListener('change', function () {
    var wanted = homeSw.checked;
    fetch('/admin/api/home', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
      cache: 'no-store',
      body: 'enabled=' + (wanted ? 1 : 0)
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok) { toast('首页开关已' + (wanted ? '开启' : '关闭')); return; }
      homeSw.checked = !wanted; toast('保存失败', true);
    }).catch(function () { homeSw.checked = !wanted; toast('保存失败', true); });
  });

  /* 通用布尔开关（测速/激活/最近/赞助） */
  document.querySelectorAll('.feat-chk').forEach(function (sw) {
    sw.addEventListener('change', function () {
      var key = sw.getAttribute('data-key');
      var wanted = sw.checked;
      var fd = new FormData();
      fd.append(key, wanted ? 1 : 0);
      post(fd, function () { toast('已' + (wanted ? '开启' : '关闭')); });
    });
  });

  /* IPv4/IPv6 地址保存 */
  var altSave = document.getElementById('altSave');
  if (altSave) altSave.addEventListener('click', function () {
    altSave.disabled = true;
    post(fdFor({ v4_url: document.getElementById('v4Url').value.trim(), v6_url: document.getElementById('v6Url').value.trim() }), function () {
      altSave.disabled = false; toast('跳转地址已保存');
    });
  });

  /* 全局名称保存 */
  var siteSave = document.getElementById('siteSave');
  if (siteSave) siteSave.addEventListener('click', function () {
    siteSave.disabled = true;
    post(fdFor({ site_name: document.getElementById('siteName').value.trim(), site_slogan: document.getElementById('siteSlogan').value.trim() }), function () {
      siteSave.disabled = false; toast('全局名称已保存');
    });
  });

  /* 赞助：图片替换（写入 zs.jpg，XHR 显示上传进度） */
  var donateFile = document.getElementById('donateFile');
  if (donateFile) donateFile.addEventListener('change', function () {
    if (!donateFile.files || !donateFile.files[0]) return;
    var btn = document.getElementById('donateBtn');
    var prog = document.getElementById('donateProg');
    var bar = document.getElementById('donateProgBar');
    var txt = document.getElementById('donateProgText');
    var fd = new FormData();
    fd.append('donate_enabled', document.getElementById('donateSwitch').checked ? 1 : 0);
    fd.append('donate_image', donateFile.files[0]);
    prog.style.display = '';
    bar.style.width = '0%';
    txt.textContent = '准备上传…';
    btn.classList.add('busy');
    var xhr = new XMLHttpRequest();
    var done = false;
    xhr.open('POST', '/admin/api/feat');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = function (e) {
      if (done) return;
      if (e.lengthComputable) {
        var p = Math.min(99, Math.round(e.loaded / e.total * 100));
        bar.style.width = p + '%';
        txt.textContent = '上传中 ' + p + '%';
      }
    };
    xhr.onload = function () {
      done = true;
      var d = null;
      try { d = JSON.parse(xhr.responseText); } catch (err) {}
      if (xhr.status === 200 && d && d.ok) {
        bar.style.width = '100%';
        txt.textContent = '上传完成，正在刷新预览…';
        toast('赞赏图已替换');
        loadSponsor(true);
        setTimeout(function () {
          prog.style.display = 'none';
          btn.classList.remove('busy');
        }, 600);
      } else {
        btn.classList.remove('busy');
        prog.style.display = 'none';
        toast('替换失败', (d && d.msg) || ('状态码 ' + xhr.status), true);
      }
    };
    xhr.onerror = function () {
      done = true;
      btn.classList.remove('busy');
      prog.style.display = 'none';
      toast('替换失败', '网络错误', true);
    };
    xhr.send(fd);
    donateFile.value = '';
  });
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
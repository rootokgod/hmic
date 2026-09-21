<?php
/**
 * 系统日志（类型：后台登录 / 前台访问 / 后台操作 / 下载）
 * @var string $user
 * @var string $pageTitle
 */
include __DIR__ . '/partials/head.php';
?>
<div class="panel">
  <div class="fm-bar">
    <div class="fm-bar-row">
      <div class="fm-bar-acts">
        <button type="button" class="fm-btn fm-btn-p" data-type="access">后台访问日志</button>
        <button type="button" class="fm-btn" data-type="login">后台登录日志</button>
        <button type="button" class="fm-btn" data-type="visit">前台访问日志</button>
        <button type="button" class="fm-btn" data-type="operation">后台操作日志</button>
        <button type="button" class="fm-btn" data-type="download">下载日志</button>
      </div>
      <div class="fm-bar-acts" style="margin-left:auto">
        <span class="tag">日期</span>
        <select class="theme-select" id="logDate" style="min-width:132px"></select>
        <button type="button" class="fm-btn" id="logRefresh">刷新</button>
        <a class="fm-btn fm-btn-p" href="/admin/settings" title="修改登录用户名与密码">系统设置</a>
      </div>
    </div>
    <div class="fm-bar-row fm-count" id="logCount"></div>
  </div>
  <table>
    <thead id="logHead"></thead>
    <tbody id="logBody"><tr><td colspan="9" class="empty">加载中…</td></tr></tbody>
  </table>
  <div class="pager">
    <button type="button" class="pbtn" id="pgPrev">上一页</button>
    <span class="pinfo">第 <b id="pgCur">1</b> / <b id="pgTotal">1</b> 页，共 <b id="pgCount">0</b> 条</span>
    <button type="button" class="pbtn" id="pgNext">下一页</button>
    <span class="pjump">
      <span>跳至</span>
      <input type="number" class="pnum" id="pgJump" min="1" value="1">
      <span>页</span>
      <button type="button" class="pbtn" id="pgGo">前往</button>
    </span>
  </div>
</div>

<style>
  .fm-count{min-height:16px}
  .fm-btn-p.active{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  td.detail{white-space:normal;word-break:break-all;min-width:180px}
  td .sl{max-width:220px;overflow:hidden;text-overflow:ellipsis}
  .pill{display:inline-block;padding:1px 8px;border-radius:6px;font-size:11px;line-height:1.6}
  .pill.ok{background:rgba(47,125,50,.14);color:var(--ok)}
  .pill.bad{background:rgba(192,57,43,.14);color:var(--err)}
</style>

<script>
(function () {
  var state = { type: 'access', date: '', page: 1 };
  var btnMap = {};
  var COLS = {
    access:    [['th', '时间'], ['method', '方法'], ['path', '路径'], ['note', '动作'], ['user', '账号'], ['ip', 'IP'], ['ua', '用户端']],
    login:     [['th', '时间'], ['user', '账号'], ['act', '动作'], ['detail', '详情'], ['ip', 'IP'], ['ua', '用户端']],
    operation: [['th', '时间'], ['user', '账号'], ['act', '动作'], ['detail', '详情'], ['ip', 'IP'], ['ua', '用户端']],
    visit:     [['th', '时间'], ['act', '动作'], ['detail', '详情'], ['ip', 'IP'], ['ua', '用户端']],
    download:  [['th', '时间'], ['act', '动作'], ['detail', '详情'], ['bytes', '大小'], ['ip', 'IP'], ['ua', '用户端']]
  };
  var WIDTH = { th: 92, bytes: 110, ip: 150, user: 120 };
  function fmtDate() {
    var d = new Date(), m = [], i;
    m[0] = d.getFullYear();
    m[1] = ('0' + (d.getMonth() + 1)).slice(-2);
    m[2] = ('0' + d.getDate()).slice(-2);
    return m.join('-');
  }
  var h = function (v) {
    var d = document.createElement('div');
    d.textContent = v == null || v === '' ? '—' : String(v);
    return d.innerHTML;
  };

  document.querySelectorAll('.fm-bar .fm-btn[data-type]').forEach(function (btn) {
    btnMap[btn.getAttribute('data-type')] = btn;
    btn.addEventListener('click', function () {
      state.type = btn.getAttribute('data-type');
      state.page = 1;
      Object.keys(btnMap).forEach(function (k) {
        btnMap[k].classList.toggle('fm-btn-p', k === state.type);
      });
      load();
    });
  });

  document.getElementById('logRefresh').addEventListener('click', function () { load(); });

  var dateSel = document.getElementById('logDate');
  dateSel.addEventListener('change', function () {
    state.date = dateSel.value;
    state.page = 1;
    load();
  });

  var jumpEl = document.getElementById('pgJump');
  function goJump() {
    var v = parseInt(jumpEl.value, 10);
    if (v > 0 && v <= parseInt(document.getElementById('pgTotal').textContent, 10)) {
      state.page = v;
      load();
    }
  }
  document.getElementById('pgGo').addEventListener('click', goJump);
  jumpEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') goJump(); });
  document.getElementById('pgPrev').addEventListener('click', function () {
    if (state.page > 1) { state.page--; load(); }
  });
  document.getElementById('pgNext').addEventListener('click', function () {
    var t = parseInt(document.getElementById('pgTotal').textContent, 10);
    if (state.page < t) { state.page++; load(); }
  });

  function render(d) {
    var cols = COLS[d.type] || COLS.login;
    var head = '<tr>' + cols.map(function (c) {
      var w = WIDTH[c[0]] ? ' style="width:' + WIDTH[c[0]] + 'px"' : '';
      return '<th' + w + '>' + c[1] + '</th>';
    }).join('') + '</tr>';
    document.getElementById('logHead').innerHTML = head;
    if (!d.rows || !d.rows.length) {
      document.getElementById('logBody').innerHTML =
        '<tr><td colspan="' + cols.length + '" class="empty">该日期暂无日志</td></tr>';
      return;
    }
    var body = '';
    d.rows.forEach(function (r) {
      var cells = cols.map(function (c) {
        var k = c[0], cls = 'mono';
        if (k === 'act' && d.type === 'login') {
          var pill = r.act === '成功' ? 'ok' : 'bad';
          return '<td><span class="pill ' + pill + '">' + h(r.act) + '</span></td>';
        }
        if (k === 'detail') cls = 'detail';
        if (k === 'ua') cls = 'detail sl';
        return '<td class="' + cls + '">' + h(r[k]) + '</td>';
      });
      body += '<tr>' + cells.join('') + '</tr>';
    });
    document.getElementById('logBody').innerHTML = body;
  }

  function load() {
    document.getElementById('logCount').textContent = '加载中…';
    var url = '/admin/api/system-logs?type=' + encodeURIComponent(state.type)
      + '&date=' + encodeURIComponent(state.date || fmtDate())
      + '&page=' + state.page;
    fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) {
          document.getElementById('logBody').innerHTML =
            '<tr><td colspan="9" class="empty">加载失败：' + h((d && d.msg) || '未知错误') + '</td></tr>';
          return;
        }
        state.type = d.type;
        state.page = d.page;
        fillDates(d.dates || [], d.date);
        document.getElementById('pgCur').textContent = d.page;
        document.getElementById('pgTotal').textContent = d.pages;
        document.getElementById('pgCount').textContent = d.total;
        document.getElementById('pgJump').max = d.pages;
        document.getElementById('pgJump').value = d.page;
        document.getElementById('pgPrev').disabled = d.page <= 1;
        document.getElementById('pgNext').disabled = d.page >= d.pages;
        document.getElementById('logCount').textContent = '共 ' + d.total + ' 条';
        render(d);
      })
      .catch(function () {
        document.getElementById('logBody').innerHTML =
          '<tr><td colspan="9" class="empty">加载失败：网络错误</td></tr>';
      });
  }

  function fillDates(dates, cur) {
    if (dates.length && dateSel.value === cur) return;
    var html = '';
    (dates.length ? dates : [cur]).forEach(function (d) {
      html += '<option value="' + h(d) + '"' + (d === cur ? ' selected' : '') + '>' + h(d) + '</option>';
    });
    dateSel.innerHTML = html;
  }

  load();
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
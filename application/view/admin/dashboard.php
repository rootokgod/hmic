<?php
/**
 * 仪表盘（页面内容，布局由 partials/head.php + foot.php 提供）
 * @var string $now
 * @var array  $today
 * @var array  $total
 * @var int    $files
 * @var int    $dirs
 * @var array  $daily
 * @var array  $cmp
 */
$fb = static fn($n) => function_exists('fmt_bytes') ? fmt_bytes((int)$n) : (string)(int)$n;
include __DIR__ . '/partials/head.php';
$cmp = $cmp ?? ['count' => null, 'bytes' => null];
$delta = static function ($d, $isBytes) use ($h, $fb) {
    if ($d === null) {
        return '';
    }
    $d = (int) $d;
    if ($d === 0) {
        return '<span class="flat">持平</span>';
    }
    $up  = $d > 0;
    $txt = $isBytes ? $fb(abs($d)) : (string) abs($d);
    return '<span class="' . ($up ? 'up' : 'down') . '">' . ($up ? '↑' : '↓') . ' ' . $h($txt) . '</span>'
        . '<span class="muted"> 较昨日</span>';
};
?>
  <div class="cards">
    <div class="card">
      <div class="k">今日下载次数</div>
      <div class="v" data-k="todayCount"><?= (int)($today['count'] ?? 0) ?></div>
      <div class="d" data-d="todayCount"><?= $delta($cmp['count'] ?? null, false) ?></div>
    </div>
    <div class="card">
      <div class="k">今日流量</div>
      <div class="v" data-k="todayBytes"><?= $h($fb($today['bytes'] ?? 0)) ?></div>
      <div class="d" data-d="todayBytes"><?= $delta($cmp['bytes'] ?? null, true) ?></div>
    </div>
    <div class="card"><div class="k">累计下载次数</div><div class="v" data-k="totalCount"><?= (int)($total['count'] ?? 0) ?></div></div>
    <div class="card"><div class="k">累计流量</div><div class="v" data-k="totalBytes"><?= $h($fb($total['bytes'] ?? 0)) ?></div></div>
    <div class="card"><div class="k">资源文件数</div><div class="v" data-k="files"><?= (int)$files ?></div></div>
    <div class="card"><div class="k">顶级目录数</div><div class="v" data-k="dirs"><?= (int)$dirs ?></div></div>
  </div>

  <div class="panel">
    <h2>访问地域分布</h2>
    <div class="body">
      <div id="visitMap" style="height:480px;width:100%"></div>
      <div id="visitMapMeta" class="muted" style="padding:11px 17px;font-size:12px;border-top:1px solid var(--rowline)"></div>
    </div>
  </div>

  <script>
    window.__VISITMAP__ = <?= isset($visitMap) ? json_encode($visitMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null' ?>;
    window.__CHINAGEO__ = <?= isset($chinaGeo) && is_array($chinaGeo) ? json_encode($chinaGeo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null' ?>;
  </script>

  <div class="grid2">
    <div class="panel">
      <h2>下载链接</h2>
      <div class="body">
<table class="ltbl" style="table-layout:fixed;width:100%">
        <thead><tr>
          <th style="width:14%">令牌</th>
          <th>文件</th>
          <th style="width:15%">剩余</th>
          <th style="width:10%">已下载</th>
        </tr></thead>
        <tbody id="linksBody"><tr><td colspan="4" class="empty">加载中…</td></tr></tbody>
      </table>
        <div class="pager" id="linksPager"></div>
      </div>
    </div>

    <div class="panel">
      <h2>近 14 天统计</h2>
      <div class="body">
        <table>
          <thead><tr><th>日期</th><th>下载次数</th><th>流量</th></tr></thead>
          <tbody>
          <?php if (empty($daily)): ?>
            <tr><td colspan="3" class="empty">暂无数据</td></tr>
          <?php else: foreach ($daily as $d): ?>
            <tr>
              <td class="mono"><?= $h($d['date'] ?? '') ?></td>
              <td><?= (int)($d['count'] ?? 0) ?></td>
              <td class="muted"><?= $h($fb($d['bytes'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>最近访问 / 下载日志</h2>
    <div class="body">
      <table>
        <thead><tr><th>时间</th><th>动作</th><th>目标</th><th>大小</th><th>IP</th></tr></thead>
        <tbody id="logsBody"><tr><td colspan="5" class="empty">加载中…</td></tr></tbody>
      </table>
      <div class="pager" id="logsPager"></div>
</div>
  </div>

<script>
(function () {
  var fmt = function (n) {
    n = Number(n) || 0;
    var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return (i === 0 ? n : n.toFixed(2)) + ' ' + u[i];
  };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };
  var set = function (k, v) {
    var el = document.querySelector('[data-k="' + k + '"]');
    if (el) el.textContent = v;
  };
  var setDelta = function (k, d, isBytes) {
    var el = document.querySelector('[data-d="' + k + '"]');
    if (!el) return;
    if (d === null || typeof d === 'undefined' || isNaN(Number(d))) { el.innerHTML = ''; return; }
    d = Number(d);
    if (d === 0) { el.innerHTML = '<span class="flat">持平</span>'; return; }
    var up = d > 0;
    var txt = isBytes ? fmt(Math.abs(d)) : String(Math.abs(d));
    el.innerHTML = '<span class="' + (up ? 'up' : 'down') + '">' + (up ? '↑' : '↓') + ' ' + esc(txt) + '</span>'
      + '<span class="muted"> 较昨日</span>';
  };

  /* 通用分页条 */
  var makePager = function (host, st, go) {
    if (!host) return;
    host.innerHTML = '';
    if (!st.total) return;
    var mk = function (label, disabled, fn) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pbtn';
      b.textContent = label;
      b.disabled = !!disabled;
      if (fn) b.onclick = fn;
      return b;
    };
    var prev = mk('上一页', st.page <= 1, function () { if (st.page > 1) go(st.page - 1); });
    var info = document.createElement('span');
    info.className = 'pinfo';
    info.innerHTML = '第 <b>' + st.page + '</b> / ' + st.pages + ' 页 · 共 ' + st.total + ' 条';
    var next = mk('下一页', st.page >= st.pages, function () { if (st.page < st.pages) go(st.page + 1); });

    var jump = document.createElement('span');
    jump.className = 'pjump';
    jump.appendChild(document.createTextNode('跳至 '));
    var inp = document.createElement('input');
    inp.type = 'number';
    inp.min = '1';
    inp.max = String(st.pages);
    inp.className = 'pnum';
    inp.value = String(st.page);
    var doJump = function () {
      var n = parseInt(inp.value, 10);
      if (!n || n < 1 || n > st.pages) { inp.value = String(st.page); return; }
      if (n !== st.page) go(n);
    };
    var btn = mk('跳转', false, doJump);
    inp.onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); doJump(); } };
    jump.appendChild(inp);
    jump.appendChild(document.createTextNode(' 页 '));
    jump.appendChild(btn);

    host.appendChild(prev);
    host.appendChild(info);
    host.appendChild(next);
    host.appendChild(jump);
  };

  /* 下载链接 */
  var loadLinks = function (p) {
    fetch('/admin/api/links?page=' + p, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var tb = document.getElementById('linksBody');
        if (!d || !d.ok) { tb.innerHTML = '<tr><td colspan="4" class="empty">加载失败</td></tr>'; return; }
        if (!d.rows.length) {
          tb.innerHTML = '<tr><td colspan="4" class="empty">暂无有效链接</td></tr>';
        } else {
          tb.innerHTML = d.rows.map(function (l) {
            return '<tr><td class="mono">' + esc(l.short) + '</td>'
              + '<td class="mono" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(l.path) + '">' + esc(l.path) + '</td>'
              + '<td><span class="tag">' + esc(l.remaining_h) + '</span></td>'
              + '<td class="muted">' + esc(l.downloaded) + '</td></tr>';
          }).join('');
        }
        makePager(document.getElementById('linksPager'), { page: d.page, pages: d.pages, total: d.total }, loadLinks);
      })
      .catch(function () {});
  };

  /* 日志 */
  var loadLogs = function (p) {
    fetch('/admin/api/logs?page=' + p, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var tb = document.getElementById('logsBody');
        if (!d || !d.ok) { tb.innerHTML = '<tr><td colspan="5" class="empty">加载失败</td></tr>'; return; }
        if (!d.rows.length) {
          tb.innerHTML = '<tr><td colspan="5" class="empty">暂无日志</td></tr>';
        } else {
          tb.innerHTML = d.rows.map(function (r) {
            var sz = r.bytes > 0 ? fmt(r.bytes) : '—';
            return '<tr><td class="muted">' + esc(r.th) + '</td>'
              + '<td>' + esc(r.act) + '</td>'
              + '<td class="mono">' + esc(r.target) + '</td>'
              + '<td class="muted">' + esc(sz) + '</td>'
              + '<td class="mono">' + esc(r.ip) + '</td></tr>';
          }).join('');
        }
        makePager(document.getElementById('logsPager'), { page: d.page, pages: d.pages, total: d.total }, loadLogs);
      })
      .catch(function () {});
  };

  /* 卡片统计自动刷新 */
  var refresh = function () {
    fetch('/admin/api/dashboard', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.ok) return;
        set('todayCount', d.today.count);
        set('todayBytes', fmt(d.today.bytes));
        set('totalCount', d.total.count);
        set('totalBytes', fmt(d.total.bytes));
        set('files', d.files);
        set('dirs', d.dirs);
        if (d.cmp) {
          setDelta('todayCount', d.cmp.count, false);
          setDelta('todayBytes', d.cmp.bytes, true);
        }
      })
      .catch(function () {});
  };

  /* ===== 节点分布地图 ===== */
  var visitChart = null;
  var visitLast = null;
  var visitFit  = null;  // 初始地图包围盒像素尺寸（{w,h}，用于算相对 zoom 防止缩放重置）
  var mapCDNs = [
    '/assets/map/echarts.min.js',
    'https://cdn.bootcdn.net/ajax/libs/echarts/5.5.0/echarts.min.js',
    'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js'
  ];
  var geoCDNs = [
    '/assets/map/china.json',
    'https://geo.datav.aliyun.com/areas_v3/bound/100000_full.json',
    'https://registry.npmmirror.com/echarts/4.9.0/files/map/json/china.json'
  ];
  var visitTheme = function () {
    var root = document.documentElement;
    var cd = root.getAttribute('data-theme');
    if (cd === 'dark') return 'dark';
    if (cd === 'light') return 'light';
    try { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; } catch (e) { return 'light'; }
  };
  var visitTxt = function () { return visitTheme() === 'dark' ? '#9ca3af' : '#666'; };
  var visitLine = function () { return visitTheme() === 'dark' ? '#3a4651' : '#ddd'; };
  var visitArea = function () { return visitTheme() === 'dark' ? '#232a31' : '#f2f2f2'; };

  var loadScript = function (src, ondone) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = function () { ondone && ondone(false); };
    s.onerror = function () { ondone && ondone(true); };
    document.head.appendChild(s);
  };

  // IPv6 截断显示（前 4 组 + … + 末组）；IPv4 原样
  var ipShort = function (ip) {
    var s = String(ip);
    if (s.indexOf(':') === -1 || s.indexOf('::ffff:') === 0) return s;
    var g = s.split(':');
    var j = g.indexOf('');
    if (j !== -1) {
      var need = 8 - (g.length - 1);
      g = g.slice(0, j).concat(new Array(need).fill('0'), g.slice(j + 1));
    }
    if (g.length < 5) return s;
    return g.slice(0, 4).join(':') + '…' + g.slice(-1);
  };

  // 初始适配态地图包围盒像素尺寸（首个真正渲染后测量一次）
  var measureFit = function () {
    if (visitFit) return;
    if (!visitChart || typeof visitChart.convertToPixel !== 'function') return;
    var p1 = visitChart.convertToPixel({ geoIndex: 0 }, [73, 54]);
    var p2 = visitChart.convertToPixel({ geoIndex: 0 }, [135, 18]);
    if (!p1 || !p2 || !isFinite(p1[0]) || !isFinite(p2[0])) return;
    visitFit = { w: Math.abs(p2[0] - p1[0]), h: Math.abs(p2[1] - p1[1]) };
  };

  // 拖拽超出视野时把地图中心收回到画布内（带当前 zoom，避免把缩放重置回初始适配）
  var clampMapRoam = function () {
    var c = visitChart;
    if (!c || typeof c.convertToPixel !== 'function') return;
    if (!visitFit) measureFit();
    var tl = c.convertToPixel({ geoIndex: 0 }, [73, 54]);
    var br = c.convertToPixel({ geoIndex: 0 }, [135, 18]);
    if (!tl || !br || !isFinite(tl[0]) || !isFinite(br[0])) return;
    var W = c.getWidth(), H = c.getHeight();
    var cx = (tl[0] + br[0]) / 2;
    var cy = (tl[1] + br[1]) / 2;
    if (cx > 0 && cx < W && cy > 0 && cy < H) return;
    var nx = Math.min(Math.max(cx, 0), W);
    var ny = Math.min(Math.max(cy, 0), H);
    var zoom = (visitFit && visitFit.w > 0) ? (br[0] - tl[0]) / visitFit.w : 1;
    var cc = c.convertFromPixel({ geoIndex: 0 }, [nx, ny]);
    if (cc) c.setOption({ geo: { center: cc, zoom: zoom } });
  };

  var buildVisitMap = function () {
    var el = document.getElementById('visitMap');
    if (!el || !window.echarts || !visitLast) return;
    var d = visitLast;
    if (!visitChart) {
      visitChart = echarts.init(el);
      visitChart.on('georoam', clampMapRoam);
    }
    var max = 1;
    (d.regions || []).forEach(function (r) { if (r.visits > max) max = r.visits; });
    var dark = visitTheme() === 'dark';
    visitChart.setOption({
      backgroundColor: 'transparent',
      tooltip: {
        trigger: 'item',
        confine: true,
        borderColor: dark ? '#333' : '#ddd',
        backgroundColor: dark ? '#111' : '#fff',
        textStyle: { color: dark ? '#e5e7eb' : '#111' },
        formatter: function (p) {
          if (!p.data || !p.data.ips_list || !p.data.ips_list.length) {
            return esc(p.name) + '<br/><span style="opacity:.6">暂无访问</span>';
          }
          var items = p.data.ips_list.map(function (ip) {
            return '<span style="font-family:Consolas,Menlo,monospace">' + esc(ipShort(ip)) + '</span>';
          });
          return '<b>' + esc(p.name) + '</b>（总访问 <b>' + p.data.visits + '</b> 次）<br/>' + items.join('<br/>');
        }
      },
      visualMap: {
        min: 0, max: max, calculable: true,
        left: 14, bottom: 14, orient: 'vertical',
        text: ['多', '少'], textGap: 10,
        textStyle: { color: visitTxt() },
        inRange: { color: ['#9be05f', '#c9d94f', '#f2c24e', '#f08a3c', '#e65d2f', '#d43d2b', '#b3121f'] }
      },
      geo: {
        map: 'china',
        roam: true,
        scaleLimit: { min: 1, max: 6 },
        label: { show: false, color: dark ? '#e5e7eb' : '#444' },
        itemStyle: { borderColor: dark ? '#4a5763' : '#aaa', borderWidth: 0.9, areaColor: visitArea() },
        emphasis: {
          label: { show: true, color: dark ? '#fff' : '#000' },
          itemStyle: { areaColor: dark ? '#313b44' : '#ffd54f' }
        }
      },
      series: [{
        type: 'map', map: 'china', geoIndex: 0,
        data: (d.regions || []).map(function (r) {
          return { name: r.name, value: r.visits, visits: r.visits, ips: r.ips, downloads: r.downloads, devices: r.devices, ips_list: r.ips_list || [] };
        })
      }]
    });
    measureFit();
  };

  var applyVisit = function (d) {
    var elMeta = document.getElementById('visitMapMeta');
    if (!d || !d.ok) { if (elMeta) elMeta.textContent = '地图数据加载失败'; return; }
    visitLast = d;
    var t = d.total || {};
    var meta = '近 ' + d.days + ' 天 · 访问 ' + t.visits + ' 次 · ' + t.ips + ' 个IP · 覆盖 ' + t.regions + ' 个省级区域';
    if ((t.downloads || 0) > 0) meta += ' · 下载 ' + t.downloads + ' 次';
    if (d.foreign && d.foreign.length) {
      var f = d.foreign.slice(0, 5).map(function (x) { return x.name + '(' + x.visits + '次 / ' + x.ips + 'IP)'; }).join('、');
      meta += ' · 海外：' + f;
    }
    if ((t.local || 0) > 0) meta += ' · 本机/内网 ' + t.local + ' 次';
    if (elMeta) elMeta.textContent = meta;
    buildVisitMap();
  };

  var loadVisitMap = function () {
    fetch('/admin/api/visitmap?days=3', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(applyVisit)
      .catch(function () {});
  };

  var initVisitMap = function () {
    var el = document.getElementById('visitMap');
    if (!el) return;
    (function tryEcharts(i) {
      if (window.echarts) {
        if (window.__CHINAGEO__) {
          window.echarts.registerMap('china', window.__CHINAGEO__);
          applyVisit(window.__VISITMAP__);
          setInterval(loadVisitMap, 60000);
          return;
        }
        (function tryGeo(j) {
          if (j >= geoCDNs.length) { var m = document.getElementById('visitMapMeta'); if (m) m.textContent = '地图资源加载失败，请检查网络'; return; }
          fetch(geoCDNs[j], { cache: 'force-cache' }).then(function (r) { return r.json(); })
            .then(function (g) {
              if (!g || !g.features) return tryGeo(j + 1);
              window.echarts.registerMap('china', g);
              loadVisitMap();
              setInterval(loadVisitMap, 60000);
            })
            .catch(function () { tryGeo(j + 1); });
        })(0);
        return;
      }
      if (i >= mapCDNs.length) { var m = document.getElementById('visitMapMeta'); if (m) m.textContent = 'ECharts 加载失败，请检查网络'; return; }
      loadScript(mapCDNs[i], function () { tryEcharts(i + 1); });
    })(0);
  };

  try {
    var _obs = new MutationObserver(function () { buildVisitMap(); });
    _obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  } catch (e) {}

  loadLinks(1);
  loadLogs(1);
  setInterval(refresh, 5000);
  initVisitMap();
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>

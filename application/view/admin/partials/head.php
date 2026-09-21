<?php
/**
 * 后台布局头部（公共）
 * 需要变量：$user、$login、$pageTitle、$activeNav
 */
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$__title = (string)($pageTitle ?? '后台管理');
$__nav   = (string)($activeNav ?? '');
$__mr    = (int) (function_exists('home_config_load') ? (home_config_load()['menu_round'] ?? 0) : 0);
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h($__title) ?> - 后台管理</title>
<?php include __DIR__ . '/theme-head.php'; ?>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;
 background:var(--bg);color:var(--ink);font-size:14px}
a{color:var(--ink2);text-decoration:none}
input,textarea,select{color:inherit;font:inherit}
select{background:var(--card)}
/* 空白点击不出现文本光标：默认箭头，仅输入类元素显示文本光标 */
body{cursor:default}
input,textarea,select,[contenteditable]{cursor:text}
button,a{cursor:pointer}
/* 非输入区隐藏文本插入光标（彻底消除点击空白闪动的"|"） */
*{caret-color:transparent!important}
input,textarea,select,[contenteditable]{caret-color:auto!important}
.app{display:flex;min-height:100vh}

/* 侧边栏 */
.side{width:208px;flex:none;background:var(--card);border-right:1px solid var(--line);
padding:6px 0;position:sticky;top:0;height:100vh;display:flex;flex-direction:column}
.side-title{padding:13px 18px;font-size:13px;font-weight:600;color:var(--ink);
border-bottom:1px dashed var(--line)}
.side nav{flex:1 1 auto;overflow:auto}
.side nav a{display:block;padding:11px 18px;border-bottom:1px dashed var(--line);
color:var(--ink2);font-size:14px;transition:background .15s,color .15s}
.side nav a:last-child{border-bottom:0}
.side nav a:hover{background:var(--hover);color:var(--ink)}
.side nav a.on{background:var(--active);color:var(--ink);font-weight:500}

/* 父菜单（可展开/收起） */
.nav-group .nav-parent{display:flex;align-items:center;justify-content:space-between;gap:8px;
padding:11px 18px;border-bottom:1px dashed var(--line);color:var(--ink2);font-size:14px;
cursor:pointer;transition:background .15s,color .15s;-webkit-user-select:none;user-select:none}
.nav-group .nav-parent:hover{background:var(--hover);color:var(--ink)}
.nav-group .nav-parent.on{background:var(--active);color:var(--ink);font-weight:500}
.nav-group .caret{width:14px;height:14px;flex:none;position:relative}
.nav-group .caret::after{content:"";position:absolute;left:1px;top:4px;width:7px;height:7px;
border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:rotate(45deg);
transition:transform .18s ease}
.nav-group .nav-parent.on .caret::after{transform:rotate(225deg)}
.nav-group .nav-children{background:var(--bg);overflow:hidden;max-height:0;opacity:0;
  transform:translateY(-6px);transition:max-height .22s ease,opacity .18s ease,transform .22s ease}
.nav-group .nav-children.open{max-height:440px;opacity:1;transform:translateY(0)}
.nav-group .nav-children a{display:block;padding:9px 18px 9px 32px;border-bottom:1px dashed var(--line);
  font-size:13.5px}
.nav-group .nav-children a:hover{background:var(--hover);color:var(--ink)}
.nav-group .nav-children a.on{background:var(--active);color:var(--ink);font-weight:500}
/* 三级菜单：子级 .nav-group 缩进更深 */
.nav-group .nav-children .nav-group .nav-parent{padding-left:32px}
.nav-group .nav-children .nav-children a{padding-left:48px}
.nav-group .nav-children .nav-group{border-bottom:1px dashed var(--line)}
.side-bottom{margin-top:auto;padding:12px 14px}
.side-bottom .logout,.side-bottom .clear-cache{display:block;width:100%;text-align:center;padding:8px;
border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink2);font-size:13px;
font-family:inherit;cursor:pointer;transition:background .15s,border-color .15s,color .15s}
.side-bottom .side-sep{border-top:1px dashed var(--line);margin:12px 4px}
.side-bottom .logout:hover,.side-bottom .clear-cache:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}

/* 主题化下拉选择框 */
select.theme-select{appearance:none;-webkit-appearance:none;-moz-appearance:none;border:1px solid var(--line);
border-radius:8px;background-color:var(--card);color:var(--ink);font-size:13px;font-family:inherit;
padding:8px 34px 8px 12px;cursor:pointer;outline:none;flex:none;
background-image:linear-gradient(45deg,transparent 50%,var(--ink3) 50%),linear-gradient(135deg,var(--ink3) 50%,transparent 50%);
background-position:calc(100% - 18px) 55%,calc(100% - 13px) 55%;background-size:5px 5px;background-repeat:no-repeat;
transition:border-color .15s,background-color .15s}
select.theme-select:hover{border-color:var(--ink3)}
select.theme-select:focus{border-color:var(--ink3)}
select.theme-select option{color:var(--ink);background:var(--card)}

/* 主区域 */
.main{flex:1;min-width:0;display:flex;flex-direction:column}
.top{background:var(--card);border-bottom:1px solid var(--line);padding:0 22px;height:56px;
display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:10}
.ptitle{font-weight:600;font-size:15px}
.top .spacer{flex:1}
.menu-btn{display:none;width:36px;height:32px;border:1px solid var(--line);border-radius:7px;background:var(--card);
padding:0;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;flex:none}
.menu-btn span{display:block;width:15px;height:1.5px;background:var(--ink2)}
.side-mask{display:none}

.wrap{width:100%;padding:22px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:15px 17px}
.card .k{font-size:12px;color:var(--ink3);margin-bottom:8px}
.card .v{font-size:22px;font-weight:600}
.card .d{font-size:12px;margin-top:6px;min-height:15px;line-height:1.2}
.card .d .up{color:var(--ok)}
.card .d .down{color:var(--err)}
.card .d .flat{color:var(--ink3)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* 分页 */
.pager{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:11px 15px;
border-top:1px solid var(--rowline);font-size:12.5px;color:var(--ink3)}
.pager .pbtn{padding:5px 12px;border:1px solid var(--line);border-radius:7px;background:var(--card);
color:var(--ink2);font-size:12.5px;font-family:inherit;cursor:pointer;
transition:background .15s,border-color .15s,color .15s}
.pager .pbtn:hover:not(:disabled){background:var(--hover);border-color:var(--ink3);color:var(--ink)}
.pager .pbtn:disabled{opacity:.4;cursor:default}
.pager .pinfo b{color:var(--ink)}
.pager .pjump{margin-left:auto;display:flex;align-items:center;gap:6px}
.pager .pnum{width:56px;padding:4px 6px;border:1px solid var(--line);border-radius:7px;background:var(--bg);
color:var(--ink);font-size:12.5px;font-family:inherit;text-align:center}
.pager .pnum:focus{outline:none;border-color:var(--ink3)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:10px;margin-bottom:14px;overflow:hidden}
.panel h2{margin:0;padding:13px 17px;font-size:13px;font-weight:600;color:var(--ink2);border-bottom:1px solid var(--rowline)}
.panel .body{padding:0}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:9px 15px;text-align:left;border-bottom:1px solid var(--rowline);white-space:nowrap}
th{background:var(--th);color:var(--ink3);font-weight:500;font-size:12px}
tr:last-child td{border-bottom:0}
td.mono,.mono{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--ink2)}
.tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;background:var(--tag-bg);color:var(--tag-ink)}
.muted{color:var(--ink3)}
.empty{padding:18px;color:var(--ink3);font-size:13px;text-align:center}

/* 通用操作按钮（文件管理 / 贴纸图库等页面共用） */
.fm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;
  border:1px solid var(--line);background:var(--card);color:var(--ink2);font-family:inherit;text-decoration:none;
  transition:background .15s,border-color .15s,color .15s;-webkit-user-select:none;user-select:none}
.fm-btn:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
.fm-btn.fm-btn-p{background:var(--btn);color:var(--btn-ink);border-color:transparent}
.fm-btn.fm-btn-p:hover{background:var(--btn);filter:brightness(.92);color:var(--btn-ink)}
.fm-btn.spinning svg{animation:fmspin .6s linear infinite}
@keyframes fmspin{to{transform:rotate(360deg)}}
.fm-btn:disabled{opacity:.5;cursor:default;filter:none}
/* 工具条布局（文件管理 / 贴纸图库共用） */
.fm-bar{display:flex;flex-direction:column;gap:8px;padding:12px 17px;border-bottom:1px solid var(--rowline)}
.fm-bar-row{display:flex;align-items:center;flex-wrap:wrap;gap:10px;min-width:0}
.fm-bar-acts{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0}
.fm-count{margin-left:auto;font-size:12.5px}

/* 设置行 / 开关 */
.set-row{display:flex;align-items:center;gap:16px;padding:16px 17px}
.set-row .set-t{font-size:14px;color:var(--ink);font-weight:500}
.set-row .set-d{font-size:12.5px;color:var(--ink3);margin-top:4px}
.switch{margin-left:auto;position:relative;display:inline-block;width:44px;height:24px;flex:none}
.switch input{opacity:0;width:0;height:0}
.switch .slider{position:absolute;inset:0;background:var(--line);border-radius:999px;
transition:background .2s;cursor:pointer}
.switch .slider::before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;
background:#fff;border-radius:50%;box-shadow:0 1px 3px var(--shadow);transition:transform .2s}
.switch input:checked + .slider{background:var(--ok)}
.switch input:checked + .slider::before{transform:translateX(20px)}
.switch input:focus-visible + .slider{outline:2px solid var(--ink3);outline-offset:2px}

/* 确认弹窗（带过渡动画） */
.modal-mask{position:fixed;inset:0;background:var(--mask);z-index:100;display:flex;align-items:center;
justify-content:center;padding:20px;opacity:0;visibility:hidden;
transition:opacity .22s ease,visibility .22s ease}
.modal-mask.open{opacity:1;visibility:visible}
.modal{width:min(92vw,420px);background:var(--card);border:1px solid var(--line);border-radius:12px;
padding:22px;opacity:0;transform:translateY(10px) scale(.97);
transition:transform .26s cubic-bezier(.2,.8,.2,1),opacity .26s ease}
.modal-mask.open .modal{opacity:1;transform:none}
.modal h3{margin:0 0 14px;font-size:15px;font-weight:600}
.cache-tip{font-size:13px;color:var(--ink2);margin-bottom:10px;line-height:1.6}
.cache-list{list-style:none;margin:0 0 10px;padding:0;max-height:42vh;overflow:auto}
.cache-list li{display:flex;align-items:center;gap:12px;padding:10px 2px;
border-bottom:1px dashed var(--line);font-size:13px}
.cache-list li:last-child{border-bottom:0}
.cache-list .nm{flex:1;color:var(--ink2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cache-list .sz{flex:none;color:var(--ink3);font-variant-numeric:tabular-nums}
.cache-total{display:flex;justify-content:space-between;gap:12px;padding-top:11px;
border-top:1px dashed var(--line);font-size:13px;margin-bottom:18px}
.cache-total .sz{font-weight:600;font-variant-numeric:tabular-nums}
.modal .acts{display:flex;justify-content:flex-end;gap:10px}
.modal .mbtn{padding:8px 16px;border-radius:8px;border:1px solid var(--line);background:var(--card);
color:var(--ink2);font-size:13px;font-family:inherit;cursor:pointer;transition:background .15s,color .15s,border-color .15s}
.modal .mbtn:hover{background:var(--hover);color:var(--ink)}
.modal .mbtn.primary{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .modal .mbtn.primary:hover{filter:brightness(.92)}
  .modal .mbtn.danger{background:var(--err);color:#fff;border-color:transparent}
  .modal .mbtn.danger:hover{filter:brightness(.92)}
  .modal .mbtn:disabled{opacity:.45;cursor:default;filter:none}
  .modal.sm{width:min(92vw,360px)}
  .modal .cf-body{font-size:13px;color:var(--ink2);line-height:1.7;white-space:pre-wrap;word-break:break-word;margin-bottom:18px}

/* 右上角提示（绿色，右侧滑入 / 滑出） */
.toast-wrap{position:fixed;top:16px;right:16px;z-index:110;display:flex;flex-direction:column;
gap:10px;pointer-events:none}
.toast{background:var(--card);border:1px solid var(--line);border-left:3px solid var(--ok);
border-radius:10px;padding:12px 15px;min-width:210px;max-width:min(78vw,320px);
box-shadow:0 8px 24px var(--shadow);opacity:0;transform:translateX(calc(100% + 28px));
transition:transform .6s cubic-bezier(.22,.9,.25,1),opacity .6s ease}
.toast.show{opacity:1;transform:translateX(0)}
.toast .t1{font-size:13px;font-weight:600;color:var(--ok)}
.toast .toast-sep{border-top:1px dashed var(--line);margin:8px 0}
.toast .t2{font-size:12.5px;color:var(--ok);word-break:break-all}
.toast .t3{font-size:12px;color:var(--ok);opacity:.85;margin-top:3px;word-break:break-all}
.toast.err{border-left-color:var(--err)}
.toast.err .t1,.toast.err .t2{color:var(--err)}
.toast.err .t3{color:var(--err);opacity:.85}

/******** 后台菜单圆润（「外观设置」配置 0=尖角 1=圆润 2=超圆润） ********/
body.mr-1 .side nav a,body.mr-2 .side nav a,
body.mr-1 .nav-group .nav-parent,body.mr-2 .nav-group .nav-parent,
body.mr-1 .nav-group .nav-children a,body.mr-2 .nav-group .nav-children a{transition:background .15s,color .15s,border-radius .2s}
body.mr-1 .side nav a,body.mr-1 .nav-group .nav-parent,
body.mr-1 .nav-group .nav-children a{border-radius:9px;margin-left:8px;margin-right:8px;border-bottom:0}
body.mr-1 .side nav a{padding:10px 13px}
body.mr-1 .nav-group .nav-children a{padding:8px 14px 8px 20px}
body.mr-1 .nav-group .nav-children .nav-group .nav-parent{padding-left:16px}
body.mr-2 .side nav a,body.mr-2 .nav-group .nav-parent,
body.mr-2 .nav-group .nav-children a{border-radius:15px;margin-left:8px;margin-right:8px;border-bottom:0}
body.mr-2 .side nav a{padding:10px 13px}
body.mr-2 .nav-group .nav-children a{padding:8px 14px 8px 20px}
body.mr-2 .nav-group .nav-children .nav-group .nav-parent{padding-left:16px}
@media(max-width:820px){
  .menu-btn{display:flex}
  .side{position:fixed;left:0;top:0;bottom:0;height:100%;width:228px;z-index:30;
  transform:translateX(-100%);transition:transform .2s;box-shadow:0 0 24px var(--shadow)}
  body.navopen .side{transform:none}
  body.navopen .side-mask{display:block;position:fixed;inset:0;background:var(--mask);z-index:20}
  .top{padding:0 14px;gap:10px}
  .grid2{grid-template-columns:1fr}
}
</style>
</head>
<body class="mr-<?= $__mr ?>">
<script>
/* 点击空白处不残留输入光标：非输入区 mousedown 时强制失焦，杜绝闪动的"|" */
(function () {
  document.addEventListener('mousedown', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (!t.closest('input,textarea,select,[contenteditable]')) {
      var a = document.activeElement;
      try { if (a && a.blur && a !== document.body) a.blur(); } catch (err) {}
    }
  }, true);
})();
/* 全局 Toast：固定标题「系统提示」+ 内容 + 可选详情 */
window.showToast = function (msg, detail, err) {
  if (typeof detail === 'boolean') { err = detail; detail = ''; }
  detail = detail || '';
  var wrap = document.querySelector('.toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'toast-wrap';
    document.body.appendChild(wrap);
  }
  var t = document.createElement('div');
  t.className = 'toast' + (err ? ' err' : '');
  t.innerHTML = '<div class="t1"></div>';
  t.querySelector('.t1').textContent = '系统提示';
  if (msg) {
    t.innerHTML += '<div class="toast-sep"></div><div class="t2"></div>';
    t.querySelector('.t2').textContent = msg;
  }
  if (detail) {
    t.innerHTML += '<div class="t3"></div>';
    t.querySelector('.t3').textContent = detail;
  }
  wrap.appendChild(t);
  requestAnimationFrame(function () { t.classList.add('show'); });
  setTimeout(function () {
    t.classList.remove('show');
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 400);
  }, 2600);
};
/* 全局确认框：复用 .modal-mask / .modal 样式，回调形式 confirmBox(msg, okText, cb) */
window.confirmBox = function (msg, okText, cb) {
  if (typeof okText === 'function') { cb = okText; okText = ''; }
  var mask = document.createElement('div');
  mask.className = 'modal-mask open';
  mask.innerHTML = '<div class="modal sm"><h3>确认执行</h3><div class="cf-body"></div>'
    + '<div class="acts"><button type="button" class="mbtn" data-c="0">取消</button>'
    + '<button type="button" class="mbtn danger" data-c="1"></button></div></div>';
  mask.querySelector('.cf-body').textContent = String(msg || '确认继续？');
  mask.querySelector('[data-c="1"]').textContent = okText || '确定';
  function close() {
    mask.classList.remove('open');
    setTimeout(function () { if (mask.parentNode) mask.parentNode.removeChild(mask); }, 220);
  }
  mask.addEventListener('click', function (e) {
    if (e.target === mask) { close(); return; }
    var c = e.target.closest('[data-c]');
    if (!c) return;
    close();
    if (c.dataset.c === '1' && cb) cb();
  });
  document.body.appendChild(mask);
};
</script>
<div class="side-mask" onclick="document.body.classList.remove('navopen')"></div>
<div class="app">
<?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header class="top">
      <button type="button" class="menu-btn" aria-label="菜单"
              onclick="document.body.classList.toggle('navopen')"><span></span><span></span><span></span></button>
      <div class="ptitle"><?= $h($__title) ?></div>
      <div class="spacer"></div>
<?php include __DIR__ . '/theme-toggle.php'; ?>
    </header>
    <div class="wrap">

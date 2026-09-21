<?php
/**
 * 主题（公共头部）：浅色 / 纯黑 变量 + 切换按钮样式 + 预先应用已保存主题
 */
?><style>
/* ===== 主题变量（与首页一致的浅色 / 纯黑）===== */
:root{
  --bg:#f6f6f6;--card:#fff;--line:#e8e8e8;
  --ink:#111;--ink2:#555;--ink3:#8a8a8a;
  --hover:#f4f4f4;--active:#f0f0f0;
  --th:#fafafa;--rowline:#f2f2f2;
  --tag-bg:#f3f3f3;--tag-ink:#555;
  --btn:#111;--btn-ink:#fff;
  --ok:#2f7d32;--err:#c0392b;
  --mask:rgba(0,0,0,.25);--shadow:rgba(0,0,0,.08);
}
[data-theme="dark"]{
  --bg:#000;--card:#0a0a0a;--line:#1f1f1f;
  --ink:#e5e7eb;--ink2:#9ca3af;--ink3:#6b7280;
  --hover:#111;--active:#161616;
  --th:#0a0a0a;--rowline:#1a1a1a;
  --tag-bg:#1a1a1a;--tag-ink:#9ca3af;
  --btn:#e5e7eb;--btn-ink:#000;
  --ok:#4ade80;--err:#f87171;
  --mask:rgba(0,0,0,.6);--shadow:rgba(0,0,0,.5);
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --bg:#000;--card:#0a0a0a;--line:#1f1f1f;
    --ink:#e5e7eb;--ink2:#9ca3af;--ink3:#6b7280;
    --hover:#111;--active:#161616;
    --th:#0a0a0a;--rowline:#1a1a1a;
    --tag-bg:#1a1a1a;--tag-ink:#9ca3af;
    --btn:#e5e7eb;--btn-ink:#000;
    --ok:#4ade80;--err:#f87171;
    --mask:rgba(0,0,0,.6);--shadow:rgba(0,0,0,.5);
  }
}

/* 切换时只做颜色平滑过渡（不做全屏涟漪，避免控件闪烁） */
body{transition:background-color .2s,color .2s}
.side,.top,.card,.panel,th,.side-bottom .logout,.box{transition:background-color .2s,color .2s,border-color .2s}

/* ===== 主题切换按钮 ===== */
.theme-toggle{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;
border-radius:50%;border:1px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;
padding:0;flex:none;transition:background .15s,border-color .15s,color .15s}
.theme-toggle:hover{background:var(--hover);border-color:var(--ink3)}
.theme-toggle svg{width:16px;height:16px}
.theme-toggle .moon{display:none}
[data-theme="dark"] .theme-toggle .sun{display:none}
[data-theme="dark"] .theme-toggle .moon{display:inline-block}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]) .theme-toggle .sun{display:none}
  :root:not([data-theme="light"]) .theme-toggle .moon{display:inline-block}
}
</style>
<script>
(function(){
  try{
    var s = localStorage.getItem('theme');
    if(s === 'light' || s === 'dark'){ document.documentElement.setAttribute('data-theme', s); }
  }catch(e){}
})();
</script>

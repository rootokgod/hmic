/* 向导通用脚本：主题切换 + 复制到剪贴板 + 步骤交互 */
(function(){
  var root=document.documentElement;
  var tb=document.createElement('button');
  tb.type='button';tb.title='切换深浅主题';tb.setAttribute('aria-label','切换主题');
  tb.className='iw-theme-toggle';
  tb.innerHTML='<svg class="sun" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="moon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
  tb.style.cssText='position:fixed;top:16px;right:16px;z-index:99;width:36px;height:36px;border-radius:50%;border:1px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;transition:background .15s,border-color .15s,color .15s';
  tb.addEventListener('click',function(){
    var c=root.getAttribute('data-theme');
    var sys=window.matchMedia('(prefers-color-scheme: dark)').matches;
    var dark=(c==='dark')||(c!=='light'&&sys);
    root.setAttribute('data-theme',dark?'light':'dark');
    try{localStorage.setItem('theme',dark?'light':'dark');}catch(e){}
    tb.blur();
  });
  document.body.appendChild(tb);
  var css=document.createElement('style');
  css.textContent='.iw-theme-toggle svg{width:16px;height:16px}.iw-theme-toggle .moon{display:none}[data-theme="dark"] .iw-theme-toggle .sun{display:none}[data-theme="dark"] .iw-theme-toggle .moon{display:inline}.iw-theme-toggle:hover{background:var(--hover);border-color:var(--line)}';
  document.head.appendChild(css);
})();
/* 复制到剪贴板（含降级） */
function iwCopyText(t){
  if(navigator.clipboard&&navigator.clipboard.writeText){return navigator.clipboard.writeText(t);}
  var ta=document.createElement('textarea');ta.value=t;ta.style.cssText='position:fixed;left:-9999px';
  document.body.appendChild(ta);ta.select();
  try{document.execCommand('copy');}catch(e){}
  document.body.removeChild(ta);return Promise.resolve();
}
(function(){
  var btns=document.querySelectorAll('.copy-btn');
  for(var i=0;i<btns.length;i++){(function(b){
    b.addEventListener('click',function(){
      var id=b.getAttribute('data-copy');var el=document.getElementById(id);
      if(!el)return;iwCopyText(el.textContent).then(function(){
        var t=b.textContent;b.textContent='已复制';setTimeout(function(){b.textContent=t;},1200);
      });
    });
  })(btns[i]);}
})();
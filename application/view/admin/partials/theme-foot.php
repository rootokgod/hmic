<?php
/**
 * 主题（公共尾部）：切换逻辑（localStorage key = theme）
 * 仅切换 :root 上的 data-theme，由 CSS 过渡完成变色，不覆盖任何控件
 */
?><script>
function toggleTheme(){
  var root = document.documentElement;
  var btn = document.getElementById('themeToggle');
  var current = root.getAttribute('data-theme');
  var sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var isDarkNow = current === 'dark' || (current !== 'light' && sysDark);
  var goingDark = !isDarkNow;
  root.setAttribute('data-theme', goingDark ? 'dark' : 'light');
  try{ localStorage.setItem('theme', goingDark ? 'dark' : 'light'); }catch(e){}
  if(btn && btn.blur){ btn.blur(); }
}
</script>

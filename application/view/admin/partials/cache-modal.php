<?php
/**
 * 清除缓存：确认弹窗 + 逻辑（公共）
 * 点击侧边栏 #clearCacheBtn：
 *   1) 先计算占用空间，逐项列出要删除的内容（虚线分隔）
 *   2) 弹窗出现后需等待 5 秒才可点「确定」（防误触），有「取消」
 */
?><div class="modal-mask" id="cacheMask">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cacheTitle">
    <h3 id="cacheTitle">清除系统缓存</h3>
    <div class="cache-tip" id="cacheTip">正在计算要清理的空间…</div>
    <ul class="cache-list" id="cacheList"></ul>
    <div class="cache-total" id="cacheTotal" style="display:none"><span>合计</span><span class="sz" id="cacheSum"></span></div>
    <div class="acts">
      <button class="mbtn" id="cacheCancel" type="button">取消</button>
      <button class="mbtn primary" id="cacheOk" type="button" disabled>确定</button>
    </div>
  </div>
</div>
<div class="toast-wrap" id="toastWrap"></div>
<script>
(function () {
  var btn = document.getElementById('clearCacheBtn');
  var mask = document.getElementById('cacheMask');
  if (!btn || !mask) return;

  var tip    = document.getElementById('cacheTip');
  var list   = document.getElementById('cacheList');
  var total  = document.getElementById('cacheTotal');
  var sum    = document.getElementById('cacheSum');
  var okBtn  = document.getElementById('cacheOk');
  var noBtn  = document.getElementById('cacheCancel');
  var state  = 'calc';
  var timer  = null;

  function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

  function showToast(desc, isError) {
    var wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    var el = document.createElement('div');
    el.className = 'toast' + (isError ? ' err' : '');
    var t1 = document.createElement('div');
    t1.className = 't1';
    t1.textContent = '系统提示';
    var sep = document.createElement('div');
    sep.className = 'toast-sep';
    var t2 = document.createElement('div');
    t2.className = 't2';
    t2.textContent = desc;
    el.appendChild(t1);
    el.appendChild(sep);
    el.appendChild(t2);
    wrap.appendChild(el);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { el.classList.add('show'); });
    });
    setTimeout(function () {
      el.classList.remove('show');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 620);
    }, 2000);
  }
  window.showToast = showToast;

  /* 清理浏览器缓存（Cache Storage）并强制重新加载，确保拿到最新页面 */
  function clearBrowserCache() {
    try {
      if (window.caches && caches.keys) {
        caches.keys().then(function (keys) {
          keys.forEach(function (k) { caches.delete(k); });
        }).catch(function () {});
      }
    } catch (e) {}
    setTimeout(function () { location.reload(); }, 2200);
  }

  function render(items) {
    list.innerHTML = '';
    for (var i = 0; i < items.length; i++) {
      var li = document.createElement('li');
      var nm = document.createElement('span');
      nm.className = 'nm';
      nm.textContent = items[i].name + (items[i].files > 1 ? ' · ' + items[i].files + ' 个文件' : '');
      var sz = document.createElement('span');
      sz.className = 'sz';
      sz.textContent = items[i].human;
      li.appendChild(nm);
      li.appendChild(sz);
      list.appendChild(li);
    }
  }

  function countdown() {
    var n = 5;
    okBtn.disabled = true;
    okBtn.textContent = '确定 (' + n + ')';
    stopTimer();
    timer = setInterval(function () {
      n--;
      if (n > 0) { okBtn.textContent = '确定 (' + n + ')'; return; }
      stopTimer();
      okBtn.textContent = '确定';
      okBtn.disabled = false;
    }, 1000);
  }

  function open() {
    stopTimer();
    state = 'calc';
    list.innerHTML = '';
    total.style.display = 'none';
    okBtn.disabled = true;
    okBtn.textContent = '确定';
    noBtn.disabled = false;
    noBtn.textContent = '取消';
    tip.textContent = '正在计算要清理的空间…';
    mask.classList.add('open');

    fetch('/admin/api/cache', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { tip.textContent = '计算失败，请稍后重试。'; return; }
        if (!d.items.length) {
          state = 'none';
          tip.textContent = '当前没有可清理的缓存。';
          return;
        }
        state = 'confirm';
        tip.textContent = '以下内容将被删除：';
        render(d.items);
        sum.textContent = d.human + '（共 ' + d.files + ' 个文件）';
        total.style.display = 'flex';
        countdown();
      })
      .catch(function () { tip.textContent = '计算失败，请稍后重试。'; });
  }

  function close() { stopTimer(); mask.classList.remove('open'); }

  function doClear() {
    stopTimer();
    state = 'clear';
    okBtn.disabled = true;
    noBtn.disabled = true;
    tip.textContent = '正在清理…';

    fetch('/admin/api/cache', { method: 'POST', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) {
          close();
          showToast('清理缓存失败，请稍后重试', true);
          return;
        }
        close();
        showToast('已清理缓存（' + d.human + '）· 浏览器缓存已清空');
        clearBrowserCache();
      })
      .catch(function () {
        close();
        showToast('清理缓存失败，请稍后重试', true);
      });
  }

  btn.addEventListener('click', open);
  noBtn.addEventListener('click', close);
  okBtn.addEventListener('click', function () {
    if (state === 'confirm') { doClear(); return; }
    close();
  });
  mask.addEventListener('click', function (e) { if (e.target === mask && state !== 'clear') close(); });
})();
</script>

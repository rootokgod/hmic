<?php
/**
 * 安装向导 - 渲染（页面骨架 / 各步骤 / 主题）
 */

function iw_steps(): array
{
    return [
        ['welcome', '欢迎'],
        ['env', '环境检测'],
        ['path', '安全路径'],
        ['account', '管理员账号'],
        ['created', '创建配置'],
        ['finish', '完成安装'],
    ];
}

function iw_active_index(string $active): int
{
    $map = ['welcome' => 0, 'env' => 1, 'path' => 2, 'account' => 3, 'created' => 4, 'finish' => 5];
    return $map[$active] ?? 0;
}

function iw_page(string $title, string $active, string $body, string $extraJs = ''): void
{
    $steps = iw_steps();
    $idx   = iw_active_index($active);

    $stepper = '<div class="iw-steps">';
    foreach ($steps as $i => $st) {
        $done = $i < $idx ? ' done' : '';
        $cur  = $i === $idx ? ' current' : '';
        $stepper .= '<div class="iw-step' . $done . $cur . '">'
            . '<span class="dot">' . ($i < $idx ? '&#10003;' : (string) ($i + 1)) . '</span>'
            . '<span class="lbl">' . iw_h($st[1]) . '</span></div>';
    }
    $stepper .= '</div>';

    $css = (string) @file_get_contents(IW_DIR . '/assets/assets.css');
    $js  = (string) @file_get_contents(IW_DIR . '/assets/assets.js');

    echo '<!doctype html><html lang="zh-CN"><head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . iw_h($title) . ' · 程序安装向导</title>'
        . '<style>' . $css . '</style>'
        . '<script>try{var t=localStorage.getItem("theme");if(t==="light"||t==="dark"){document.documentElement.setAttribute("data-theme",t);}}catch(e){}</script>'
        . '</head><body' . ($active === 'welcome' ? ' class="iw-welcome-body"' : '') . '>'
        . $stepper
        . '<main class="iw-main">' . $body . '</main>'
        . '<script>' . $js . $extraJs . '</script>'
        . '</body></html>';
}

/* ---------------- 欢迎页（Step 1） ---------------- */

function iw_render_welcome(string $err = ''): void
{
    $errHtml = $err !== '' ? '<div class="iw-err">' . iw_h($err) . '</div>' : '';
    $body = '<form class="iw-hero" method="post" action="' . iw_h(IW_BASE . '?step=welcome') . '" id="welcomeForm">'
        . '<input type="hidden" name="act" value="begin">'
        . '<input type="hidden" name="from" value="welcome">'
        . '<div class="hero-inner">'
        . '<div class="hero-hi">你好</div>'
        . '<div class="hero-idiom">万象更新<span>· 恭候新程</span></div>'
        . '<p class="hero-desc">欢迎使用本站服务。程序安装向导正在为您准备运行环境，请稍候片刻。</p>'
        . $errHtml
        . '<div class="hero-progress"><i></i></div>'
        . '<button type="submit" class="iw-btn iw-btn-primary hero-begin" disabled>开始安装</button>'
        . '</div></form>';

    $js = "var _hb=document.getElementsByClassName('hero-begin')[0];"
        . "if(_hb){setTimeout(function(){_hb.disabled=false;_hb.classList.add('visible');},2400);}";
    iw_page('开始安装', 'welcome', $body, $js);
}

/* ---------------- 环境检测（Step 2） ---------------- */

function iw_render_env(array $items, bool $rewriteOk): void
{
    $rows = '';
    foreach ($items as $it) {
        $chip = $it['state'] === 'ok' ? 'ok' : ($it['state'] === 'danger' ? 'dag' : ($it['state'] === 'warn' ? 'warn' : 'pen'));
        $ctor = !empty($it['rewrite']) ? ' data-probe="1"' : '';
        $lbl  = $it['state'] === 'ok' ? '正常' : ($it['state'] === 'danger' ? '异常' : ($it['state'] === 'warn' ? '提醒' : '检测中…'));
        $rows .= '<tr class="iw-check-row"' . $ctor . '>'
            . '<td class="nm">' . iw_h($it['label']) . '</td>'
            . '<td class="st"><span class="iw-chip ' . $chip . '">' . iw_h($lbl) . '</span></td>'
            . '<td class="tx">' . iw_h($it['text']) . '</td></tr>';
    }

    $rules = htmlspecialchars(iw_rewrite_rules(), ENT_QUOTES, 'UTF-8');

    $body = '<div class="iw-card">'
        . '<h2>环境检测</h2>'
        . '<p class="sub">为确保系统稳定运行，请确认以下环境与配置项均已就绪。<br>其中「伪静态 / 友好路由」为可选优化项：未配置不影响程序与安装向导的正常使用。</p>'
        . '<table class="iw-check">' . $rows . '</table>'

        . '<div class="iw-rules" id="rulesPanel" style="display:none">'
        . '<h3>伪静态规则未启用（可选优化）</h3>'
        . '<p>以下规则<b>不属于安装必需项</b>：未配置时程序与向导均可正常运行。若需启用 <code>/d/</code>、<code>/stk/</code>、<code>mas.ps1</code> 等友好链接，请将下列 Nginx 规则加入站点配置（其他 Web 服务可参照等价 rewrite 规则），保存后点击「重新检测」。</p>'
        . '<div class="code-wrap"><button type="button" class="iw-btn iw-btn-ghost copy-btn" data-copy="rulesCode">复制规则</button>'
        . '<pre id="rulesCode">' . $rules . '</pre></div>'
        . '</div>'

        . '<form method="post" action="' . iw_h(IW_BASE . '?step=env') . '" id="envForm">'
        . '<input type="hidden" name="act" value="env_next">'
        . '<input type="hidden" name="rewrite_ok" id="rewriteOk" value="' . ($rewriteOk ? '1' : '0') . '">'
        . '<div class="btn-row">'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=welcome') . '\'">上一步</button>'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=env') . '\'">重新检测</button>'
        . '<button type="submit" class="iw-btn iw-btn-primary" id="envNext">下一步</button>'
        . '</div></form>'
        . '</div>';

    $js = <<<'JS'
(function(){
  function probe(){
    var row=document.querySelector('tr[data-probe="1"]');
    if(!row)return;
    var chip=row.querySelector('.iw-chip');
    var tx=row.querySelector('.tx');
    var okEl=document.getElementById('rewriteOk');
    function fail(){
      chip.className='iw-chip warn';chip.textContent='未配置';
      tx.textContent='伪静态规则未配置（可选优化项）；程序与安装向导可正常使用，仅在需要友好链接时添加下方规则';
      var p=document.getElementById('rulesPanel');if(p)p.style.display='block';
      if(okEl)okEl.value='0';
    }
    fetch('/__install__/ping',{cache:'no-store'})
      .then(function(r){if(!r.ok)throw new Error('http'+r.status);return r.json();})
      .then(function(j){
        if(j&&j.ok){chip.className='iw-chip ok';chip.textContent='正常';tx.textContent='伪静态规则已生效，友好路由可正常访问';if(okEl)okEl.value='1';}
        else{fail();}
      },fail);
  }
  if(document.readyState==='interactive'||document.readyState==='complete'){probe();}
  else{window.addEventListener('DOMContentLoaded',probe);}
})();
JS;
    iw_page('环境检测', 'env', $body, $js);
}

function iw_rewrite_rules(): string
{
    return <<<'RULES'
# ThinkPHP-compatible routing
# Route /d/{token} to download.php?token={token} - hides real file path
location ~ ^/d/([a-f0-9]+)$ {
    rewrite ^/d/([a-f0-9]+)$ /download.php?token=$1 last;
}

location = /mas.ps1 {
    rewrite ^ /mas_ps1.php last;
}

# Main site routing
location / {
    try_files $uri $uri/ /index.php?$args;
}
# Sticker images public service (data/jpg/sticker) -> stk.php
location ^~ /stk/ {
    rewrite ^/stk/(.+)$ /stk.php?f=$1 last;
}
RULES;
}

/* ---------------- 安全路径（Step 3） ---------------- */

function iw_render_path(string $err = '', string $value = 'admin', string $mode = 'default'): void
{
    $selDefault = $mode === 'default' ? ' checked' : '';
    $selCustom  = $mode === 'custom' ? ' checked' : '';
    $errHtml    = $err !== '' ? '<div class="iw-err">' . iw_h($err) . '</div>' : '';

    $body = '<div class="iw-card">'
        . '<h2>设置安全路径</h2>'
        . '<p class="sub">后台入口采用独立安全路径，可有效降低被扫描与爆破的风险。<br>若暂时不设置，将沿用默认后台路径 <b>/admin</b>，安装完成后仍可在后台随时修改。</p>'
        . $errHtml
        . '<form method="post" action="' . iw_h(IW_BASE . '?step=path') . '">'
        . '<input type="hidden" name="act" value="path_next">'

        . '<label class="iw-choice' . ($mode === 'default' ? ' checked' : '') . '">'
        . '<input type="radio" name="path_mode" value="default"' . $selDefault . '>'
        . '<span class="ck"></span><span class="tt">使用默认安全路径</span>'
        . '<span class="dt">后台地址：<code>/admin</code></span></label>'

        . '<label class="iw-choice' . ($mode === 'custom' ? ' checked' : '') . '">'
        . '<input type="radio" name="path_mode" value="custom"' . $selCustom . '>'
        . '<span class="ck"></span><span class="tt">自定义安全路径（推荐）</span>'
        . '<span class="dt">以字母开头，仅含字母、数字、下划线（_）、连字符（-），长度 2~32。</span>'
        . '<span class="io"><input type="text" name="admin_path" id="adminPathInput" value="' . iw_h($value) . '" maxlength="32" spellcheck="false" placeholder="输入安全路径，如 myadmin">'
        . '<button type="button" class="iw-btn iw-btn-ghost" id="genPath">随机生成</button></span></label>'

        . '<div class="btn-row">'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=env') . '\'">上一步</button>'
        . '<button type="submit" class="iw-btn iw-btn-primary">下一步</button>'
        . '</div>'
        . '</form></div>';

    $js = <<<'JS'
(function(){
  var gen=document.getElementById('genPath');
  var inp=document.getElementById('adminPathInput');
  var radios=document.querySelectorAll('input[name=path_mode]');
  if(gen&&inp){
    gen.addEventListener('click',function(){
      var a='abcdefghijklmnopqrstuvwxyz';var s=a.charAt(Math.floor(Math.random()*a.length));
      for(var i=0;i<7;i++){s+=a.charAt(Math.floor(Math.random()*a.length));}
      inp.value=s;
      var j;for(j=0;j<radios.length;j++){if(radios[j].value==='custom'){radios[j].checked=true;}}
    });
  }
  for(var k=0;k<radios.length;k++){
    radios[k].addEventListener('change',function(){
      var i;for(i=0;i<radios.length;i++){var el=radios[i].closest('.iw-choice');if(el)el.classList.remove('checked');}
      var cur=this.closest('.iw-choice');if(cur)cur.classList.add('checked');
      if(this.value==='custom'&&inp){inp.focus();}
    });
  }
})();
JS;
    iw_page('安全路径', 'path', $body, $js);
}

/* ---------------- 管理员账号（Step 4） ---------------- */

function iw_render_account(string $err = '', string $user = ''): void
{
    $errHtml = $err !== '' ? '<div class="iw-err">' . iw_h($err) . '</div>' : '';
    $body    = '<div class="iw-card">'
        . '<h2>设置后台管理员</h2>'
        . '<p class="sub">该账号用于登录后台管理，密码将以加密哈希方式安全存储（无法还原明文），请务必妥善保管。</p>'
        . $errHtml
        . '<form method="post" action="' . iw_h(IW_BASE . '?step=account') . '" autocomplete="off">'
        . '<input type="hidden" name="act" value="account_next">'

        . '<label class="iw-field"><span>管理员用户名</span>'
        . '<input type="text" name="admin_user" value="' . iw_h($user) . '" maxlength="32" spellcheck="false" autocomplete="username" placeholder="2~32 位，仅字母、数字、_、-"></label>'

        . '<label class="iw-field"><span>管理员密码</span>'
        . '<input type="password" name="admin_pass" id="pass1" maxlength="64" autocomplete="new-password" placeholder="至少 6 位，建议字母 + 数字组合"></label>'

        . '<label class="iw-field"><span>确认密码</span>'
        . '<input type="password" name="admin_pass2" id="pass2" maxlength="64" autocomplete="new-password" placeholder="再次输入密码"></label>'

        . '<div class="pw-hint" id="pwHint"></div>'

        . '<div class="btn-row">'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=path') . '\'">上一步</button>'
        . '<button type="submit" class="iw-btn iw-btn-primary">创建配置并继续</button>'
        . '</div>'
        . '</form></div>';

    $js = <<<'JS'
(function(){
  var p1=document.getElementById('pass1');
  var p2=document.getElementById('pass2');
  var hint=document.getElementById('pwHint');
  function upd(){
    var v1=p1?p1.value:'';var v2=p2?p2.value:'';var s='';
    if(v1.length>0){
      if(v1.length<6){s='密码过短，至少 6 位';}
      else if(/[a-zA-Z]/.test(v1)&&/[0-9]/.test(v1)){s='密码强度良好';}
      else{s='建议同时包含字母与数字';}
      if(v2.length>0){s+=(v1===v2?'（两次输入一致）':'（两次输入不一致）');}
    }
    if(hint){hint.textContent=s;hint.style.visibility=s?'visible':'hidden';}
  }
  if(p1&&p2){p1.addEventListener('input',upd);p2.addEventListener('input',upd);}
})();
JS;
    iw_page('管理员账号', 'account', $body, $js);
}

/* ---------------- 创建配置结果（Step 5） ---------------- */

function iw_render_created(): void
{
    $results = iw_sess_get('create_results', []);
    $rows = '';
    $hasErr = false;
    foreach ($results as $r) {
        $st = $r['status'] ?? 'err';
        if ($st === 'err') {
            $hasErr = true;
        }
        $rows .= '<div class="iw-created-row"><span class="iw-chip ' . ($st === 'ok' ? 'ok' : 'dag') . ' mini">'
            . ($st === 'ok' ? '&#10003;' : '&#33;') . '</span>'
            . '<span class="nm">' . iw_h($r['label'] ?? '') . '</span>'
            . '<span class="nt">' . iw_h($r['note'] ?? '') . '</span></div>';
    }

    $warn = $hasErr
        ? '<div class="iw-err">部分文件创建失败，请检查站点目录写入权限（chmod -R 755 或 www 用户 775）后重试。</div>'
        : '<div class="iw-ok">基础配置已全部创建完成。</div>';

    $body = '<div class="iw-card">'
        . '<h2>创建基础配置</h2>'
        . '<p class="sub">本站采用 JSON 无数据库架构：data/ 目录存放运行数据，app/ 目录为后台文件管理资源库，以下配置已自动生成：</p>'
        . $warn
        . '<div class="iw-list">' . $rows . '</div>'
        . '<form method="post" action="' . iw_h(IW_BASE . '?step=created') . '">'
        . '<input type="hidden" name="act" value="created_next">'
        . '<div class="btn-row">'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=account') . '\'">上一步</button>'
        . '<button type="submit" class="iw-btn iw-btn-primary">进入下一步</button>'
        . '</div></form></div>';

    iw_page('创建配置', 'created', $body);
}

/* ---------------- 网站目录 / 完成（Step 6） ---------------- */

function iw_render_finish(): void
{
    $path = (string) iw_sess_get('admin_path', 'admin');
    $user = (string) iw_sess_get('admin_user', '');

    $body = '<div class="iw-card">'
        . '<h2>完成安装</h2>'
        . '<p class="sub">安装配置已就绪。点击「完成安装」写入完成标记，即可进入系统。</p>'
        . '<ol class="iw-guide">'
        . '<li><b>完成安装</b>：点击下方按钮写入安装完成标记，访问首页即进入系统。</li>'
        . '<li><b>（推荐）收紧运行目录</b>：进入网站管理面板，将本站「网站目录 → 运行目录」改为 <code>/public</code>，仅对外暴露公共入口，更安全。</li>'
        . '<li><b>保存生效</b>：保存后配置立即生效，无需重启 Web 服务。若先前已切换运行目录，也不影响安装。</li>'
        . '</ol>'
        . '<div class="iw-tip">无论运行目录是「站点根」还是 <code>/public</code>，安装向导都可通过 <code>/install/</code> 访问；请勿删除 <code>install/</code> 目录（重装时仍需使用）。</div>'
        . '<div class="iw-sum">'
        . '<div><span>后台路径</span><code>/' . iw_h($path) . '</code></div>'
        . '<div><span>站长账号</span><code>' . iw_h($user) . '</code></div>'
        . '<div><span>运行目录</span><code>站点根（或 /public）</code></div>'
        . '</div>'
        . '<form method="post" action="' . iw_h(IW_BASE . '?step=finish') . '">'
        . '<input type="hidden" name="act" value="finish">'
        . '<div class="btn-row">'
        . '<button type="button" class="iw-btn iw-btn-ghost" onclick="location.href=\'' . iw_h(IW_BASE . '?step=created') . '\'">上一步</button>'
        . '<button type="submit" class="iw-btn iw-btn-primary iw-btn-lg">完成安装 · 体验系统</button>'
        . '</div></form></div>';

    iw_page('完成安装', 'finish', $body);
}

/* ---------------- 已安装 / 错误 ---------------- */

function iw_render_installed(): void
{
    $body = '<div class="iw-card iw-installed">'
        . '<div class="ok-icon">&#10003;</div>'
        . '<h2>系统已安装完成</h2>'
        . '<p class="sub">当前站点已完成初始化配置，可直接访问系统首页。</p>'
        . '<div class="btn-row">'
        . '<a class="iw-btn iw-btn-primary" href="/">进入系统首页</a>'
        . '</div>'
        . '<p class="micro">如需重装系统，请删除 <code>data/Configuration/install.lock</code> 后再访问根目录。</p>'
        . '</div>';

    iw_page('已安装', 'welcome', $body);
}

function iw_render_error(string $msg): void
{
    $body = '<div class="iw-card">'
        . '<h2>操作失败</h2>'
        . '<div class="iw-err">' . iw_h($msg) . '</div>'
        . '<div class="btn-row"><a class="iw-btn iw-btn-ghost" href="javascript:history.back()">返回</a></div>'
        . '</div>';
    iw_page('出错', 'env', $body);
}
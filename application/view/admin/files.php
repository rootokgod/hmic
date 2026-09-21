<?php
/**
 * 文件管理（页面内容，布局由 partials/head.php + foot.php 提供）
 * @var string $dir       当前相对路径（相对 app/）
 * @var string $parent    上级相对路径（'' 表示在根）
 * @var array  $crumbs    面包屑 [['name'=>..,'dir'=>..], ...]
 * @var array  $entries   当前目录条目
 * @var array  $top       顶级目录列表（筛选/快捷跳转）
 * @var int    $dirCount  当前目录子目录数
 * @var int    $fileCount 当前目录文件数
 * @var string $dirBytes  当前目录内容合计大小
 */
include __DIR__ . '/partials/head.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$enter = static function (string $d): string {
    return '/admin/files' . ($d === '' ? '' : '?dir=' . rawurlencode($d));
};
$last = count($crumbs) - 1;

$folderSvg = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
$fileSvg   = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';
$icoDl = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>';
$icoRn = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>';
$icoMe = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h10"/><path d="M18 6h2"/><path d="M4 12h2"/><path d="M10 12h10"/><path d="M4 18h10"/><path d="M18 18h2"/><circle cx="15" cy="6" r="2"/><circle cx="7" cy="12" r="2"/><circle cx="15" cy="18" r="2"/></svg>';
$icoX  = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>';
$icoDel = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
$icoRef = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>';
$icoNewDir = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M10 12h4"/><path d="M12 10v4"/></svg>';
$icoLock = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
?>
  <div class="panel">
    <h2>文件管理</h2>
    <div class="body">
      <div class="fm-bar">
        <div class="fm-bar-row">
          <div class="fm-bar-acts">
            <button type="button" class="fm-btn fm-btn-p" id="fmUpBtn" title="上传文件到当前目录">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>上传
            </button>
            <button type="button" class="fm-btn" id="fmMkdirBtn" title="新建目录">
              <?= $icoNewDir ?>新建目录
            </button>
            <button type="button" class="fm-btn" id="fmReloadBtn" title="刷新当前目录">
              <?= $icoRef ?>刷新
            </button>
            <div class="search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
              <input id="fmq" type="text" placeholder="搜索当前目录" autocomplete="off">
            </div>
            <select class="theme-select" id="fmFilter" title="筛选目录（快捷跳转）">
              <option value=""<?= $dir === '' ? ' selected' : '' ?>>筛选目录</option>
              <?php foreach ($top as $t): ?>
              <option value="<?= $h($t['name']) ?>"<?= $dir === $t['name'] ? ' selected' : '' ?>><?= $h($t['dname']) ?></option>
              <?php endforeach; ?>
            </select>
            <a class="fm-btn" href="/admin/sticker" title="跳转到贴纸图库"><?= $icoRef ?>贴纸图库</a>
          </div>
          <span class="fm-count muted"><?= $dirCount ?> 个目录 · <?= $fileCount ?> 个文件 · 本层内容合计 <?= $dirBytes ?></span>
        </div>
        <div class="fm-bulk" id="fmBulk" style="display:none">
          <label class="bb-lab"><input type="checkbox" id="bbAll"> 全选</label>
          <button type="button" class="bb-btn" id="bbInv">反选</button>
          <span class="bb-count" id="bbCount">已选 0 项</span>
          <span class="bb-space"></span>
          <button type="button" class="bb-btn bb-btn-p" data-op="bulk-dl"><?= $icoDl ?>批量打包下载</button>
          <button type="button" class="bb-btn" data-op="bulk-rn">批量重命名</button>
          <button type="button" class="bb-btn" data-op="bulk-me">批量参数</button>
          <button type="button" class="bb-btn bb-del" data-op="bulk-del"><?= $icoDel ?>删除</button>
          <button type="button" class="bb-btn bb-clearselect" id="bbClear">取消选择</button>
        </div>
      </div>
      <div class="fm-status" id="fmStatus" style="display:none"></div>
      <table>
        <tr>
          <th style="width:34px"><input type="checkbox" id="fmAll"></th>
          <th>名称</th><th style="width:92px;text-align:center">密码保护</th><th style="width:76px;text-align:center">首页显示</th><th>类型</th><th>大小</th><th>修改时间</th><th class="fm-ops">操作</th>
        </tr>
        <?php if ($dir !== ''): ?>
        <tr class="fm-up">
          <td></td>
          <td><a class="fm-link" href="<?= $h($enter($parent)) ?>"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>返回上级</a></td>
          <td></td><td></td><td></td><td></td><td></td>
        </tr>
        <?php endif; ?>
        <?php if (empty($entries)): ?>
        <tr class="fm-empty"><td colspan="7" class="empty">空目录</td></tr>
        <?php else: foreach ($entries as $e): $durl = $e['dir'] ? $enter($dir === '' ? $e['name'] : $dir . '/' . $e['name']) : ''; ?>
        <tr class="fm-row<?= $e['dir'] ? ' fm-dirrow' : '' ?>" data-rel="<?= $h($e['rel']) ?>" data-name="<?= $h($e['name']) ?>" data-dname="<?= $h($e['dname']) ?>"
            data-arch="<?= $h($e['arch']) ?>" data-os="<?= $h($e['os']) ?>" data-zip="<?= $e['zip'] ? '1' : '0' ?>"
            data-hidden="<?= !empty($e['hidden']) ? '1' : '0' ?>"
            data-lock="<?= !empty($e['lock']) ? '1' : '0' ?>"
            data-lockby="<?= $h((string)($e['lockby'] ?? '')) ?>"
            data-needle="<?= $h(strtolower($e['dname'] . ' ' . $e['name'])) ?>"<?= $durl !== '' ? ' data-open="' . $h($durl) . '"' : '' ?>>
          <td><input type="checkbox" class="row-chk" data-rel="<?= $h($e['rel']) ?>"></td>
          <td style="max-width:460px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?php if ($e['dir']): ?>
              <span class="fm-ic"><?= $folderSvg ?></span>
              <a class="fm-link fm-dir" href="<?= $h($durl) ?>" title="<?= $h($e['name']) ?>"><?= $h($e['dname']) ?></a>
            <?php else: ?>
              <span class="fm-ic fm-ic-file"><?= $fileSvg ?></span>
              <span class="fm-file" title="<?= $h($e['name']) ?>"><?= $h($e['dname']) ?></span>
            <?php endif; ?>
            <?php
            $meta = [];
            if ($e['arch'] !== '') { $meta[] = $e['arch']; }
            if ($e['os'] !== '') { $meta[] = $e['os']; }
            if ($meta): ?><span class="fm-meta"><?= $h(implode(' · ', $meta)) ?></span><?php endif; ?>
          </td>
          <td style="text-align:center">
            <span class="lock-ic<?= !empty($e['lock']) ? ' on' : '' ?>" title="<?= !empty($e['lock']) ? ('受密码保护' . ($e['lockby'] !== $e['rel'] ? '（父级：' . $e['lockby'] . '）' : '')) : '未设置密码' ?>"><?= !empty($e['lock']) ? $icoLock : '<span class="lock-off">-</span>' ?></span>
          </td>
          <td style="text-align:center">
            <label class="sw<?= !empty($e['hidden']) ? '' : ' sw-on' ?>" data-rel="<?= $h($e['rel']) ?>" title="<?= !empty($e['hidden']) ? '首页已隐藏，点击恢复显示' : '首页显示中，点击隐藏' ?>">
              <input type="checkbox" class="sw-chk" <?= !empty($e['hidden']) ? '' : 'checked' ?> data-rel="<?= $h($e['rel']) ?>">
              <span class="sw-slider"></span>
            </label>
          </td>
          <td><?= $e['dir'] ? (($e['zip'] ? '<span class="tag">打包目录</span>' : '<span class="tag">目录</span>')) : '<span class="tag" style="background:var(--th)">文件</span>' ?></td>
          <td class="mono"><?= $h($e['size_h']) ?></td>
          <td class="muted"><?= $h($e['mtime']) ?></td>
          <td class="fm-ops">
            <a class="op-btn" data-op="dl" href="/<?= $h($e['link']) ?>" title="下载"><?= $icoDl ?>下载</a>
            <button type="button" class="op-btn" data-op="lock" title="设置/清除密码保护"><?= $icoLock ?>密码保护</button>
            <button type="button" class="op-btn" data-op="rn" title="修改名称"><?= $icoRn ?>重命名</button>
            <button type="button" class="op-btn" data-op="me" title="修改参数（架构/系统/是否zip/首页显示）"><?= $icoMe ?>参数</button>
            <button type="button" class="op-btn op-del" data-op="del" title="删除"><?= $icoDel ?>删除</button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </table>
    </div>
  </div>

  <div class="modal-mask" id="mdlUpload">
    <div class="modal">
      <h3>上传文件到当前目录</h3>
      <button type="button" class="up-pick" id="upPick">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
        <span>点击选择文件（可多选）</span>
      </button>
      <input type="file" id="upSel" multiple style="display:none">
      <ul class="up-list" id="upList"></ul>
      <div class="up-prog-wrap" id="upProgWrap" style="display:none">
        <div class="up-prog"><i id="upProgBar"></i></div>
        <div class="up-prog-txt" id="upProgTxt"></div>
      </div>
      <div class="up-res" id="upRes" style="display:none"></div>
      <div class="acts" style="margin-top:16px">
        <button type="button" class="mbtn" data-close>取消</button>
        <button type="button" class="mbtn primary" id="upOk">开始上传</button>
      </div>
    </div>
  </div>

  <div class="modal-mask" id="mdlRename">
    <div class="modal">
      <h3 id="mdlRenameTitle">修改名称</h3>
      <div class="frm">
        <div class="fld"><div class="fl">新名称</div><input type="text" id="rnNew" class="txt" placeholder="请输入新名称"></div>
        <div class="fld" id="rnPrf" style="display:none"><div class="fl">前缀</div><input type="text" id="rnPrefix" class="txt" placeholder="添加到名称前面"></div>
        <div class="fld" id="rnSuf" style="display:none"><div class="fl">后缀</div><input type="text" id="rnSuffix" class="txt" placeholder="添加到名称后面"></div>
      </div>
      <div class="acts"><button type="button" class="mbtn" data-close>取消</button><button type="button" class="mbtn primary" id="rnOk">确定</button></div>
    </div>
  </div>

  <div class="modal-mask" id="mdlMeta">
    <div class="modal">
      <h3 id="mdlMetaTitle">修改参数</h3>
      <div class="frm">
        <div class="fld"><div class="fl">架构</div>
          <select id="meArch" class="theme-select" style="width:100%">
            <option value="">自动（按名称）</option>
            <option value="x64">x64</option><option value="x86">x86</option>
            <option value="arm64">arm64</option><option value="arm">arm</option>
          </select></div>
        <div class="fld"><div class="fl">系统</div>
          <select id="meOs" class="theme-select" style="width:100%">
            <option value="">自动（按名称）</option>
            <option value="Windows">Windows</option><option value="Linux">Linux</option>
            <option value="macOS">macOS</option>
          </select></div>
        <div class="fld"><div class="fl">是否 ZIP 打包</div>
          <select id="meZip" class="theme-select" style="width:100%">
            <option value="">自动（按名称）</option>
            <option value="1">是（支持下载）</option><option value="0">否（仅进入）</option>
          </select></div>
        <div class="fld"><div class="fl">首页显示</div>
          <select id="meHidden" class="theme-select" style="width:100%">
            <option value="">不修改（保持现状）</option>
            <option value="0">显示（默认）</option>
            <option value="1">隐藏（首页不显示）</option>
          </select></div>
        <div class="fld" id="meTarget"><div class="fl muted" style="font-weight:400;font-size:12.5px">将应用到全部所选条目</div></div>
      </div>
      <div class="acts"><button type="button" class="mbtn" data-close>取消</button><button type="button" class="mbtn primary" id="meOk">确定</button></div>
    </div>
  </div>

  <div class="modal-mask" id="mdlLock">
    <div class="modal">
      <h3 id="mdlLockTitle">设置密码保护</h3>
      <div class="frm">
        <div class="fld"><div class="fl">新密码</div><input type="password" id="lkNew" class="txt" placeholder="请输入访问密码（至少 4 位）" autocomplete="new-password"></div>
        <div class="fld"><div class="fl">确认密码</div><input type="password" id="lkNew2" class="txt" placeholder="请再次输入密码" autocomplete="new-password"></div>
        <div class="fld" id="lkHas"><div class="fl muted" style="font-weight:400;font-size:12.5px"></div></div>
        <div class="fld"><div class="fl">说明</div><div class="muted" style="font-size:12.5px;line-height:1.7">设置后，访客在前端<u>进入该目录/下载该条目</u>时均需输入密码。留空提交可清除已设置的密码。对目录设锁后，其<u>全部子内容</u>一并受保护。</div></div>
      </div>
      <div class="acts">
        <button type="button" class="mbtn" data-close>取消</button>
        <button type="button" class="mbtn danger" id="lkClear" style="display:none">清除密码</button>
        <button type="button" class="mbtn primary" id="lkOk">保存密码</button>
      </div>
    </div>
  </div>

  <div class="modal-mask" id="mdlMkdir">
    <div class="modal">
      <h3>新建目录</h3>
      <div class="frm">
        <div class="fld"><div class="fl">目录名</div><input type="text" id="mkName" class="txt" placeholder="请输入目录名称"></div>
        <div class="fld" id="mkOptsWrap"><div class="fl">创建参数</div>
          <div class="fld3">
            <select id="mkArch" class="theme-select">
              <option value="">架构自动</option>
              <option value="x64">x64</option><option value="x86">x86</option>
              <option value="arm64">arm64</option><option value="arm">arm</option>
            </select>
            <select id="mkOs" class="theme-select">
              <option value="">系统自动</option>
              <option value="Windows">Windows</option><option value="Linux">Linux</option>
              <option value="macOS">macOS</option>
            </select>
            <select id="mkZip" class="theme-select">
              <option value="">ZIP 自动</option>
              <option value="1">ZIP 打包</option><option value="0">普通目录</option>
            </select>
            <select id="mkHidden" class="theme-select">
              <option value="">首页显示</option>
              <option value="0">显示</option><option value="1">隐藏</option>
            </select>
          </div>
          <div class="mk-hint muted">留空即按名称自动推断（默认可下载/进入规则不变）</div>
        </div>
      </div>
      <div class="acts"><button type="button" class="mbtn" data-close>取消</button><button type="button" class="mbtn primary" id="mkOk">创建</button></div>
    </div>
  </div>

  <div class="modal-mask" id="mdlConfirm">
    <div class="modal sm">
      <h3 id="cfTitle">确认执行</h3>
      <div class="cf-body" id="cfBody"></div>
      <div class="acts"><button type="button" class="mbtn" data-close>取消</button><button type="button" class="mbtn danger" id="cfOk">确定</button></div>
    </div>
  </div>

<style>
  .fm-bar{display:flex;flex-direction:column;gap:8px;padding:12px 17px;border-bottom:1px solid var(--rowline)}
  .fm-bar-row{display:flex;align-items:center;flex-wrap:wrap;gap:10px;min-width:0}
  .fm-bar-acts{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0}
  .fm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;
    border:1px solid var(--line);background:var(--card);color:var(--ink2);font-family:inherit;
    transition:background .15s,border-color .15s,color .15s;-webkit-user-select:none;user-select:none}
  .fm-btn:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .fm-btn-p{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .fm-btn-p:hover{background:var(--btn);filter:brightness(.92);color:var(--btn-ink)}
  .fm-btn.spinning svg{animation:fmspin .6s linear infinite}
  @keyframes fmspin{to{transform:rotate(360deg)}}
  .search{position:relative}
  .search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--ink3);pointer-events:none}
  .search input{width:230px;max-width:42vw;border:1px solid var(--line);border-radius:8px;padding:8px 12px 8px 34px;font-size:13px;
    outline:none;background:var(--card);color:var(--ink);transition:border-color .15s}
  .search input::placeholder{color:var(--ink3)}
  .search input:focus{border-color:var(--ink3)}
  .fm-count{margin-left:auto;font-size:12.5px}
  /* 批量操作行（选中后显示，位于顶部栏内） */
  .fm-bulk{display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-top:1px dashed var(--line);padding-top:10px;
    font-size:12.5px}
  .bb-lab{display:inline-flex;align-items:center;gap:6px;color:var(--ink2);cursor:pointer}
  .bb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;cursor:pointer;
    border:1px solid var(--line);background:var(--card);color:var(--ink2);font-family:inherit;font-size:12.5px;
    transition:background .15s,border-color .15s,color .15s}
  .bb-btn:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .bb-btn-p{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .bb-btn-p:hover{background:var(--btn);filter:brightness(.92);color:var(--btn-ink)}
  .bb-clear{color:var(--ink2)}
  .bb-clear:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .bb-clearselect{color:var(--ink2)}
  .bb-count{color:var(--ink3);font-variant-numeric:tabular-nums}
  .bb-space{flex:1}
  .fm-status{border-bottom:1px solid var(--rowline);padding:9px 17px;font-size:12.5px;color:var(--ok)}
  .fm-status.err{color:var(--err)}
  .fm-ic{display:inline-flex;vertical-align:-3px;margin-right:6px;color:var(--ink2)}
  .lock-ic{display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;color:var(--ink3)}
  .lock-ic.on{color:#eab308}
  .lock-ic .lock-off{font-size:12px}
  .lock-ic svg{display:block}
  .fm-ic-file{color:var(--ink3)}
  .fm-link{color:var(--ink2);text-decoration:none}
  .fm-link:hover{color:var(--ink);text-decoration:underline}
  .fm-dir{font-weight:500}
  .fm-file{color:var(--ink);font-family:ui-monospace,Consolas,monospace;font-size:12.5px}
  .fm-meta{display:inline-block;margin-left:8px;padding:1px 7px;border-radius:6px;font-size:11px;
    background:var(--tag-bg);color:var(--tag-ink);vertical-align:1px}
  .fm-ops{white-space:nowrap}
  .op-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:12px;cursor:pointer;
    border:1px solid var(--line);background:var(--card);color:var(--ink2);font-family:inherit;margin-left:5px;
    transition:background .15s,border-color .15s,color .15s;text-decoration:none}
  .op-btn:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .op-del{color:var(--err)}
  .op-del:hover{background:var(--hover);border-color:var(--err);color:var(--err)}
  .sw{display:inline-block;position:relative;width:38px;height:20px;vertical-align:middle;cursor:pointer;user-select:none}
  .sw .sw-chk{position:absolute;opacity:0;width:0;height:0;margin:0}
  .sw-slider{position:absolute;top:0;left:0;right:0;bottom:0;border-radius:20px;background:var(--hover,#ddd);transition:.2s;display:block}
  .sw-slider:before{content:"";position:absolute;left:2px;top:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:.2s;display:block}
  .sw.sw-on .sw-slider{background:#34c759}
  .sw.sw-on .sw-slider:before{left:20px}
  .sw .sw-chk:disabled + .sw-slider{opacity:.5;cursor:not-allowed}
  .fld3{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
  .fld3 .theme-select{width:100%}
  .mk-hint{font-size:12px;color:var(--muted,#888);margin-top:6px}
  .bb-del{color:var(--err)}
  /* 目录整行可点击 */
  tr.fm-dirrow{cursor:pointer;transition:background .12s}
  tr.fm-dirrow:hover td{background:var(--hover)}
  tr.fm-dirrow:hover td:first-child{background:var(--hover)}
  /* 返回上级：使用页面底色，与表头（--th）区分开 */
  tr.fm-up td{background:var(--bg);padding-top:7px;padding-bottom:7px}
  tr.fm-up .fm-link{color:var(--ink3);font-size:12.5px}
  tr.fm-up .fm-link:hover{color:var(--ink)}
  tr.fm-row.off td{opacity:.35}
  .modal .frm{display:flex;flex-direction:column;gap:14px;margin-bottom:18px}
  .modal .fld .fl{font-size:13px;color:var(--ink3);margin-bottom:6px}
  .modal .txt{width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:var(--card);
    color:var(--ink);font-size:13px;font-family:inherit;outline:none;transition:border-color .15s}
  .modal .txt:focus{border-color:var(--ink3)}
  /* 上传弹窗 */
  .up-pick{display:flex;flex-direction:column;align-items:center;gap:9px;width:100%;padding:26px 12px;border-radius:10px;
    border:1.5px dashed var(--line);background:var(--bg);color:var(--ink2);cursor:pointer;font:inherit;font-size:13px;
    transition:border-color .15s,color .15s}
  .up-pick:hover{border-color:var(--ink3);color:var(--ink)}
  .up-list{list-style:none;margin:14px 0 0;padding:0;max-height:34vh;overflow:auto}
  .up-list li{display:flex;align-items:center;gap:10px;padding:8px 2px;border-bottom:1px dashed var(--line);font-size:13px}
  .up-list li:last-child{border-bottom:0}
  .up-list .nm{flex:1;color:var(--ink2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .up-list .sz{flex:none;color:var(--ink3);font-variant-numeric:tabular-nums}
  .up-list .rm{flex:none;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
    border:none;background:none;color:var(--ink3);cursor:pointer;border-radius:5px;padding:0}
  .up-list .rm:hover{background:var(--hover);color:var(--err)}
  .up-prog-wrap{margin-top:14px}
  .up-prog{height:8px;border-radius:999px;background:var(--line);overflow:hidden}
  .up-prog i{display:block;height:100%;width:0;background:var(--ok);border-radius:999px;transition:width .12s linear}
  .up-prog i.done{animation:upPulse 1.1s ease-in-out infinite}
  @keyframes upPulse{0%,100%{opacity:1}50%{opacity:.5}}
  .up-prog-txt{font-size:12px;color:var(--ink3);margin-top:6px;font-variant-numeric:tabular-nums}
  .up-res{margin-top:14px;font-size:12.5px;max-height:22vh;overflow:auto}
  .up-res-t{color:var(--ok);font-weight:600;margin-bottom:6px}
  .up-res-t.err{color:var(--err)}
  .up-res-fail{color:var(--err);font-size:12px;line-height:1.8;word-break:break-all}
</style>

<script>
(function () {
  var DIR = <?= json_encode($dir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var sel = {};
  var q = '';
  var upFiles = [];

  function $(id) { return document.getElementById(id); }

  function fmtB(n) {
    n = Number(n);
    if (!isFinite(n) || n <= 0) return '--';
    var u = ['B','KB','MB','GB','TB'];
    var i = 0, v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return (i === 0 ? v : v.toFixed(2)) + ' ' + u[i];
  }

  function toast(t1, t2, err) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.appendChild(wrap); }
    var t = document.createElement('div');
    t.className = 'toast' + (err ? ' err' : '');
    t.innerHTML = '<div class="t1"></div>';
    t.querySelector('.t1').textContent = '系统提示';
    t.innerHTML += '<div class="toast-sep"></div><div class="t2"></div>';
    t.querySelector('.t2').textContent = t1;
    if (t2) {
      t.innerHTML += '<div class="t3"></div>';
      t.querySelector('.t3').textContent = t2;
    }
    wrap.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 400);
    }, 4200);
  }

  function status(msg, err) {
    var s = $('fmStatus');
    s.textContent = msg;
    s.className = 'fm-status' + (err ? ' err' : '');
    s.style.display = msg ? '' : 'none';
  }

  function visibleRows() {
    var out = [];
    document.querySelectorAll('tr.fm-row').forEach(function (tr) {
      if (tr.style.display !== 'none') out.push(tr);
    });
    return out;
  }

  function refresh() {
    var n = 0;
    for (var k in sel) if (sel[k]) n++;
    $('bbCount').textContent = '\u5df2\u9009 ' + n + ' \u9879';
    $('fmBulk').style.display = n ? '' : 'none';
    $('fmAll').checked = visibleRows().length > 0 && visibleRows().every(function (tr) {
      return sel[tr.getAttribute('data-rel')];
    });
    document.querySelectorAll('tr.fm-row').forEach(function (tr) {
      tr.classList.toggle('off', !!sel[tr.getAttribute('data-rel')]);
    });
  }

  function rels() {
    return Object.keys(sel).filter(function (k) { return sel[k]; });
  }

  function reload() { location.reload(); }

  document.getElementById('fmReloadBtn').addEventListener('click', function () {
    this.classList.add('spinning');
    var me = this;
    setTimeout(function () { me.classList.remove('spinning'); }, 600);
    reload();
  });

  function api(url, data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: body }).then(function (r) { return r.json(); });
  }

  /* ---- 新建目录 ---- */
  $('fmMkdirBtn').addEventListener('click', function () {
    $('mkName').value = '';
    $('mkArch').value = '';
    $('mkOs').value = '';
    $('mkZip').value = '';
    $('mkHidden').value = '';
    openMask('mdlMkdir');
    setTimeout(function () { $('mkName').focus(); }, 60);
  });
  $('mkOk').addEventListener('click', function () {
    var name = $('mkName').value.trim();
    if (!name) { toast('请输入目录名', '', true); return; }
    $('mkOk').disabled = true;
    api('/admin/api/files/mkdir', {
      dir: DIR,
      name: name,
      arch: $('mkArch').value,
      os: $('mkOs').value,
      zip: $('mkZip').value,
      hidden: $('mkHidden').value
    }).then(function (r) {
      $('mkOk').disabled = false;
      if (r && r.ok) { closeMask('mdlMkdir'); toast((r.msg || '\u76ee\u5f55\u5df2\u521b\u5efa')); reload(); }
      else toast('创建失败', (r && r.msg) || '', true);
    });
  });

  /* ---- 首页显示滑块 ---- */
  document.querySelectorAll('.sw-chk').forEach(function (c) {
    c.addEventListener('change', function () {
      var rel = this.getAttribute('data-rel');
      var lb = this.closest('label.sw');
      if (lb) lb.classList.toggle('sw-on', this.checked);
      this.disabled = true;
      api('/admin/api/files/meta', { items: JSON.stringify([rel]), hidden: this.checked ? '0' : '1' }).then(function (r) {
        c.disabled = false;
        if (r && r.ok) {
          var row = c.closest('tr');
          if (row) row.setAttribute('data-hidden', c.checked ? '0' : '1');
          toast('已更新首页显示状态', (r.msg || ''));
        } else {
          c.checked = !c.checked;
          var lbc = c.closest('label.sw');
          if (lbc) lbc.classList.toggle('sw-on', c.checked);
          toast('更新失败', (r && r.msg) || '', true);
        }
      });
    });
  });

  /* ---- 勾选 ---- */
  document.querySelectorAll('.row-chk').forEach(function (c) {
    c.addEventListener('change', function () {
      var rel = this.getAttribute('data-rel');
      sel[rel] = this.checked ? 1 : 0;
      refresh();
    });
  });

  $('fmAll').addEventListener('change', function () {
    var on = this.checked;
    visibleRows().forEach(function (tr) {
      var rel = tr.getAttribute('data-rel');
      sel[rel] = on ? 1 : 0;
      var cb = tr.querySelector('.row-chk');
      if (cb) cb.checked = on;
    });
    refresh();
  });
  $('bbAll').addEventListener('change', function () {
    var cb = $('fmAll');
    cb.checked = this.checked;
    cb.dispatchEvent(new Event('change'));
  });
  $('bbInv').addEventListener('click', function () {
    visibleRows().forEach(function (tr) {
      var rel = tr.getAttribute('data-rel');
      sel[rel] = sel[rel] ? 0 : 1;
      var cb = tr.querySelector('.row-chk');
      if (cb) cb.checked = cb.checked ? false : true;
    });
    refresh();
  });
  $('bbClear').addEventListener('click', function () {
    sel = {};
    document.querySelectorAll('.row-chk').forEach(function (c) { c.checked = false; });
    refresh();
    toast('\u5df2\u53d6\u6d88\u9009\u62e9');
  });

  /* ---- 行点击：多选时切换勾选；非多选时目录进入 ---- */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr.fm-row');
    if (!tr) return;
    if (e.target.closest('.op-btn') || e.target.closest('.sw') || e.target.closest('input') || e.target.closest('a') || e.target.closest('select')) return;
    var n = 0;
    for (var k in sel) if (sel[k]) n++;
    if (n > 0) {
      var cb = tr.querySelector('.row-chk');
      if (cb) {
        var rel = cb.getAttribute('data-rel');
        sel[rel] = (rel && sel[rel]) ? 0 : 1;
        cb.checked = !!sel[rel];
        refresh();
      }
      return;
    }
    var url = tr.getAttribute('data-open');
    if (url) location.href = url;
  });

  /* ---- 搜索（客户端过滤当前目录） ---- */
  $('fmq').addEventListener('input', function () {
    q = this.value.trim().toLowerCase();
    document.querySelectorAll('tr.fm-row').forEach(function (tr) {
      var needle = tr.getAttribute('data-needle') || '';
      tr.style.display = q ? (needle.indexOf(q) === -1 ? 'none' : '') : '';
    });
  });

  /* ---- 筛选目录（快捷跳转） ---- */
  $('fmFilter').addEventListener('change', function () {
    location.href = '/admin/files' + (this.value ? '?dir=' + encodeURIComponent(this.value) : '');
  });

  /* ---- 弹窗开关 ---- */
  function openMask(id) { $(id).classList.add('open'); }
  function closeMask(id) { $(id).classList.remove('open'); }
  function confirmBox(msg, okText, cb) {
    $('cfBody').textContent = msg || '';
    $('cfOk').textContent = okText || '\u786e\u5b9a';
    var ok = $('cfOk');
    ok.onclick = function () { ok.onclick = null; closeMask('mdlConfirm'); if (cb) cb(); };
    openMask('mdlConfirm');
  }
  document.querySelectorAll('.modal-mask').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) closeMask(m.id); });
    m.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { closeMask(m.id); }); });
  });

  /* ---- 上传弹窗 ---- */
  function renderUp() {
    var ul = $('upList');
    ul.innerHTML = '';
    upFiles.forEach(function (f, i) {
      var li = document.createElement('li');
      var nm = document.createElement('span');
      nm.className = 'nm';
      nm.textContent = f.name;
      var sz = document.createElement('span');
      sz.className = 'sz';
      sz.textContent = fmtB(f.size);
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'rm';
      rm.title = '\u79fb\u9664';
      rm.innerHTML = '<?= $icoX ?>';
      rm.addEventListener('click', function () { upFiles.splice(i, 1); renderUp(); });
      li.appendChild(nm); li.appendChild(sz); li.appendChild(rm);
      ul.appendChild(li);
    });
    var ok = $('upOk');
    ok.disabled = upFiles.length === 0;
    ok.innerHTML = '\u5f00\u59cb\u4e0a\u4f20' + (upFiles.length ? ' (' + upFiles.length + ')' : '');
    $('upProgWrap').style.display = 'none';
    $('upProgBar').style.width = '0%';
    $('upRes').style.display = 'none';
  }
  function fmtB(n) {
    if (n < 1024) return n + ' B';
    var u = ['KB', 'MB', 'GB'], i = -1;
    do { n /= 1024; i++; } while (n >= 1024 && i < u.length - 1);
    return n.toFixed(1) + ' ' + u[i];
  }
  function escHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  $('fmUpBtn').addEventListener('click', function () {
    upFiles = [];
    renderUp();
    openMask('mdlUpload');
  });
  $('upPick').addEventListener('click', function () { $('upSel').click(); });
  $('upSel').addEventListener('change', function () {
    Array.prototype.forEach.call(this.files, function (f) {
      var dup = upFiles.some(function (x) { return x.name === f.name && x.size === f.size; });
      if (!dup) upFiles.push(f);
    });
    this.value = '';
    renderUp();
  });
  var upBusy = false;
  $('upOk').addEventListener('click', function () {
    if (upBusy || !upFiles.length) return;
    var okBtn = $('upOk');
    var pickBtn = $('upPick');
    var total = upFiles.reduce(function (s, f) { return s + f.size; }, 0);
    upBusy = true;
    okBtn.disabled = true; okBtn.innerHTML = '\u4e0a\u4f20\u4e2d...';
    pickBtn.disabled = true;
    $('upProgWrap').style.display = '';
    $('upRes').style.display = 'none';
    $('upProgBar').style.width = '0%';
    $('upProgTxt').textContent = '0 B / ' + fmtB(total) + ' \u00b7 0%';
    var fd = new FormData();
    fd.append('dir', DIR);
    upFiles.forEach(function (f) { fd.append('files[]', f); });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/admin/api/files/upload');
    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable) return;
      var pct = Math.round(ev.loaded / ev.total * 100);
      $('upProgBar').style.width = pct + '%';
      if (pct >= 100) {
        $('upProgBar').classList.add('done');
        $('upProgTxt').textContent = '\u5df2\u4e0a\u4f20 100%\uff0c\u670d\u52a1\u5668\u6b63\u5728\u5199\u5165\u76d8\uff0c\u8bf7\u7a0d\u5019...';
      } else {
        $('upProgBar').classList.remove('done');
        $('upProgTxt').textContent = fmtB(ev.loaded) + ' / ' + fmtB(ev.total) + ' \u00b7 ' + pct + '%';
      }
    };
    xhr.onload = function () {
      upBusy = false;
      okBtn.disabled = false; okBtn.innerHTML = '\u5f00\u59cb\u4e0a\u4f20';
      pickBtn.disabled = false;
      $('upProgBar').classList.remove('done');
      var raw = xhr.responseText;
      var r = null;
      try { r = JSON.parse(raw); } catch (e) { r = null; }
      if (!r || typeof r !== 'object') {
        $('upProgWrap').style.display = 'none';
        $('upRes').style.display = '';
        $('upRes').innerHTML = '<div class="up-res-t err">\u4e0a\u4f20\u5931\u8d25\uff1a\u670d\u52a1\u5668\u8fd4\u56de\u5f02\u5e38\uff0c\u53ef\u80fd\u4f1a\u8bdd\u5df2\u8fc7\u671f\uff0c\u8bf7\u5230 /admin/login \u91cd\u65b0\u767b\u5f55</div>';
        status('\u4e0a\u4f20\u5931\u8d25\uff1a\u670d\u52a1\u5668\u8fd4\u56de\u5f02\u5e38', true);
        return;
      }
      $('upRes').style.display = '';
      if (r.ok) {
        closeMask('mdlUpload');
        var msg = (r.done || []).length + ' \u9879\u6210\u529f' + ((r.fail || []).length ? ' \uff0c\u5931\u8d25 ' + r.fail.length + ' \u9879' : '');
        toast('\u4e0a\u4f20\u5b8c\u6210', msg, (r.fail || []).length > 0);
        status('\u4e0a\u4f20\u5b8c\u6210\uff1a' + msg, (r.fail || []).length > 0);
        setTimeout(reload, 900);
      } else {
        $('upProgWrap').style.display = 'none';
        $('upRes').innerHTML = '<div class="up-res-t err">\u4e0a\u4f20\u5931\u8d25\uff1a' + escHtml(r.msg || '\u672a\u77e5\u9519\u8bef') + '</div>';
        status('\u4e0a\u4f20\u5931\u8d25\uff1a' + (r.msg || ''), true);
      }
    };
    xhr.onerror = function () {
      upBusy = false;
      okBtn.disabled = false; okBtn.innerHTML = '\u5f00\u59cb\u4e0a\u4f20';
      pickBtn.disabled = false;
      $('upProgWrap').style.display = 'none';
      $('upRes').style.display = '';
      $('upRes').innerHTML = '<div class="up-res-t err">\u4e0a\u4f20\u5931\u8d25\uff1a\u7f51\u7edc\u9519\u8bef</div>';
      status('\u4e0a\u4f20\u5931\u8d25', true);
    };
    xhr.send(fd);
  });

  /* ---- 行操作 ---- */
  var curName = '';
  document.querySelectorAll('.op-btn[data-op]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      var op = b.getAttribute('data-op');
      var tr = b.closest('tr.fm-row');
      var rel = tr.getAttribute('data-rel');
      if (op === 'dl') return;
      if (op === 'rn') {
        curName = rel;
        $('mdlRenameTitle').textContent = '\u4fee\u6539\u540d\u79f0\uff1a' + tr.getAttribute('data-dname');
        $('rnNew').value = tr.getAttribute('data-name');
        $('rnPrf').style.display = 'none';
        $('rnSuf').style.display = 'none';
        openMask('mdlRename');
      } else if (op === 'me') {
        openMeta([rel], tr);
      } else if (op === 'lock') {
        openLock([rel], tr);
      } else if (op === 'del') {
        confirmDel([rel]);
      }
    });
  });

  $('rnOk').addEventListener('click', function () {
    var bulk = $('rnPrf').style.display !== 'none';
    if (bulk) {
      var pre = $('rnPrefix').value;
      var suf = $('rnSuffix').value;
      if (!pre && !suf) { toast('\u8bf7\u8f93\u5165\u524d\u7f00\u6216\u540e\u7f00', '', true); return; }
      var items = curBulk.slice();
      if (!items.length) return;
      $('rnOk').disabled = true;
      var okC = 0, failC = [];
      var chain = Promise.resolve();
      items.forEach(function (rel) {
        chain = chain.then(function () {
          var old = rel.split('/').pop();
          return api('/admin/api/files/rename', { dir: DIR, name: old, newName: pre + old + suf }).then(function (r) {
            if (r && r.ok) okC++;
            else failC.push(old + (r && r.msg ? ' (' + r.msg + ')' : ''));
          });
        });
      });
      chain.then(function () {
        $('rnOk').disabled = false;
        closeMask('mdlRename');
        toast('\u6279\u91cf\u91cd\u547d\u540d\u5b8c\u6210', '\u6210\u529f ' + okC + ' \u9879\u3002' + (failC.length ? '\u5931\u8d25 ' + failC.join(';') : ''), failC.length > 0);
        reload();
      });
      return;
    }
    var rel = curName;
    var name = rel.split('/').pop();
    var newName = $('rnNew').value.trim();
    if (!newName) { toast('\u540d\u79f0\u4e0d\u80fd\u4e3a\u7a7a', '', true); return; }
    $('rnOk').disabled = true;
    api('/admin/api/files/rename', { dir: DIR, name: name, newName: newName }).then(function (r) {
      $('rnOk').disabled = false;
      if (r && r.ok) { closeMask('mdlRename'); toast('\u5df2\u91cd\u547d\u540d', newName); reload(); }
      else toast('\u91cd\u547d\u540d\u5931\u8d25', (r && r.msg) || '', true);
    });
  });

  var curBulk = [];
  document.querySelectorAll('.bb-btn[data-op="bulk-rn"]').forEach(function (b) {
    b.addEventListener('click', function () {
      curBulk = rels();
      if (!curBulk.length) { toast('\u8bf7\u5148\u52fe\u9009\u9879\u76ee', '', true); return; }
      $('rnPrf').style.display = '';
      $('rnSuf').style.display = '';
      $('rnPrefix').value = '';
      $('rnSuffix').value = '';
      $('mdlRenameTitle').textContent = '\u6279\u91cf\u91cd\u547d\u540d\uff08' + curBulk.length + ' \u9879\uff09';
      openMask('mdlRename');
    });
  });

  var curMetaRels = [];
  function openMeta(relsArr, tr) {
    curMetaRels = relsArr;
    var single = relsArr.length === 1 && tr;
    $('mdlMetaTitle').textContent = single ? '\u4fee\u6539\u53c2\u6570\uff1a' + (tr.getAttribute('data-dname') || '') : '\u6279\u91cf\u4fee\u6539\u53c2\u6570\uff08' + relsArr.length + ' \u9879\uff09';
    $('meTarget').style.display = single ? 'none' : '';
    if (single) {
      $('meArch').value = tr.getAttribute('data-arch') || '';
      $('meOs').value = tr.getAttribute('data-os') || '';
      $('meZip').value = tr.getAttribute('data-zip') || '';
      $('meHidden').value = tr.getAttribute('data-hidden') === '1' ? '1' : '0';
    } else {
      $('meArch').value = '';
      $('meOs').value = '';
      $('meZip').value = '';
      $('meHidden').value = '';
    }
    openMask('mdlMeta');
  }
  document.querySelectorAll('.bb-btn[data-op="bulk-me"]').forEach(function (b) {
    b.addEventListener('click', function () {
      var r = rels();
      if (!r.length) { toast('\u8bf7\u5148\u52fe\u9009\u9879\u76ee', '', true); return; }
      openMeta(r, null);
    });
  });

  $('meOk').addEventListener('click', function () {
    if (!curMetaRels.length) return;
    $('meOk').disabled = true;
    api('/admin/api/files/meta', { items: JSON.stringify(curMetaRels), arch: $('meArch').value, os: $('meOs').value, zip: $('meZip').value, hidden: $('meHidden').value }).then(function (r) {
      $('meOk').disabled = false;
      if (r && r.ok) { closeMask('mdlMeta'); toast('\u53c2\u6570\u5df2\u66f4\u65b0', (r.msg || '')); reload(); }
      else toast('\u66f4\u65b0\u5931\u8d25', (r && r.msg) || '', true);
    });
  });

  document.querySelectorAll('.bb-btn[data-op="bulk-dl"]').forEach(function (b) {
    b.addEventListener('click', function () {
      var r = rels();
      if (!r.length) { toast('\u8bf7\u5148\u52fe\u9009\u9879\u76ee', '', true); return; }
      b.disabled = true;
      api('/admin/api/files/bulk-link', { items: JSON.stringify(r) }).then(function (resp) {
        b.disabled = false;
        if (resp && resp.ok && resp.url) { location.href = '/' + resp.url; };
      }).catch(function () { b.disabled = false; toast('\u6253\u5305\u5931\u8d25', '', true); });
    });
  });

  /* ---- 密码保护 ---- */
  var curLockRels = [];
  function openLock(relsArr, tr) {
    curLockRels = relsArr;
    if (relsArr.length === 1 && tr) {
      var isLocked = tr.getAttribute('data-lock') === '1';
      var lockby = tr.getAttribute('data-lockby') || '';
      $('mdlLockTitle').textContent = '\u8bbe\u7f6e\u5bc6\u7801\u4fdd\u62a4\uff1a' + (tr.getAttribute('data-dname') || '');
      $('lkHas').style.display = isLocked ? '' : 'none';
      $('lkHas').querySelector('.fl').textContent = isLocked ? '\u5f53\u524d\u5df2\u8bbe\u7f6e\u5bc6\u7801' + (lockby && lockby !== tr.getAttribute('data-rel') ? '\uff08\u53d7\u7236\u7ea7\u4fdd\u62a4\uff1a' + lockby + '\uff09' : '') : '';
      $('lkClear').style.display = isLocked ? '' : 'none';
    } else {
      $('mdlLockTitle').textContent = '\u6279\u91cf\u8bbe\u7f6e\u5bc6\u7801\uff08' + relsArr.length + ' \u9879\uff09';
      $('lkHas').style.display = 'none';
      $('lkClear').style.display = 'none';
    }
    $('lkNew').value = '';
    $('lkNew2').value = '';
    openMask('mdlLock');
    setTimeout(function () { $('lkNew').focus(); }, 60);
  }

  function lockSubmit(clear) {
    var items = curLockRels.slice();
    if (!items.length) return;
    var pwd = clear ? '' : $('lkNew').value;
    var pwd2 = clear ? '' : $('lkNew2').value;
    if (!clear) {
      if (pwd.length < 4) { toast('\u5bc6\u7801\u81f3\u5c11 4 \u4f4d', '', true); return; }
      if (pwd !== pwd2) { toast('\u4e24\u6b21\u8f93\u5165\u7684\u5bc6\u7801\u4e0d\u4e00\u81f4', '', true); return; }
    }
    $('lkOk').disabled = true;
    api('/admin/api/files/lock', { items: JSON.stringify(items), pwd: pwd }).then(function (r) {
      $('lkOk').disabled = false;
      if (r && r.ok) { closeMask('mdlLock'); toast(r.msg || '\u5bc6\u7801\u4fdd\u62a4\u5df2\u66f4\u65b0'); reload(); }
      else toast(clear ? '\u6e05\u9664\u5931\u8d25' : '\u8bbe\u7f6e\u5931\u8d25', (r && r.msg) || '', true);
    });
  }
  $('lkOk').addEventListener('click', function () { lockSubmit(false); });
  $('lkClear').addEventListener('click', function () {
    var items = curLockRels.slice();
    if (!items.length) return;
    $('cfBody').textContent = items.length > 1 ? ('\u786e\u5b9a\u6e05\u9664 ' + items.length + ' \u9879\u7684\u5bc6\u7801\u4fdd\u62a4\uff1f') : ('\u786e\u5b9a\u6e05\u9664\u5bc6\u7801\u4fdd\u62a4\uff1f\u6e05\u9664\u540e\u8bbf\u5ba2\u53ef\u76f4\u63a5\u8bbf\u95ee\u3002');
    $('cfOk').textContent = '\u6e05\u9664\u5bc6\u7801';
    var ok = $('cfOk');
    ok.onclick = function () { ok.onclick = null; closeMask('mdlConfirm'); lockSubmit(true); };
    openMask('mdlConfirm');
  });

  /* ---- 删除（单条 + 批量） ---- */
  var delTimer = null;
  function confirmDel(items) {
    if (!items || !items.length) return;
    if (delTimer) { clearTimeout(delTimer); delTimer = null; }
    $('cfOk').disabled = true;
    $('cfBody').textContent = '\u6b63\u5728\u8ba1\u7b97\u5c06\u91ca\u653e\u7684\u7a7a\u95f4...';
    openMask('mdlConfirm');
    api('/admin/api/files/size', { dir: DIR, items: JSON.stringify(items.map(function (rel) { return rel.split('/').pop(); })) }).then(function (d) {
      var sz = (d && d.ok) ? d.total : 0;
      var names = items.map(function (rel) { return rel.split('/').pop(); });
      $('cfBody').textContent = '\u786e\u5b9a\u5220\u9664\uff1a' + (names.length > 3 ? names.slice(0, 3).join('\u3001') + ' \u7b49 ' + names.length + ' \u9879' : names.join('\u3001')) + '\uff0c\u5c06\u91ca\u653e\u7ea6 ' + fmtB(sz) + ' \u3002\u76ee\u5f55\u5c06\u8fde\u540c\u5185\u5bb9\u4e00\u5e76\u5220\u9664\uff0c\u4e0d\u53ef\u6062\u590d\uff01';
      $('cfOk').disabled = false;
      $('cfOk').textContent = '\u5220\u9664 (5s)';
      var sec = 5;
      delTimer = setInterval(function () {
        sec--;
        $('cfOk').textContent = '\u5220\u9664 (' + sec + 's)';
        if (sec <= 0) {
          clearInterval(delTimer); delTimer = null;
          if ($('mdlConfirm').classList.contains('open')) { closeMask('mdlConfirm'); doDelete(items); }
        }
      }, 1000);
      $('cfOk').onclick = function () {
        clearInterval(delTimer); delTimer = null;
        $('cfOk').textContent = '\u5220\u9664';
        closeMask('mdlConfirm');
        doDelete(items);
      };
    });
  }
  function doDelete(items) {
    if (!items || !items.length) { toast('\u6ca1\u6709\u53ef\u5220\u9664\u7684\u9879\u76ee', '', true); return; }
    status('\u6b63\u5728\u5220\u9664...');
    var body = new FormData();
    body.append('dir', DIR);
    body.append('items', JSON.stringify(items.map(function (rel) { return rel.split('/').pop(); })));
    fetch('/admin/api/files/delete', { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          var freedTxt = (d.freed > 0) ? ('\uff0c\u91ca\u653e ' + fmtB(d.freed)) : '';
          toast('\u5220\u9664\u5b8c\u6210', (d.deleted || []).length + ' \u9879\u5df2\u5220\u9664' + freedTxt, (d.fail || []).length > 0);
          reload();
        } else {
          toast('\u5220\u9664\u5931\u8d25', (d && d.msg) || '', true);
          status('\u5220\u9664\u5931\u8d25\uff1a' + ((d && d.msg) || ''), true);
        }
      })
      .catch(function () { toast('\u5220\u9664\u5931\u8d25\uff1a\u7f51\u7edc\u9519\u8bef', '', true); status('\u5220\u9664\u5931\u8d25\uff1a\u7f51\u7edc\u9519\u8bef', true); });
  }
  document.querySelectorAll('.bb-btn[data-op="bulk-del"]').forEach(function (b) {
    b.addEventListener('click', function () {
      var r = rels();
      if (!r.length) { toast('\u8bf7\u5148\u52fe\u9009\u9879\u76ee', '', true); return; }
      confirmDel(r);
    });
  });

  refresh();
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
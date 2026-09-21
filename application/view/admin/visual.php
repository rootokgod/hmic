<?php
/**
 * 可视化面板：首页顶部按钮 / 信息块的「搭积木」编辑 + 实时预览
 * 参考开源低代码设计器（GrapesJS / epic-designer / FcDesigner）：
 *   - 可视区全屏画布 + 网格十字
 *   - 面板可鼠标拖拽、随处浮放、可最小化为气泡
 *   - 面板内搜索 + 筛选
 *   - 块支持「链接按钮 / 信息提醒」两种类型、多款样式、复制 / 显隐 / 删除
 *   - 预览内点击 / 下载 / 提交全部禁用，需真实体验请「新窗口预览」
 * @var array  $layout   有序布局配置
 * @var array  $blocks   内置块元数据
 * @var array  $styles   样式清单 {key,name}
 * @var string $defaultsJson 默认布局 JSON
 * 经 partials/head.php 提供 $h、全局 showToast
 */
include __DIR__ . '/partials/head.php';
?>
  <div class="vz-root">
    <div class="vzbar">
      <span class="vzbar-name">可视化面板</span>
      <button type="button" class="vz-btn" id="vzGridBtn">网格：开</button>
      <button type="button" class="vz-btn" id="vzMinBtn">收起面板</button>
      <span class="vzbar-hint">按住面板标题可拖到画布任意位置 · 搜索筛选 · 预览内操作已禁用</span>
      <div class="spacer"></div>
      <button type="button" class="vz-btn" id="vzOpenBtn">新窗口预览</button>
      <button type="button" class="vz-btn" id="vzResetBtn">恢复默认</button>
      <button type="button" class="vz-btn primary" id="vzSaveBtn">保存布局</button>
    </div>

    <div class="vz-stage grid" id="vzStage">
      <iframe id="vzFrame" src="/?preview=1" title="首页实时预览" loading="lazy"></iframe>

      <aside class="vz-drawer" id="vzDrawer">
        <div class="vz-minbubble" id="vzMinBubble" title="展开可视化面板（可拖动）">面板 ›</div>

        <div class="vz-panel">
          <div class="vz-panel-head" id="vzDragHead">
            <span class="vz-panel-title">可视化面板</span>
            <div class="vz-panel-acts">
              <span class="vz-panel-count" id="vzCount"></span>
              <button type="button" class="vz-btn small" id="vzMinBtn2" title="收起为气泡">–</button>
            </div>
          </div>

          <div class="vz-tabs">
            <button type="button" class="on" data-sec="mod">模块</button>
            <button type="button" data-sec="stk">贴纸</button>
          </div>

          <div class="vz-panel-body">
            <div class="vz-sec" data-sec="mod">
              <div class="vz-tools">
            <input type="text" class="vz-q" id="vzQ" placeholder="搜索：名称 / 按钮文字 / 编号" autocomplete="off">
            <div class="vz-filters" id="vzFilters">
              <button type="button" class="on" data-f="all">全部</button>
              <button type="button" data-f="builtin">内置</button>
              <button type="button" data-f="custom">自定义</button>
              <button type="button" data-f="off">已隐藏</button>
            </div>
          </div>

          <div class="vz-add" id="vzAdd">
            <div class="vz-a-kind" id="vzAKind">
              <button type="button" class="on" data-k="btn">链接按钮</button>
              <button type="button" data-k="info">信息提醒</button>
            </div>
            <input type="text" class="vz-a-field" id="vzAUrl" placeholder="链接：/networkpanel/ 或 https://…（提醒可不填）" maxlength="500">
            <div class="vz-a-row">
              <input type="text" class="vz-a-field" id="vzALabel" placeholder="按钮 / 提醒文字" maxlength="24">
              <button type="button" class="vz-btn primary small" id="vzAddBtn">＋ 添加</button>
            </div>
            <div class="vz-a-style" id="vzAStyles"></div>
          </div>

            <ol class="vz-list" id="vzList">
<?php foreach ($layout as $id => $b):
      $bid   = (string) $id;
      $isCust = is_custom_block_id($bid);
      $cKind  = $isCust ? ((($b['kind'] ?? 'btn') === 'info') ? 'info' : 'btn') : 'btn';
?>
              <li class="vz-item<?= empty($b['show']) ? ' off' : '' ?>" data-id="<?= $h($bid) ?>" data-kind="<?= $isCust ? 'custom' : 'builtin' ?>">
                <span class="vz-grip" title="拖动排序" aria-hidden="true">⋮⋮</span>
                <span class="vz-mv">
                  <button type="button" data-m="up" title="上移">▲</button>
                  <button type="button" data-m="dn" title="下移">▼</button>
                </span>
<?php if (!$isCust): ?>
                <span class="vz-name"><?= $h($blocks[$bid]['name'] ?? $bid) ?></span>
<?php   if (($blocks[$bid]['label'] ?? null) !== null): ?>
                <input type="text" class="vz-label" data-id="<?= $h($bid) ?>" value="<?= $h(isset($b['label']) && $b['label'] !== '' ? $b['label'] : $blocks[$bid]['label']) ?>" maxlength="24" title="修改按钮文字">
<?php   else: ?>
                <span class="vz-fixed">固定样式</span>
<?php   endif; ?>
<?php else: ?>
                <span class="vz-cname"><?= $cKind === 'info' ? '提醒' : '按钮' ?></span>
                <div class="vz-cfields">
                  <input type="text" class="vz-label" data-id="<?= $h($bid) ?>" value="<?= $h(isset($b['label']) ? $b['label'] : '') ?>" placeholder="文字" maxlength="24">
                  <select class="vz-kind" data-id="<?= $h($bid) ?>">
                    <option value="btn"<?= $cKind === 'btn' ? ' selected' : '' ?>>链接按钮</option>
                    <option value="info"<?= $cKind === 'info' ? ' selected' : '' ?>>信息提醒</option>
                  </select>
                  <input type="text" class="vz-url" data-id="<?= $h($bid) ?>" value="<?= $h($b['url'] ?? '') ?>" placeholder="链接（可空）" maxlength="500">
                  <select class="vz-style" data-id="<?= $h($bid) ?>">
<?php   foreach ($styles as $st2): ?>
                    <option value="<?= $h($st2['key']) ?>"<?= ($b['style'] ?? '') === $st2['key'] ? ' selected' : '' ?>><?= $h($st2['name']) ?></option>
<?php   endforeach; ?>
                  </select>
                </div>
                <button type="button" class="vz-del" data-id="<?= $h($bid) ?>" title="删除这个块">×</button>
<?php endif; ?>
                <button type="button" class="vz-dup" data-id="<?= $h($bid) ?>" title="复制这个块">⧉</button>
                <label class="switch vz-vis">
                  <input type="checkbox" data-id="<?= $h($bid) ?>" <?= !empty($b['show']) ? 'checked' : '' ?>>
                  <span class="slider"></span>
                </label>
              </li>
<?php endforeach; ?>
            </ol>
            <div class="vz-empty" id="vzEmpty" style="display:none">没有匹配的块</div>
            <div class="vz-note">「隐藏」=收走显示，功能开关仍由「首页管理」控制；链接按钮可跳转，「信息提醒」仅展示文字。</div>
            </div>

            <div class="vz-sec" data-sec="stk" style="display:none">
              <div class="vz-stkhead">
                <strong>已摆放 <span id="vzStkStrong">0</span> 张</strong>
                <button type="button" class="vz-btn small primary" id="vzStkAdd">＋ 添加贴纸</button>
              </div>
              <div class="vz-stklist" id="vzStkList"></div>
              <div class="vz-empty2" id="vzStkEmpty" style="display:none"></div>
              <div class="vz-note">画布里拖动贴纸可摆放位置；「跟随页面」=贴纸随页面上下滚动（取消=固定在屏幕）；取消「手机端」则小屏不显示。</div>
            </div>
          </div>

          <div class="vz-panel-foot">
            <button type="button" class="vz-btn primary" id="vzSaveBtn2">保存布局</button>
            <button type="button" class="vz-btn" id="vzResetBtn2">恢复默认</button>
          </div>
        </div>
      </aside>
    </div>

    <div class="vz-pick" id="vzPick" hidden>
      <div class="vz-pickbox">
        <div class="vz-pickhead">
          <strong>选择贴纸</strong>
          <button type="button" id="vzPickClose">×</button>
        </div>
        <div class="vz-pickgrid" id="vzPickGrid"></div>
        <div class="vz-pickfoot"><a href="/admin/sticker" target="_blank">前往贴纸图库上传 ›</a></div>
      </div>
    </div>
  </div>

<style>
  /* 本页全高工作区 */
  .wrap{height:calc(100vh - 56px);padding:14px;display:flex;flex-direction:column;overflow:hidden}
  .vz-root{flex:1;display:flex;flex-direction:column;min-height:0}
  .vzbar{flex:none;display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
  .vzbar .spacer{flex:1}
  .vzbar-name{font-size:14px;font-weight:600}
  .vzbar-hint{font-size:12px;color:var(--ink3)}

  .vz-btn{padding:7px 15px;border-radius:8px;border:1px solid var(--line);
    background:var(--card);color:var(--ink);font-size:13px;cursor:pointer;font-family:inherit}
  .vz-btn:hover{background:var(--hover);color:var(--ink)}
  .vz-btn.small{padding:3px 9px;font-size:12px;line-height:1}
  .vz-btn.primary{background:var(--btn);color:var(--btn-ink);border-color:transparent}
  .vz-btn.primary:hover{filter:brightness(.92);color:var(--btn-ink)}
  .vz-btn:disabled{opacity:.5;cursor:default;filter:none}

  /* 画布：全屏预览 + 网格十字/蓝图 */
  .vz-stage{position:relative;flex:1;min-height:0;background:#fff;border:1px solid var(--line);
    border-radius:12px;overflow:hidden}
  .vz-stage.grid{background:radial-gradient(circle,rgba(90,120,220,.14) 1px,transparent 1px),#fff;
    background-size:22px 22px}
  #vzFrame{position:absolute;inset:12px;width:calc(100% - 24px);height:calc(100% - 24px);
    border:0;background:#fff;border-radius:8px;box-shadow:0 4px 24px rgba(30,50,120,.10);
    transition:inset .25s}
  .vz-stage.grid #vzFrame{inset:22px;width:calc(100% - 44px);height:calc(100% - 44px)}

  /* 浮动面板：可拖拽，随处摆放 */
  .vz-drawer{position:absolute;top:12px;left:calc(100% - 368px);width:min(356px,90vw);
    background:var(--card);border:1px solid var(--line);border-radius:12px;
    box-shadow:0 14px 44px var(--shadow);z-index:20;display:flex;flex-direction:column;overflow:hidden}
  .vz-drawer.min{width:auto;min-width:0}
  .vz-drawer.min .vz-panel{display:none}
  .vz-minbubble{display:none;align-items:center;gap:6px;padding:9px 14px;font-size:13px;font-weight:600;
    cursor:grab;color:var(--ink);user-select:none}
  .vz-drawer.min .vz-minbubble{display:flex}

  .vz-panel{display:flex;flex-direction:column;max-height:calc(100vh - 150px)}
  .vz-panel-head{flex:none;display:flex;align-items:center;justify-content:space-between;gap:8px;
    padding:11px 13px;border-bottom:1px solid var(--line);cursor:grab;user-select:none;background:var(--card)}
  .vz-panel-head:active{cursor:grabbing}
  .vz-panel-title{font-size:13px;font-weight:600}
  .vz-panel-acts{display:flex;align-items:center;gap:8px}
  .vz-panel-count{font-size:11.5px;color:var(--ink3);font-variant-numeric:tabular-nums}
  .vz-panel-count.dirty{color:#e5484d;font-weight:600}

  .vz-tabs{flex:none;display:flex;gap:4px;padding:9px 13px 0;background:var(--bg);border-bottom:1px solid var(--line)}
  .vz-tabs button{padding:6px 0;flex:1;border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;color:var(--ink2);font-size:12.5px;cursor:pointer;font-family:inherit}
  .vz-tabs button.on{background:var(--card);border-color:var(--line);color:var(--ink);font-weight:600}

  .vz-panel-body{flex:1 1 auto;min-height:0;overflow-y:auto;display:flex;flex-direction:column}
  .vz-sec{flex:1;min-height:0;display:flex;flex-direction:column}
  .vz-tools{flex:none;display:flex;flex-direction:column;gap:7px;padding:10px 13px;
    border-bottom:1px dashed var(--line);background:var(--bg)}
  .vz-q{width:100%;padding:6px 10px;border:1px solid var(--line);border-radius:8px;
    background:var(--card);color:var(--ink);font-size:12.5px;box-sizing:border-box;font-family:inherit}
  .vz-q:focus{outline:none;border-color:var(--ink3)}
  .vz-filters{display:flex;gap:6px;flex-wrap:wrap}
  .vz-filters button{padding:3px 10px;border:1px solid var(--line);border-radius:999px;background:var(--card);
    color:var(--ink2);font-size:11.5px;cursor:pointer;font-family:inherit}
  .vz-filters button.on{background:var(--btn);color:var(--btn-ink);border-color:transparent}

  .vz-add{flex:none;display:flex;flex-direction:column;gap:7px;padding:11px 13px;
    border-bottom:1px dashed var(--line);background:var(--bg)}
  .vz-a-kind{display:flex;gap:6px}
  .vz-a-kind button{padding:4px 12px;border:1px solid var(--line);border-radius:8px;background:var(--card);
    color:var(--ink2);font-size:12px;cursor:pointer;font-family:inherit}
  .vz-a-kind button.on{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .vz-a-field{width:100%;padding:6px 9px;border:1px solid var(--line);border-radius:7px;
    background:var(--card);color:var(--ink);font-size:12.5px;box-sizing:border-box;font-family:inherit}
  .vz-a-field:focus{outline:none;border-color:var(--ink3)}
  .vz-a-row{display:flex;gap:7px}
  .vz-a-row .vz-a-field{flex:1}
  .vz-a-style{display:flex;flex-wrap:wrap;gap:6px}
  .vz-swatch{width:24px;height:20px;border-radius:6px;border:1.5px solid var(--line);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:11px;line-height:1;flex:none;padding:0}
  .vz-swatch.on{border-color:var(--blue);box-shadow:0 0 0 2px rgba(59,130,246,.25)}

  .vz-list{list-style:none;margin:0;padding:12px 12px 0;display:flex;flex-direction:column;gap:8px}
  .vz-item{display:flex;align-items:center;gap:7px;padding:8px 10px;border:1px solid var(--line);
    border-radius:10px;background:var(--bg);cursor:grab;transition:opacity .15s;touch-action:none}
  .vz-item input,.vz-item select,.vz-item button,.vz-item label{cursor:auto}
  .vz-item.dragging{opacity:.45;border-style:dashed}
  .vz-item.off{opacity:.55}
  .vz-grip{flex:none;color:var(--ink3);font-size:14px;letter-spacing:-1px;cursor:grab;user-select:none;padding:2px 4px}
  .vz-grip:active{cursor:grabbing}
  .vz-mv{flex:none;display:flex;flex-direction:column;gap:2px}
  .vz-mv button{width:16px;height:12px;padding:0;border:1px solid var(--line);border-radius:3px;background:var(--card);
    color:var(--ink2);font-size:8px;line-height:1;cursor:pointer;font-family:inherit}
  .vz-mv button:hover{background:var(--hover);color:var(--ink)}
  .vz-name{flex:1;min-width:0;font-size:13.5px;font-weight:500;color:var(--ink)}
  .vz-cname{flex:none;font-size:10.5px;color:var(--ink3);border:1px solid var(--line);
    border-radius:5px;padding:2px 6px}
  .vz-cfields{flex:1;min-width:0;display:flex;flex-wrap:wrap;gap:5px}
  .vz-label{flex:none;width:84px;padding:5px 8px;border:1px solid var(--line);border-radius:7px;
    background:var(--card);color:var(--ink);font-size:12.5px}
  .vz-kind{flex:none;padding:5px 4px;border:1px solid var(--line);border-radius:7px;background:var(--card);
    color:var(--ink);font-size:12px;font-family:inherit}
  .vz-url{flex:1;min-width:92px;padding:5px 8px;border:1px solid var(--line);border-radius:7px;
    background:var(--card);color:var(--ink);font-size:12px}
  .vz-style{flex:none;padding:5px 6px;border:1px solid var(--line);border-radius:7px;background:var(--card);
    color:var(--ink);font-size:12px;font-family:inherit;max-width:114px}
  .vz-label:focus,.vz-url:focus,.vz-style:focus,.vz-kind:focus{outline:none;border-color:var(--ink3)}
  .vz-fixed{flex:none;font-size:11.5px;color:var(--ink3);background:var(--hover);
    border:1px dashed var(--line);border-radius:6px;padding:4px 8px}
  .vz-del,.vz-dup{flex:none;width:22px;height:22px;border:1px solid var(--line);background:var(--card);
    border-radius:6px;cursor:pointer;font-size:13px;line-height:1;font-family:inherit;padding:0}
  .vz-dup{color:var(--ink2)}
  .vz-dup:hover{background:var(--hover);border-color:var(--ink3);color:var(--ink)}
  .vz-del{color:var(--ink3)}
  .vz-del:hover{background:#fee2e2;border-color:#fca5a5;color:#b91c1c}
  .vz-item .switch.vz-vis{margin-left:0}
  .vz-empty{padding:18px 0;text-align:center;font-size:12.5px;color:var(--ink3)}
  .vz-empty2{padding:22px 12px;text-align:center;font-size:12.5px;color:var(--ink3)}
  .vz-empty2 a{color:var(--blue)}
  .vz-note{margin:8px 12px 12px;font-size:11.5px;color:var(--ink3);line-height:1.7}

  /* 贴纸页 */
  .vz-stkhead{flex:none;display:flex;align-items:center;justify-content:space-between;gap:8px;
    padding:11px 13px;border-bottom:1px dashed var(--line)}
  .vz-stkhead strong{font-size:12.5px;font-weight:600}
  .vz-stklist{flex:1 1 auto;min-height:0;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:9px}
  .vz-stkrow{display:flex;align-items:center;gap:9px;padding:9px;border:1px solid var(--line);
    border-radius:10px;background:var(--bg)}
  .vz-stkthumb{flex:none;width:46px;height:46px;object-fit:contain;background:
    repeating-conic-gradient(rgba(128,128,128,.09) 0 25%,transparent 0 50%) 0 0/12px 12px;border-radius:7px;border:1px solid var(--line)}
  .vz-stkmeta{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px}
  .vz-stkr1{display:flex;align-items:center;gap:6px;min-width:0}
  .vz-stkcap{flex:1;min-width:0;font-size:11px;color:var(--ink2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .vz-stkxy{flex:none;font-size:10px;color:var(--ink3);font-variant-numeric:tabular-nums}
  .vz-stkctrl{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:center}
  .stk-sw{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--ink2);cursor:pointer}
  .stk-sw input{accent-color:var(--btn)}
  .stk-sl{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--ink2)}
  .stk-sl input[type=range]{width:74px;height:14px;accent-color:var(--btn)}
  .stk-sl em{font-style:normal;font-size:10.5px;color:var(--ink3);min-width:22px;text-align:right}
  .vz-stkdel{flex:none;width:22px;height:22px;border:1px solid var(--line);background:var(--card);
    border-radius:6px;cursor:pointer;font-size:13px;line-height:1;font-family:inherit;padding:0;color:var(--ink3)}
  .vz-stkdel:hover{background:#fee2e2;border-color:#fca5a5;color:#b91c1c}

  /* 贴纸选择弹层 */
  .vz-pick{position:fixed;inset:0;background:rgba(10,15,40,.45);z-index:2000;display:flex;
    align-items:center;justify-content:center;padding:20px}
  .vz-pick[hidden]{display:none}
  .vz-pickbox{display:flex;flex-direction:column;width:min(720px,94vw);max-height:82vh;background:var(--card);
    border-radius:14px;border:1px solid var(--line);box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden}
  .vz-pickhead{flex:none;display:flex;align-items:center;justify-content:space-between;padding:13px 16px;
    border-bottom:1px solid var(--line)}
  .vz-pickhead strong{font-size:14px}
  .vz-pickhead button{border:1px solid var(--line);background:var(--card);width:26px;height:26px;border-radius:7px;
    cursor:pointer;font-size:15px;line-height:1;color:var(--ink2);font-family:inherit}
  .vz-pickgrid{flex:1 1 auto;min-height:0;overflow-y:auto;padding:16px;display:grid;
    grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px}
  .vz-pickcard{border:1px solid var(--line);border-radius:10px;background:var(--bg);padding:8px;cursor:pointer;
    font-family:inherit;color:var(--ink);display:flex;flex-direction:column;align-items:center;gap:6px}
  .vz-pickcard:hover{border-color:var(--ink3);background:var(--hover)}
  .vz-pickcard img{width:100%;height:84px;object-fit:contain;background:
    repeating-conic-gradient(rgba(128,128,128,.09) 0 25%,transparent 0 50%) 0 0/12px 12px;border-radius:6px}
  .vz-pickcard span{font-size:11px;color:var(--ink2);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .vz-pickempty{padding:30px 10px;text-align:center;color:var(--ink3);font-size:13px;grid-column:1/-1}
  .vz-pickfoot{flex:none;border-top:1px solid var(--line);padding:10px 16px;text-align:center}
  .vz-pickfoot a{color:var(--blue);font-size:12.5px}

  .vz-panel-foot{flex:none;display:flex;gap:8px;padding:10px 12px;border-top:1px solid var(--line)}

  /* 预览画布内贴纸：可选中 / 拖拽 */
  .vz-e-wrap{position:absolute;line-height:0;cursor:move;pointer-events:auto;-webkit-user-select:none;
    user-select:none;touch-action:none}
  .vz-e-wrap.vz-drag{cursor:grabbing}
  .vz-e-wrap img.stk{position:relative;display:block;pointer-events:none}
  .vz-e-wrap .vz-e-ring{position:absolute;inset:0;border:1.5px dashed rgba(99,102,241,.9);border-radius:5px;
    pointer-events:none}
  .vz-e-wrap.vz-sel .vz-e-ring{border:2px solid #6366f1}
  .vz-e-wrap .vz-e-lab{position:absolute;left:0;transform:translateY(-100%);max-width:220px;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;font:normal 10px/1.5 system-ui,sans-serif;color:#fff;
    background:rgba(79,80,214,.92);padding:2px 7px;border-radius:5px;pointer-events:none}
  .vz-stkrow.sel{border-color:var(--btn);box-shadow:0 0 0 2px rgba(99,102,241,.18)}
  .stk-znum{width:50px;padding:3px 4px;border:1px solid var(--line);border-radius:6px;background:var(--card);
    color:var(--ink);font-size:11.5px;font-family:inherit;text-align:center}
  .stk-znum:focus{outline:none;border-color:var(--ink3)}
  .stk-zb{padding:3px 9px;border:1px solid var(--line);border-radius:6px;background:var(--card);color:var(--ink2);
    font-size:11px;cursor:pointer;font-family:inherit;line-height:1.4}
  .stk-zb:hover{background:var(--hover);color:var(--ink);border-color:var(--ink3)}

  /* 模块拖拽排序：ghost 克隆 + 落点指示 */
  .vz-ghost{position:fixed;z-index:2147483000;pointer-events:none;opacity:.82;transform:rotate(1.5deg);
    box-shadow:0 14px 34px var(--shadow);border:1px solid var(--line);border-radius:10px;background:var(--card)}
  .vz-dropi{height:3px;border-radius:3px;background:var(--btn);margin:1px 0;flex:none}

  @media(max-width:640px){
    .wrap{height:calc(100vh - 56px)}
    .vz-drawer{width:88vw}
  }
</style>

<script>
(function () {
  var BLOCKS   = <?= $blocksJson ?>;
  var STYLES   = <?= $stylesJson ?>;
  var DEFAULTS = <?= $defaultsJson ?>;
  var list     = document.getElementById('vzList');
  var frame    = document.getElementById('vzFrame');
  var stage    = document.getElementById('vzStage');
  var drawer   = document.getElementById('vzDrawer');
  if (!list || !frame) return;

  var DESK_KEY   = 'vzDrawerPos';
  var activeFilt = 'all';

  var stickersInit = <?= $stickersJson ?: '{}' ?>;
  var stickers = [];
  if (Array.isArray(stickersInit)) stickers = stickersInit.slice();
  else if (stickersInit) Object.keys(stickersInit).forEach(function (k) { stickers.push(stickersInit[k]); });
  stickers.forEach(function (s) {
    s.x = Number(s.x) || 50; s.y = Number(s.y) || 50;
    s.opacity = Number(s.opacity) || 100; s.size = Number(s.size) || 90; s.z = Number(s.z) || 1;
    s.fixed = !!s.fixed; s.mobile = !!(s.mobile !== false);
  });
  var libList = [];
  var selStkId = null;
  var dirty = false;

  var toast = function (msg, detail, err) {
    if (typeof detail === 'boolean') { err = detail; detail = ''; }
    if (typeof window.showToast === 'function') window.showToast(msg, detail || '', err);
  };
  function markDirty() {
    dirty = true;
    updateCount();
  }

  /* ================== 浮动面板：拖拽 + 位置记忆 ================== */
  /* 约束：顶部不可溢出（可拖回），底部 / 右侧允许伸出画布 */
  function constrainXY(x, y) {
    var sw = stage.clientWidth, sh = stage.clientHeight;
    var w = drawer.offsetWidth, h = drawer.offsetHeight;
    if (w > sw) w = 60;
    if (h > sh) h = 60;
    x = Math.min(Math.max(x, -(w - 120)), Math.max(0, sw - 60));
    y = Math.min(Math.max(y, 0), Math.max(0, sh - 44));
    return [x, y];
  }
  function placeXY(x, y) {
    var p = constrainXY(x, y);
    drawer.style.left = p[0] + 'px';
    drawer.style.top  = p[1] + 'px';
    return p;
  }
  function resetPos() {
    try { localStorage.removeItem(DESK_KEY); } catch (e) {}
    var w = drawer.offsetWidth;
    placeXY(stage.clientWidth - w - 12, 12);
  }
  function dragFrom(startX, startY, startL, startT) {
    var moved = false;
    function mm(e) {
      e.preventDefault();
      placeXY(startL + (e.clientX - startX), startT + (e.clientY - startY));
      moved = true;
    }
    function mu(e) {
      document.removeEventListener('mousemove', mm);
      document.removeEventListener('mouseup', mu);
      document.removeEventListener('mouseleave', mu);
      drawer.classList.remove('dragging');
      var p = [parseInt(drawer.style.left, 10) || 0, parseInt(drawer.style.top, 10) || 0];
      try { localStorage.setItem(DESK_KEY, JSON.stringify({ x: p[0], y: p[1] })); } catch (err) {}
      if (!moved) return;
      e.preventDefault();
    }
    document.addEventListener('mousemove', mm);
    document.addEventListener('mouseup', mu);
    document.addEventListener('mouseleave', mu);
  }
  document.getElementById('vzDragHead').addEventListener('mousedown', function (e) {
    if (e.button !== 0) return;
    if (e.target.closest('button')) return;
    e.preventDefault();
    drawer.classList.add('dragging');
    dragFrom(e.clientX, e.clientY, parseInt(drawer.style.left, 10) || 0, parseInt(drawer.style.top, 10) || 0);
  });
  document.getElementById('vzMinBubble').addEventListener('mousedown', function (e) {
    if (e.button !== 0) return;
    e.preventDefault();
    dragFrom(e.clientX, e.clientY, parseInt(drawer.style.left, 10) || 0, parseInt(drawer.style.top, 10) || 0);
  });
  document.getElementById('vzMinBubble').addEventListener('click', function () { setMin(false); });

  /* ================== 最小化 = 气泡 ================== */
  function setMin(on) {
    drawer.classList.toggle('min', on);
    document.getElementById('vzMinBtn').textContent = on ? '展开面板' : '收起面板';
    if (!on) placeXY(parseInt(drawer.style.left, 10) || 0, parseInt(drawer.style.top, 10) || 0);
  }
  document.getElementById('vzMinBtn').addEventListener('click', function () { setMin(!drawer.classList.contains('min')); });
  document.getElementById('vzMinBtn2').addEventListener('click', function () { setMin(true); });

  /* ================== 搜索 + 筛选 ================== */
  document.getElementById('vzQ').addEventListener('input', applyListFilter);
  document.getElementById('vzFilters').addEventListener('click', function (e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    activeFilt = btn.dataset.f;
    [].forEach.call(this.children, function (b) { b.classList.toggle('on', b === btn); });
    applyListFilter();
  });
  function liText(li) {
    var name = (li.querySelector('.vz-name') || {}).textContent || '';
    var lab = (li.querySelector('.vz-label') || {}).value || '';
    return (name + ' ' + lab).toLowerCase();
  }
  function applyListFilter() {
    var q = (document.getElementById('vzQ').value || '').trim().toLowerCase();
    var count = 0;
    list.querySelectorAll('.vz-item').forEach(function (li) {
      var matchQ = q === '' || li.dataset.id.toLowerCase().indexOf(q) >= 0 || liText(li).indexOf(q) >= 0;
      var matchF = activeFilt === 'all'
        || (activeFilt === 'builtin' && li.dataset.kind === 'builtin')
        || (activeFilt === 'custom' && li.dataset.kind === 'custom')
        || (activeFilt === 'off' && li.classList.contains('off'));
      var show = matchQ && matchF;
      li.style.display = show ? '' : 'none';
      if (show) count++;
    });
    document.getElementById('vzEmpty').style.display = count === 0 ? 'block' : 'none';
    updateCount();
  }

  /* ================== 状态读取 ================== */
  function collectState() {
    var out = [];
    list.querySelectorAll('.vz-item').forEach(function (li) {
      var chk = li.querySelector('.vz-vis input');
      var lab = li.querySelector('.vz-label');
      var item = { id: li.dataset.id, show: !!(chk && chk.checked), label: lab ? lab.value.trim() : '' };
      if (li.dataset.kind === 'custom') {
        var url = li.querySelector('.vz-url');
        var st  = li.querySelector('.vz-style');
        var kd  = li.querySelector('.vz-kind');
        item.url   = url ? url.value.trim() : '';
        item.style = st ? st.value : 'ghost';
        item.kind  = kd ? kd.value : 'btn';
      }
      out.push(item);
      li.classList.toggle('off', !(chk && chk.checked));
    });
    return out;
  }

  /* ================== 预览绘制 ================== */
  function replaceText(container, label) {
    if (!container) return;
    var kids = [];
    container.childNodes.forEach(function (n) { if (n.nodeType === 1) kids.push(n); });
    container.textContent = '';
    kids.forEach(function (n) { container.appendChild(n); });
    if (label !== '') container.appendChild(document.createTextNode(' ' + label));
  }
  var MOB_MAP = { support: '.sp-donate', speed: '.sp-speed', mas: '.sp-mas' };
  function ensureDoc() {
    try { return frame.contentDocument; } catch (e) { return null; }
  }
  function makeCustomEl(it, order, desktop) {
    var style = 'spx-ghost';
    for (var i = 0; i < STYLES.length; i++) if (STYLES[i].key === it.style) { style = 'spx-' + it.style; break; }
    var info = it.kind === 'info' || !String(it.url || '').trim();
    var label = (it.label || '').trim() || (info ? '提示' : '按钮');
    if (info) {
      var s = document.createElement('span');
      s.className = 'act-btn spx ' + style + ' nolink' + (desktop ? ' desktop-only' : '');
      s.textContent = label;
      if (desktop) s.style.order = String(order);
      return s;
    }
    var a = document.createElement('a');
    a.className = 'act-btn spx ' + style + (desktop ? ' desktop-only' : '');
    a.href = it.url.trim();
    a.textContent = label;
    if (/^https?:\/\//i.test(it.url)) { a.target = '_blank'; a.rel = 'noopener'; }
    if (desktop) a.style.order = String(order);
    return a;
  }
  function applyPreview() {
    var doc = ensureDoc();
    if (!doc || !doc.head) return;
    var items = collectState();

    var css = '', idx = 1, customs = [];
    items.forEach(function (it) {
      var b = BLOCKS[it.id];
      if (it.show) {
        if (b && b.sel) css += b.sel + '{order:' + idx + '}';
        if (!b) customs.push({ it: it, order: idx });
        idx++;
      } else if (b && b.hides) {
        b.hides.forEach(function (s) { css += s + '{display:none!important}'; });
      }
    });
    css += '.hamburger-wrap{order:99}';
    var st = doc.getElementById('vz-style');
    if (!st) { st = doc.createElement('style'); st.id = 'vz-style'; doc.head.appendChild(st); }
    st.textContent = css;

    items.forEach(function (it) {
      var b = BLOCKS[it.id];
      if (!b || b.label == null) return;
      var label = it.label !== '' ? it.label : (b.label || '');
      if (it.id === 'support') replaceText(doc.querySelector('.support-btn'), label);
      else replaceText(doc.querySelector(b.sel), label);
      var mob = doc.querySelector('.mobile-menu ' + (MOB_MAP[it.id] || ''));
      if (mob) replaceText(mob, label);
    });

    var tb = doc.querySelector('.tb-right');
    var stub = doc.getElementById('vz-custom');
    if (!stub && tb) { stub = doc.createElement('div'); stub.id = 'vz-custom'; stub.style.cssText = 'display:contents'; tb.insertBefore(stub, tb.querySelector('.hamburger-wrap') || null); }
    if (stub) stub.textContent = '';
    var mm = doc.querySelector('.mobile-menu');
    var stubM = doc.getElementById('vz-custom-m');
    if (!stubM && mm) { stubM = doc.createElement('div'); stubM.id = 'vz-custom-m'; stubM.style.cssText = 'display:contents'; mm.appendChild(stubM); }
    if (stubM) stubM.textContent = '';

    customs.forEach(function (c) {
      var el = makeCustomEl(c.it, c.order, true);
      if (el && stub) stub.appendChild(el);
      var elM = makeCustomEl(c.it, c.order, false);
      if (elM && stubM) stubM.appendChild(elM);
    });

    renderPreviewStickers();
  }

  /* ================== 预览禁交互 ================== */
  function protectPreview() {
    var doc = ensureDoc();
    if (!doc) return;
    if (!doc.documentElement.dataset.vzBlocked) {
      doc.documentElement.dataset.vzBlocked = '1';
      doc.documentElement.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); }, true);
      doc.documentElement.addEventListener('submit', function (e) { e.preventDefault(); e.stopPropagation(); }, true);
      doc.documentElement.addEventListener('dblclick', function (e) { e.preventDefault(); }, true);
    }
    if (!doc.getElementById('vz-badge')) {
      var b = document.createElement('div');
      b.id = 'vz-badge';
      b.textContent = '预览模式 · 点击/下载已禁用';
      b.style.cssText = 'position:fixed;right:14px;bottom:12px;z-index:99999;background:rgba(0,0,0,.72);color:#fff;'
        + 'font-size:12px;padding:6px 12px;border-radius:999px;pointer-events:none;font-family:system-ui,sans-serif;'
        + 'box-shadow:0 4px 16px rgba(0,0,0,.25)';
      doc.body.appendChild(b);
    }
  }

  /* ================== 网格十字 ================== */
  function applyGrid() {
    var on  = stage.classList.contains('grid');
    document.getElementById('vzGridBtn').textContent = on ? '网格：开' : '网格：关';
    var doc = ensureDoc();
    if (!doc || !doc.head) return;
    var ov = doc.getElementById('vz-gridov');
    if (!on) { if (ov) ov.classList.add('hide'); return; }
    if (!ov) {
      var cs = doc.createElement('style');
      cs.textContent = '.vz-gridov{position:fixed;inset:0;z-index:99990;pointer-events:none;'
        + 'background-image:linear-gradient(rgba(120,140,200,.10) 1px,transparent 1px),'
        + 'linear-gradient(90deg,rgba(120,140,200,.10) 1px,transparent 1px),'
        + 'repeating-linear-gradient(45deg,rgba(120,140,200,.05) 0 1px,transparent 1px 8px);'
        + 'background-size:22px 22px,22px 22px,22px 22px}'
        + '.vz-gridov.hide{display:none}';
      doc.head.appendChild(cs);
      ov = document.createElement('div');
      ov.id = 'vz-gridov';
      ov.className = 'vz-gridov';
      doc.body.appendChild(ov);
    }
    ov.classList.remove('hide');
  }
  document.getElementById('vzGridBtn').addEventListener('click', function () {
    stage.classList.toggle('grid');
    applyGrid();
    protectPreview();
  });

  /* ================== 添加按钮（两种类型） ================== */
  var addKind = 'btn', selStyle = 'ghost';
  function buildSwatches() {
    var box = document.getElementById('vzAStyles');
    if (!box) return;
    box.textContent = '';
    STYLES.forEach(function (s) {
      var sw = document.createElement('button');
      sw.type = 'button';
      sw.className = 'vz-swatch' + (s.key === selStyle ? ' on' : '');
      sw.title = s.name;
      sw.dataset.key = s.key;
      var c = 'background:transparent;border-color:var(--ink3)';
      switch (s.key) {
        case 'line': c = 'background:transparent;border:1.5px solid #3b82f6'; break;
        case 'solid': c = 'background:#3b82f6'; break;
        case 'grad': c = 'background:linear-gradient(135deg,#6366f1,#8b5cf6)'; break;
        case 'green': c = 'background:#16a34a'; break;
        case 'blue': c = 'background:#2563eb'; break;
        case 'orange': c = 'background:#ea580c'; break;
        case 'red': c = 'background:#dc2626'; break;
        case 'link': c = 'background:transparent;color:#3b82f6;border-color:transparent;text-decoration:underline'; break;
        case 'soft-blue': c = 'background:#eff6ff;color:#1d4ed8'; break;
        case 'soft-green': c = 'background:#dcfce7;color:#15803d'; break;
        case 'soft-amber': c = 'background:#fef3c7;color:#b45309'; break;
        case 'soft-red': c = 'background:#fef2f2;color:#b91c1c'; break;
      }
      sw.style.cssText = c || 'background:transparent;border-color:var(--ink3)';
      sw.addEventListener('click', function () {
        selStyle = s.key;
        [].forEach.call(box.children, function (x) { x.classList.toggle('on', x.dataset.key === selStyle); });
      });
      box.appendChild(sw);
    });
  }
  buildSwatches();
  document.getElementById('vzAKind').addEventListener('click', function (e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    addKind = btn.dataset.k;
    [].forEach.call(this.children, function (b) { b.classList.toggle('on', b === btn); });
    document.getElementById('vzAUrl').placeholder = addKind === 'info' ? '信息提醒无需链接' : '链接：/networkpanel/ 或 https://…';
  });
  function encodeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function uniqueId() { return 'c_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7); }
  function makeCustomLI(init) {
    init = init || {};
    var li = document.createElement('li');
    li.className = 'vz-item';
    li.dataset.id = uniqueId();
    li.dataset.kind = 'custom';
    var kind = init.kind || addKind;
    var kOpt = '<option value="btn"' + (kind === 'btn' ? ' selected' : '') + '>链接按钮</option>'
      + '<option value="info"' + (kind === 'info' ? ' selected' : '') + '>信息提醒</option>';
    var styleOpts = STYLES.map(function (s) {
      return '<option value="' + s.key + '"' + (s.key === (init.style || selStyle) ? ' selected' : '') + '>' + s.name + '</option>';
    }).join('');
    li.innerHTML =
      '<span class="vz-grip" title="拖动排序" aria-hidden="true">⋮⋮</span>' +
      '<span class="vz-mv"><button type="button" data-m="up" title="上移">▲</button><button type="button" data-m="dn" title="下移">▼</button></span>' +
      '<span class="vz-cname">' + (kind === 'info' ? '提醒' : '按钮') + '</span>' +
      '<div class="vz-cfields">' +
        '<input type="text" class="vz-label" value="' + encodeHtml(init.label || '') + '" placeholder="文字" maxlength="24">' +
        '<select class="vz-kind">' + kOpt + '</select>' +
        '<input type="text" class="vz-url" value="' + encodeHtml(init.url || '') + '" placeholder="链接（可空）" maxlength="500">' +
        '<select class="vz-style">' + styleOpts + '</select>' +
      '</div>' +
      '<button type="button" class="vz-del" title="删除这个块">×</button>' +
      '<button type="button" class="vz-dup" title="复制这个块">⧉</button>' +
      '<label class="switch vz-vis"><input type="checkbox"' + (init.show !== false ? ' checked' : '') + '><span class="slider"></span></label>';
    return li;
  }
  function addCustom() {
    var label = document.getElementById('vzALabel').value.trim();
    var url   = document.getElementById('vzAUrl').value.trim();
    if (label === '' && url === '') { toast('请至少填写文字或链接', '', true); return; }
    var li = makeCustomLI({ label: label, url: url, kind: addKind, style: selStyle });
    list.appendChild(li);
    document.getElementById('vzALabel').value = '';
    document.getElementById('vzAUrl').value = '';
    document.getElementById('vzQ').value = '';
    activeFilt = 'all';
    [].forEach.call(document.getElementById('vzFilters').children, function (b) { b.classList.toggle('on', b.dataset.f === 'all'); });
    applyListFilter();
    applyPreview();
    toast('已添加块，保存后生效');
  }
  document.getElementById('vzAddBtn').addEventListener('click', addCustom);
  ['vzAUrl', 'vzALabel'].forEach(function (id) {
    document.getElementById(id).addEventListener('keydown', function (e) { if (e.key === 'Enter') addCustom(); });
  });

  /* ================== 拖拽排序（Pointer，可靠）+ ▲▼ 移动兜底 ================== */
  function visibleItems() {
    return [].slice.call(list.querySelectorAll('.vz-item')).filter(function (x) { return x.style.display !== 'none'; });
  }
  list.addEventListener('mousedown', function (e) {
    if (e.button !== 0) return;
    var li = e.target.closest('.vz-item');
    if (!li) return;
    if (e.target.closest('input,select,button,a,label,.switch')) return;
    e.preventDefault();
    var rect = li.getBoundingClientRect();
    var panelBody = li.closest('.vz-panel-body');
    var ghost = li.cloneNode(true);
    ghost.className = 'vz-ghost';
    [].forEach.call(ghost.querySelectorAll('input,select,button'), function (n) { n.disabled = true; });
    document.body.appendChild(ghost);
    function posGhost(e2) {
      ghost.style.width = rect.width + 'px';
      ghost.style.left = Math.round(e2.clientX - rect.width / 2) + 'px';
      ghost.style.top = Math.round(e2.clientY - rect.height / 2) + 'px';
    }
    posGhost(e);
    li.classList.add('dragging');
    var ind = null;
    function slot(e2) {
      var vis = visibleItems().filter(function (x) { return x !== li; });
      var after = null;
      for (var i = 0; i < vis.length; i++) {
        var b = vis[i].getBoundingClientRect();
        if (e2.clientY - 6 < b.top + b.height / 2) { after = vis[i]; break; }
      }
      if (!ind) { ind = document.createElement('div'); ind.className = 'vz-dropi'; }
      if (after) { if (after.previousSibling !== ind) list.insertBefore(ind, after); }
      else if (list.lastElementChild !== ind) list.appendChild(ind);
      if (panelBody && panelBody.scrollHeight > panelBody.clientHeight) {
        var pbr = panelBody.getBoundingClientRect();
        if (e2.clientY < pbr.top + 42) panelBody.scrollTop -= 14;
        else if (e2.clientY > pbr.bottom - 42) panelBody.scrollTop += 14;
      }
    }
    function mm(e2) { posGhost(e2); slot(e2); }
    function mu() {
      document.removeEventListener('mousemove', mm);
      document.removeEventListener('mouseup', mu);
      document.removeEventListener('mouseleave', mu);
      if (ghost.parentNode) ghost.parentNode.removeChild(ghost);
      li.classList.remove('dragging');
      var before = null;
      if (ind && ind.parentNode) {
        before = ind.nextElementSibling;
        ind.parentNode.removeChild(ind);
      }
      if (before) list.insertBefore(li, before);
      else list.appendChild(li);
      applyPreview();
      applyListFilter();
    }
    document.addEventListener('mousemove', mm);
    document.addEventListener('mouseup', mu);
    document.addEventListener('mouseleave', mu);
  });
  list.addEventListener('click', function (e) {
    var mv = e.target.closest('.vz-mv button');
    if (!mv) return;
    e.preventDefault();
    var li = mv.closest('.vz-item');
    var vis = visibleItems();
    var idx = vis.indexOf(li);
    if (idx < 0) return;
    if (mv.dataset.m === 'up' && idx > 0) list.insertBefore(li, vis[idx - 1]);
    if (mv.dataset.m === 'dn' && idx < vis.length - 1) {
      var target = vis[idx + 1];
      if (target.nextElementSibling) list.insertBefore(li, target.nextElementSibling);
      else list.appendChild(li);
    }
    applyPreview();
    applyListFilter();
  });

  /* ================== 显隐/文字/类型/链接/样式/复制/删除 ================== */
  list.addEventListener('change', function (e) {
    if (e.target.matches('.vz-vis input') || e.target.matches('.vz-style') || e.target.matches('.vz-kind')) {
      var li = e.target.closest('.vz-item');
      if (e.target.matches('.vz-kind') && li) {
        var cname = li.querySelector('.vz-cname');
        if (cname) cname.textContent = e.target.value === 'info' ? '提醒' : '按钮';
      }
      applyPreview();
      applyListFilter();
    }
  });
  list.addEventListener('input', function (e) {
    if (e.target.matches('.vz-label') || e.target.matches('.vz-url')) { applyPreview(); applyListFilter(); }
  });
  list.addEventListener('click', function (e) {
    if (e.target.closest('.vz-del')) {
      e.preventDefault();
      e.stopPropagation();
      var li = e.target.closest('.vz-item');
      if (li) li.remove();
      applyPreview();
      applyListFilter();
      return;
    }
    if (e.target.closest('.vz-dup')) {
      e.preventDefault();
      e.stopPropagation();
      var src = e.target.closest('.vz-item');
      if (!src) return;
      var labE = (src.querySelector('.vz-label') || {}).value || '';
      var nli;
      if (src.dataset.kind === 'custom') {
        nli = makeCustomLI({
          label: labE,
          url: (src.querySelector('.vz-url') || {}).value || '',
          style: (src.querySelector('.vz-style') || {}).value || 'ghost',
          kind: (src.querySelector('.vz-kind') || {}).value || 'btn',
          show: ((src.querySelector('.vz-vis input') || {}).checked) !== false
        });
      } else {
        nli = makeCustomLI({ label: labE, kind: 'btn', show: ((src.querySelector('.vz-vis input') || {}).checked) !== false });
      }
      src.after(nli);
      applyPreview();
      applyListFilter();
      toast('已复制（新块在下方）');
      return;
    }
  });

  /* ================== Tabs：模块 / 贴纸 ================== */
  var CUR_SEC = 'mod';
  function switchSec(sec) {
    CUR_SEC = sec;
    [].forEach.call(document.querySelectorAll('.vz-tabs button'), function (b) { b.classList.toggle('on', b.dataset.sec === sec); });
    [].forEach.call(document.querySelectorAll('.vz-sec'), function (s) { s.style.display = s.dataset.sec === sec ? '' : 'none'; });
    updateCount();
  }
  function updateCount() {
    var c = document.getElementById('vzCount');
    if (!c) return;
    if (CUR_SEC === 'mod') {
      c.textContent = list.querySelectorAll('.vz-item').length + ' 块 · 显示 ' + visibleItems().length;
    } else {
      c.textContent = stickers.length + ' 张';
    }
    if (dirty) {
      c.textContent += ' · 未保存';
      c.classList.add('dirty');
    } else c.classList.remove('dirty');
  }
  document.querySelectorAll('.vz-tabs button').forEach(function (b) {
    b.addEventListener('click', function () { switchSec(b.dataset.sec); });
  });

  /* ================== 贴纸：状态 / 列表 / 预览拖放 / 保存 ================== */
  function esc2(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }
  function nextZ() {
    var m = 0;
    stickers.forEach(function (s) { if (s.z > m) m = s.z; });
    return m + 1;
  }
  function addSticker(file) {
    var sid = 'stk_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
    stickers.push({ id: sid, file: file, x: 50, y: 50, fixed: true, mobile: true, opacity: 100, size: 90, z: nextZ() });
    setSel(sid);
    renderStickerRows();
    markDirty();
    toast('已添加贴纸，可拖动摆放，保存后生效');
  }
  function updateStkEmpty() {
    var el = document.getElementById('vzStkEmpty');
    document.getElementById('vzStkStrong').textContent = stickers.length;
    if (!el) return;
    if (stickers.length) { el.style.display = 'none'; return; }
    el.innerHTML = libList.length
      ? '还没有摆放贴纸，点击「＋ 添加贴纸」从图库选取。'
      : '贴纸图库还没有图片 → <a href="/admin/sticker" target="_blank">前往贴纸图库</a>';
    el.style.display = 'block';
  }
  function updateStkXY(s) {
    var row = document.querySelector('.vz-stkrow[data-id="' + s.id + '"] .vz-stkxy');
    if (row) row.textContent = '(' + s.x.toFixed(1) + '%, ' + s.y.toFixed(1) + '%) · L' + s.z;
  }
  function renderStickerRows() {
    var box = document.getElementById('vzStkList');
    box.textContent = '';
    stickers.forEach(function (s) {
      var row = document.createElement('div');
      row.className = 'vz-stkrow' + (s.id === selStkId ? ' sel' : '');
      row.dataset.id = s.id;
      var img = document.createElement('img');
      img.className = 'vz-stkthumb';
      img.src = '/stk/' + encodeURIComponent(s.file);
      img.alt = '';
      var meta = document.createElement('div');
      meta.className = 'vz-stkmeta';
      var r1 = document.createElement('div');
      r1.className = 'vz-stkr1';
      var cap = document.createElement('span');
      cap.className = 'vz-stkcap';
      cap.textContent = s.file;
      cap.title = s.file;
      var xy = document.createElement('span');
      xy.className = 'vz-stkxy';
      xy.textContent = '(' + s.x.toFixed(1) + '%, ' + s.y.toFixed(1) + '%) · L' + s.z;
      r1.appendChild(cap); r1.appendChild(xy);
      var r2 = document.createElement('div');
      r2.className = 'vz-stkctrl';
      var l1 = document.createElement('label');
      l1.className = 'stk-sw';
      l1.innerHTML = '<input type="checkbox"' + (s.fixed ? ' checked' : '') + '><span>跟随页面</span>';
      var l2 = document.createElement('label');
      l2.className = 'stk-sw';
      l2.innerHTML = '<input type="checkbox"' + (s.mobile ? ' checked' : '') + '><span>手机端</span>';
      r2.appendChild(l1); r2.appendChild(l2);
      var r3 = document.createElement('div');
      r3.className = 'vz-stkctrl';
      var op = document.createElement('label');
      op.className = 'stk-sl';
      op.innerHTML = '透明度 <input type="range" min="10" max="100" value="' + s.opacity + '"><em>' + s.opacity + '</em>';
      var sz = document.createElement('label');
      sz.className = 'stk-sl';
      sz.innerHTML = '大小 <input type="range" min="20" max="900" value="' + s.size + '"><em>' + s.size + '</em>';
      var zc = document.createElement('div');
      zc.className = 'vz-stkctrl';
      var zLab = document.createElement('label');
      zLab.className = 'stk-sl';
      zLab.textContent = '层级 ';
      var zn = document.createElement('input');
      zn.type = 'number';
      zn.className = 'stk-znum';
      zn.min = '1'; zn.max = '999';
      zn.value = String(s.z);
      zLab.appendChild(zn);
      var zbTop = document.createElement('button');
      zbTop.type = 'button';
      zbTop.className = 'stk-zb';
      zbTop.textContent = '最高层';
      zbTop.title = '置顶：盖住页面所有元素';
      var zbBtm = document.createElement('button');
      zbBtm.type = 'button';
      zbBtm.className = 'stk-zb';
      zbBtm.textContent = '底层';
      zbBtm.title = '置于页面最底';
      zc.appendChild(zLab); zc.appendChild(zbTop); zc.appendChild(zbBtm);
      r3.appendChild(op); r3.appendChild(sz);
      meta.appendChild(r1); meta.appendChild(r2); meta.appendChild(r3); meta.appendChild(zc);
      var del = document.createElement('button');
      del.className = 'vz-stkdel';
      del.type = 'button';
      del.textContent = '×';
      del.title = '移除此贴纸';
      row.appendChild(img); row.appendChild(meta); row.appendChild(del);
      box.appendChild(row);

      row.addEventListener('mousedown', function (e) {
        if (e.target.closest('input,button,select')) return;
        setSel(s.id);
      });

      l1.querySelector('input').addEventListener('change', function () { s.fixed = this.checked; renderPreviewStickers(); markDirty(); });
      l2.querySelector('input').addEventListener('change', function () { s.mobile = this.checked; renderPreviewStickers(); markDirty(); });
      op.querySelector('input').addEventListener('input', function () {
        s.opacity = +this.value;
        op.querySelector('em').textContent = this.value;
        var im = findPrevImg(s.id);
        if (im) im.style.opacity = String(s.opacity / 100);
        markDirty();
      });
      sz.querySelector('input').addEventListener('input', function () {
        s.size = +this.value;
        sz.querySelector('em').textContent = this.value;
        var im = findPrevImg(s.id);
        if (im) im.style.width = this.value + 'px';
        markDirty();
      });
      zn.addEventListener('change', function () {
        s.z = Math.min(999, Math.max(1, parseInt(zn.value, 10) || 1));
        zn.value = String(s.z);
        updateStkXY(s);
        renderPreviewStickers();
        markDirty();
      });
      zbTop.addEventListener('click', function () {
        s.z = nextZ();
        zn.value = String(s.z);
        updateStkXY(s);
        renderPreviewStickers();
        markDirty();
      });
      zbBtm.addEventListener('click', function () {
        s.z = 1;
        zn.value = String(s.z);
        updateStkXY(s);
        renderPreviewStickers();
        markDirty();
      });
      del.addEventListener('click', function () {
        if (selStkId === s.id) selStkId = null;
        stickers = stickers.filter(function (x) { return x.id !== s.id; });
        renderStickerRows();
        renderPreviewStickers();
        updateCount();
        markDirty();
      });
    });
    updateCount();
  }
  function findPrevImg(sid) {
    var doc = ensureDoc();
    if (!doc) return null;
    var w = doc.querySelector('.vz-e-wrap[data-sid="' + sid + '"]');
    return w ? w.querySelector('img.stk') : null;
  }
  function setSel(sid) {
    selStkId = sid;
    [].forEach.call(document.querySelectorAll('.vz-stkrow'), function (r) {
      r.classList.toggle('sel', r.dataset.id === sid);
    });
    renderPreviewStickers();
  }
  function renderPreviewStickers() {
    var doc = ensureDoc();
    if (!doc || !doc.body) return;
    if (!doc.getElementById('vz-stk-style')) {
      var cs = doc.createElement('style');
      cs.id = 'vz-stk-style';
      cs.textContent = '.vz-e-wrap{position:absolute;line-height:0;cursor:move;pointer-events:auto;'
        + '-webkit-user-select:none;user-select:none;touch-action:none}'
        + '.vz-e-wrap.vz-drag{cursor:grabbing}'
        + '.vz-e-wrap img.stk{position:relative;display:block;pointer-events:none}'
        + '.vz-e-wrap .vz-e-ring{position:absolute;inset:0;border:1.5px dashed rgba(99,102,241,.9);border-radius:5px;pointer-events:none}'
        + '.vz-e-wrap.vz-sel .vz-e-ring{border:2px solid #6366f1}'
        + '.vz-e-wrap .vz-e-lab{position:absolute;left:0;transform:translateY(-100%);max-width:220px;overflow:hidden;'
        + 'text-overflow:ellipsis;white-space:nowrap;font:normal 10px/1.5 system-ui,sans-serif;color:#fff;'
        + 'background:rgba(79,80,214,.92);padding:2px 7px;border-radius:5px;pointer-events:none}'
        + '@media(max-width:820px){.vz-e-wrap.vz-nomob{display:none!important}}';
      doc.head.appendChild(cs);
    }
    doc.querySelectorAll('.vz-e-wrap,img.stk').forEach(function (el) { el.remove(); });
    var host = doc.getElementById('vz-stkhost');
    if (!host) { host = doc.createElement('div'); host.id = 'vz-stkhost'; doc.body.appendChild(host); }
    var lib = {};
    libList.forEach(function (n) { lib[n] = 1; });
    stickers.forEach(function (s) {
      if (!lib[s.file]) return;
      var box = doc.createElement('div');
      box.className = 'vz-e-wrap' + (s.id === selStkId ? ' vz-sel' : '');
      box.dataset.sid = s.id;
      box.style.cssText = 'position:' + (s.fixed ? 'absolute' : 'fixed') + ';left:' + s.x + '%;top:' + s.y + '%;'
        + 'z-index:' + s.z + ';';
      var im = doc.createElement('img');
      im.className = 'stk';
      im.src = '/stk/' + encodeURIComponent(s.file);
      im.alt = '';
      im.loading = 'lazy';
      im.style.cssText = 'width:' + s.size + 'px;max-width:none;opacity:' + String(s.opacity / 100);
      if (!s.mobile) box.classList.add('vz-nomob');
      var ring = doc.createElement('span');
      ring.className = 'vz-e-ring';
      box.appendChild(im); box.appendChild(ring);
      if (s.id === selStkId) {
        var lab = doc.createElement('span');
        lab.className = 'vz-e-lab';
        lab.textContent = s.file + ' · 层级 ' + s.z;
        box.appendChild(lab);
      }
      box.addEventListener('mousedown', stickerPointer(s, box));
      host.appendChild(box);
    });
  }
  function stickerPointer(s, box) {
    return function (ev) {
      if (ev.button !== 0) return;
      ev.preventDefault();
      ev.stopPropagation();
      var doc = ensureDoc();
      if (!doc) return;
      var win = doc.defaultView;
      var moved = false;
      box.classList.add('vz-drag');
      if (s.id !== selStkId) setSel(s.id);
      var rect = box.getBoundingClientRect();
      var offX = ev.clientX - rect.left;
      var offY = ev.clientY - rect.top;
      function mm(e) {
        var vw = doc.documentElement.clientWidth || win.innerWidth;
        var vh = doc.documentElement.clientHeight || win.innerHeight;
        var ex = e.clientX - offX, ey = e.clientY - offY;
        if (s.fixed) { ex += (win.scrollX || win.pageXOffset || 0); ey += (win.scrollY || win.pageYOffset || 0); }
        s.x = Math.min(135, Math.max(-35, (ex / vw) * 100));
        s.y = Math.min(135, Math.max(-35, (ey / vh) * 100));
        box.style.left = s.x + '%';
        box.style.top = s.y + '%';
        updateStkXY(s);
        moved = true;
      }
      function stop() {
        doc.removeEventListener('mousemove', mm);
        doc.removeEventListener('mouseup', stop);
        doc.removeEventListener('mouseleave', stop);
        box.classList.remove('vz-drag');
        if (moved) { toast('贴纸位置已更新，点击「保存布局」生效'); markDirty(); }
      }
      doc.addEventListener('mousemove', mm);
      doc.addEventListener('mouseup', stop);
      doc.addEventListener('mouseleave', stop);
    };
  }
  function loadLibrary() {
    fetch('/admin/api/stickers', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        libList = ((d && d.list) || []).map(function (x) { return x.name; });
        var lib = {}; libList.forEach(function (n) { lib[n] = 1; });
        var before = stickers.length;
        stickers = stickers.filter(function (s) { return lib[s.file]; });
        if (stickers.length !== before && before > 0) toast('部分贴纸图片已不存在，已移除');
        renderStickerRows();
        renderPreviewStickers();
        updateStkEmpty();
      })
      .catch(function () {
        renderStickerRows();
        renderPreviewStickers();
        updateStkEmpty();
      });
  }
  document.getElementById('vzStkAdd').addEventListener('click', openPicker);
  function openPicker() {
    var grid = document.getElementById('vzPickGrid');
    var box = document.getElementById('vzPick');
    grid.textContent = '';
    box.hidden = false;
    fetch('/admin/api/stickers', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var list = (d && d.list) || [];
        if (list.length) { libList = list.map(function (x) { return x.name; }); updateStkEmpty(); }
        if (!list.length) {
          grid.innerHTML = '<div class="vz-pickempty">图库还没有图片，请先前往贴纸图库上传</div>';
          return;
        }
        list.forEach(function (it) {
          var c = document.createElement('button');
          c.type = 'button';
          c.className = 'vz-pickcard';
          c.innerHTML = '<img src="' + esc2(it.url) + '" alt="" loading="lazy"><span>' + esc2(it.name) + '</span>';
          c.addEventListener('click', function () { addSticker(it.name); box.hidden = true; });
          grid.appendChild(c);
        });
      })
      .catch(function () { grid.innerHTML = '<div class="vz-pickempty">图库加载失败</div>'; });
  }
  document.getElementById('vzPickClose').addEventListener('click', function () {
    document.getElementById('vzPick').hidden = true;
  });
  document.getElementById('vzPick').addEventListener('mousedown', function (e) {
    if (e.target === this) this.hidden = true;
  });

  /* ================== 恢复默认 ================== */
  function resetToDefaults() {
    list.querySelectorAll('.vz-item[data-kind="custom"]').forEach(function (li) { li.remove(); });
    var ids = Object.keys(DEFAULTS);
    ids.forEach(function (id) {
      var li = null;
      list.querySelectorAll('.vz-item[data-kind="builtin"]').forEach(function (x) { if (x.dataset.id === id) li = x; });
      if (li) list.appendChild(li);
    });
    list.querySelectorAll('.vz-item[data-kind="builtin"]').forEach(function (li) {
      var id = li.dataset.id, d = DEFAULTS[id] || {};
      var chk = li.querySelector('.vz-vis input');
      if (chk) chk.checked = !!(d.show !== false);
      var lab = li.querySelector('.vz-label');
      if (lab) lab.value = d.label || '';
    });
    document.getElementById('vzQ').value = '';
    activeFilt = 'all';
    [].forEach.call(document.getElementById('vzFilters').children, function (b) { b.classList.toggle('on', b.dataset.f === 'all'); });
    applyListFilter();
    applyPreview();
    toast('已恢复默认布局，点击「保存布局」生效');
  }
  document.getElementById('vzResetBtn').addEventListener('click', resetToDefaults);
  var resetBtn2 = document.getElementById('vzResetBtn2');
  if (resetBtn2) resetBtn2.addEventListener('click', resetToDefaults);

  /* ================== 保存 ================== */
  function save() {
    var savers = [document.getElementById('vzSaveBtn'), document.getElementById('vzSaveBtn2')];
    savers.forEach(function (b) { if (b) b.disabled = true; });
    fetch('/admin/api/layout', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      cache: 'no-store',
      body: JSON.stringify({ blocks: collectState(), stickers: stickers })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          dirty = false;
          toast('布局已保存');
          if (d.stickers) {
            stickers.length = 0;
            if (Array.isArray(d.stickers)) d.stickers.forEach(function (s) { stickers.push(s); });
            else Object.keys(d.stickers).forEach(function (k) { stickers.push(d.stickers[k]); });
            renderStickerRows();
          }
          return;
        }
        toast('保存失败', (d && d.msg) || '未知错误', true);
      })
      .catch(function () { toast('保存失败', '网络错误', true); })
      .then(function () { savers.forEach(function (b) { if (b) b.disabled = false; }); });
  }
  document.getElementById('vzSaveBtn').addEventListener('click', save);
  if (document.getElementById('vzSaveBtn2')) document.getElementById('vzSaveBtn2').addEventListener('click', save);

  /* ================== 新窗口预览 ================== */
  document.getElementById('vzOpenBtn').addEventListener('click', function () {
    window.open('/', '_blank');
  });

  /* ================== 初始化 ================== */
  (function init() {
    var saved = null;
    try { saved = JSON.parse(localStorage.getItem(DESK_KEY) || 'null'); } catch (e) {}
    if (saved && typeof saved.x === 'number') placeXY(saved.x, saved.y);
    else {
      var w = drawer.offsetWidth || 356;
      drawer.style.left = (stage.clientWidth - w - 12) + 'px';
      drawer.style.top = '12px';
    }
    applyListFilter();
    updateCount();
    loadLibrary();
  })();
  window.addEventListener('resize', function () {
    placeXY(parseInt(drawer.style.left, 10) || 0, parseInt(drawer.style.top, 10) || 0);
  });
  frame.addEventListener('load', function () {
    var doc = ensureDoc();
    if (doc && doc.head) {
      var th = document.documentElement.getAttribute('data-theme');
      if (th && doc.documentElement.getAttribute('data-theme') !== th) doc.documentElement.setAttribute('data-theme', th);
      applyPreview();
      protectPreview();
      applyGrid();
    }
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
})();
</script>
<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
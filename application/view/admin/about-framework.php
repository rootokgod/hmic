<?php
/**
 * 关于程序 - 框架与引用
 * @var string $user
 * @var string $pageTitle
 * @var string $activeNav
 * @var string $phpVersion
 * @var string $thinkVersion
 * @var array  $deps
 * @var string $echartsVersion
 */
include __DIR__ . '/partials/head.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
  <div class="panel">
    <h2>运行环境</h2>
    <div class="body">
      <div class="set-row">
        <div><div class="set-t">PHP 版本</div><div class="set-d mono"><?= $h($phpVersion) ?></div></div>
      </div>
      <div class="set-row">
        <div><div class="set-t">框架</div><div class="set-d mono">ThinkPHP <?= $h($thinkVersion) ?>（MIT License）</div></div>
      </div>
      <div class="set-row">
        <div><div class="set-t">Composer 依赖</div>
          <div class="set-d mono" style="line-height:1.9;white-space:pre-wrap"><?= $h(implode("\n", $deps)) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>引用资源</h2>
    <div class="body">
      <div class="set-row">
        <div>
          <div class="set-t">ECharts 图表库</div>
          <div class="set-d">Apache License 2.0 · https://echarts.apache.org/ （本地 v<?= $h($echartsVersion) ?>，并支持在线 CDN 回退）</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">中国地图数据</div>
          <div class="set-d">本地 china.json + 阿里 DataV GeoAtlas 边界数据（http://datav.aliyun.com/portal/school/atlas/area_selector），并按位掩码预生成数据文件</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">IP 归属地解析</div>
          <div class="set-d">ip2region（MIT 协议，官方开放数据，持续更新离线库 resolve/ip2region.xdb）</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">图标</div>
          <div class="set-d">Feather Icons / 内联 SVG（MIT 协议），部分为原创手绘矢量</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">字体</div>
          <div class="set-d">系统字体栈（system-ui / PingFang SC / Microsoft YaHei 等），无第三方商用字体依赖</div>
        </div>
      </div>
      <div class="set-row">
        <div>
          <div class="set-t">其他</div>
          <div class="set-d">Windows 激活脚本仅收录微软官方 KMS 激活通道脚本（mas.ps1），不内置任何第三方激活工具</div>
        </div>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/partials/theme-foot.php'; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
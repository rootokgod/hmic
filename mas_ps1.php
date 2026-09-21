<?php
/**
 * MAS 激活工具脚本 
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '127.0.0.1';
$baseUrl = $scheme . '://' . $host;
$downloadUrl = $baseUrl . '/data/mas/MAS_AIO_CN_v3.12.cmd';

//直接输出完整
$script = <<<'PS'
#MAS

param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Parameters
)

if (-not $Parameters -and -not $args) {
    Write-Host ''
    Write-Host '  Windows 激活工具 (MAS 汉化版)' -ForegroundColor Cyan
    Write-Host ''
}

& {
    $psv = (Get-Host).Version.Major
    $troubleshoot = 'https://github.com/cmontage/mas-cn/issues'

    # 检查 PowerShell 执行模式
    if ($ExecutionContext.SessionState.LanguageMode.value__ -ne 0) {
        Write-Host "PowerShell 未在完整语言模式下运行。"
        Write-Host "帮助 - https://massgrave.dev/troubleshoot" -ForegroundColor White -BackgroundColor Blue
        return
    }

    # 检查 .NET 环境
    try {
        [void][System.AppDomain]::CurrentDomain.GetAssemblies(); [void][System.Math]::Sqrt(144)
    }
    catch {
        Write-Host "错误: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "PowerShell 无法加载 .NET 命令。"
        Write-Host "帮助 - https://massgrave.dev/troubleshoot" -ForegroundColor White -BackgroundColor Blue
        return
    }

    # 检查第三方杀毒软件
    function Check3rdAV {
        $cmd = if ($psv -ge 3) { 'Get-CimInstance' } else { 'Get-WmiObject' }
        try {
            $avList = & $cmd -Namespace root\SecurityCenter2 -Class AntiVirusProduct -ErrorAction SilentlyContinue |
                      Where-Object { $_.displayName -notlike '*windows*' } |
                      Select-Object -ExpandProperty displayName

            if ($avList) {
                Write-Host '第三方杀毒软件可能会阻止脚本运行 - ' -ForegroundColor White -BackgroundColor Blue -NoNewline
                Write-Host " $($avList -join ', ')" -ForegroundColor DarkRed -BackgroundColor White
            }
        } catch {}
    }

    # 检查文件是否创建成功
    function CheckFile {
        param ([string]$FilePath)
        if (-not (Test-Path $FilePath)) {
            Check3rdAV
            Write-Host "无法在临时文件夹中创建 MAS 文件，操作中止！"
            Write-Host "帮助 - $troubleshoot" -ForegroundColor White -BackgroundColor Blue
            throw
        }
    }

    # 设置安全协议
    try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}

    $downloadUrl = '__DOWNLOADURL__'
    $fileName = 'MAS_AIO_CN_v3.12.cmd'
    $version = '3.12.cn'
    $source = '本站'

    Write-Progress -Activity "下载 MAS 文件..." -Status "请稍等"

    $tempFile = [System.IO.Path]::Combine([System.IO.Path]::GetTempPath(), [System.IO.Path]::GetRandomFileName() + ".cmd")
    $downloadSuccess = $false

    try {
        Write-Host "下载链接: $downloadUrl" -ForegroundColor Gray
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("User-Agent", "PowerShell MAS-CN Script")
        $wc.DownloadFile($downloadUrl, $tempFile)
        $downloadSuccess = $true
    } catch {
        Write-Host "下载失败: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Progress -Activity "下载 MAS 文件..." -Status "完成" -Completed

    if (-not $downloadSuccess -or -not (Test-Path $tempFile)) {
        Check3rdAV
        Write-Host "无法下载 MAS 文件，操作中止！"
        Write-Host "帮助 - $troubleshoot" -ForegroundColor White -BackgroundColor Blue
        return
    }

    # 检查文件是否创建成功
    CheckFile $tempFile

    # 启动前设置代码页为936
    $cmdArgs = "/c chcp 936 && `"$tempFile`""
    if ($Parameters) {
        $cmdArgs += " " + ($Parameters -join " ")
    }
    Start-Process -FilePath "cmd.exe" -ArgumentList $cmdArgs -Wait

    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
}
PS;

$script = str_replace('__DOWNLOADURL__', $downloadUrl, $script);

$script = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $script));

echo $script;

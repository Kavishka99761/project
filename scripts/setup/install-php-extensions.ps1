<#
.SYNOPSIS
    Prepares a Windows PHP install (XAMPP by default) for the EDU-SMART API.

.DESCRIPTION
    The Laravel API talks to Microsoft SQL Server through the official
    Microsoft Drivers for PHP for SQL Server (pdo_sqlsrv / sqlsrv) and needs a
    few bundled extensions that XAMPP ships disabled:

        gd, zip   -> PhpSpreadsheet (Excel export), DOCX/PPTX text extraction
        intl      -> Laravel number/locale helpers
        pdo_sqlsrv, sqlsrv -> SQL Server connectivity (downloaded from GitHub)

    The script is idempotent: it detects PHP version / thread-safety /
    architecture, downloads the matching driver build, copies the DLLs into
    <php>\ext, backs up php.ini once, and enables each extension exactly once.

    Requires "ODBC Driver 17 or 18 for SQL Server" (installed with SSMS / SQL
    Server; otherwise: https://aka.ms/odbc18).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup\install-php-extensions.ps1
.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup\install-php-extensions.ps1 -PhpDir "D:\php"
#>
[CmdletBinding()]
param(
    [string]$PhpDir = ""
)

$ErrorActionPreference = 'Stop'

function Resolve-PhpDir {
    param([string]$Hint)
    if ($Hint -and (Test-Path (Join-Path $Hint 'php.exe'))) { return (Resolve-Path $Hint).Path }
    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) { return Split-Path $onPath.Source -Parent }
    foreach ($candidate in 'C:\xampp\php', 'C:\php', 'C:\laragon\bin\php') {
        if (Test-Path (Join-Path $candidate 'php.exe')) { return $candidate }
    }
    throw 'PHP not found. Pass -PhpDir "C:\path\to\php".'
}

$PhpDir = Resolve-PhpDir $PhpDir
$php = Join-Path $PhpDir 'php.exe'
$ini = Join-Path $PhpDir 'php.ini'
$extDir = Join-Path $PhpDir 'ext'

if (-not (Test-Path $ini)) { throw "php.ini not found at $ini" }

# --- Detect build -----------------------------------------------------------
# (Windows PowerShell 5.1 drops embedded double quotes when calling native
#  executables, so the inline PHP below only uses single quotes.)
$info = & $php -n -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION . ''|'' . (PHP_ZTS ? ''ts'' : ''nts'') . ''|'' . (PHP_INT_SIZE === 8 ? ''x64'' : ''x86'');'
$ver, $ts, $arch = $info.Split('|')
Write-Host "PHP $($ver.Insert(1,'.')) ($ts, $arch) at $PhpDir" -ForegroundColor Cyan

# Driver 5.12 is the last release with PHP 8.1/8.2 builds; 5.13 covers 8.3+.
$release = if ([int]$ver -le 82) { '5.12.0' } else { '5.13.3' }
$pdoDll = "php_pdo_sqlsrv_${ver}_${ts}_${arch}.dll"
$srvDll = "php_sqlsrv_${ver}_${ts}_${arch}.dll"

# --- Download the SQL Server drivers (skipped when already present) ----------
if (-not ((Test-Path (Join-Path $extDir $pdoDll)) -and (Test-Path (Join-Path $extDir $srvDll)))) {
    $tmp = Join-Path ([IO.Path]::GetTempPath()) "msphpsql-$release"
    New-Item -ItemType Directory -Force -Path $tmp | Out-Null
    $zip = Join-Path $tmp 'drivers.zip'
    $url = "https://github.com/microsoft/msphpsql/releases/download/v$release/Windows_${release}RTW.zip"
    Write-Host "Downloading Microsoft Drivers for PHP for SQL Server $release ..."
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
    Expand-Archive -Path $zip -DestinationPath $tmp -Force
    foreach ($dll in $pdoDll, $srvDll) {
        $src = Get-ChildItem -Path $tmp -Recurse -Filter $dll | Select-Object -First 1
        if (-not $src) { throw "$dll is not part of driver release $release." }
        Copy-Item $src.FullName -Destination $extDir -Force
        Write-Host "  copied $dll"
    }
}

# --- Enable extensions in php.ini ---------------------------------------------
$backup = "$ini.edusmart-backup"
if (-not (Test-Path $backup)) {
    Copy-Item $ini $backup
    Write-Host "Backed up php.ini -> $backup"
}

$content = Get-Content $ini -Raw
$changed = $false

foreach ($name in 'gd', 'zip', 'intl') {
    # [ \t]* (never \s*) so a pattern cannot swallow neighbouring line breaks.
    $enabled  = "(?m)^[ \t]*extension[ \t]*=[ \t]*(php_)?$name(\.dll)?[ \t]*\r?$"
    $disabled = "(?m)^[ \t]*;[ \t]*extension[ \t]*=[ \t]*((php_)?$name(\.dll)?)[ \t]*(\r?)$"
    if ($content -match $enabled) { continue }
    if ($content -match $disabled) {
        $content = [regex]::Replace($content, $disabled, 'extension=$1$4')
    } else {
        $content = $content.TrimEnd() + "`r`nextension=$name`r`n"
    }
    $changed = $true
    Write-Host "  enabled $name"
}

$driverLines = @($pdoDll, $srvDll) | Where-Object { $content -notmatch [regex]::Escape($_) }
if ($driverLines) {
    $content = $content.TrimEnd() + "`r`n`r`n; EDU-SMART: Microsoft Drivers for PHP for SQL Server`r`n"
    foreach ($dll in $driverLines) {
        $content += "extension=$dll`r`n"
        Write-Host "  enabled $dll"
    }
    $changed = $true
}

if ($changed) { Set-Content -Path $ini -Value $content -NoNewline -Encoding ascii }

# --- Verify ----------------------------------------------------------------------
$loaded = & $php -r 'foreach ([''pdo_sqlsrv'',''sqlsrv'',''gd'',''zip'',''intl'',''fileinfo'',''mbstring'',''openssl''] as $e) echo $e . ''='' . (extension_loaded($e) ? ''1'' : ''0'') . PHP_EOL;'
$missing = $loaded | Where-Object { $_ -match '=0$' }
$loaded | ForEach-Object { Write-Host "  $_" }
if ($missing) { throw "Some extensions failed to load: $($missing -join ', ')" }
Write-Host 'PHP is ready for EDU-SMART.' -ForegroundColor Green

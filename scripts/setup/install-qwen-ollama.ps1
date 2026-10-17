<#
.SYNOPSIS
    Installs Ollama and pulls the Qwen3 model used by AcadeAlert's Academic
    Assistant (Kavishka's module).

.DESCRIPTION
    The assistant answers through an OpenAI-compatible endpoint. Locally that is
    Ollama on http://127.0.0.1:11434/v1, which needs no API key. This script:

      1. installs Ollama with winget (skipped if it is already installed);
      2. pulls the model (default qwen3:8b, about 5 GB);
      3. checks the server answers and the model is listed.

    Nothing in the project changes — the API reads LLM_* from apps/api/.env,
    which already points at this endpoint.

.PARAMETER Model
    Model tag to pull. qwen3:8b is the default; qwen3:4b is roughly half the
    size and noticeably faster on a laptop without a GPU.

.PARAMETER SkipInstall
    Only pull the model and check the server, without touching winget.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup\install-qwen-ollama.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup\install-qwen-ollama.ps1 -Model qwen3:4b
#>
[CmdletBinding()]
param(
    [string] $Model = 'qwen3:8b',
    [switch] $SkipInstall
)

$ErrorActionPreference = 'Stop'

function Write-Step([string] $Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Write-Ok([string] $Text) { Write-Host "    $Text" -ForegroundColor Green }
function Write-Warn([string] $Text) { Write-Host "    $Text" -ForegroundColor Yellow }

$ollama = (Get-Command ollama -ErrorAction SilentlyContinue)?.Source

if (-not $ollama -and -not $SkipInstall) {
    Write-Step 'Installing Ollama'
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw 'winget is not available. Install Ollama manually from https://ollama.com/download and re-run with -SkipInstall.'
    }
    winget install --id Ollama.Ollama --accept-source-agreements --accept-package-agreements
    # winget does not refresh this session's PATH.
    $env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
    $ollama = (Get-Command ollama -ErrorAction SilentlyContinue)?.Source
}

if (-not $ollama) {
    throw 'ollama is still not on PATH. Open a new terminal and re-run this script with -SkipInstall.'
}
Write-Ok "ollama: $ollama"

Write-Step "Pulling $Model (this is a few GB the first time)"
& ollama pull $Model
if ($LASTEXITCODE -ne 0) { throw "ollama pull $Model failed." }

Write-Step 'Checking the server'
$base = 'http://127.0.0.1:11434'
try {
    $tags = Invoke-RestMethod -Uri "$base/api/tags" -TimeoutSec 10
} catch {
    Write-Warn 'Server not answering yet — starting it in the background.'
    Start-Process -FilePath $ollama -ArgumentList 'serve' -WindowStyle Hidden
    Start-Sleep -Seconds 4
    $tags = Invoke-RestMethod -Uri "$base/api/tags" -TimeoutSec 10
}

$names = @($tags.models | ForEach-Object { $_.name })
Write-Ok "models installed: $($names -join ', ')"
if ($names -notcontains $Model) { Write-Warn "$Model is not in that list — check the pull output above." }

Write-Step 'Asking the model one question'
$body = @{
    model    = $Model
    messages = @(@{ role = 'user'; content = 'Reply with exactly: AcadeAlert ready' })
    stream   = $false
    think    = $false
} | ConvertTo-Json -Depth 5

$reply = Invoke-RestMethod -Uri "$base/v1/chat/completions" -Method Post -ContentType 'application/json' -Body $body -TimeoutSec 180
$text = $reply.choices[0].message.content -replace '(?s)<think>.*?</think>', ''
Write-Ok "model says: $($text.Trim())"

Write-Host ''
Write-Host 'Done. The Academic Assistant will now answer through this model.' -ForegroundColor Green
Write-Host "  apps/api/.env       LLM_ENABLED=true, LLM_MODEL=$Model, LLM_BASE_URL=$base/v1"
Write-Host '  static demo         frontend-web/assistant.html talks to the same endpoint from the browser'
if ($Model -ne 'qwen3:8b') {
    Write-Host "  remember to set LLM_MODEL=$Model in apps/api/.env, and in the demo run:" -ForegroundColor Yellow
    Write-Host "      ES.llm.save({ model: '$Model' })   (browser console, once)" -ForegroundColor Yellow
}

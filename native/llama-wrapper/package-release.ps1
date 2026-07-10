# package-release.ps1 — Build ferry_llama.dll and stage it + SHA256 in native-binaries/windows-x86_64/
#
# Usage:
#   powershell -File native/llama-wrapper/package-release.ps1 -LlamaDir C:\llama
#   powershell -File native/llama-wrapper/package-release.ps1 -LlamaDir C:\llama -OutDir native-binaries/windows-x86_64
#
# Requires Visual Studio 2022 and the llama.cpp build (DLLs + headers + .lib files) in -LlamaDir.

param(
    [Parameter(Mandatory = $true)]
    [string]$LlamaDir,
    [string]$OutDir = "native-binaries/windows-x86_64"
)

$ErrorActionPreference = "Stop"

$buildScript = Join-Path $PSScriptRoot "build.ps1"
$dll = Join-Path $LlamaDir "ferry_llama.dll"

Write-Host "=== Build ferry_llama.dll ==="
& $buildScript -LlamaDir $LlamaDir

if (-not (Test-Path $dll)) { throw "Build did not produce $dll" }

$name = "ferry_llama-windows-x86_64.dll"
$dest = Join-Path $OutDir $name
$destDir = Split-Path $dest -Parent
if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }

Copy-Item $dll $dest -Force
Write-Host "=== Copied $name to $dest ==="
Write-Host "$((Get-Item $dest).Length) bytes"

$hashFile = Join-Path $destDir "$name.sha256"
$hash = (Get-FileHash -Algorithm SHA256 $dest).Hash.ToLower()
Set-Content -Path $hashFile -Value "$hash  $name"
Write-Host "=== SHA256 $hash ==="

Write-Host "=== Package complete: $dest ==="

# Build ferry_llama.dll — flat C wrapper around llama.cpp for PHP FFI.
#
# Why: PHP FFI cannot reliably pass llama.cpp structs (llama_model_params,
# llama_context_params) BY VALUE — the layout it infers mismatches the
# Clang-built DLL and crashes on model load (see native/llama-wrapper/README.md).
# This wrapper builds those structs inside a real C DLL (MSVC), exposing a flat
# API that only takes pointers/ints/strings across the FFI boundary.
#
# Prerequisites (place all in -LlamaDir):
#   1. llama.cpp Windows build DLLs: llama.dll, ggml.dll, ggml-base.dll,
#      ggml-cpu-*.dll, and (for GPU) ggml-cuda.dll + CUDA runtime DLLs.
#        Source: https://github.com/ggml-org/llama.cpp/releases
#                (CUDA build: llama-bXXXX-bin-win-cuda-*.zip)
#   2. Matching headers (same build/commit) next to the DLLs:
#      llama.h, ggml.h, ggml-cpu.h, ggml-backend.h, ggml-alloc.h, ggml-opt.h, gguf.h
#        Source: https://github.com/ggml-org/llama.cpp  (ggml/include + include)
#   3. Import libs generated from the DLLs (this script creates them if missing):
#      llama.lib, ggml.lib  (via dumpbin /exports -> .def -> lib /def)
#   4. Visual Studio 2022 (cl, lib, dumpbin).
#
# Usage:
#   powershell -File build.ps1 -LlamaDir <dir> [-Out <dir>\ferry_llama.dll]

param(
    [Parameter(Mandatory = $true)]
    [string]$LlamaDir,
    [string]$Out = ""
)

if ($Out -eq "") { $Out = Join-Path $LlamaDir "ferry_llama.dll" }

# Locate the Visual Studio VS 2022+ installation. GH Actions runners ship
# VS Enterprise; local dev machines may have Community, Professional or BuildTools.
# vswhere is bundled with any VS 2017+ install and is on PATH inside a VS
# developer prompt; on GitHub runners it lives under the well-known path.
$vswhere = if (Get-Command vswhere -ErrorAction SilentlyContinue) {
    "vswhere"
} else {
    "${Env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
}

$vsPath = & $vswhere -latest -products * -property installationPath 2>$null

if ($vsPath) {
    $vcvars = Join-Path $vsPath "VC\Auxiliary\Build\vcvars64.bat"
    if (-not (Test-Path $vcvars)) {
        throw "vcvars64.bat not found at $vcvars"
    }
} else {
    # Fallback for environments where vswhere is not available
    $vcvars = "${Env:ProgramFiles}\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat"
    if (-not (Test-Path $vcvars)) {
        throw "vcvars64.bat not found (tried vswhere + Community fallback). Ensure Visual Studio 2022 is installed."
    }
}

$src = Join-Path $PSScriptRoot "ferry_llama.c"
$bat = Join-Path $env:TEMP "ferry_build.bat"

# Generate import libs (llama.lib, ggml.lib) from the DLLs if they are missing.
$genLibs = @"
@echo off
call "$vcvars" >nul
cd /d "$LlamaDir"
for %%D in (llama ggml) do (
  if not exist %%D.lib (
    dumpbin /exports %%D.dll > "%TEMP%\%%D_exports.txt"
    powershell -NoProfile -Command "$lines = Get-Content \"$env:TEMP\%%D_exports.txt\"; $n=@(); foreach ($l in $lines){ if ($l -match '^\s+\d+\s+[0-9A-Fa-f]+\s+[0-9A-Fa-f]{8}\s+(\S+)'){ $n+=$Matches[1] } }; Set-Content '%%D.def' 'EXPORTS'; Add-Content '%%D.def' $n"
    lib /def:%%D.def /out:%%D.lib /machine:x64 >nul
  )
)
cl /nologo /LD /O2 /I"$LlamaDir" "$src" /Fe:"$Out" /Fo:"%TEMP%\ferry_llama.obj" /link /LIBPATH:"$LlamaDir" llama.lib ggml.lib
echo BUILD_EXIT=%ERRORLEVEL%
"@

Set-Content -Path $bat -Value $genLibs -Encoding Ascii
cmd /c $bat

if (Test-Path $Out) { Write-Host "OK: $Out" } else { throw "Build failed" }

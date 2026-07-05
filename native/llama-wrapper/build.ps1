# Build ferry_llama.dll — flat C wrapper around llama.cpp for PHP FFI.
#
# Why: PHP FFI cannot reliably pass llama.cpp structs (llama_model_params,
# llama_context_params) BY VALUE — the layout it infers mismatches the
# Clang-built DLL and crashes on model load (DEBT_REPORT.md #12). This wrapper
# builds those structs inside a real C DLL (MSVC), exposing a flat API that only
# takes pointers/ints/strings across the FFI boundary.
#
# Prerequisites (place all in $LlamaDir, default D:\FerryAI):
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
#   powershell -File build.ps1 [-LlamaDir D:\FerryAI] [-Out D:\FerryAI\ferry_llama.dll]

param(
    [string]$LlamaDir = "D:\FerryAI",
    [string]$Out = ""
)

if ($Out -eq "") { $Out = Join-Path $LlamaDir "ferry_llama.dll" }

$vcvars = "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat"
if (-not (Test-Path $vcvars)) { throw "vcvars64.bat not found: $vcvars" }

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

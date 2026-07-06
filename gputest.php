<?php
require "vendor/autoload.php";
use FerryAI\AI;
putenv("FERRY_AI_LLAMA_WRAPPER=D:\\FerryAI\\ferry_llama.dll");
putenv("PATH=D:\\FerryAI;" . (getenv("PATH") ?: ""));
AI::config(["backend"=>"llama","device"=>"cuda","backends"=>["llama"=>["model_path"=>"D:\\FerryAI\\qwen3-0.6b-f16.gguf"]]]);
echo "--- GPU chat ---\n";
$r = AI::chat([["role"=>"user","content"=>"Capital of France? One word."]], ["max_tokens"=>5]);
echo "RESULT: " . $r->text . " (" . $r->tokensGenerated . " tok, " . round($r->durationMs) . " ms)\n";
echo "--- GPU grammar ---\n";
$r2 = AI::chat([["role"=>"user","content"=>"Is the sky blue?"]], ["grammar"=>'root ::= "yes" | "no"', "max_tokens"=>6]);
echo "GRAMMAR: " . trim($r2->text) . " (" . round($r2->durationMs) . " ms)\n";
echo "=== OK ===\n";

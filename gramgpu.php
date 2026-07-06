<?php
require "vendor/autoload.php";
use FerryAI\AI;
putenv("FERRY_AI_LLAMA_WRAPPER=D:\\FerryAI\\ferry_llama.dll");
putenv("PATH=D:\\FerryAI;" . (getenv("PATH") ?: ""));
AI::config(["backend"=>"llama","device"=>"cuda","backends"=>["llama"=>["model_path"=>"D:\\FerryAI\\qwen3-0.6b-f16.gguf"]]]);
$start = microtime(true);
$r = AI::chat([["role"=>"user","content"=>"Is the sky blue? Answer only yes or no."]], ["grammar"=>'root ::= "yes" | "no"', "max_tokens"=>6]);
$elapsed = round((microtime(true)-$start)*1000);
echo "RESULT: " . trim($r->text) . " (" . $r->tokensGenerated . " tok, " . $elapsed . " ms)\n";
echo "=== OK ===\n";

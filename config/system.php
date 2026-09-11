<?php
// Avoid Apple's xcrun shim when Intel XAMPP runs on Apple Silicon.
$gitBinary=getenv('VRS_GIT_BINARY');
if(!$gitBinary){$gitBinary='git';if(PHP_OS_FAMILY==='Darwin')foreach(['/opt/homebrew/bin/git','/Library/Developer/CommandLineTools/usr/bin/git'] as $candidate)if(is_executable($candidate)){$gitBinary=$candidate;break;}}
return [
    'name' => 'Vehicle Requisition & Scheduling',
    'timezone' => 'Asia/Manila',
    'demo' => true,
    'update_repository' => 'MrMilar12/VRS',
    'update_branch' => 'main',
    'update_git_binary' => $gitBinary,
    'update_php_binary' => getenv('VRS_PHP_BINARY') ?: PHP_BINDIR.'/php',
    'booking_ai_provider' => getenv('BOOKING_AI_PROVIDER') ?: 'ollama',
    'ollama_url' => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434',
    'ollama_model' => getenv('OLLAMA_MODEL') ?: 'gpt-oss:120b-cloud',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
    'dsn' => 'mysql:host=127.0.0.1;dbname=vrs;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
];

<?php
return [
    'name' => 'Vehicle Requisition & Scheduling',
    'timezone' => 'Asia/Manila',
    'demo' => true,
    'update_repository' => 'MrMilar12/VRS',
    'update_branch' => 'main',
    'update_php_binary' => getenv('VRS_PHP_BINARY') ?: PHP_BINDIR.'/php',
    'booking_ai_provider' => getenv('BOOKING_AI_PROVIDER') ?: 'ollama',
    'ollama_url' => getenv('OLLAMA_URL') ?: 'https://ollama.com',
    'ollama_model' => getenv('OLLAMA_MODEL') ?: 'gpt-oss:20b',
    'ollama_api_key' => getenv('OLLAMA_API_KEY') ?: '',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
    'dsn' => 'mysql:host=127.0.0.1;dbname=vrs;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
];

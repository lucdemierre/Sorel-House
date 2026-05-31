<?php

return [
    'app_name' => 'Sorel House',
    'timezone' => 'Europe/London',
    // SQLite works on most shared hosts. For MySQL use:
    // 'dsn' => 'mysql:host=localhost;dbname=sorel_house;charset=utf8mb4',
    'dsn' => 'sqlite:' . __DIR__ . '/storage/sorel-house.sqlite',
    'db_user' => '',
    'db_password' => '',
    // Set to false after checking the interface with the starter records.
    'seed_demo_data' => true,
    // Change this before sharing the site. This MVP supports one landlord account.
    'landlord_email' => 'landlord@example.com',
    'landlord_password' => 'change-me',
    // Use 'openrouter' or 'anthropic'.
    'ai_provider' => 'openrouter',
    'openrouter_api_key' => '',
    'openrouter_model' => 'nvidia/nemotron-nano-12b-v2-vl:free',
    'openrouter_site_url' => 'http://localhost:8080',
    'anthropic_api_key' => '',
    'anthropic_model' => 'claude-sonnet-4-6',
];

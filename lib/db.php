<?php
// シンプルな PDO 接続ヘルパー
// 期待: config/config.php が $config 配列を返すか定義する

function get_db(): PDO
{
    // config/config.php はリポジトリには含めない設計
    $configPath = __DIR__ . '/../config/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException("Missing config file: $configPath. Copy config/config.example.php -> config/config.php and fill values.");
    }
    $config = require $configPath;

    $dsn = $config['db_dsn'] ?? null;
    $user = $config['db_user'] ?? null;
    $pass = $config['db_pass'] ?? null;
    $options = $config['db_options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (!$dsn) {
        throw new RuntimeException('DB DSN not configured in config/config.php');
    }

    return new PDO($dsn, $user, $pass, $options);
}

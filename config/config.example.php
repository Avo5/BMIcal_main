<?php
/**
 * config/config.example.php
 * 
 * このファイルはテンプレートです。
 * 本番環境では config/config.php を作成して使用してください。
 * config.php は .gitignore に登録されているため、リポジトリには含まれません。
 */

return [
    // Database Configuration
    // ローカル (MAMP) での設定例:
    'db_dsn' => 'mysql:dbname=tech_base_php;unix_socket=/tmp/mysql.sock;charset=utf8mb4',
    
    // 別マシンで実行する場合の設定例:
    // 'db_dsn' => 'mysql:host=your.db.host;port=3306;dbname=tech_base_php;charset=utf8mb4',
    
    'db_user' => 'root',
    'db_pass' => '',  // MAMP: パスワードなし / 本番: 実際のパスワードに置き換え
];

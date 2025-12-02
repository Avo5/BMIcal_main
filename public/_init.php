<?php
// 公開ページ用ブートストラップ
// ここに共通初期化処理を集約する（session, エラーハンドラ, 共通ライブラリ読み込みなど）

// Ensure session cookie is valid for the project path and HttpOnly for safety.
if (session_status() === PHP_SESSION_NONE) {
    // set cookie params so session cookie is available at root of the site
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        // 'secure' => true, // enable in production with HTTPS
    ]);
    session_start();
}

// 必要な共通ライブラリを読み込む
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

// 追加の初期化やセキュリティヘッダをここで設定可能
// header('Content-Security-Policy: default-src 'self');
?>
<?php
// 認証ヘルパー
// 注: session_start() は public ページで呼び出すこと

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user(): ?array
    {
        $uid = current_user_id();
        if (!$uid) return null;
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT id, username, birth_date, sex, created_at, updated_at FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: null;
    }
}

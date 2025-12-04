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
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return (int)$_SESSION['user_id'];
    }
}

// NOTE: don't name this get_current_user() because PHP has a built-in function
// with that name (returns the OS user). Use current_user() to avoid collision.
if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        $uid = current_user_id();
        if (!$uid) return null;
        $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username, birth_date, sex, height_cm, activity_level, created_at, updated_at FROM users WHERE id = ?');
        $stmt->execute([(int)$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        return $row;
    }
}

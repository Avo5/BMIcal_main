<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

// ログイン済みならダッシュボード、未ログインならログイン画面へ
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;

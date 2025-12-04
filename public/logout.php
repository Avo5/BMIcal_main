<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

// セッションを破棄
session_destroy();

// ログインページへリダイレクト
header('Location: /login.php');
exit;

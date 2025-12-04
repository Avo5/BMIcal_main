<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = current_user();

// ユーザー情報が取得できない場合はエラー
if (!$user || !is_array($user)) {
    die('ユーザー情報を取得できませんでした。');
}

// GET パラメータで record_id を取得
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$record_id) {
    die('記録 ID が指定されていません。');
}

// 既存レコードを確認（所有権確認）
$stmt = $pdo->prepare('SELECT id FROM body_records WHERE id = ? AND user_id = ?');
$stmt->execute([$record_id, $user['id']]);
$record = $stmt->fetch();

if (!$record) {
    die('指定された記録が見つかりません。');
}

// DELETE 実行
$del = $pdo->prepare('DELETE FROM body_records WHERE id = ? AND user_id = ?');
$del->execute([$record_id, $user['id']]);

// ダッシュボードにリダイレクト
header('Location: /dashboard.php?deleted=1');
exit;

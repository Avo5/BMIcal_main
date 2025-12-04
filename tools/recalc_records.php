<?php
declare(strict_types=1);
// 簡易 CLI スクリプト: 指定ユーザの body_records の BMR/TDEE を再計算
// 使用法: php tools/recalc_records.php <user_id>

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/functions.php';

if (php_sapi_name() !== 'cli') {
    die("このスクリプトは CLI からのみ実行してください。\n");
}

if ($argc < 2) {
    echo "使用法: php tools/recalc_records.php <user_id>\n";
    exit(1);
}

$user_id = (int)$argv[1];

try {
    $pdo = get_db();

    // ユーザを取得
    $user_stmt = $pdo->prepare('SELECT id, birth_date, sex FROM users WHERE id = ?');
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        echo "ユーザが見つかりません: $user_id\n";
        exit(1);
    }

    echo "ユーザ ID: {$user['id']}\n";
    echo "生年月日: {$user['birth_date']}\n";
    echo "性別: {$user['sex']}\n";

    // lib の再計算関数を呼び出す（一元化）
    echo "再計算を実行します...\n";
    $updated = recalc_body_records_for_user($pdo, $user_id);
    echo "更新完了: {$updated} 件\n";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}

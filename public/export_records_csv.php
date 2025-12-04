<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_login();
$pdo = get_db();
$user = current_user();

// Fetch all records for user
$stmt = $pdo->prepare('SELECT record_date, height_cm, weight_kg, bmi, bmr, tdee, memo, created_at FROM body_records WHERE user_id = ? ORDER BY record_date ASC');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = sprintf('records_user_%d_%s.csv', $user['id'], date('Ymd_His'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output BOM for Excel compatibility
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
// Header
fputcsv($out, ['record_date', 'height_cm', 'weight_kg', 'bmi', 'bmr', 'tdee', 'memo', 'created_at']);

foreach ($rows as $r) {
    fputcsv($out, [$r['record_date'], $r['height_cm'], $r['weight_kg'], $r['bmi'], $r['bmr'], $r['tdee'], $r['memo'], $r['created_at']]);
}

fclose($out);
exit;

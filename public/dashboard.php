<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = get_current_user();

// DEBUG: detailed session logging before user check
error_log(sprintf("[DEBUG] dashboard.php at top: session_id=%s, _SESSION user_id=%s, isset=%s, keys=%s, user_result=%s", 
    session_id(), 
    var_export($_SESSION['user_id'] ?? 'NOTSET', true),
    var_export(isset($_SESSION['user_id']), true),
    var_export(array_keys($_SESSION), true),
    var_export($user, true)
));

// ユーザー情報が取得できない場合はエラー
if (!$user || !is_array($user)) {
    error_log('[DEBUG] dashboard.php: get_current_user returned null or non-array');
    die('ユーザー情報を取得できませんでした。');
}

$errors = [];
$success = false;

// フォーム送信: 新しい body_record を追加
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    if (empty($errors)) {
        $record_date = trim((string)($_POST['record_date'] ?? ''));
        $height_cm = trim((string)($_POST['height_cm'] ?? ''));
        $weight_kg = trim((string)($_POST['weight_kg'] ?? ''));
        $memo = trim((string)($_POST['memo'] ?? ''));

        // バリデーション
        if (empty($record_date)) {
            $errors[] = '記録日を指定してください。';
        }
        if (empty($height_cm) || empty($weight_kg)) {
            $errors[] = '身長と体重は必須です。';
        }

        $validation_errors = validate_height_weight($height_cm, $weight_kg);
        $errors = array_merge($errors, $validation_errors);

        if (empty($errors)) {
            // サーバ側で BMI を計算
            $bmi = calc_bmi((float)$weight_kg, (float)$height_cm);

            // birth_date と sex がある場合のみ BMR/TDEE を計算
            $bmr = null;
            $tdee = null;
            if ($user['birth_date'] && $user['sex']) {
                $birth = new DateTime($user['birth_date']);
                $record_dt = new DateTime($record_date);
                $age = age_on_date($birth, $record_dt);
                $bmr = calc_bmr($user['sex'], (float)$weight_kg, (float)$height_cm, $age);
                $tdee = calc_tdee($bmr);
            }

            // INSERT: 新しい body_record を追加（重複許可）
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $ins = $pdo->prepare(
                'INSERT INTO body_records (user_id, record_date, height_cm, weight_kg, memo, bmi, bmr, tdee, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $user['id'],
                $record_date,
                (float)$height_cm,
                (float)$weight_kg,
                $memo ?: null,
                $bmi,
                $bmr,
                $tdee,
                $now,
                $now,
            ]);
            $success = true;
        }
    }
}

// 既存レコード一覧を取得（新しい順）
$stmt = $pdo->prepare(
    'SELECT id, record_date, height_cm, weight_kg, memo, bmi, bmr, tdee FROM body_records WHERE user_id = ? ORDER BY record_date DESC LIMIT 30'
);
$stmt->execute([$user['id']]);
$records = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<h2>ダッシュボード</h2>
<p>ユーザ名: <?php echo htmlspecialchars($user['username']); ?></p>

<?php if ($success): ?>
    <div class="success">記録を追加しました。</div>
<?php endif; ?>

<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h3>新しい記録を追加</h3>
<form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <label>記録日: <input type="date" name="record_date" value="<?php echo htmlspecialchars($_POST['record_date'] ?? date('Y-m-d')); ?>"></label><br>
    <label>身長 (cm): <input type="number" step="0.1" name="height_cm" value="<?php echo htmlspecialchars($_POST['height_cm'] ?? ''); ?>"></label><br>
    <label>体重 (kg): <input type="number" step="0.1" name="weight_kg" value="<?php echo htmlspecialchars($_POST['weight_kg'] ?? ''); ?>"></label><br>
    <label>メモ: <textarea name="memo"><?php echo htmlspecialchars($_POST['memo'] ?? ''); ?></textarea></label><br>
    <button type="submit">追加</button>
</form>

<h3>記録一覧</h3>
<?php if ($records): ?>
    <table border="1">
        <thead>
            <tr>
                <th>日付</th>
                <th>身長 (cm)</th>
                <th>体重 (kg)</th>
                <th>BMI</th>
                <th>BMR</th>
                <th>TDEE</th>
                <th>メモ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['record_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['height_cm']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['weight_kg']); ?></td>
                    <td><?php echo $r['bmi'] ? htmlspecialchars((string)$r['bmi']) : '-'; ?></td>
                    <td><?php echo $r['bmr'] ? htmlspecialchars((string)$r['bmr']) : '-'; ?></td>
                    <td><?php echo $r['tdee'] ? htmlspecialchars((string)$r['tdee']) : '-'; ?></td>
                    <td><?php echo htmlspecialchars((string)($r['memo'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>記録がまだありません。</p>
<?php endif; ?>

<hr>
<p>
    <a href="/settings_profile.php">プロフィール編集</a> |
    <a href="/logout.php">ログアウト</a>
</p>

<?php require_once __DIR__ . '/../templates/footer.php';

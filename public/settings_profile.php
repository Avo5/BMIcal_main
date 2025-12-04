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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    if (empty($errors)) {
    $birth_date = trim((string)($_POST['birth_date'] ?? '')) ?: null;
    $sex = trim((string)($_POST['sex'] ?? '')) ?: null;
    $height_cm = trim((string)($_POST['height_cm'] ?? '')) ?: null;
    $activity_level = trim((string)($_POST['activity_level'] ?? '')) ?: null;

        // sex の値チェック
        if ($sex && !in_array($sex, ['male', 'female', 'other'], true)) {
            $errors[] = '無効な性別です。';
        }
        // activity_level のチェック
        if ($activity_level && !in_array($activity_level, ['low', 'medium', 'high'], true)) {
            $errors[] = '無効な活動レベルです。';
        }

        if (empty($errors)) {
            $now = (new DateTime())->format('Y-m-d H:i:s');

            // プロフィール更新（身長を追加）
            $upd = $pdo->prepare(
                'UPDATE users SET birth_date = ?, sex = ?, height_cm = ?, activity_level = ?, updated_at = ? WHERE id = ?'
            );
            $upd->execute([$birth_date, $sex, $height_cm ? (float)$height_cm : null, $activity_level ?: null, $now, $user['id']]);

            // 既存の body_records の BMR/TDEE を再計算（lib の共通関数を使用）
            $updated = recalc_body_records_for_user($pdo, $user['id']);
            $success = true;
            // 更新後のユーザデータを再取得（height_cm を含める）
            $stmt = $pdo->prepare('SELECT id, username, birth_date, sex, height_cm, activity_level, created_at, updated_at FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<h2>プロフィール編集</h2>

<?php if ($success): ?>
    <div class="success">プロフィールを更新し、過去の記録を再計算しました。（更新件数: <?php echo htmlspecialchars((string)($updated ?? 0)); ?> 件）</div>
<?php endif; ?>

<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <label>生年月日: <input type="date" name="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>"></label><br>
    <label>性別:
        <select name="sex">
            <option value="">選択してください</option>
            <option value="male" <?php echo ($user['sex'] === 'male') ? 'selected' : ''; ?>>男性</option>
            <option value="female" <?php echo ($user['sex'] === 'female') ? 'selected' : ''; ?>>女性</option>
            <option value="other" <?php echo ($user['sex'] === 'other') ? 'selected' : ''; ?>>その他</option>
        </select>
    </label><br>
    <label>身長 (cm): <input type="number" step="0.1" name="height_cm" value="<?php echo htmlspecialchars((string)($user['height_cm'] ?? '')); ?>"></label><br>
    <label>活動レベル:
        <select name="activity_level">
            <option value="">選択してください</option>
            <option value="low" <?php echo ($user['activity_level'] === 'low') ? 'selected' : ''; ?>>低い（座りがち）</option>
            <option value="medium" <?php echo ($user['activity_level'] === 'medium') ? 'selected' : ''; ?>>中程度（適度に活動）</option>
            <option value="high" <?php echo ($user['activity_level'] === 'high') ? 'selected' : ''; ?>>高い（活発）</option>
        </select>
    </label><br>
    <button type="submit">更新</button>
</form>

<hr>
<p><a href="/dashboard.php">ダッシュボードに戻る</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

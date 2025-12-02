<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = get_current_user();

// ユーザー情報が取得できない場合はエラー
if (!$user) {
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

        // sex の値チェック
        if ($sex && !in_array($sex, ['male', 'female', 'other'], true)) {
            $errors[] = '無効な性別です。';
        }

        if (empty($errors)) {
            $now = (new DateTime())->format('Y-m-d H:i:s');

            // プロフィール更新
            $upd = $pdo->prepare(
                'UPDATE users SET birth_date = ?, sex = ?, updated_at = ? WHERE id = ?'
            );
            $upd->execute([$birth_date, $sex, $now, $user['id']]);

            // 既存の body_records の BMR/TDEE を再計算（lib の共通関数を使用）
            $updated = recalc_body_records_for_user($pdo, $user['id']);
            $success = true;
            // 更新後のユーザデータを再取得
            $user = get_current_user();
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
    <button type="submit">更新</button>
</form>

<hr>
<p><a href="/dashboard.php">ダッシュボードに戻る</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = current_user();

$uid = current_user_id();
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$uid]);
$stored_hash = $stmt->fetchColumn();

if (!$stored_hash) {
    die("パスワード情報を取得できませんでした。");
}

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
        $current_password = (string)($_POST['current_password'] ?? '');
        $new_password = (string)($_POST['new_password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        // 現在のパスワード検証
        if (!password_verify($current_password, $stored_hash)) {
            $errors[] = '現在のパスワードが正しくありません。';
        }

        // 新パスワード検証
        if (strlen($new_password) < 6) {
            $errors[] = '新パスワードは 6 文字以上で指定してください。';
        }

        // 確認パスワード検証
        if ($new_password !== $confirm_password) {
            $errors[] = '新パスワードと確認用パスワードが一致しません。';
        }

        if (empty($errors)) {
            // パスワードを更新
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $now = (new DateTime())->format('Y-m-d H:i:s');

            $upd = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
            $upd->execute([$hash, $now, $user['id']]);

            $success = true;
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<h2>パスワード変更</h2>

<?php if ($success): ?>
    <div class="success">パスワードを変更しました。</div>
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
    
    <label>現在のパスワード: 
        <input type="password" name="current_password" required>
    </label><br>
    
    <label>新しいパスワード (6 文字以上): 
        <input type="password" name="new_password" required>
    </label><br>
    
    <label>確認用パスワード: 
        <input type="password" name="confirm_password" required>
    </label><br>
    
    <button type="submit">変更</button>
</form>

<hr>
<p><a href="/dashboard.php">ダッシュボードに戻る</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

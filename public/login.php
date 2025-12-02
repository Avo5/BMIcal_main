<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($errors)) {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            // ログイン成功
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            // DEBUG: log session id and session contents to php error log for troubleshooting
            error_log(sprintf("[DEBUG] login.php: session_id=%s, _SESSION=%s, _COOKIE=%s", session_id(), var_export($_SESSION, true), var_export($_COOKIE, true)));
            header('Location: /dashboard.php');
            exit;
        }
        $errors[] = 'ユーザ名かパスワードが違います。';
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<h2>ログイン</h2>
<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <label>ユーザ名: <input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"></label><br>
    <label>パスワード: <input name="password" type="password"></label><br>
    <button type="submit">ログイン</button>
</form>

<p>アカウントをお持ちでない場合は <a href="/register.php">登録</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

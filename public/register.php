<?php
declare(strict_types=1);

// 共通初期化（session_start, 共通ライブラリ読み込み）
require_once __DIR__ . '/_init.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (strlen($username) < 3 || strlen($username) > 100) {
        $errors[] = 'ユーザ名は 3 文字以上で指定してください。';
    }
    if (strlen($password) < 6) {
        $errors[] = 'パスワードは 6 文字以上で指定してください。';
    }

    if (empty($errors)) {
        $pdo = get_db();
        // ユニーク確認
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if ($row) {
            $errors[] = 'そのユーザ名は既に使われています。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $ins = $pdo->prepare('INSERT INTO users (username,password_hash,created_at,updated_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$username, $hash, $now, $now]);
            // DEBUG: log registration and session/cookie state
            error_log(sprintf("[DEBUG] register.php: new user id inserted, username=%s, _SESSION=%s, _COOKIE=%s", $username, var_export($_SESSION, true), var_export($_COOKIE, true)));
            header('Location: /login.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>
<h2>会員登録</h2>
<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <label>ユーザ名: <input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"></label><br>
    <label>パスワード: <input name="password" type="password"></label><br>
    <button type="submit">登録</button>
</form>

<p>アカウントをお持ちの場合は <a href="https://tech-base.net/tb-270805/PHPインターン/mission6/project/public/login.php">ログイン</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

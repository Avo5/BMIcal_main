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

// 既存の目標値を取得（あれば）
$stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = ?');
$stmt->execute([$user['id']]);
$goal = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $errors[] = '不正な CSRF トークンです。';
    }

    if (empty($errors)) {
        // edit_field はどの値を直接編集するかを示す： 'weight'|'bmi'|'bmr'|'tdee'
        $edit_field = trim((string)($_POST['edit_field'] ?? '')) ?: null;
        $target_weight_kg = trim((string)($_POST['target_weight_kg'] ?? '')) ?: null;
        $target_bmi = trim((string)($_POST['target_bmi'] ?? '')) ?: null;
        $target_bmr = trim((string)($_POST['target_bmr'] ?? '')) ?: null;
        $target_tdee = trim((string)($_POST['target_tdee'] ?? '')) ?: null;

        // 必須: 編集対象を一つだけ選ぶポリシー。未指定なら 'weight' を想定。
        if ($edit_field === null) {
            $edit_field = 'weight';
        }

        // どれが編集されたかに応じて残りを算出
        // 1) weight を編集: weight -> bmi, bmr, tdee
        if ($edit_field === 'weight' && $target_weight_kg !== null) {
            if (!empty($user['height_cm'])) {
                $target_bmi = round(calc_bmi((float)$target_weight_kg, (float)$user['height_cm']), 2);
            }
            if (!empty($user['birth_date']) && !empty($user['sex']) && !empty($user['height_cm'])) {
                $birth = new DateTime($user['birth_date']);
                $now = new DateTime();
                $age = age_on_date($birth, $now);
                $calculated_bmr = calc_bmr($user['sex'], (float)$target_weight_kg, (float)$user['height_cm'], $age);
                $target_bmr = round($calculated_bmr, 2);
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $target_tdee = round(calc_tdee($target_bmr, $activity), 2);
            }
        }

        // 2) bmi を編集: bmi -> weight -> bmr,tdee
        if ($edit_field === 'bmi' && $target_bmi !== null && !empty($user['height_cm'])) {
            $h_m = (float)$user['height_cm'] / 100.0;
            $w = (float)$target_bmi * ($h_m * $h_m);
            $target_weight_kg = round($w, 2);
            if (!empty($user['birth_date']) && !empty($user['sex'])) {
                $birth = new DateTime($user['birth_date']);
                $now = new DateTime();
                $age = age_on_date($birth, $now);
                $calculated_bmr = calc_bmr($user['sex'], (float)$target_weight_kg, (float)$user['height_cm'], $age);
                $target_bmr = round($calculated_bmr, 2);
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $target_tdee = round(calc_tdee($target_bmr, $activity), 2);
            }
        }

        // 3) bmr を編集: bmr -> weight (逆算) -> tdee
        if ($edit_field === 'bmr' && $target_bmr !== null && !empty($user['height_cm']) && !empty($user['birth_date']) && !empty($user['sex'])) {
            $birth = new DateTime($user['birth_date']);
            $now = new DateTime();
            $age = age_on_date($birth, $now);
            $w = weight_from_bmr($user['sex'], (float)$target_bmr, (float)$user['height_cm'], $age);
            if ($w !== null) {
                $target_weight_kg = $w;
                // BMI を算出
                $target_bmi = round(calc_bmi((float)$target_weight_kg, (float)$user['height_cm']), 2);
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $target_tdee = round(calc_tdee((float)$target_bmr, $activity), 2);
            }
        }

        // 4) tdee を編集: tdee -> bmr -> weight
        if ($edit_field === 'tdee' && $target_tdee !== null && !empty($user['height_cm']) && !empty($user['birth_date']) && !empty($user['sex'])) {
            $activity = activity_factor_from_level($user['activity_level'] ?? null);
            // tdee = bmr * activity -> bmr = tdee / activity
            $bmr_val = ((float)$target_tdee) / $activity;
            $target_bmr = round($bmr_val, 2);
            $birth = new DateTime($user['birth_date']);
            $now = new DateTime();
            $age = age_on_date($birth, $now);
            $w = weight_from_bmr($user['sex'], $bmr_val, (float)$user['height_cm'], $age);
            if ($w !== null) {
                $target_weight_kg = $w;
                $target_bmi = round(calc_bmi((float)$target_weight_kg, (float)$user['height_cm']), 2);
            }
        }

        // バリデーション：範囲チェック
        if ($target_weight_kg !== null) {
            $w = (float)$target_weight_kg;
            if ($w < 2.0 || $w > 1000.0) {
                $errors[] = '理想体重は 2.0 〜 1000.0 kg の範囲で指定してください。';
            }
        }

        if ($target_bmi !== null) {
            $bmi = (float)$target_bmi;
            if ($bmi < 10.0 || $bmi > 60.0) {
                $errors[] = '目標 BMI は 10.0 〜 60.0 の範囲で指定してください。';
            }
        }

        if ($target_bmr !== null) {
            $bmr = (float)$target_bmr;
            if ($bmr < 100.0 || $bmr > 5000.0) {
                $errors[] = '目標 BMR は 100.0 〜 5000.0 kcal/day の範囲で指定してください。';
            }
        }

        if ($target_tdee !== null) {
            $tdee = (float)$target_tdee;
            if ($tdee < 100.0 || $tdee > 10000.0) {
                $errors[] = '目標 TDEE は 100.0 〜 10000.0 kcal/day の範囲で指定してください。';
            }
        }

        if (empty($errors)) {
            $now = (new DateTime())->format('Y-m-d H:i:s');

            if ($goal) {
                // 既存レコードを更新
                $upd = $pdo->prepare(
                    'UPDATE goals SET target_weight_kg = ?, target_bmi = ?, target_bmr = ?, target_tdee = ?, updated_at = ? WHERE user_id = ?'
                );
                $upd->execute([
                    $target_weight_kg ? (float)$target_weight_kg : null,
                    $target_bmi ? (float)$target_bmi : null,
                    $target_bmr ? (float)$target_bmr : null,
                    $target_tdee ? (float)$target_tdee : null,
                    $now,
                    $user['id']
                ]);
            } else {
                // 新規挿入
                $ins = $pdo->prepare(
                    'INSERT INTO goals (user_id, target_weight_kg, target_bmi, target_bmr, target_tdee, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $user['id'],
                    $target_weight_kg ? (float)$target_weight_kg : null,
                    $target_bmi ? (float)$target_bmi : null,
                    $target_bmr ? (float)$target_bmr : null,
                    $target_tdee ? (float)$target_tdee : null,
                    $now,
                    $now
                ]);
            }

            $success = true;
            // 更新後の目標データを再取得
            $stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<h2>目標値設定</h2>

<?php if ($success): ?>
    <div class="success">目標値を保存しました。</div>
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
    <p>どの値を直接編集しますか？（1つだけ選択してください）</p>
    <label><input type="radio" name="edit_field" value="weight" <?php echo (isset($_POST['edit_field']) ? $_POST['edit_field'] === 'weight' : true) ? 'checked' : ''; ?>> 理想体重 (kg) を編集</label>
    <label><input type="radio" name="edit_field" value="bmi" <?php echo (isset($_POST['edit_field']) && $_POST['edit_field'] === 'bmi') ? 'checked' : ''; ?>> 目標 BMI を編集</label>
    <label><input type="radio" name="edit_field" value="bmr" <?php echo (isset($_POST['edit_field']) && $_POST['edit_field'] === 'bmr') ? 'checked' : ''; ?>> 目標 BMR を編集</label>
    <label><input type="radio" name="edit_field" value="tdee" <?php echo (isset($_POST['edit_field']) && $_POST['edit_field'] === 'tdee') ? 'checked' : ''; ?>> 目標 TDEE を編集</label>
    <br>
    
    <label>理想体重 (kg): 
        <input type="number" step="0.01" name="target_weight_kg" 
               value="<?php echo $goal && $goal['target_weight_kg'] ? htmlspecialchars((string)$goal['target_weight_kg']) : (isset($_POST['target_weight_kg']) ? htmlspecialchars($_POST['target_weight_kg']) : ''); ?>" 
               placeholder="例: 65.5">
    </label><br>
    
    <label>目標 BMI: 
        <input type="number" step="0.01" name="target_bmi" 
               value="<?php echo $goal && $goal['target_bmi'] ? htmlspecialchars((string)$goal['target_bmi']) : (isset($_POST['target_bmi']) ? htmlspecialchars($_POST['target_bmi']) : ''); ?>" 
               placeholder="例: 22.5">
    </label><br>
    
    <label>目標 BMR (kcal/day): 
        <input type="number" step="0.01" name="target_bmr" 
               value="<?php echo $goal && $goal['target_bmr'] ? htmlspecialchars((string)$goal['target_bmr']) : (isset($_POST['target_bmr']) ? htmlspecialchars($_POST['target_bmr']) : ''); ?>" 
               placeholder="例: 1500">
    </label><br>
    
    <label>目標 TDEE (kcal/day): 
        <input type="number" step="0.01" name="target_tdee" 
               value="<?php echo $goal && $goal['target_tdee'] ? htmlspecialchars((string)$goal['target_tdee']) : (isset($_POST['target_tdee']) ? htmlspecialchars($_POST['target_tdee']) : ''); ?>" 
               placeholder="例: 2000">
    </label><br>
    
    <button type="submit">保存</button>
</form>

<script>
    // フォームで編集対象に応じて他の入力を無効化する
    (function(){
        const radios = document.querySelectorAll('input[name="edit_field"]');
        const fields = {
            weight: document.querySelector('input[name="target_weight_kg"]'),
            bmi: document.querySelector('input[name="target_bmi"]'),
            bmr: document.querySelector('input[name="target_bmr"]'),
            tdee: document.querySelector('input[name="target_tdee"]')
        };
        function refresh(){
            const sel = document.querySelector('input[name="edit_field"]:checked');
            const which = sel ? sel.value : 'weight';
            Object.keys(fields).forEach(k => {
                if (!fields[k]) return;
                if (k === which) {
                    fields[k].removeAttribute('disabled');
                } else {
                    fields[k].setAttribute('disabled', 'disabled');
                }
            });
        }
        radios.forEach(r => r.addEventListener('change', refresh));
        refresh();
    })();
</script>

<hr>
<p><a href="/dashboard.php">ダッシュボードに戻る</a></p>

<?php require_once __DIR__ . '/../templates/footer.php';

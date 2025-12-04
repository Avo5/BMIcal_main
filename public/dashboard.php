<?php
declare(strict_types=1);

// 共通初期化
require_once __DIR__ . '/_init.php';

require_login();

$pdo = get_db();
$user = current_user();

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
    error_log(sprintf('[DEBUG] dashboard.php: current_user returned %s', var_export($user, true)));
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
        // 身長はフォームで未入力でもプロフィールの身長があればそれを使う
        if (empty($height_cm)) {
            if (!empty($user['height_cm'])) {
                $height_cm = (string)$user['height_cm'];
            }
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
                // ユーザーの活動レベルに応じた係数を使用
                $activity = activity_factor_from_level($user['activity_level'] ?? null);
                $tdee = calc_tdee($bmr, $activity);
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

// 目標値を取得（あれば）
$gstmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = ?');
$gstmt->execute([$user['id']]);
$goal = $gstmt->fetch(PDO::FETCH_ASSOC);

// バッジ表示は廃止（履歴は仕様変更履歴書.md を参照）

require_once __DIR__ . '/../templates/header.php';
?>

<h2>ダッシュボード</h2>
<p>ユーザ名: <?php echo htmlspecialchars($user['username']); ?></p>

<?php if ($success): ?>
    <div class="success">記録を追加しました。</div>
<?php endif; ?>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div class="success">記録を削除しました。</div>
<?php endif; ?>

<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!empty($goal) && is_array($goal)): ?>
    <section class="goal-summary">
        <h3>現在の目標</h3>
        <ul>
            <?php if ($goal['target_weight_kg'] !== null): ?><li>理想体重: <?php echo htmlspecialchars((string)$goal['target_weight_kg']); ?> kg</li><?php endif; ?>
            <?php if ($goal['target_bmi'] !== null): ?><li>目標 BMI: <?php echo htmlspecialchars((string)$goal['target_bmi']); ?></li><?php endif; ?>
            <?php if ($goal['target_bmr'] !== null): ?><li>目標 BMR: <?php echo htmlspecialchars((string)$goal['target_bmr']); ?> kcal/day</li><?php endif; ?>
            <?php if ($goal['target_tdee'] !== null): ?><li>目標 TDEE: <?php echo htmlspecialchars((string)$goal['target_tdee']); ?> kcal/day</li><?php endif; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- BMI / BMR / TDEE の簡単説明（ユーザの目標説明の直後が分かりやすい） -->
<section class="info-cards">
    <div class="card">
        <h4>BMI（体格指数）</h4>
        <p>身長と体重から計算される指標です。<strong>BMI = 体重(kg) ÷ (身長(m))²</strong>。健康管理の簡易指標として使われます。</p>
    </div>
    <div class="card">
        <h4>BMR（基礎代謝量）</h4>
        <p>何もしていない状態で消費する1日のエネルギー量の目安。性別・年齢・身長・体重で計算します。</p>
    </div>
    <div class="card">
        <h4>TDEE（総消費カロリー）</h4>
        <p>BMR に活動係数を掛けた値。日常生活で必要なカロリー量の目安です<br>（初期値は1.2。デスクワーク中心で、意識的な運動はほとんどしない生活レベル）。</p>
    </div>
</section>

<h3>記録推移</h3>
<label for="periodSelect">期間: </label>
<select id="periodSelect">
    <option value="30">30日</option>
    <option value="90">90日</option>
    <option value="365">1年</option>
    <option value="0">全て</option>
</select>
<div class="chart-container">
    <canvas id="trendChart" width="800" height="320"></canvas>
    <div class="chart-actions"><a href="/export_records_csv.php">CSV をダウンロード</a></div>
</div>

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
                <?php if (!empty($goal['target_weight_kg'])): ?><th>差 (kg)</th><?php endif; ?>
                <th>操作</th>
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
                    <?php if (!empty($goal['target_weight_kg'])): ?>
                        <td><?php echo is_numeric($r['weight_kg']) ? htmlspecialchars((string)round($r['weight_kg'] - (float)$goal['target_weight_kg'], 2)) : '-'; ?></td>
                    <?php endif; ?>
                    <td>
                        <a href="/record_edit.php?id=<?php echo htmlspecialchars((string)$r['id']); ?>">編集</a> |
                        <a href="/record_delete.php?id=<?php echo htmlspecialchars((string)$r['id']); ?>" onclick="return confirm('削除しますか？');">削除</a>
                    </td>
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
    <a href="/settings_goal.php">目標値設定</a> |
    <a href="/settings_password.php">パスワード変更</a> |
    <a href="/logout.php">ログアウト</a>
</p>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script>
// Chart: 体重/BMI の推移（ページロード時に PHP の $records を使って描画）
(() => {
    try {
        const raw = <?php echo json_encode($records, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
        if (!raw.length) return;

        // Convert to chronological order (oldest -> newest)
        const originalRecords = raw.slice().reverse();
        const targetWeight = <?php echo isset($goal['target_weight_kg']) && $goal['target_weight_kg'] !== null ? json_encode((float)$goal['target_weight_kg']) : 'null'; ?>;

        const ctx = document.getElementById('trendChart');
        if (!ctx) return;

        function buildDataset(records) {
            const labels = records.map(r => r.record_date);
            const weights = records.map(r => r.weight_kg !== null ? parseFloat(r.weight_kg) : null);
            const bmis = records.map(r => r.bmi !== null ? parseFloat(r.bmi) : null);
            const diffs = (targetWeight !== null) ? weights.map(w => w !== null ? +(w - targetWeight).toFixed(2) : null) : null;
            const datasets = [
                { label: '体重 (kg)', data: weights, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.1)', yAxisID: 'y', tension: 0.2, spanGaps: true },
                { label: 'BMI', data: bmis, borderColor: '#388e3c', backgroundColor: 'rgba(56,142,60,0.1)', yAxisID: 'y1', tension: 0.2, spanGaps: true }
            ];
            if (targetWeight !== null) {
                datasets.push({ label: '差（kg）', data: diffs, borderColor: '#ff5722', backgroundColor: 'rgba(255,87,34,0.08)', yAxisID: 'y', borderDash: [6,4], tension: 0.2, spanGaps: true });
            }
            return { labels, datasets };
        }

        let chart = new Chart(ctx, { type: 'line', data: buildDataset(originalRecords), options: { responsive: true, interaction: { mode: 'index', intersect: false }, stacked: false, scales: { y: { type: 'linear', display: true, position: 'left', title: { display: true, text: '体重 (kg)' } }, y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'BMI' } } } } });

        // filter function: days = 0 means all
        function applyFilter(days) {
            if (!days || days <= 0) {
                chart.data = buildDataset(originalRecords);
            } else {
                const cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - days);
                const filtered = originalRecords.filter(r => new Date(r.record_date) >= cutoff);
                chart.data = buildDataset(filtered);
            }
            chart.update();
        }

        const sel = document.getElementById('periodSelect');
        if (sel) {
            sel.addEventListener('change', (e) => {
                const days = parseInt(e.target.value, 10);
                applyFilter(days);
            });
            // 初期値: 30 日
            sel.value = '30';
            applyFilter(30);
        }

    } catch (e) {
        console.error('chart render error', e);
    }
})();
</script>

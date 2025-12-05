<?php
// 共通ヘッダ。シンプルな HTML5 の骨組み。
// 注: session_start() と共通ライブラリの読み込みは public/_init.php に集約してください
?><!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>PHP TechBase</title>
    <link rel="stylesheet" href="/styles.css">
    <!-- Chart.js CDN: ダッシュボードでのグラフ表示に使用 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<header>
    <h1>BMI計測サイト</h1>
</header>
<main>

<?php
// 簡易スクリプト: schema.sql の CREATE TABLE 定義を読み、ALTER TABLE ADD COLUMN IF NOT EXISTS 文を生成します。
// 実行方法: php scripts/generate_alter_from_schema.php > migrations/alter_add_columns.sql

$schemaFile = __DIR__ . '/../schema.sql';
if (!file_exists($schemaFile)) {
    fwrite(STDERR, "schema.sql not found\n");
    exit(1);
}
$contents = file_get_contents($schemaFile);
$tables = ['users', 'goals', 'body_records'];
$out = [];
foreach ($tables as $t) {
    $pattern = '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($t, '/') . '\s*\((.*?)\)\s*ENGINE=/s';
    if (preg_match($pattern, $contents, $m)) {
        $cols_block = $m[1];
        // split lines
        $lines = preg_split('/,\s*\n/', trim($cols_block));
        foreach ($lines as $line) {
            $line = trim($line);
            // skip constraint or index lines
            $lc = strtolower($line);
            if (strpos($lc, 'primary key') !== false || strpos($lc, 'constraint') !== false || strpos($lc, 'foreign key') !== false || strpos($lc, 'unique') !== false || strpos($lc,'index')!== false) continue;
            // column name is first token
            if (preg_match('/^`?(\w+)`?\s+(.*)$/', $line, $colm)) {
                $col = $colm[1];
                $def = $colm[2];
                // Clean trailing comma/engine
                $def = preg_replace('/\)\s*$/', '', $def);
                $def = trim($def);
                // Build ALTER TABLE
                $out[] = sprintf("ALTER TABLE `%s` ADD COLUMN IF NOT EXISTS `%s` %s;", $t, $col, $def);
            }
        }
    }
}
// Print header
echo "-- Generated ALTER statements from schema.sql\n";
echo "-- Run these on your database to add missing columns (MySQL 8+ supports ADD COLUMN IF NOT EXISTS).\n\n";
foreach ($out as $l) echo $l . "\n";

exit(0);

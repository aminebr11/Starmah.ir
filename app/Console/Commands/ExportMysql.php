<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * خروجی MySQL از دیتابیس فعلی (SQLite توسعه) برای import در phpMyAdmin روی cPanel.
 * توجه: روش ترجیحی همان `php artisan migrate --seed` است؛ این فقط برای راحتی است.
 */
class ExportMysql extends Command
{
    protected $signature = 'db:export-mysql {--path=database/starmah_mysql.sql}';
    protected $description = 'تولید دامپ MySQL از دیتابیس فعلی';

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        $out = [];
        $out[] = "-- ستاره ماه ۲.۰ — دامپ MySQL";
        $out[] = "-- تولیدشده در: " . now();
        $out[] = "SET NAMES utf8mb4;";
        $out[] = "SET FOREIGN_KEY_CHECKS=0;";
        $out[] = "";

        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, ['migrations']));

        foreach ($tables as $table) {
            $out[] = "-- ---------- {$table} ----------";
            $out[] = "DROP TABLE IF EXISTS `{$table}`;";
            $out[] = $this->createTable($table);
            $out[] = "";

            $rows = DB::table($table)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $cols = array_keys((array) $rows->first());
            $colList = '`' . implode('`,`', $cols) . '`';

            foreach ($rows->chunk(50) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $vals = array_map(fn ($v) => $this->val($v), array_values((array) $row));
                    $values[] = '(' . implode(',', $vals) . ')';
                }
                $out[] = "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $values) . ';';
            }
            $out[] = "";
        }

        $out[] = "SET FOREIGN_KEY_CHECKS=1;";

        file_put_contents($path, implode("\n", $out));
        $this->info("دامپ نوشته شد: {$path} (" . count($tables) . " جدول)");

        return self::SUCCESS;
    }

    private function createTable(string $table): string
    {
        $cols = DB::select("PRAGMA table_info(`{$table}`)");
        $lines = [];
        $pk = [];

        // AUTO_INCREMENT فقط وقتی مجاز است که کلید اصلی تک‌ستونی و عددی باشد.
        $pkCount = collect($cols)->where('pk', '>', 0)->count();

        foreach ($cols as $c) {
            $isInt = str_contains(strtolower($c->type), 'int');
            $autoInc = $pkCount === 1 && $c->pk && $isInt;

            $type = $this->mapType($c->type, $autoInc);
            $line = "  `{$c->name}` {$type}";
            $line .= $c->notnull ? ' NOT NULL' : ' NULL';
            if ($autoInc) {
                $line .= ' AUTO_INCREMENT';
            }
            if ($c->dflt_value !== null && ! str_contains($c->type, 'TEXT')) {
                $d = trim($c->dflt_value, "'");
                if (strtoupper($d) === 'CURRENT_TIMESTAMP') {
                    $line .= ' DEFAULT CURRENT_TIMESTAMP';
                } elseif (is_numeric($d)) {
                    $line .= " DEFAULT {$d}";
                } else {
                    $line .= " DEFAULT '" . addslashes($d) . "'";
                }
            }
            $lines[] = $line;
            if ($c->pk) {
                $pk[] = "`{$c->name}`";
            }
        }
        if ($pk) {
            $lines[] = '  PRIMARY KEY (' . implode(',', $pk) . ')';
        }

        return "CREATE TABLE `{$table}` (\n" . implode(",\n", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }

    private function mapType(string $t, bool $isAutoPk): string
    {
        $t = strtolower(trim($t));
        return match (true) {
            $isAutoPk                                   => 'int',
            str_contains($t, 'tinyint')                 => 'tinyint(1)',
            str_contains($t, 'int')                     => 'int',
            $t === 'varchar'                            => 'varchar(255)',
            str_contains($t, 'varchar')                 => $t,
            str_contains($t, 'char')                    => $t,
            str_contains($t, 'decimal'), str_contains($t, 'numeric') => str_replace('numeric', 'decimal', $t ?: 'decimal(8,2)'),
            str_contains($t, 'datetime'), str_contains($t, 'timestamp') => 'timestamp',
            str_contains($t, 'date')                    => 'date',
            default                                      => 'text',
        };
    }

    private function val($v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
    }
}

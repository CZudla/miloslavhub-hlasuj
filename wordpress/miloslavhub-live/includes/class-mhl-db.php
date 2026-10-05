<?php
if (!defined('ABSPATH')) { exit; }

class MHL_DB {
    private static $db = null;
    private static $error = null;

    public static function configured(): bool {
        foreach (array('MHL_LIVE_DB_HOST','MHL_LIVE_DB_NAME','MHL_LIVE_DB_USER','MHL_LIVE_DB_PASSWORD') as $constant) {
            if (!defined($constant) || trim((string) constant($constant)) === '') { return false; }
        }
        return true;
    }

    public static function table(string $name): string {
        return 'mhl_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    }

    public static function db(): wpdb {
        if (self::$db instanceof wpdb) { return self::$db; }
        if (!self::configured()) { throw new RuntimeException('MiloslavHub Live: samostatná databáze není nakonfigurována v wp-config.php.'); }

        if (function_exists('mysqli_init')) {
            $host = (string) MHL_LIVE_DB_HOST;
            $port = null;
            if (preg_match('/^(.+):(\d+)$/', $host, $m)) { $host = $m[1]; $port = (int) $m[2]; }
            $mysqli = mysqli_init();
            @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $ok = @mysqli_real_connect($mysqli, $host, (string) MHL_LIVE_DB_USER, (string) MHL_LIVE_DB_PASSWORD, (string) MHL_LIVE_DB_NAME, $port ?: (int) ini_get('mysqli.default_port'));
            if (!$ok) {
                self::$error = mysqli_connect_error() ?: 'Nepodařilo se připojit k databázi.';
                if ($mysqli) { @mysqli_close($mysqli); }
                throw new RuntimeException('MiloslavHub Live: ' . self::$error);
            }
            @mysqli_close($mysqli);
        }

        self::$db = new wpdb((string) MHL_LIVE_DB_USER, (string) MHL_LIVE_DB_PASSWORD, (string) MHL_LIVE_DB_NAME, (string) MHL_LIVE_DB_HOST);
        self::$db->set_charset(self::$db->dbh, 'utf8mb4');
        self::$db->suppress_errors(true);
        return self::$db;
    }

    public static function required_tables(): array {
        return array(self::table('runs'), self::table('sessions'), self::table('votes'), self::table('participants'), self::table('session_joins'));
    }

    public static function required_columns(): array {
        return array(
            self::table('runs') => array('id','lecture_id','subject_id','title','mode','status','started_at','expires_at','closed_at','created_by'),
            self::table('sessions') => array('id','run_id','question_id','mode','status','joining_started_at','last_join_at','opened_at','closed_at','reset_at','created_at'),
            self::table('votes') => array('id','session_id','run_id','question_id','mode','participant_key','nickname','option_index','is_correct','response_ms','points','created_at'),
            self::table('participants') => array('id','subject_id','mode','participant_key','nickname','nickname_key','claimed_at','last_seen_at','expires_at','hall_of_fame_opt_in','hall_opted_at','hall_visibility'),
            self::table('session_joins') => array('id','session_id','participant_key','first_seen_at'),
        );
    }

    public static function missing_tables(): array {
        if (!self::configured()) { return self::required_tables(); }
        try {
            $db = self::db(); $missing = array();
            foreach (self::required_tables() as $table) {
                $db->last_error = '';
                $db->get_var("SELECT 1 FROM `{$table}` LIMIT 1");
                if ($db->last_error !== '') { $missing[] = $table; }
            }
            return $missing;
        } catch (Throwable $e) { self::$error = $e->getMessage(); return self::required_tables(); }
    }

    public static function missing_columns(): array {
        if (!self::configured() || self::missing_tables()) { return array(); }
        $db = self::db(); $missing = array();
        foreach (self::required_columns() as $table => $columns) {
            $existing = $db->get_col("SHOW COLUMNS FROM `{$table}`", 0);
            foreach ($columns as $column) {
                if (!in_array($column, $existing ?: array(), true)) { $missing[] = $table . '.' . $column; }
            }
        }
        return $missing;
    }

    public static function schema_ready(): bool {
        return self::configured() && count(self::missing_tables()) === 0 && count(self::missing_columns()) === 0;
    }

    public static function mark_schema_if_ready(): bool {
        if (!self::schema_ready()) { return false; }
        update_option('mhl_external_db_schema_version', MHL_SCHEMA_VERSION, false);
        return true;
    }

    public static function status(): array {
        if (!self::configured()) { return array('ok'=>false,'message'=>'Chybí konstanty MHL_LIVE_DB_* ve wp-config.php.'); }
        try {
            $db = self::db();
            if ((string) $db->get_var('SELECT 1') !== '1') { return array('ok'=>false,'message'=>'Databáze neodpověděla na testovací dotaz.'); }
            $missing_tables = self::missing_tables();
            if ($missing_tables) { return array('ok'=>false,'message'=>'Chybí tabulky: ' . implode(', ', $missing_tables) . '. Importujte databázové schéma účtem admin.'); }
            $missing_columns = self::missing_columns();
            if ($missing_columns) { return array('ok'=>false,'message'=>'Databáze vyžaduje aktuální migraci. Chybí: ' . implode(', ', $missing_columns) . '. Importujte soubor database-migration-0.8.1-to-0.8.5.sql účtem admin.'); }
            return array('ok'=>true,'message'=>'Připojení i databázové schéma jsou v pořádku.');
        } catch (Throwable $e) { return array('ok'=>false,'message'=>$e->getMessage()); }
    }

    public static function ensure_schema(): bool {
        if (!self::configured()) { return false; }
        if (self::schema_ready()) { update_option('mhl_external_db_schema_version', MHL_SCHEMA_VERSION, false); return true; }
        return false; // WEDOS web účet nemá CREATE/ALTER; schema se importuje účtem admin.
    }
}

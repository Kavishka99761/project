<?php

namespace App\Services\Platform\DataAudit;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Database-level audit trail for SQL Server.
 *
 * Installs one AFTER INSERT, UPDATE, DELETE trigger per application table.
 * Each trigger writes one dbo.data_change_log row per affected record with
 * the old and new values as JSON and the list of changed columns, so every
 * create / update / delete is kept inside SQL Server — whether it came from
 * the API, a scheduled job, a seeder or a manual edit in SSMS.
 *
 * Who made the change comes from SESSION_CONTEXT (set per request by
 * DatabaseAuditSession); a change without it is attributed to the SQL login.
 *
 * Triggers list their columns explicitly (to mask secrets and trim large
 * text), so they are regenerated after every migration run.
 */
class DataAuditInstaller
{
    public const LOG_TABLE = 'data_change_log';

    public const TRIGGER_PREFIX = 'trg_audit_';

    /** Framework plumbing and the audit tables themselves — never audited. */
    public const EXCLUDED_TABLES = [
        self::LOG_TABLE, 'activity_logs', 'migrations', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens',
        'email_verification_codes',
    ];

    /** Secrets are stored as a short fingerprint: a change is visible, the value is not. */
    public const MASKED_COLUMNS = ['password', 'remember_token', 'token', 'two_factor_secret', 'two_factor_recovery_codes', 'api_key', 'secret'];

    /** Columns that change on every request and would only add noise. */
    public const IGNORED_COLUMNS = ['personal_access_tokens' => ['last_used_at', 'updated_at']];

    /** Long text / JSON columns are trimmed to this many characters in the log. */
    public const MAX_TEXT = 2000;

    public function __construct(private readonly ?Connection $connection = null) {}

    public function supported(): bool
    {
        return $this->db()->getDriverName() === 'sqlsrv';
    }

    public function logTableExists(): bool
    {
        return $this->supported() && $this->db()->getSchemaBuilder()->hasTable(self::LOG_TABLE);
    }

    /**
     * (Re)create the audit trigger on every application table.
     *
     * @return array<int, string> audited table names
     */
    public function install(): array
    {
        if (! $this->logTableExists()) {
            return [];
        }

        $this->uninstall();

        $audited = [];
        foreach ($this->tables() as $table => $columns) {
            if (in_array($table, self::EXCLUDED_TABLES, true) || ! isset($columns['id'])) {
                continue;
            }
            $this->db()->unprepared($this->triggerSql($table, $columns));
            $audited[] = $table;
        }

        return $audited;
    }

    /** Drop every audit trigger (before migrations alter tables). */
    public function uninstall(): int
    {
        if (! $this->supported()) {
            return 0;
        }

        $triggers = $this->installedTriggers();
        foreach ($triggers as $trigger) {
            $this->db()->unprepared("DROP TRIGGER IF EXISTS [dbo].[{$trigger}]");
        }

        return count($triggers);
    }

    /** @return array<int, string> */
    public function installedTriggers(): array
    {
        if (! $this->supported()) {
            return [];
        }

        return array_map(fn ($row) => $row->name, $this->db()->select(
            "SELECT tr.name FROM sys.triggers tr WHERE tr.parent_class = 1 AND tr.name LIKE ? ORDER BY tr.name",
            [self::TRIGGER_PREFIX.'%'],
        ));
    }

    /**
     * Base tables of the dbo schema with their columns.
     *
     * @return array<string, array<string, array{type: string, max: bool}>>
     */
    private function tables(): array
    {
        $rows = $this->db()->select(<<<'SQL'
            SELECT t.name AS table_name, c.name AS column_name, ty.name AS type_name,
                   CASE WHEN c.max_length = -1 THEN 1 ELSE 0 END AS is_max, c.column_id
            FROM sys.tables t
            JOIN sys.schemas s ON s.schema_id = t.schema_id AND s.name = 'dbo'
            JOIN sys.columns c ON c.object_id = t.object_id
            JOIN sys.types ty ON ty.user_type_id = c.user_type_id
            WHERE t.is_ms_shipped = 0
            ORDER BY t.name, c.column_id
            SQL);

        $tables = [];
        foreach ($rows as $row) {
            $tables[$row->table_name][$row->column_name] = ['type' => strtolower($row->type_name), 'max' => (bool) $row->is_max];
        }

        return $tables;
    }

    /** @param  array<string, array{type: string, max: bool}>  $columns */
    private function triggerSql(string $table, array $columns): string
    {
        $trigger = self::TRIGGER_PREFIX.$table;
        $ignored = self::IGNORED_COLUMNS[$table] ?? [];

        $json = fn (string $alias) => implode(",\n                    ", array_filter(array_map(
            fn ($name, $meta) => in_array($name, $ignored, true) ? null : $this->columnExpression($alias, $name, $meta),
            array_keys($columns), $columns,
        )));

        $owner = match (true) {
            $table === 'users' => 'COALESCE(i.[id], d.[id])',
            $table === 'personal_access_tokens' => 'COALESCE(i.[tokenable_id], d.[tokenable_id])',
            isset($columns['user_id']) => 'COALESCE(i.[user_id], d.[user_id])',
            default => 'NULL',
        };

        $log = self::LOG_TABLE;

        return <<<SQL
            CREATE OR ALTER TRIGGER [dbo].[{$trigger}] ON [dbo].[{$table}]
            AFTER INSERT, UPDATE, DELETE
            AS
            BEGIN
                SET NOCOUNT ON;
                IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;
                IF TRY_CAST(SESSION_CONTEXT(N'audit_disabled') AS bit) = 1 RETURN;

                DECLARE @actor bigint = TRY_CAST(SESSION_CONTEXT(N'user_id') AS bigint);
                DECLARE @source nvarchar(32) = ISNULL(TRY_CAST(SESSION_CONTEXT(N'source') AS nvarchar(32)), N'sql');
                DECLARE @request nvarchar(36) = TRY_CAST(SESSION_CONTEXT(N'request_id') AS nvarchar(36));

                WITH changed AS (
                    SELECT
                        CAST(COALESCE(i.[id], d.[id]) AS nvarchar(64)) AS record_id,
                        CASE WHEN d.[id] IS NULL THEN 'I' WHEN i.[id] IS NULL THEN 'D' ELSE 'U' END AS operation,
                        {$owner} AS owner_user_id,
                        (SELECT
                            {$json('d')}
                         WHERE d.[id] IS NOT NULL
                         FOR JSON PATH, WITHOUT_ARRAY_WRAPPER, INCLUDE_NULL_VALUES) AS old_values,
                        (SELECT
                            {$json('i')}
                         WHERE i.[id] IS NOT NULL
                         FOR JSON PATH, WITHOUT_ARRAY_WRAPPER, INCLUDE_NULL_VALUES) AS new_values
                    FROM inserted AS i
                    FULL OUTER JOIN deleted AS d ON d.[id] = i.[id]
                )
                INSERT INTO dbo.[{$log}]
                    (table_name, operation, record_id, owner_user_id, actor_user_id, source, request_id,
                     changed_columns, old_values, new_values, db_login, host_name, app_name)
                SELECT N'{$table}', c.operation, c.record_id, c.owner_user_id, @actor, @source, @request,
                       diff.cols, c.old_values, c.new_values, ORIGINAL_LOGIN(), HOST_NAME(), APP_NAME()
                FROM changed AS c
                OUTER APPLY (
                    SELECT STRING_AGG(CAST(COALESCE(o.[key], n.[key]) AS nvarchar(max)), N',') AS cols
                    FROM OPENJSON(c.old_values) AS o
                    FULL OUTER JOIN OPENJSON(c.new_values) AS n ON n.[key] = o.[key]
                    WHERE c.operation = 'U'
                      AND (o.[key] IS NULL OR n.[key] IS NULL OR o.[type] <> n.[type]
                           OR ISNULL(o.[value], N'') COLLATE Latin1_General_BIN2 <> ISNULL(n.[value], N'') COLLATE Latin1_General_BIN2)
                ) AS diff
                WHERE c.operation <> 'U' OR diff.cols IS NOT NULL; -- skip no-op updates
            END
            SQL;
    }

    /** @param  array{type: string, max: bool}  $meta */
    private function columnExpression(string $alias, string $name, array $meta): string
    {
        $col = "{$alias}.[{$name}]";
        $as = '['.str_replace(']', ']]', $name).']';

        if (in_array($name, self::MASKED_COLUMNS, true)) {
            return "CASE WHEN {$col} IS NULL THEN NULL ELSE N'[hidden ' + LEFT(CONVERT(nvarchar(64), HASHBYTES('SHA2_256', CAST({$col} AS nvarchar(max))), 2), 10) + N']' END AS {$as}";
        }
        if (in_array($meta['type'], ['varbinary', 'binary', 'image', 'timestamp', 'rowversion'], true)) {
            return "CASE WHEN {$col} IS NULL THEN NULL ELSE CONCAT(N'[binary ', DATALENGTH({$col}), N' bytes]') END AS {$as}";
        }
        if ($meta['max'] || in_array($meta['type'], ['text', 'ntext', 'xml'], true)) {
            $limit = self::MAX_TEXT;

            return "CASE WHEN LEN(CAST({$col} AS nvarchar(max))) > {$limit} THEN LEFT(CAST({$col} AS nvarchar(max)), {$limit}) + N'… [' + CAST(LEN(CAST({$col} AS nvarchar(max))) AS nvarchar(12)) + N' chars]' ELSE CAST({$col} AS nvarchar(max)) END AS {$as}";
        }

        return "{$col} AS {$as}";
    }

    private function db(): Connection
    {
        return $this->connection ?? DB::connection();
    }
}

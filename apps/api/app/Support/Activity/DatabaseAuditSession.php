<?php

namespace App\Support\Activity;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Tells SQL Server who is making the current changes.
 *
 * Values go into SESSION_CONTEXT, which the audit triggers read when they
 * write dbo.data_change_log: the acting user, where the change came from
 * (api / console / queue; "sql" when nothing is set, e.g. SSMS) and the
 * request id that links a row change to its activity_logs entry.
 */
class DatabaseAuditSession
{
    public static function begin(string $source, ?string $requestId = null, ?int $userId = null, ?Connection $connection = null): void
    {
        self::set(['source' => $source, 'request_id' => $requestId, 'user_id' => $userId], $connection);
    }

    public static function user(?int $userId): void
    {
        self::set(['user_id' => $userId]);
    }

    /** Run $callback without writing data_change_log rows (bulk maintenance only). */
    public static function withoutAuditing(callable $callback): mixed
    {
        self::set(['audit_disabled' => 1]);
        try {
            return $callback();
        } finally {
            self::set(['audit_disabled' => 0]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private static function set(array $values, ?Connection $connection = null): void
    {
        try {
            $connection ??= DB::connection();
            if ($connection->getDriverName() !== 'sqlsrv') {
                return;
            }
            // One round trip for all keys.
            $sql = implode('; ', array_fill(0, count($values), 'EXEC sys.sp_set_session_context @key = ?, @value = ?'));
            $bindings = [];
            foreach ($values as $key => $value) {
                array_push($bindings, $key, $value === null ? null : (string) $value);
            }
            $connection->statement($sql, $bindings);
        } catch (\Throwable $e) {
            // Attribution is best-effort; it must never block the change itself.
            report($e);
        }
    }
}

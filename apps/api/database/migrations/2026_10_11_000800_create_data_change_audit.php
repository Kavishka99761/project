<?php

use App\Services\Platform\DataAudit\DataAuditInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level audit trail inside SQL Server.
 *
 *  - dbo.data_change_log: one row per inserted / updated / deleted record,
 *    with old + new values (JSON) and the changed columns. Written only by
 *    the trg_audit_<table> triggers, so nothing can change data unrecorded.
 *  - The key comes from a SEQUENCE, not IDENTITY, on purpose: pdo_sqlsrv
 *    reads new ids with @@IDENTITY, which an IDENTITY insert inside a
 *    trigger would overwrite.
 *  - activity_logs.request_id links an API request to the rows it changed.
 *  - Views and procedures for reading the log in SSMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('request_id', 36)->nullable()->after('user_id');
            $table->index('request_id');
        });

        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::unprepared('CREATE SEQUENCE dbo.seq_data_change_log AS bigint START WITH 1 INCREMENT BY 1 CACHE 50');

        DB::unprepared(<<<'SQL'
            CREATE TABLE dbo.data_change_log (
                id              bigint        NOT NULL CONSTRAINT df_data_change_log_id DEFAULT (NEXT VALUE FOR dbo.seq_data_change_log),
                changed_at      datetime2(3)  NOT NULL CONSTRAINT df_data_change_log_changed_at DEFAULT (SYSDATETIME()),
                table_name      nvarchar(128) NOT NULL,
                operation       char(1)       NOT NULL CONSTRAINT ck_data_change_log_operation CHECK (operation IN ('I', 'U', 'D')),
                record_id       nvarchar(64)  NULL,
                owner_user_id   bigint        NULL,
                actor_user_id   bigint        NULL,
                source          nvarchar(32)  NOT NULL CONSTRAINT df_data_change_log_source DEFAULT (N'sql'),
                request_id      nvarchar(36)  NULL,
                changed_columns nvarchar(max) NULL,
                old_values      nvarchar(max) NULL,
                new_values      nvarchar(max) NULL,
                db_login        nvarchar(128) NULL,
                host_name       nvarchar(128) NULL,
                app_name        nvarchar(128) NULL,
                CONSTRAINT pk_data_change_log PRIMARY KEY CLUSTERED (id),
                CONSTRAINT ck_data_change_log_old_json CHECK (old_values IS NULL OR ISJSON(old_values) = 1),
                CONSTRAINT ck_data_change_log_new_json CHECK (new_values IS NULL OR ISJSON(new_values) = 1)
            );
            CREATE INDEX ix_data_change_log_changed_at ON dbo.data_change_log (changed_at);
            CREATE INDEX ix_data_change_log_record ON dbo.data_change_log (table_name, record_id, changed_at);
            CREATE INDEX ix_data_change_log_owner ON dbo.data_change_log (owner_user_id, changed_at);
            CREATE INDEX ix_data_change_log_actor ON dbo.data_change_log (actor_user_id, changed_at);
            CREATE INDEX ix_data_change_log_request ON dbo.data_change_log (request_id);
            SQL);

        // Readable view: operation names, who did it, and the request it belongs to.
        DB::unprepared(<<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_data_changes AS
            SELECT c.id, c.changed_at, c.table_name,
                   CASE c.operation WHEN 'I' THEN 'INSERT' WHEN 'U' THEN 'UPDATE' ELSE 'DELETE' END AS operation,
                   c.record_id, c.changed_columns,
                   COALESCE(c.actor_user_id, c.owner_user_id) AS user_id,
                   -- No app user + source "sql" = edited directly in SQL Server (SSMS, sqlcmd…).
                   COALESCE(actor.name, CASE WHEN c.source = N'sql' THEN c.db_login END, owner.name, c.db_login) AS changed_by,
                   c.source, c.request_id, a.action AS api_action, a.description AS api_description,
                   c.old_values, c.new_values, c.db_login, c.host_name, c.app_name
            FROM dbo.data_change_log AS c
            LEFT JOIN dbo.users AS actor ON actor.id = c.actor_user_id
            LEFT JOIN dbo.users AS owner ON owner.id = c.owner_user_id
            OUTER APPLY (SELECT TOP (1) l.action, l.description FROM dbo.activity_logs AS l
                         WHERE c.request_id IS NOT NULL AND l.request_id = c.request_id ORDER BY l.id) AS a
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_data_changes_daily AS
            SELECT CAST(changed_at AS date) AS change_date, table_name,
                   SUM(CASE WHEN operation = 'I' THEN 1 ELSE 0 END) AS inserts,
                   SUM(CASE WHEN operation = 'U' THEN 1 ELSE 0 END) AS updates,
                   SUM(CASE WHEN operation = 'D' THEN 1 ELSE 0 END) AS deletes,
                   COUNT(*) AS total
            FROM dbo.data_change_log
            GROUP BY CAST(changed_at AS date), table_name
            SQL);

        // EXEC dbo.sp_data_changes;  EXEC dbo.sp_data_changes @Table = 'assignments', @Operation = 'DELETE', @Days = 30;
        DB::unprepared(<<<'SQL'
            CREATE OR ALTER PROCEDURE dbo.sp_data_changes
                @Table nvarchar(128) = NULL, @Operation varchar(6) = NULL, @UserId bigint = NULL,
                @Days int = 7, @Top int = 500
            AS
            BEGIN
                SET NOCOUNT ON;
                SELECT TOP (@Top) id, changed_at, table_name, operation, record_id, changed_columns,
                       changed_by, source, api_action, old_values, new_values
                FROM dbo.vw_data_changes
                WHERE changed_at >= DATEADD(day, -@Days, SYSDATETIME())
                  AND (@Table IS NULL OR table_name = @Table)
                  AND (@Operation IS NULL OR operation = @Operation)
                  AND (@UserId IS NULL OR user_id = @UserId)
                ORDER BY id DESC;
            END
            SQL);

        // Full history of one record:  EXEC dbo.sp_record_history 'assignments', 3;
        DB::unprepared(<<<'SQL'
            CREATE OR ALTER PROCEDURE dbo.sp_record_history @Table nvarchar(128), @RecordId nvarchar(64)
            AS
            BEGIN
                SET NOCOUNT ON;
                SELECT id, changed_at, operation, changed_columns, changed_by, source, api_action, old_values, new_values
                FROM dbo.vw_data_changes
                WHERE table_name = @Table AND record_id = @RecordId
                ORDER BY id;
            END
            SQL);

        app(DataAuditInstaller::class)->install();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            app(DataAuditInstaller::class)->uninstall();
            DB::unprepared('DROP PROCEDURE IF EXISTS dbo.sp_record_history');
            DB::unprepared('DROP PROCEDURE IF EXISTS dbo.sp_data_changes');
            DB::unprepared('DROP VIEW IF EXISTS dbo.vw_data_changes_daily');
            DB::unprepared('DROP VIEW IF EXISTS dbo.vw_data_changes');
            DB::unprepared('DROP TABLE IF EXISTS dbo.data_change_log');
            DB::unprepared('DROP SEQUENCE IF EXISTS dbo.seq_data_change_log');
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};

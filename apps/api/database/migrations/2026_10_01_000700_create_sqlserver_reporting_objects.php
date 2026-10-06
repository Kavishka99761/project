<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SQL Server reporting layer — views and stored procedures that make the
 * EDU-SMART data easy to explore and report on from SQL Server Management
 * Studio (SSMS), Excel or Power BI without touching application code.
 *
 *   EXEC dbo.sp_student_overview @UserId = 1;
 *   EXEC dbo.sp_weekly_study_report @UserId = 1;
 *   EXEC dbo.sp_assignment_risk_report @UserId = 1;
 *   SELECT * FROM dbo.vw_assignment_risk ORDER BY risk_score DESC;
 *
 * Skipped on other drivers (the test-suite can run on SQLite as well).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $views = [
        'vw_assignment_risk' => <<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_assignment_risk AS
            SELECT a.id AS assignment_id, a.user_id, u.name AS student,
                   m.code AS module_code, m.name AS module_name,
                   a.title, a.type, a.deadline, a.priority, a.status, a.progress,
                   a.estimated_hours, a.completed_hours,
                   CAST(a.estimated_hours * (100 - a.progress) / 100.0 AS decimal(7, 2)) AS remaining_hours,
                   a.risk_score, a.risk_level, a.priority_rank, a.risk_updated_at,
                   DATEDIFF(day, CAST(GETDATE() AS date), CAST(a.deadline AS date)) AS days_left,
                   CASE WHEN a.status <> 'completed' AND a.deadline < GETDATE() THEN 1 ELSE 0 END AS is_overdue
            FROM dbo.assignments AS a
            INNER JOIN dbo.users AS u ON u.id = a.user_id
            LEFT JOIN dbo.modules AS m ON m.id = a.module_id
            WHERE a.deleted_at IS NULL
            SQL,
        'vw_study_daily' => <<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_study_daily AS
            SELECT s.user_id, CAST(s.started_at AS date) AS study_date,
                   COUNT(*) AS sessions,
                   SUM(s.actual_minutes) AS focus_minutes,
                   SUM(s.planned_minutes) AS planned_minutes,
                   AVG(CAST(s.avg_engagement AS float)) AS avg_engagement
            FROM dbo.study_sessions AS s
            WHERE s.status = 'completed'
            GROUP BY s.user_id, CAST(s.started_at AS date)
            SQL,
        'vw_learning_library' => <<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_learning_library AS
            SELECT d.id AS document_id, d.user_id, d.title, d.topic, d.kind,
                   m.code AS module_code, d.pages, d.word_count, d.size_bytes,
                   d.extraction_status, d.created_at,
                   (SELECT COUNT(*) FROM dbo.summaries AS s
                     WHERE s.document_id = d.id AND s.deleted_at IS NULL) AS summary_count
            FROM dbo.documents AS d
            LEFT JOIN dbo.modules AS m ON m.id = d.module_id
            WHERE d.deleted_at IS NULL
            SQL,
        'vw_knowledge_base' => <<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_knowledge_base AS
            SELECT k.id AS knowledge_document_id, k.user_id, k.category, k.title,
                   k.pages, k.chunk_count, k.status, k.indexed_at,
                   (SELECT COUNT(*) FROM dbo.academic_dates AS ad
                     WHERE ad.knowledge_document_id = k.id) AS extracted_dates
            FROM dbo.knowledge_documents AS k
            WHERE k.deleted_at IS NULL
            SQL,
        'vw_activity_daily' => <<<'SQL'
            CREATE OR ALTER VIEW dbo.vw_activity_daily AS
            SELECT user_id, CAST(created_at AS date) AS activity_date, module, COUNT(*) AS actions
            FROM dbo.activity_logs
            GROUP BY user_id, CAST(created_at AS date), module
            SQL,
    ];

    /** @var array<string, string> */
    private array $procedures = [
        'sp_student_overview' => <<<'SQL'
            CREATE OR ALTER PROCEDURE dbo.sp_student_overview @UserId BIGINT
            AS
            BEGIN
                SET NOCOUNT ON;
                SELECT
                    (SELECT COUNT(*) FROM dbo.documents WHERE user_id = @UserId AND deleted_at IS NULL) AS documents,
                    (SELECT COUNT(*) FROM dbo.summaries WHERE user_id = @UserId AND deleted_at IS NULL) AS summaries,
                    (SELECT COUNT(*) FROM dbo.knowledge_documents WHERE user_id = @UserId AND deleted_at IS NULL) AS knowledge_documents,
                    (SELECT COUNT(*) FROM dbo.chat_conversations WHERE user_id = @UserId) AS conversations,
                    (SELECT COUNT(*) FROM dbo.assignments WHERE user_id = @UserId AND deleted_at IS NULL AND status <> 'completed') AS active_assignments,
                    (SELECT COUNT(*) FROM dbo.assignments WHERE user_id = @UserId AND deleted_at IS NULL AND status <> 'completed' AND deadline < GETDATE()) AS overdue_assignments,
                    (SELECT ISNULL(SUM(actual_minutes), 0) FROM dbo.study_sessions
                      WHERE user_id = @UserId AND status = 'completed'
                        AND started_at >= DATEADD(day, -6, CAST(GETDATE() AS date))) AS study_minutes_last_7_days,
                    (SELECT COUNT(*) FROM dbo.activity_logs WHERE user_id = @UserId) AS recorded_actions;
            END
            SQL,
        'sp_weekly_study_report' => <<<'SQL'
            CREATE OR ALTER PROCEDURE dbo.sp_weekly_study_report @UserId BIGINT, @WeekStart DATE = NULL
            AS
            BEGIN
                SET NOCOUNT ON;
                IF @WeekStart IS NULL SET @WeekStart = DATEADD(day, -6, CAST(GETDATE() AS date));
                SELECT d.study_date, d.sessions, d.focus_minutes, d.planned_minutes,
                       CAST(d.avg_engagement AS decimal(5, 1)) AS avg_engagement
                FROM dbo.vw_study_daily AS d
                WHERE d.user_id = @UserId
                  AND d.study_date BETWEEN @WeekStart AND DATEADD(day, 6, @WeekStart)
                ORDER BY d.study_date;
            END
            SQL,
        'sp_assignment_risk_report' => <<<'SQL'
            CREATE OR ALTER PROCEDURE dbo.sp_assignment_risk_report @UserId BIGINT
            AS
            BEGIN
                SET NOCOUNT ON;
                SELECT *
                FROM dbo.vw_assignment_risk
                WHERE user_id = @UserId AND status <> 'completed'
                ORDER BY risk_score DESC, deadline;
            END
            SQL,
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        foreach ([...$this->views, ...$this->procedures] as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        foreach (array_keys($this->procedures) as $name) {
            DB::unprepared("DROP PROCEDURE IF EXISTS dbo.{$name}");
        }
        foreach (array_reverse(array_keys($this->views)) as $name) {
            DB::unprepared("DROP VIEW IF EXISTS dbo.{$name}");
        }
    }
};

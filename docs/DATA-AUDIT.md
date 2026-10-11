# Data audit: every CRUD action saved in SQL Server

AcadeAlert keeps two audit tables in the `AcadeAlert` database:

| Table | One row per | Written by | Answers |
| --- | --- | --- | --- |
| `dbo.activity_logs` | API request (create, update, delete, login, export, …) | `RecordActivity` middleware | *What did the student do?* |
| `dbo.data_change_log` | inserted, updated or deleted **database row** | SQL Server triggers `trg_audit_<table>` | *What exactly changed in the data?* |

The two are linked by `request_id`, so one API call (for example "Updated module")
shows every row it changed.

## How it works

- Each of the 25 application tables has an `AFTER INSERT, UPDATE, DELETE` trigger.
  Because the triggers live in the database, a change is recorded whether it
  comes from the app, a scheduled job, a seeder or a manual edit in SSMS.
- Each log row stores:
  - the operation (`I`, `U` or `D`) and the record id
  - the old and new values as JSON, plus the list of changed columns
  - who made the change, the source (`api`, `console`, `queue` or `sql`), and the SQL login and app name
- Updates that change nothing are skipped.
- Passwords and tokens are stored only as a short fingerprint
  (`[hidden 3FA9…]`). Long text is trimmed to 2,000 characters.
- Laravel tells SQL Server who is acting through `SESSION_CONTEXT`
  (`App\Support\Activity\DatabaseAuditSession`).
- Triggers are dropped before migrations run and re-created afterwards, so
  schema changes never break them.
- Rows older than 365 days are removed by `php artisan acadealert:prune`
  (setting: `DATA_CHANGE_RETENTION_DAYS`).

## Viewing it as a table

### 1. In the web app

Sign in, then go to **Platform → Activity log**. The page has two tabs:

- **Database changes**:
  - counters for created, updated and deleted rows
  - filters by operation, table, date and value
  - click a row to see its before/after diff, or the full history of that record
- **API requests**: every action, with its status code and duration.

Use **Export** on either tab to download the table as PDF, Excel, CSV or JSON.

### 2. In SSMS (server `(localdb)\MSSQLLocalDB`, database `AcadeAlert`)

```sql
-- Latest changes, newest first
SELECT TOP (100) * FROM dbo.vw_data_changes ORDER BY id DESC;

-- Filtered report (all parameters are optional)
EXEC dbo.sp_data_changes;                                   -- last 7 days
EXEC dbo.sp_data_changes @Table = 'assignments', @Days = 30;
EXEC dbo.sp_data_changes @Operation = 'DELETE';

-- Every version of one record
EXEC dbo.sp_record_history 'assignments', '19';

-- Inserts, updates and deletes per table per day
SELECT * FROM dbo.vw_data_changes_daily ORDER BY change_date DESC;

-- API requests
SELECT TOP (100) created_at, action, description, method, status_code
FROM dbo.activity_logs ORDER BY id DESC;
```

### 3. In the terminal (`apps/api`)

```bash
php artisan acadealert:audit status    # audited tables + insert/update/delete counts
php artisan acadealert:audit recent    # latest 20 changes as a table
php artisan acadealert:audit install   # re-create the triggers
```

### 4. Through the API (`/api/v1`, bearer token)

| Endpoint | Returns |
| --- | --- |
| `GET /data-changes?table=&operation=insert\|update\|delete&from=&to=&q=` | Paginated table |
| `GET /data-changes/{id}` | One change with its field-by-field diff |
| `GET /data-changes/history/{table}/{recordId}` | Every change to one record |
| `GET /data-changes/stats` | Counts by operation, by table and by day |
| `GET /exports/data_changes?format=pdf\|xlsx\|csv\|json` | Download |

## Why the log id uses a SEQUENCE

`pdo_sqlsrv` reads a new row's id with `@@IDENTITY`. If the log table had an
`IDENTITY` column, the insert inside the trigger would change that value, and
Laravel would get the wrong id for the row it just created. A `SEQUENCE`
default doesn't affect `@@IDENTITY`.

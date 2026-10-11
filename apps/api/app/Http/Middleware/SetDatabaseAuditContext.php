<?php

namespace App\Http\Middleware;

use App\Support\Activity\ActivityContext;
use App\Support\Activity\DatabaseAuditSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens the database audit session for an API request: every row the
 * request inserts, updates or deletes is attributed (by the SQL Server
 * triggers) to the signed-in student and linked to this request's id, which
 * is also stored on the request's activity_logs row.
 */
class SetDatabaseAuditContext
{
    public function __construct(private readonly ActivityContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Set for reads too: opening a document or searching also writes rows.
        if ($request->method() !== 'OPTIONS') {
            DatabaseAuditSession::begin('api', $this->context->requestId(), $request->user('sanctum')?->id);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Platform\ActivityLogger;
use App\Support\Activity\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit trail: every state-changing API request (POST/PUT/PATCH/DELETE) is
 * recorded in activity_logs together with who did it, what changed, the
 * outcome and how long it took. Read-only requests are recorded only for
 * meaningful "views" (opening a document, downloading an export, …) listed
 * below, so polling and dashboard refreshes don't flood the log.
 */
class RecordActivity
{
    /** GET route name => action recorded for it. */
    private const AUDITED_READS = [
        'documents.show' => 'documents.viewed',
        'documents.file' => 'documents.opened_file',
        'documents.analysis' => 'documents.analysed',
        'summaries.show' => 'summaries.viewed',
        'summaries.download' => 'summaries.downloaded',
        'knowledge.show' => 'knowledge.viewed',
        'knowledge.file' => 'knowledge.opened_file',
        'knowledge.search' => 'knowledge.searched',
        'assistant.conversations.show' => 'assistant.conversation_viewed',
        'assistant.conversations.export' => 'assistant.conversation_exported',
        'assistant.messages.search' => 'assistant.history_searched',
        'assignments.show' => 'assignments.viewed',
        'study.sessions.show' => 'study.session_viewed',
        'exports.download' => 'exports.download',
        'exports.backup' => 'exports.backup',
        'search' => 'platform.searched',
    ];

    public function __construct(
        private readonly ActivityContext $context,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $this->context->start();

        $response = $next($request);

        $routeName = $request->route()?->getName();
        $auditedRead = self::AUDITED_READS[$routeName] ?? null;
        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if (($isWrite || $auditedRead) && ! $this->context->isSkipped()) {
            if ($auditedRead && $this->context->getAction() === null) {
                $this->context->action($auditedRead);
            }
            try {
                $this->logger->recordRequest($request, $response, $this->context, $started);
            } catch (\Throwable $e) {
                // Auditing must never break the student's request.
                report($e);
            }
        }

        return $response;
    }
}

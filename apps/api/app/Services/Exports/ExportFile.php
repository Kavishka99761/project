<?php

namespace App\Services\Exports;

use Symfony\Component\HttpFoundation\Response;

/**
 * A generated download (kept in memory — exports are created on demand from
 * SQL Server and never stored on disk).
 */
final class ExportFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $mime,
        public readonly string $content,
        public readonly int $rows = 0,
    ) {}

    public function size(): int
    {
        return strlen($this->content);
    }

    public function toResponse(): Response
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $this->name) ?: 'export';

        return response($this->content, 200, [
            'Content-Type' => $this->mime,
            'Content-Disposition' => sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $fallback, rawurlencode($this->name)),
            'Content-Length' => (string) $this->size(),
            'X-Export-Rows' => (string) $this->rows,
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Export-Rows',
            'Cache-Control' => 'no-store',
        ]);
    }
}

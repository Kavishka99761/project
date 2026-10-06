<?php

namespace App\Services\Documents;

/**
 * Result of extracting text from an uploaded file.
 */
final class ExtractedText
{
    /**
     * @param  list<string>  $pages  text per page / slide (1-based page = index + 1)
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $text,
        public readonly array $pages,
        public readonly int $pageCount,
        public readonly array $warnings = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}

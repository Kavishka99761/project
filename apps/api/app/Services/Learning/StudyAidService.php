<?php

namespace App\Services\Learning;

use App\Enums\StudyAidType;
use App\Models\Document;
use App\Models\StudyAid;
use App\Services\Nlp\StudyAidGenerator;

/**
 * BETHMI — NotebookLM-style study aids (flashcards, quiz, mind map).
 */
class StudyAidService
{
    public function __construct(
        private readonly DocumentAnalysisService $analysis,
        private readonly StudyAidGenerator $generator,
    ) {}

    /** @return array{type: string, title: string, content: mixed, item_count: int} */
    public function generate(Document $document, StudyAidType $type): array
    {
        $analysis = $this->analysis->analyze($document);
        if ($analysis->isEmpty()) {
            throw new \DomainException('This document has no extracted text yet.');
        }

        $content = match ($type) {
            StudyAidType::Flashcards => $this->generator->flashcards($analysis),
            StudyAidType::Quiz => $this->generator->quiz($analysis),
            StudyAidType::Mindmap => $this->generator->mindmap($analysis, $document->title),
        };

        $count = match ($type) {
            StudyAidType::Mindmap => count($content['children'] ?? []),
            default => count($content),
        };
        if ($count === 0) {
            throw new \DomainException("There isn't enough distinctive content in this document to build a {$type->label()} yet.");
        }

        return [
            'type' => $type->value,
            'title' => mb_substr($document->title, 0, 170).' — '.$type->label(),
            'content' => $content,
            'item_count' => $count,
        ];
    }

    public function generateAndSave(Document $document, StudyAidType $type): StudyAid
    {
        $payload = $this->generate($document, $type);

        return StudyAid::forceCreate([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'type' => $type,
            'title' => $payload['title'],
            'content' => $payload['content'],
            'item_count' => $payload['item_count'],
        ]);
    }
}

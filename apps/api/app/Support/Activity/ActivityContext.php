<?php

namespace App\Support\Activity;

use App\Enums\ModuleKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Request-scoped description of "what the student just did".
 *
 * The RecordActivity middleware opens a context for every API request and,
 * after the response is produced, persists exactly one activity_logs row.
 * Controllers and services enrich it with a human-readable sentence and the
 * affected record; Auditable models add their attribute diffs.
 */
class ActivityContext
{
    private bool $recording = false;

    private bool $skipped = false;

    private ?string $action = null;

    private ?ModuleKey $module = null;

    private ?string $description = null;

    private ?string $subjectType = null;

    private ?int $subjectId = null;

    private ?int $userId = null;

    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var array<int, array<string, mixed>> */
    private array $changes = [];

    public function start(): void
    {
        $this->recording = true;
    }

    public function isRecording(): bool
    {
        return $this->recording;
    }

    public function action(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function module(ModuleKey $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function describe(string $description): static
    {
        $this->description = mb_substr($description, 0, 500);

        return $this;
    }

    public function on(Model $subject): static
    {
        $this->subjectType = class_basename($subject);
        $this->subjectId = (int) $subject->getKey();

        return $this;
    }

    /** @param  array<string, mixed>  $properties */
    public function with(array $properties): static
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    /** Attribute the action to a student (login/registration, before auth). */
    public function asUser(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /** Do not persist this request (e.g. high-frequency telemetry). */
    public function skip(): static
    {
        $this->skipped = true;

        return $this;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    /** @param  array<string, mixed>  $diff */
    public function recordChange(string $model, mixed $id, string $event, array $diff): void
    {
        if (count($this->changes) < 25) {
            $this->changes[] = ['model' => $model, 'id' => $id, 'event' => $event, 'diff' => $diff];
        }
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getModule(): ?ModuleKey
    {
        return $this->module;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function getProperties(): array
    {
        return $this->changes === []
            ? $this->properties
            : $this->properties + ['changes' => $this->changes];
    }
}

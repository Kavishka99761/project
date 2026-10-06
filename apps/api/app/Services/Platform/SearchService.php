<?php

namespace App\Services\Platform;

use App\Models\SearchHistory;
use App\Models\User;
use App\Services\Firebase\FirebaseService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Common Services — Search API. One query across every module: learning
 * documents, summaries, knowledge-base passages, chat history, assignments,
 * calendar events, modules and study sessions, with highlighted snippets.
 */
class SearchService
{
    public const SCOPES = ['all', 'documents', 'summaries', 'knowledge', 'chats', 'assignments', 'events', 'modules', 'sessions'];

    public function __construct(private readonly FirebaseService $firebase) {}

    /** @return array{query: string, total: int, groups: list<array<string, mixed>>} */
    public function search(User $user, string $query, string $scope = 'all', int $limit = 6, bool $record = true): array
    {
        $query = trim($query);
        $like = '%'.$this->escapeLike($query).'%';
        $want = fn (string $group) => $scope === 'all' || $scope === $group;
        $groups = [];

        if ($want('documents')) {
            $groups[] = $this->group('documents', 'Learning materials', 'journal-richtext',
                $user->documents()->with('module:id,code')
                    ->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('topic', 'like', $like)->orWhere('content', 'like', $like))
                    ->latest()->limit($limit)->get(['id', 'title', 'topic', 'module_id', 'kind', 'content'])
                    ->map(fn ($d) => $this->hit($d->id, $d->title, trim(($d->module?->code ?? '').' · '.($d->topic ?? $d->kind->label()), ' ·'),
                        $this->snippet($d->content ?? '', $query), "/learning/documents/{$d->id}", $d->kind->meta()['icon'])));
        }

        if ($want('summaries')) {
            $groups[] = $this->group('summaries', 'Saved summaries', 'card-text',
                $user->summaries()->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                    ->latest()->limit($limit)->get(['id', 'title', 'length', 'content'])
                    ->map(fn ($s) => $this->hit($s->id, $s->title, $s->length->label().' summary', $this->snippet($s->content, $query), "/learning/summaries/{$s->id}", 'card-text')));
        }

        if ($want('knowledge')) {
            $chunks = $user->knowledgeChunks()->with('document:id,title,category')
                ->whereHas('document', fn ($q) => $q->whereNull('deleted_at'))
                ->where(fn (Builder $q) => $q->where('content', 'like', $like)->orWhere('section', 'like', $like))
                ->limit($limit)->get(['id', 'knowledge_document_id', 'section', 'page_number', 'content']);
            $groups[] = $this->group('knowledge', 'Academic documents', 'book-half',
                $chunks->map(fn ($c) => $this->hit($c->knowledge_document_id, $c->document?->title ?? 'Document',
                    trim(($c->section ?? '').($c->page_number ? ' · p. '.$c->page_number : ''), ' ·'),
                    $this->snippet($c->content, $query), "/assistant/knowledge/{$c->knowledge_document_id}?page={$c->page_number}", $c->document?->category?->meta()['icon'] ?? 'book')));
        }

        if ($want('chats')) {
            $groups[] = $this->group('chats', 'Previous questions', 'chat-dots',
                $user->chatMessages()->where('role', 'user')->where('content', 'like', $like)
                    ->latest()->limit($limit)->get(['id', 'chat_conversation_id', 'content', 'created_at'])
                    ->map(fn ($m) => $this->hit($m->chat_conversation_id, $m->content, 'Asked '.$m->created_at->diffForHumans(), null,
                        "/assistant/chat/{$m->chat_conversation_id}", 'chat-dots')));
        }

        if ($want('assignments')) {
            $groups[] = $this->group('assignments', 'Assignments', 'clipboard-data',
                $user->assignments()->with('module:id,code')
                    ->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
                    ->orderBy('deadline')->limit($limit)->get()
                    ->map(fn ($a) => $this->hit($a->id, $a->title, trim(($a->module?->code ?? '').' · due '.$a->deadline->format('d M Y'), ' ·'),
                        $this->snippet($a->description ?? '', $query), "/assignments/{$a->id}", 'clipboard-check')));
        }

        if ($want('events')) {
            $groups[] = $this->group('events', 'Calendar', 'calendar-event',
                $user->calendarEvents()->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
                    ->orderBy('starts_at')->limit($limit)->get()
                    ->map(fn ($e) => $this->hit($e->id, $e->title, $e->type->label().' · '.$e->starts_at->format('d M Y'), null,
                        '/calendar?date='.$e->starts_at->toDateString(), $e->type->meta()['icon'])));
        }

        if ($want('modules')) {
            $groups[] = $this->group('modules', 'Modules', 'collection',
                $user->modules()->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->limit($limit)->get()
                    ->map(fn ($m) => $this->hit($m->id, $m->code.' — '.$m->name, $m->lecturer, null, "/modules?highlight={$m->id}", $m->icon)));
        }

        if ($want('sessions')) {
            $groups[] = $this->group('sessions', 'Study sessions', 'stopwatch',
                $user->studySessions()->where(fn (Builder $q) => $q->where('goal', 'like', $like)->orWhere('notes', 'like', $like))
                    ->latest('started_at')->limit($limit)->get()
                    ->map(fn ($s) => $this->hit($s->id, $s->goal ?: $s->activity->label(), $s->started_at->format('d M Y, H:i').' · '.$s->actual_minutes.' min',
                        $this->snippet((string) $s->notes, $query), "/study/sessions/{$s->id}", 'stopwatch')));
        }

        $groups = array_values(array_filter($groups, fn ($g) => $g['items'] !== []));
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        if ($record && mb_strlen($query) >= 2) {
            $history = SearchHistory::create(['user_id' => $user->id, 'query' => mb_substr($query, 0, 255), 'scope' => $scope, 'results_count' => $total]);
            $this->firebase->mirrorSearch($user->id, $history->id, $query, $total);
        }

        return ['query' => $query, 'total' => $total, 'groups' => $groups];
    }

    /** Text around the first match, for highlighted result previews. */
    public function snippet(string $text, string $query, int $radius = 90): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '' || $query === '') {
            return null;
        }
        $position = mb_stripos($text, $query);
        if ($position === false) {
            return mb_strlen($text) > $radius * 2 ? mb_substr($text, 0, $radius * 2).'…' : $text;
        }
        $start = max(0, $position - $radius);
        $snippet = mb_substr($text, $start, mb_strlen($query) + $radius * 2);

        return ($start > 0 ? '…' : '').$snippet.($start + mb_strlen($snippet) < mb_strlen($text) ? '…' : '');
    }

    /** SQL Server LIKE wildcards are escaped with brackets. */
    public function escapeLike(string $value): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    private function group(string $key, string $label, string $icon, iterable $items): array
    {
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => collect($items)->values()->all()];
    }

    private function hit(int $id, string $title, ?string $subtitle, ?string $snippet, string $url, ?string $icon): array
    {
        return compact('id', 'title', 'subtitle', 'snippet', 'url', 'icon');
    }
}

@extends('exports.layout')

@section('title', $summary->title)
@section('badge', 'Learning · '.$summary->length->label().' summary')

@section('content')
    <h1>{{ $summary->title }}</h1>
    <p class="muted">
        @if ($summary->document) Source: {{ $summary->document->title }} · @endif
        {{ $summary->word_count }} words · created {{ $summary->created_at->format('d M Y, H:i') }}
    </p>

    <h2>Summary</h2>
    @foreach (preg_split('/\n{2,}/', $summary->content) as $paragraph)
        @if (str_starts_with($paragraph, '### '))
            <h3>{{ substr($paragraph, 4) }}</h3>
        @elseif (preg_match('/^### (.+)\n+(.*)$/s', $paragraph, $m))
            <h3>{{ $m[1] }}</h3><p>{{ $m[2] }}</p>
        @else
            <p>{{ $paragraph }}</p>
        @endif
    @endforeach

    @if ($summary->key_concepts)
        <h2>Key concepts</h2>
        @foreach ($summary->key_concepts as $concept)
            <div class="card"><strong>{{ $concept['term'] }}</strong> — {{ $concept['definition'] }}</div>
        @endforeach
    @endif

    @if ($summary->bullet_points)
        <h2>Revision notes</h2>
        @foreach ($summary->bullet_points as $group)
            <h3>{{ $group['heading'] }}</h3>
            <ul>@foreach ($group['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
        @endforeach
    @endif

    @if ($summary->highlights)
        <h2>Important sentences</h2>
        <ul>
            @foreach (array_slice($summary->highlights, 0, 12) as $highlight)
                <li>@if (($highlight['level'] ?? '') === 'key')<strong>{{ $highlight['text'] }}</strong>@else{{ $highlight['text'] }}@endif</li>
            @endforeach
        </ul>
    @endif

    @if ($summary->keywords)
        <h2>Keywords</h2>
        @foreach ($summary->keywords as $keyword)<span class="chip">{{ $keyword['term'] }}</span>@endforeach
    @endif
@endsection

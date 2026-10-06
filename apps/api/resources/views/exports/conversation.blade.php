@extends('exports.layout')

@section('title', $conversation->title)
@section('badge', 'Academic assistant')

@section('content')
    <h1>{{ $conversation->title }}</h1>
    <p class="muted">{{ $messages->count() }} messages · started {{ $conversation->created_at->format('d M Y, H:i') }}</p>

    @foreach ($messages as $message)
        @if ($message->role === 'user')
            <div class="card" style="background:#eef2ff;border-color:#c7d2fe">
                <strong style="color:#4338ca">You</strong> <span class="muted">· {{ $message->created_at->format('d M, H:i') }}</span><br>
                {{ $message->content }}
            </div>
        @else
            <div class="card" style="background:#ffffff">
                <strong style="color:#7c3aed">Assistant</strong>
                @if ($message->confidence !== null)<span class="muted">· confidence {{ round($message->confidence * 100) }}%</span>@endif
                <br>{{ $message->content }}
                @php($cited = array_filter((array) $message->sources, fn ($s) => $s['cited'] ?? false))
                @if ($cited)
                    <div style="margin-top:6px">
                        @foreach ($cited as $source)
                            <span class="chip">[{{ $source['n'] }}] {{ $source['document'] }}@if ($source['section']) — {{ $source['section'] }}@endif @if ($source['page']), p. {{ $source['page'] }}@endif</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endforeach
@endsection

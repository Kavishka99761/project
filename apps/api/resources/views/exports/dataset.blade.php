@extends('exports.layout')

@section('title', $title)
@section('badge', $module)

@section('content')
    <h1>{{ $title }}</h1>
    <p class="muted">
        {{ number_format($total) }} record{{ $total === 1 ? '' : 's' }}
        @if ($range) · {{ $range }} @endif
        @if ($truncated) · showing the first {{ count($rows) }} (download Excel/CSV/JSON for every row) @endif
    </p>

    @if (count($rows) === 0)
        <div class="card">No records to show yet.</div>
    @else
        <table class="data">
            <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ \Illuminate\Support\Str::limit((string) $cell, 380) }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection

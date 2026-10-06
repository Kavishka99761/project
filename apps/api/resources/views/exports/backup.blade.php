@extends('exports.layout')

@section('title', 'Account backup')
@section('badge', 'Full backup')

@section('content')
    <h1>Account backup</h1>
    <p class="muted">Every dataset stored for {{ $user->name }} across the four EDU-SMART modules — {{ number_format($total) }} records in total.</p>

    <table class="data">
        <thead><tr><th>Dataset</th><th>Module</th><th style="width: 60px">Records</th></tr></thead>
        <tbody>
        @foreach ($sections as $section)
            <tr><td>{{ $section['title'] }}</td><td>{{ $section['module'] }}</td><td>{{ $section['total'] }}</td></tr>
        @endforeach
        </tbody>
    </table>

    @foreach ($sections as $section)
        @if ($section['total'] > 0)
            <div class="page-break"></div>
            <h2>{{ $section['title'] }} <span class="muted">· {{ $section['module'] }} · {{ $section['total'] }} records</span></h2>
            @if ($section['total'] > count($section['rows']))
                <p class="muted">Showing the first {{ count($section['rows']) }} rows — the Excel and JSON backups contain everything.</p>
            @endif
            <table class="data">
                <thead><tr>@foreach ($section['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($section['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td>{{ \Illuminate\Support\Str::limit((string) $cell, 220) }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
@endsection

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title') · EDU-SMART</title>
    <style>
        @page { margin: 92px 36px 64px 36px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1e293b; line-height: 1.45; }
        header { position: fixed; top: -72px; left: 0; right: 0; height: 56px; border-bottom: 2px solid #4f46e5; }
        header .brand { font-size: 17px; font-weight: bold; color: #4f46e5; letter-spacing: .5px; }
        header .brand span { color: #0f172a; }
        header .meta { font-size: 8.5px; color: #64748b; margin-top: 2px; }
        header .badge { float: right; margin-top: 6px; background: #eef2ff; color: #4338ca; border-radius: 10px; padding: 4px 10px; font-size: 8.5px; }
        footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 24px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        h1 { font-size: 18px; color: #0f172a; margin: 0 0 4px; }
        h2 { font-size: 13px; color: #312e81; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e0e7ff; }
        h3 { font-size: 11px; color: #334155; margin: 12px 0 4px; }
        p { margin: 0 0 8px; }
        .muted { color: #64748b; }
        .chip { display: inline-block; background: #f1f5f9; border-radius: 8px; padding: 2px 7px; margin: 0 3px 3px 0; font-size: 8.5px; color: #334155; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: #f8fafc; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #4f46e5; color: #fff; text-align: left; font-size: 8.5px; padding: 5px 6px; }
        table.data td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; vertical-align: top; font-size: 8.5px; word-wrap: break-word; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.kpi { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 8px -6px 4px; }
        table.kpi td { background: #eef2ff; border-radius: 8px; padding: 8px 10px; }
        table.kpi .value { font-size: 15px; font-weight: bold; color: #312e81; }
        table.kpi .label { font-size: 8px; color: #6366f1; text-transform: uppercase; letter-spacing: .4px; }
        .page-break { page-break-after: always; }
        ul { margin: 0 0 8px 0; padding-left: 14px; }
        li { margin-bottom: 3px; }
    </style>
</head>
<body>
<header>
    <span class="badge">@yield('badge', 'Export')</span>
    <div class="brand">EDU<span>-SMART</span></div>
    <div class="meta">{{ $user->name }} · {{ $user->email }} · Generated {{ now()->format('d M Y, H:i') }} · Source: Microsoft SQL Server</div>
</header>
<footer>EDU-SMART academic productivity platform — confidential student data. Exported on {{ now()->format('d M Y H:i') }}.</footer>

<main>
    @yield('content')
</main>

<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont("DejaVu Sans");
        $pdf->page_text($pdf->get_width() - 92, $pdf->get_height() - 34, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 7.5, [0.58, 0.64, 0.72]);
    }
</script>
</body>
</html>

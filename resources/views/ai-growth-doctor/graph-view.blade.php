<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Growth Doctor Graph - {{ $runId }}</title>
    @php
        $manifestPath = public_path('build/manifest.json');
        $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
        $entry = $manifest['resources/js/agd-graph/app.jsx'] ?? null;
        $devServer = env('VITE_DEV_SERVER_URL', 'http://localhost:5173');
        $useDevServer = app()->environment('local') && !file_exists($manifestPath);
    @endphp
    @if (!$useDevServer && $entry)
        @foreach (($entry['css'] ?? []) as $cssFile)
            <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}">
        @endforeach
    @endif
</head>
<body>
    <div class="agd-page-shell">
        <header class="agd-page-header">
            <div>
                <p>AI Growth Doctor</p>
                <h1>Agent Society Graph</h1>
                <span>Run {{ $runId }}</span>
            </div>
            <nav>
                <a href="{{ url('/ai-growth-doctor') }}">Dashboard</a>
                <a href="{{ route('ai-growth-doctor.runs.graph', ['runId' => $runId]) }}">Graph JSON</a>
            </nav>
        </header>

        <div
            id="agd-graph-root"
            data-run-id="{{ $runId }}"
            data-graph-url="{{ route('ai-growth-doctor.runs.graph', ['runId' => $runId]) }}"
        >
            <div class="agd-loading-fallback">Loading graph visualizer...</div>
        </div>
    </div>

    @if ($useDevServer)
        <script type="module" src="{{ $devServer }}/@vite/client"></script>
        <script type="module" src="{{ $devServer }}/resources/js/agd-graph/app.jsx"></script>
    @elseif ($entry)
        <script type="module" src="{{ asset('build/' . $entry['file']) }}"></script>
    @else
        <script>
            document.getElementById('agd-graph-root').innerHTML = '<div class="agd-loading-fallback">Graph asset is not built yet. Run npm run build, or start npm run dev.</div>';
        </script>
    @endif
</body>
</html>

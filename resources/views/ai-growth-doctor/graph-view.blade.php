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
        $configuredAssetUrl = rtrim((string) config('app.asset_url'), '/');
        $appPath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        $requestBasePath = trim((string) request()->getBaseUrl(), '/');
        $assetBasePath = $configuredAssetUrl ?: ($requestBasePath ? '/' . $requestBasePath : ($appPath ? '/' . $appPath : ''));
        $graphAsset = static function (string $file) use ($assetBasePath): string {
            return rtrim($assetBasePath, '/') . '/build/' . ltrim($file, '/');
        };
    @endphp
    @if (!$useDevServer && $entry)
        @foreach (($entry['css'] ?? []) as $cssFile)
            <link rel="stylesheet" href="{{ $graphAsset($cssFile) }}">
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
        <script>
            window.setTimeout(function () {
                var root = document.getElementById('agd-graph-root');
                if (!root || root.dataset.graphMounted === 'true') {
                    return;
                }

                root.innerHTML = '<div class="agd-loading-fallback">Graph asset failed to load. Check the graph JS/CSS build URL and run npm run build.</div>';
            }, 4000);
        </script>
        <script
            type="module"
            src="{{ $graphAsset($entry['file']) }}"
            onload="document.getElementById('agd-graph-root').dataset.graphMounted = 'true'"
        ></script>
    @else
        <script>
            document.getElementById('agd-graph-root').innerHTML = '<div class="agd-loading-fallback">Graph asset is not built yet. Run npm run build, or start npm run dev.</div>';
        </script>
    @endif
</body>
</html>

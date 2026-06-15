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
        $graphAssetCandidates = static function (string $file): array {
            $file = ltrim($file, '/');
            $basename = basename($file);

            return array_values(array_unique([
                route('ai-growth-doctor.graph-asset', ['file' => $basename]),
                asset('build/' . $file),
                url('/build/' . $file),
                url('/ai-growth-doctor/build/' . $file),
            ]));
        };
    @endphp
    @if (!$useDevServer && $entry)
        @foreach (($entry['css'] ?? []) as $cssFile)
            @foreach ($graphAssetCandidates($cssFile) as $cssHref)
                <link rel="stylesheet" href="{{ $cssHref }}">
            @endforeach
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
            (function () {
                var graphScriptUrls = @json($graphAssetCandidates($entry['file']));
                var attemptedGraphScriptUrls = [];
                var root = document.getElementById('agd-graph-root');

                function showGraphAssetError() {
                    root = root || document.getElementById('agd-graph-root');
                    if (!root || root.dataset.graphMounted === 'true') {
                        return;
                    }

                    root.innerHTML = '<div class="agd-loading-fallback">Graph asset failed to load. Tried: ' + attemptedGraphScriptUrls.join(', ') + '</div>';
                }

                function loadGraphScript(index) {
                    if (index >= graphScriptUrls.length) {
                        showGraphAssetError();
                        return;
                    }

                    var script = document.createElement('script');
                    script.type = 'module';
                    script.src = graphScriptUrls[index];
                    attemptedGraphScriptUrls.push(script.src);
                    script.onload = function () {
                        root = root || document.getElementById('agd-graph-root');
                        if (root) {
                            root.dataset.graphMounted = 'true';
                        }
                    };
                    script.onerror = function () {
                        loadGraphScript(index + 1);
                    };
                    document.body.appendChild(script);
                }

                loadGraphScript(0);

                window.setTimeout(function () {
                    showGraphAssetError();
                }, 6000);
            })();
        </script>
    @else
        <script>
            document.getElementById('agd-graph-root').innerHTML = '<div class="agd-loading-fallback">Graph asset is not built yet. Run npm run build, or start npm run dev.</div>';
        </script>
    @endif
</body>
</html>

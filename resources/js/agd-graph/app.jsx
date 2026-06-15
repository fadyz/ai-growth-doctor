import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '@xyflow/react/dist/style.css';
import './styles.css';
import AiGrowthDoctorGraph from './components/AiGrowthDoctorGraph';
import { mapGraphPayload } from './graphMapper';

function GraphApp({ root }) {
  const graphUrl = root.dataset.graphUrl;
  const runId = root.dataset.runId;
  const [graph, setGraph] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;

    fetch(graphUrl)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Graph request failed with HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (mounted) {
          setGraph(mapGraphPayload(payload));
        }
      })
      .catch((requestError) => {
        if (mounted) {
          setError(requestError.message || 'Graph failed to load.');
        }
      });

    return () => {
      mounted = false;
    };
  }, [graphUrl]);

  if (error) {
    return (
      <div className="agd-error">
        <h2>Graph visualizer could not load</h2>
        <p>{error}</p>
        <a href={graphUrl}>Open graph JSON</a>
      </div>
    );
  }

  if (!graph) {
    return <div className="agd-loading">Loading graph data for {runId}...</div>;
  }

  return <AiGrowthDoctorGraph graph={graph} graphUrl={graphUrl} />;
}

const root = document.getElementById('agd-graph-root');

if (root) {
  root.dataset.graphMounted = 'true';
  createRoot(root).render(<GraphApp root={root} />);
}

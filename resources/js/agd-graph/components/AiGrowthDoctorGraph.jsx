import React, { useMemo, useState } from 'react';
import { toPng } from 'html-to-image';
import {
  Background,
  Controls,
  MiniMap,
  ReactFlow,
  useEdgesState,
  useNodesState,
  useReactFlow,
  ReactFlowProvider,
} from '@xyflow/react';
import PipelineNode from './nodes/PipelineNode';
import AgentNode from './nodes/AgentNode';
import NegotiationNode from './nodes/NegotiationNode';
import DecisionNode from './nodes/DecisionNode';
import OutputNode from './nodes/OutputNode';
import GraphDetailPanel from './GraphDetailPanel';
import GraphToolbar from './GraphToolbar';

const nodeTypes = {
  pipelineNode: PipelineNode,
  agentNode: AgentNode,
  negotiationNode: NegotiationNode,
  decisionNode: DecisionNode,
  outputNode: OutputNode,
};

function GraphCanvas({ graph, graphUrl }) {
  const [nodes, setNodes, onNodesChange] = useNodesState(graph.nodes || []);
  const [edges, setEdges, onEdgesChange] = useEdgesState(graph.edges || []);
  const [selectedNode, setSelectedNode] = useState(null);
  const [showMiniMap, setShowMiniMap] = useState(true);
  const [showDetails, setShowDetails] = useState(true);
  const [showEdgeLabels, setShowEdgeLabels] = useState(true);
  const [presentationMode, setPresentationMode] = useState(true);
  const [isExporting, setIsExporting] = useState(false);
  const { fitView, setViewport } = useReactFlow();

  const visibleEdges = useMemo(
    () => edges.map((edge) => ({ ...edge, label: showEdgeLabels ? edge.label : '' })),
    [edges, showEdgeLabels]
  );

  const selectedId = selectedNode?.id || null;
  const decoratedNodes = useMemo(
    () =>
      nodes.map((node) => ({
        ...node,
        data: {
          ...node.data,
          selected: node.id === selectedId,
          presentationMode,
        },
      })),
    [nodes, selectedId, presentationMode]
  );

  const exportPng = async () => {
    const flowElement = document.querySelector('.agd-flow-frame .react-flow');
    const frameElement = document.querySelector('.agd-flow-frame');

    if (!flowElement) {
      window.alert('Graph canvas is not ready yet.');
      return;
    }

    setIsExporting(true);
    frameElement?.classList.add('is-exporting-png');

    try {
      await fitView({ padding: 0.12, duration: 0 });
      await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));

      const dataUrl = await toPng(flowElement, {
        backgroundColor: '#f8fafc',
        cacheBust: true,
        pixelRatio: 2,
        filter: (node) => !node.classList?.contains('react-flow__controls') && !node.classList?.contains('react-flow__minimap'),
      });
      const link = document.createElement('a');
      link.download = `ai-growth-doctor-graph-${graph.run_id || 'run'}.png`;
      link.href = dataUrl;
      link.click();
    } catch (error) {
      window.alert(`PNG export failed: ${error.message || 'Unknown error'}`);
    } finally {
      frameElement?.classList.remove('is-exporting-png');
      setIsExporting(false);
    }
  };

  return (
    <div className={`agd-graph-layout ${showDetails ? 'has-details' : ''}`}>
      <main className="agd-graph-main">
        <div className="agd-summary">
          <SummaryCard label="Run status" value={graph.summary?.status || 'unknown'} />
          <SummaryCard label="Agents" value={graph.summary?.agent_count ?? 6} />
          <SummaryCard label="Hard conflicts" value={graph.summary?.total_conflict_count ?? 0} />
          <SummaryCard label="Bounded tensions" value={graph.summary?.bounded_tension_count ?? 0} />
          <SummaryCard label="Partial concessions" value={graph.summary?.partial_concession_count ?? 0} />
          <SummaryCard label="Final verdict" value={graph.summary?.business_verdict || 'No verdict'} />
          <SummaryCard label="Forecast trust" value={graph.summary?.forecast_trust_score ?? 'N/A'} />
          <SummaryCard label="Unsafe prevented" value={graph.summary?.unsafe_recommendation_prevented === true ? 'Yes' : graph.summary?.unsafe_recommendation_prevented === false ? 'No' : 'N/A'} />
        </div>

        <GraphToolbar
          graphUrl={graphUrl}
          onFitView={() => fitView({ padding: 0.12, duration: 250 })}
          onResetZoom={() => setViewport({ x: 20, y: 20, zoom: 0.7 }, { duration: 250 })}
          showMiniMap={showMiniMap}
          onToggleMiniMap={() => setShowMiniMap((value) => !value)}
          showDetails={showDetails}
          onToggleDetails={() => setShowDetails((value) => !value)}
          showEdgeLabels={showEdgeLabels}
          onToggleEdgeLabels={() => setShowEdgeLabels((value) => !value)}
          presentationMode={presentationMode}
          onTogglePresentation={() => setPresentationMode((value) => !value)}
          onExportPng={exportPng}
          isExporting={isExporting}
        />

        <div className="agd-flow-frame">
          <ReactFlow
            nodes={decoratedNodes}
            edges={visibleEdges}
            nodeTypes={nodeTypes}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onNodeClick={(_, node) => setSelectedNode(node)}
            onPaneClick={() => setSelectedNode(null)}
            fitView
            fitViewOptions={{ padding: 0.1 }}
            minZoom={0.2}
            maxZoom={1.4}
          >
            <Background gap={28} size={1} />
            <Controls />
            {showMiniMap && <MiniMap zoomable pannable nodeStrokeWidth={3} />}
          </ReactFlow>
        </div>
      </main>

      {showDetails && <GraphDetailPanel graph={graph} selectedNode={selectedNode} />}
    </div>
  );
}

function SummaryCard({ label, value }) {
  return (
    <div className="agd-summary-card">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

export default function AiGrowthDoctorGraph(props) {
  return (
    <ReactFlowProvider>
      <GraphCanvas {...props} />
    </ReactFlowProvider>
  );
}

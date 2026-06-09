import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function NegotiationNode({ data }) {
  return (
    <div className={`agd-node agd-negotiation-node ${data.selected ? 'is-selected' : ''} ${data.totalConflictCount > 0 ? 'has-conflicts' : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">negotiation</div>
      <h3>{data.title}</h3>
      <div className="agd-metrics-grid">
        <span><strong>{data.maxRounds ?? 1}</strong> round</span>
        <span><strong>{data.totalConflictCount ?? 0}</strong> conflicts</span>
        <span><strong>{data.materialConflictCount ?? 0}</strong> material</span>
        <span><strong>{data.agentResponseCount ?? 0}</strong> responses</span>
      </div>
      <div className="agd-badge-row">
        <Badge tone={data.criticalConflictCount > 0 ? 'danger' : data.materialConflictCount > 0 ? 'warning' : 'success'}>{data.status}</Badge>
      </div>
      {!data.presentationMode && <p>{data.resultSummary || 'No data available'}</p>}
      <Handle type="source" position={Position.Right} />
    </div>
  );
}

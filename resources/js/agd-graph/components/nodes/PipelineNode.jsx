import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function PipelineNode({ data }) {
  return (
    <div className={`agd-node agd-pipeline-node ${data.selected ? 'is-selected' : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">{data.category || 'pipeline'}</div>
      <h3>{data.title}</h3>
      <p className="agd-node-subtitle">{data.subtitle || 'No data available'}</p>
      <div className="agd-badge-row">
        <Badge tone={data.status === 'empty' ? 'neutral' : 'success'}>{data.status}</Badge>
        <Badge tone="neutral">{data.badge}</Badge>
      </div>
      {!data.presentationMode && <p>{data.summary || 'No data available'}</p>}
      <Handle type="source" position={Position.Right} />
    </div>
  );
}

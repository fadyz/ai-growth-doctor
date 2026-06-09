import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function AgentNode({ data }) {
  const tone = data.highlight === 'material-conflict' ? 'warning' : data.highlight === 'low-trust' ? 'danger' : data.status === 'warning' ? 'warning' : 'success';

  return (
    <div className={`agd-node agd-agent-node ${data.selected ? 'is-selected' : ''} ${data.highlight ? `is-${data.highlight}` : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">{data.domain || 'agent'}</div>
      <h3>{data.title}</h3>
      <div className="agd-badge-row">
        <Badge tone={tone}>{data.status || 'empty'}</Badge>
        {data.cacheHit && <Badge tone="neutral">cached</Badge>}
        {data.confidence !== null && data.confidence !== undefined && <Badge tone="neutral">{data.confidence}% confidence</Badge>}
      </div>
      {!data.presentationMode && <p>{data.summary || 'No data available'}</p>}
      {data.model && <span className="agd-node-model">{data.model}</span>}
      <Handle type="source" position={Position.Right} />
    </div>
  );
}

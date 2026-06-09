import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function DecisionNode({ data }) {
  return (
    <div className={`agd-node agd-decision-node ${data.selected ? 'is-selected' : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">decision owner</div>
      <h3>{data.title}</h3>
      <div className="agd-badge-row">
        <Badge tone="success">{data.businessVerdict}</Badge>
        {data.confidence !== null && data.confidence !== undefined && <Badge tone="neutral">{data.confidence}% confidence</Badge>}
      </div>
      <p>{data.summary || 'No data available'}</p>
      <Handle type="source" position={Position.Right} />
    </div>
  );
}

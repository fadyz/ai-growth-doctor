import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function OutputNode({ data }) {
  return (
    <div className={`agd-node agd-output-node ${data.selected ? 'is-selected' : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">output</div>
      <h3>{data.title}</h3>
      <Badge tone={data.status === 'empty' ? 'neutral' : 'success'}>{data.status}</Badge>
      <p>{data.summary || 'No data available'}</p>
    </div>
  );
}

import React from 'react';
import Badge from './Badge';
import EvidenceList from './EvidenceList';
import { asArray } from '../graphMapper';

export default function ConflictMatrix({ conflicts }) {
  const rows = asArray(conflicts);

  if (!rows.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-conflicts">
      {rows.map((conflict, index) => (
        <article className="agd-conflict-card" key={conflict.conflict_id || index}>
          <div className="agd-conflict-head">
            <strong>{conflict.topic || conflict.conflict_id || `Conflict ${index + 1}`}</strong>
            <Badge tone={conflict.severity === 'critical' ? 'danger' : conflict.severity === 'material' ? 'warning' : 'neutral'}>
              {conflict.severity || 'unknown'}
            </Badge>
          </div>
          <p>{asArray(conflict.agents_involved).join(', ') || 'No agents listed'}</p>
          <dl className="agd-detail-dl">
            <dt>Initial position</dt>
            <dd>{conflict.initial_position || 'No data available'}</dd>
            <dt>Counter position</dt>
            <dd>{conflict.counter_position || 'No data available'}</dd>
            <dt>Resolution candidate</dt>
            <dd>{conflict.resolution_candidate || 'No data available'}</dd>
          </dl>
          <EvidenceList items={conflict.evidence_summary} />
        </article>
      ))}
    </div>
  );
}

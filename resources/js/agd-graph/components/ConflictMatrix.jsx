import React from 'react';
import Badge from './Badge';
import EvidenceList from './EvidenceList';
import { asArray } from '../graphMapper';

export default function ConflictMatrix({ conflicts, emptyLabel = 'No data available' }) {
  const rows = asArray(conflicts);

  if (!rows.length) {
    return <p className="agd-empty">{emptyLabel}</p>;
  }

  return (
    <div className="agd-conflicts">
      {rows.map((conflict, index) => (
        <article className="agd-conflict-card" key={conflict.conflict_id || index}>
          <div className="agd-conflict-head">
            <strong>{conflict.title || conflict.topic || conflict.conflict_id || `Conflict ${index + 1}`}</strong>
            <Badge tone={conflict.severity === 'critical' ? 'danger' : conflict.severity === 'material' ? 'warning' : 'neutral'}>
              {conflict.type === 'bounded_tension' || conflict.conflict_type === 'bounded_tension' ? 'bounded tension' : (conflict.severity || 'unknown')}
            </Badge>
          </div>
          <p>{asArray(conflict.supporting_agents || conflict.agents_involved).join(', ') || 'No agents listed'}</p>
          <dl className="agd-detail-dl">
            <dt>{conflict.type === 'bounded_tension' || conflict.conflict_type === 'bounded_tension' ? 'Domain-only tension' : 'Initial position'}</dt>
            <dd>{conflict.domain_only_tension || conflict.initial_position || 'No data available'}</dd>
            <dt>{conflict.type === 'bounded_tension' || conflict.conflict_type === 'bounded_tension' ? 'Bounded-system resolution' : 'Counter position'}</dt>
            <dd>{conflict.bounded_system_resolution || conflict.counter_position || 'No data available'}</dd>
            <dt>Resolution mode</dt>
            <dd>{conflict.resolution_mode || conflict.resolution_candidate || 'No data available'}</dd>
            <dt>Status</dt>
            <dd>{conflict.status || 'No data available'}</dd>
          </dl>
          <EvidenceList items={conflict.evidence_summary || conflict.evidence_refs} />
        </article>
      ))}
    </div>
  );
}

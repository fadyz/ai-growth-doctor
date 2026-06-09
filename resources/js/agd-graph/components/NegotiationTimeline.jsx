import React from 'react';
import Badge from './Badge';
import { asArray } from '../graphMapper';

export default function NegotiationTimeline({ items }) {
  const rows = asArray(items);

  if (!rows.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-timeline">
      {rows.map((row, index) => (
        <article className="agd-timeline-item" key={row.sequence || index}>
          <div className="agd-timeline-dot">{row.sequence || index + 1}</div>
          <div>
            <div className="agd-timeline-head">
              <strong>{row.from || 'Unknown'} → {row.to || 'Unknown'}</strong>
              <Badge tone={row.severity === 'material' ? 'warning' : 'neutral'}>{row.severity || row.type}</Badge>
            </div>
            <p>{row.claim || 'No data available'}</p>
            {row.revised_recommendation && <p><strong>Revised:</strong> {row.revised_recommendation}</p>}
            {!!asArray(row.evidence_refs).length && (
              <details>
                <summary>Evidence refs</summary>
                <ul>
                  {asArray(row.evidence_refs).map((ref, refIndex) => <li key={refIndex}>{ref}</li>)}
                </ul>
              </details>
            )}
          </div>
        </article>
      ))}
    </div>
  );
}

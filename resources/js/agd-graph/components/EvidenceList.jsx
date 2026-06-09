import React from 'react';
import { asArray, stringifyValue } from '../graphMapper';

export default function EvidenceList({ items }) {
  const evidence = asArray(items);

  if (!evidence.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-evidence-list">
      {evidence.map((item, index) => (
        <div className="agd-evidence-item" key={index}>
          {typeof item === 'object' && item !== null ? (
            <>
              <strong>{item.metric || item.label || `Evidence ${index + 1}`}</strong>
              <span>{stringifyValue(item.value || item.interpretation || item)}</span>
            </>
          ) : (
            <span>{stringifyValue(item)}</span>
          )}
        </div>
      ))}
    </div>
  );
}

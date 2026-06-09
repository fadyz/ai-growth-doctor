import React from 'react';

export default function Badge({ children, tone = 'neutral' }) {
  if (children === undefined || children === null || children === '') {
    return null;
  }

  return <span className={`agd-badge agd-badge-${tone}`}>{children}</span>;
}

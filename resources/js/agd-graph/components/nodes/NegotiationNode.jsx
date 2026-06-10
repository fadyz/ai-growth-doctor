import React from 'react';
import { Handle, Position } from '@xyflow/react';
import Badge from '../Badge';

export default function NegotiationNode({ data }) {
  const rounds = data.rounds || [
    { label: 'Round 1', name: 'Objection / Support / Risk Warning', status: 'skipped' },
    { label: 'Round 2', name: 'Revision / Rebuttal', status: 'skipped' },
    { label: 'Round 3', name: 'Escalation Only', status: 'skipped' },
  ];
  const earlyExit = data.earlyExit || {
    label: 'Early Exit',
    condition: `material_or_higher_conflict_count = ${data.materialOrHigherConflictCount ?? 0}`,
    status: (data.materialOrHigherConflictCount ?? 0) === 0 ? 'clear' : 'blocked',
  };
  const statusTone = (status) => {
    if (status === 'completed' || status === 'clear') {
      return 'success';
    }
    if (status === 'skipped') {
      return 'neutral';
    }
    return 'warning';
  };

  return (
    <div className={`agd-node agd-negotiation-node ${data.selected ? 'is-selected' : ''} ${data.totalConflictCount > 0 ? 'has-conflicts' : ''}`}>
      <Handle type="target" position={Position.Left} />
      <div className="agd-node-kicker">negotiation</div>
      <h3>{data.title}</h3>
      <div className="agd-negotiation-rounds">
        {rounds.map((round) => (
          <div className={`agd-negotiation-round is-${round.status}`} key={round.label}>
            <span>
              <strong>{round.label}</strong>
              {round.name}
            </span>
            <Badge tone={statusTone(round.status)}>{round.status}</Badge>
          </div>
        ))}
        <div className={`agd-negotiation-round agd-early-exit is-${earlyExit.status}`}>
          <span>
            <strong>{earlyExit.label}</strong>
            {earlyExit.interpretation || earlyExit.condition}
          </span>
          <Badge tone={statusTone(earlyExit.status)}>{earlyExit.status}</Badge>
        </div>
      </div>
      <div className="agd-badge-row">
        <Badge tone="success">{data.completedRoundCount ?? rounds.filter((round) => round.status === 'completed').length} completed</Badge>
        <Badge tone="neutral">{data.maxRounds ?? rounds.length} rounds</Badge>
        <Badge tone="neutral">{data.boundedTensionCount ?? 0} bounded tensions</Badge>
        <Badge tone="neutral">{data.partialConcessionCount ?? 0} partial concessions</Badge>
        <Badge tone={data.criticalConflictCount > 0 ? 'danger' : data.materialConflictCount > 0 ? 'warning' : 'success'}>{data.status}</Badge>
      </div>
      {!data.presentationMode && <p>{data.resultSummary || 'No data available'}</p>}
      <Handle type="source" position={Position.Right} />
    </div>
  );
}

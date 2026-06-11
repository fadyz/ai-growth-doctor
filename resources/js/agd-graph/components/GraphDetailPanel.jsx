import React from 'react';
import Badge from './Badge';
import ConflictMatrix from './ConflictMatrix';
import EvidenceList from './EvidenceList';
import NegotiationTimeline from './NegotiationTimeline';
import { asArray, readPath, stringifyValue } from '../graphMapper';

const agentNameByNode = {
  activation_agent: 'ai_activation_agent',
  retention_agent: 'ai_retention_agent',
  monetization_agent: 'ai_monetization_agent',
  version_agent: 'ai_version_agent',
  ads_agent: 'ai_ads_agent',
  tomorrow_forecast_agent: 'ai_tomorrow_forecast_agent',
};

export default function GraphDetailPanel({ graph, selectedNode }) {
  if (!selectedNode) {
    return <RunSummary graph={graph} />;
  }

  if (selectedNode.type === 'agentNode') {
    return <AgentDetails graph={graph} node={selectedNode} />;
  }

  if (selectedNode.type === 'negotiationNode') {
    return <NegotiationDetails negotiation={graph.details?.structured_negotiation || {}} />;
  }

  if (selectedNode.type === 'decisionNode') {
    return <FinalDecisionDetails decision={graph.details?.final_decision || {}} />;
  }

  if (selectedNode.type === 'outputNode') {
    return <ScenarioDetails simulator={graph.details?.scenario_simulator || {}} />;
  }

  if (selectedNode.id === 'guardrail_context') {
    return <GuardrailDetails node={selectedNode} guardrail={graph.details?.guardrail_context || {}} />;
  }

  if (selectedNode.id === 'app_data_mapping') {
    return <AppDataMappingDetails node={selectedNode} mapping={graph.details?.app_data_mapping || {}} />;
  }

  const detail = readPath(graph.details || {}, selectedNode.data?.detailKey) || {};
  return (
    <aside className="agd-detail-panel">
      <PanelTitle node={selectedNode} />
      <JsonBlock value={detail} />
    </aside>
  );
}

function RunSummary({ graph }) {
  return (
    <aside className="agd-detail-panel">
      <h2>Run Summary</h2>
      <dl className="agd-detail-dl">
        <dt>Run ID</dt>
        <dd>{graph.run_id || 'No data available'}</dd>
        <dt>Workflow mode</dt>
        <dd>{graph.workflow_mode || 'No data available'}</dd>
        <dt>Status</dt>
        <dd>{graph.summary?.status || 'No data available'}</dd>
        <dt>Business verdict</dt>
        <dd>{graph.summary?.business_verdict || 'No data available'}</dd>
        <dt>Unresolved hard conflicts</dt>
        <dd>{graph.summary?.unresolved_hard_conflict_count ?? graph.summary?.total_conflict_count ?? 'No data available'}</dd>
      </dl>
      <Section title="Interaction Log">
        <EvidenceList items={graph.details?.interaction_log} />
      </Section>
    </aside>
  );
}

function AppDataMappingDetails({ node, mapping }) {
  const appProfile = mapping.app_profile || {};
  const validation = mapping.mapping_validation || {};

  return (
    <aside className="agd-detail-panel">
      <PanelTitle node={node} />
      <div className="agd-badge-row">
        <Badge tone="neutral">{appProfile.app_category || 'category unknown'}</Badge>
        <Badge tone={validation.status === 'valid' ? 'success' : validation.status === 'invalid' ? 'danger' : 'warning'}>{validation.status || 'unknown'}</Badge>
        <Badge tone="neutral">{validation.mapped_metric_count ?? 0} mapped</Badge>
      </div>

      <Section title="App Profile">
        <dl className="agd-detail-dl">
          <dt>App</dt>
          <dd>{appProfile.app_name || 'No data available'}</dd>
          <dt>Core action</dt>
          <dd>{appProfile.core_action_name || 'No data available'}</dd>
          <dt>Source label</dt>
          <dd>{appProfile.core_action_success_label || 'No data available'}</dd>
          <dt>Workspace</dt>
          <dd>{appProfile.workspace_name || 'No data available'}</dd>
          <dt>Monetization</dt>
          <dd>{appProfile.monetization_model || 'No data available'}</dd>
          <dt>Data mode</dt>
          <dd>{appProfile.data_mode || 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="Mapping Validation">
        <dl className="agd-detail-dl">
          <dt>Status</dt>
          <dd>{validation.status || 'No data available'}</dd>
          <dt>Mapped metrics</dt>
          <dd>{validation.mapped_metric_count ?? 'No data available'}</dd>
          <dt>Required metrics</dt>
          <dd>{validation.required_metric_count ?? 'No data available'}</dd>
          <dt>Missing required</dt>
          <dd>{asArray(validation.missing_required_metrics).join(', ') || 'None'}</dd>
          <dt>Warnings</dt>
          <dd>{[...asArray(validation.low_sample_warnings), ...asArray(validation.data_quality_warnings)].join(', ') || 'None'}</dd>
        </dl>
      </Section>

      <Section title="Generic Metrics Preview">
        <MetricTree value={mapping.generic_metrics_context} />
      </Section>

      <Section title="Metric Mapping">
        <details>
          <summary>Show mapping table</summary>
          <MappingTable mapping={mapping.metric_mapping} />
        </details>
      </Section>

      <Section title="Source Metric References">
        <details>
          <summary>Show source refs</summary>
          <MappingTable mapping={mapping.source_metric_refs} />
        </details>
      </Section>
    </aside>
  );
}

function GuardrailDetails({ node, guardrail }) {
  const deterministicDecision = guardrail.deterministic_decision || {};
  const triggeredGuardrails = Object.values(guardrail.triggered_guardrails || {});
  const allGuardrails = Object.values(guardrail.guardrails || {});

  return (
    <aside className="agd-detail-panel">
      <PanelTitle node={node} />
      <div className="agd-badge-row">
        <Badge tone="neutral">{guardrail.policy_version || 'policy unknown'}</Badge>
        <Badge tone="warning">{guardrail.winning_guardrail || deterministicDecision.winning_guardrail || 'no winning guardrail'}</Badge>
        <Badge tone={triggeredGuardrails.length > 0 ? 'warning' : 'success'}>
          {triggeredGuardrails.length} triggered
        </Badge>
      </div>

      <Section title="Deterministic Decision">
        <dl className="agd-detail-dl">
          <dt>Business verdict</dt>
          <dd>{deterministicDecision.business_verdict || 'No data available'}</dd>
          <dt>Ads decision</dt>
          <dd>{deterministicDecision.ads_decision || 'No data available'}</dd>
          <dt>Blocked decision</dt>
          <dd>{deterministicDecision.blocked_decision || 'No data available'}</dd>
          <dt>Allowed decision</dt>
          <dd>{deterministicDecision.allowed_decision || 'No data available'}</dd>
          <dt>Confidence</dt>
          <dd>{deterministicDecision.confidence_score ?? 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="Triggered Guardrails">
        {triggeredGuardrails.length > 0 ? (
          <div className="agd-conflicts">
            {triggeredGuardrails.map((item) => (
              <article className="agd-conflict-card" key={item.name}>
                <div className="agd-conflict-head">
                  <strong>{item.name || 'Unnamed guardrail'}</strong>
                  <Badge tone={item.severity === 'high' ? 'danger' : item.severity === 'medium' ? 'warning' : 'neutral'}>
                    {item.severity || 'unknown'}
                  </Badge>
                </div>
                <p>{item.explanation || 'No data available'}</p>
                <dl className="agd-detail-dl">
                  <dt>Priority</dt>
                  <dd>{item.priority ?? 'No data available'}</dd>
                  <dt>Blocked actions</dt>
                  <dd>{asArray(item.blocked_actions).join(', ') || 'No data available'}</dd>
                  <dt>Allowed actions</dt>
                  <dd>{asArray(item.allowed_actions).join(', ') || 'No data available'}</dd>
                  <dt>Reason codes</dt>
                  <dd>{asArray(item.reason_codes).join(', ') || 'No data available'}</dd>
                </dl>
              </article>
            ))}
          </div>
        ) : (
          <p className="agd-empty">No triggered guardrails</p>
        )}
      </Section>

      <Section title="Decision Constraints">
        <dl className="agd-detail-dl">
          <dt>Blocked actions</dt>
          <dd>{asArray(deterministicDecision.blocked_actions).join(', ') || 'No data available'}</dd>
          <dt>Allowed actions</dt>
          <dd>{asArray(deterministicDecision.allowed_actions).join(', ') || 'No data available'}</dd>
          <dt>Reason codes</dt>
          <dd>{asArray(deterministicDecision.reason_codes).join(', ') || 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="All Guardrails">
        {allGuardrails.length > 0 ? (
          <div className="agd-evidence-list">
            {allGuardrails.map((item) => (
              <div className="agd-evidence-item" key={item.name}>
                <strong>{item.name || 'Unnamed guardrail'}</strong>
                <span>
                  {item.triggered ? 'Triggered' : 'Not triggered'}
                  {item.reason_codes?.length ? ` - ${item.reason_codes.join(', ')}` : ''}
                </span>
              </div>
            ))}
          </div>
        ) : (
          <p className="agd-empty">No data available</p>
        )}
      </Section>
    </aside>
  );
}

function AgentDetails({ graph, node }) {
  const agentKey = agentNameByNode[node.id];
  const agent = graph.details?.agents?.[agentKey] || {};
  const result = agent.result || {};
  const summary = findSpecialistSummary(graph.details?.structured_negotiation, node.data?.title);

  return (
    <aside className="agd-detail-panel">
      <PanelTitle node={node} />
      <div className="agd-badge-row">
        <Badge tone="neutral">{node.data?.domain}</Badge>
        <Badge tone={result.status === 'warning' ? 'warning' : 'success'}>{result.status || agent.status || 'empty'}</Badge>
        {agent.model && <Badge tone="neutral">{agent.model}</Badge>}
        {agent.cache?.hit && <Badge tone="neutral">cache hit</Badge>}
      </div>
      <Section title="Summary">
        <p>{summary?.finding || result.diagnosis || result.main_diagnosis || result.executive_summary || result.summary || 'No data available'}</p>
      </Section>
      <Section title="Supporting Evidence">
        <EvidenceList items={summary?.supporting_evidence || result.metric_facts || result.campaign_observations || result.forecast_evidence} />
      </Section>
      <Section title="Recommendation">
        <p>{stringifyValue(summary?.recommendation || result.recommended_experiment || result.recommended_preventive_action || result.impact_on_final_decision || result.recommended_actions)}</p>
      </Section>
      <Section title="Bounded System Position">
        <dl className="agd-detail-dl">
          <dt>Domain-only position</dt>
          <dd>{summary?.domain_only_position || 'No data available'}</dd>
          <dt>Bounded-system position</dt>
          <dd>{summary?.bounded_system_position || 'No data available'}</dd>
          <dt>Constraints considered</dt>
          <dd>{asArray(summary?.constraint_acknowledgement).join(', ') || 'No data available'}</dd>
          <dt>Negotiation need</dt>
          <dd>{summary?.negotiation_need || 'No data available'}</dd>
          <dt>Residual conflict</dt>
          <dd>{summary?.residual_conflict_severity || summary?.residual_conflict || 'No data available'}</dd>
          <dt>Why no further negotiation</dt>
          <dd>{summary?.why_no_further_negotiation_needed || 'No data available'}</dd>
        </dl>
      </Section>
      <Section title="Caveat / Risk">
        <p>{stringifyValue(summary?.caveat_or_risk || result.risks || result.risk_flags || result.guardrails)}</p>
      </Section>
    </aside>
  );
}

function NegotiationDetails({ negotiation }) {
  const rules = negotiation.rules || {};
  const execution = negotiation.execution || {};
  const baseline = negotiation.baseline_comparison || {};

  return (
    <aside className="agd-detail-panel">
      <h2>Adaptive Structured Negotiation</h2>
      <Section title="Rules">
        <dl className="agd-detail-dl">
          <dt>Max rounds</dt>
          <dd>{rules.max_rounds ?? 3}</dd>
          <dt>Rounds completed</dt>
          <dd>{execution.rounds_completed ?? negotiation.rounds_completed ?? negotiation.round ?? 0}</dd>
          <dt>Early exit</dt>
          <dd>{String(execution.early_exit ?? false)}</dd>
          <dt>Early exit reason</dt>
          <dd>{execution.early_exit_reason || 'No data available'}</dd>
          <dt>Early exit interpretation</dt>
          <dd>{execution.early_exit_interpretation || 'No data available'}</dd>
          <dt>Unresolved hard conflicts</dt>
          <dd>{execution.material_or_higher_conflict_count ?? negotiation.summary?.material_or_higher_conflict_count ?? 0}</dd>
          <dt>Resolved material tensions</dt>
          <dd>{execution.resolved_material_tension_count ?? negotiation.summary?.resolved_material_tension_count ?? 0}</dd>
          <dt>Minor bounded cautions</dt>
          <dd>{negotiation.ui_summary?.minor_bounded_caution_count ?? execution.minor_bounded_tension_count ?? negotiation.summary?.minor_bounded_tension_count ?? 0}</dd>
          <dt>Partial concessions</dt>
          <dd>{execution.partial_concession_count ?? negotiation.summary?.partial_concession_count ?? 0}</dd>
          <dt>Safety-bounded revisions</dt>
          <dd>{execution.safety_bounded_revision_count ?? negotiation.summary?.safety_bounded_revision_count ?? 0}</dd>
          <dt>Raw chain-of-thought allowed</dt>
          <dd>{String(rules.raw_chain_of_thought_allowed ?? false)}</dd>
          <dt>Evidence required</dt>
          <dd>{String(rules.evidence_required_for_objection ?? true)}</dd>
          <dt>Final decision owner</dt>
          <dd>{rules.final_decision_owner || 'FinalDecisionAgent'}</dd>
        </dl>
      </Section>
      <Section title="Why did Round 2 not run?">
        <p>
          Agents surfaced material business tensions and minor cautions. The material tensions were resolved in Round 1 because unsafe
          actions were rejected while safe controlled actions were preserved. Round 2 was skipped because all material tensions were resolved
          in Round 1.
        </p>
      </Section>
      <Section title="Round Summaries">
        <div className="agd-evidence-list">
          {asArray(negotiation.round_summaries).map((round) => (
            <div className="agd-evidence-item" key={round.round}>
              <strong>{round.label || `Round ${round.round}`}</strong>
              <span>
                {round.status || 'unknown'} · {round.turn_count ?? 0} turns · {round.unresolved_material_conflict_count_after_round ?? round.material_or_higher_conflict_count_after_round ?? 0} unresolved hard conflicts
                {round.skip_reason ? ` · ${round.skip_reason}` : ''}
              </span>
            </div>
          ))}
        </div>
      </Section>
      <Section title="Negotiation Timeline">
        <NegotiationTimeline items={negotiation.negotiation_timeline} />
      </Section>
      <Section title="Unresolved Hard Conflicts">
        <ConflictMatrix
          conflicts={asArray(negotiation.conflicts).filter((conflict) => conflict.type !== 'bounded_tension' && conflict.conflict_type !== 'bounded_tension')}
          emptyLabel="No unresolved hard conflicts."
        />
      </Section>
      <Section title="Tension & Resolution Matrix">
        <ConflictMatrix
          conflicts={asArray(negotiation.conflicts).filter((conflict) => conflict.type === 'bounded_tension' || conflict.conflict_type === 'bounded_tension')}
          emptyLabel={(negotiation.ui_summary?.resolved_material_tension_count ?? negotiation.summary?.resolved_material_tension_count ?? 0) > 0 ? 'No unresolved hard conflicts.' : 'No bounded cautions'}
        />
      </Section>
      <Section title="Baseline Comparison">
        <dl className="agd-detail-dl">
          <dt>Single Agent missed unresolved conflicts</dt>
          <dd>{baseline.single_agent_baseline?.missed_unresolved_conflicts ?? baseline.single_agent_baseline?.missed_conflicts ?? 'No data available'}</dd>
          <dt>Single Agent missed resolved material tensions</dt>
          <dd>{baseline.single_agent_baseline?.missed_resolved_material_tensions ?? 'No data available'}</dd>
          <dt>Agent Society unresolved conflicts detected</dt>
          <dd>{baseline.agent_society?.unresolved_conflicts_detected ?? baseline.agent_society?.conflicts_detected ?? 'No data available'}</dd>
          <dt>Agent Society resolved material tensions detected</dt>
          <dd>{baseline.agent_society?.resolved_material_tensions_detected ?? 'No data available'}</dd>
          <dt>Unsafe prevented</dt>
          <dd>{stringifyValue(baseline.agent_society?.unsafe_recommendation_prevented)}</dd>
          <dt>Unsafe prevention basis</dt>
          <dd>{asArray(baseline.agent_society?.unsafe_prevention_basis).join(' ') || 'No data available'}</dd>
          <dt>Evidence coverage</dt>
          <dd>{baseline.agent_society?.evidence_coverage_score ?? 'No data available'}</dd>
          <dt>Caveat coverage</dt>
          <dd>{baseline.agent_society?.caveat_coverage_score ?? 'No data available'}</dd>
        </dl>
      </Section>
    </aside>
  );
}

function FinalDecisionDetails({ decision }) {
  return (
    <aside className="agd-detail-panel">
      <h2>Final Decision Agent</h2>
      <dl className="agd-detail-dl">
        <dt>Business verdict</dt>
        <dd>{decision.business_verdict || 'No data available'}</dd>
        <dt>Confidence</dt>
        <dd>{decision.confidence_score ?? 'No data available'}</dd>
        <dt>Top priority</dt>
        <dd>{decision.top_priority || decision.today_operator_summary || 'No data available'}</dd>
        <dt>Rationale</dt>
        <dd>{decision.rationale || 'No data available'}</dd>
      </dl>
      <Section title="Accepted Recommendations"><EvidenceList items={decision.accepted_recommendations} /></Section>
      <Section title="Rejected Recommendations"><EvidenceList items={decision.rejected_recommendations} /></Section>
      <Section title="Resolved Conflicts"><EvidenceList items={decision.resolved_conflicts} /></Section>
      <Section title="Action Plan 24-72h"><JsonBlock value={decision.action_plan_24_72h || decision.operational_action_plan || decision.action_plan} /></Section>
      <Section title="Risk Notes"><EvidenceList items={decision.risk_notes || decision.weak_evidence_or_uncertainty} /></Section>
    </aside>
  );
}

function ScenarioDetails({ simulator }) {
  const baseline = simulator.baseline_without_intervention || {};
  const recommended = simulator.recommended_intervention || {};
  const scenario = simulator.scenario_with_intervention || {};
  const comparison = simulator.baseline_vs_intervention_comparison || {};

  return (
    <aside className="agd-detail-panel">
      <h2>Decision Scenario Simulator</h2>
      <div className="agd-badge-row">
        <Badge tone="neutral">{simulator.simulation_type || 'simulation'}</Badge>
        <Badge tone="neutral">{simulator.simulation_scope || 'scope unknown'}</Badge>
        <Badge tone={scenario.confidence === 'low' || scenario.confidence === 'medium_low' ? 'warning' : 'success'}>
          {scenario.confidence || 'confidence unknown'}
        </Badge>
      </div>

      <Section title="Baseline Without Intervention">
        <p>{baseline.summary || 'No data available'}</p>
        <MetricList items={baseline.risk_flags} />
      </Section>

      <Section title="Recommended Intervention">
        <dl className="agd-detail-dl">
          <dt>Action</dt>
          <dd>{recommended.action || 'No data available'}</dd>
          <dt>Source</dt>
          <dd>{recommended.source || 'No data available'}</dd>
          <dt>Why</dt>
          <dd>{recommended.why_this_action || 'No data available'}</dd>
          <dt>Not simulated</dt>
          <dd>{recommended.what_is_not_being_simulated || 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="Scenario With Intervention">
        <p>{scenario.summary || 'No data available'}</p>
        <MetricList items={scenario.expected_direction} />
        <dl className="agd-detail-dl">
          <dt>Confidence reason</dt>
          <dd>{scenario.confidence_reason || 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="Comparison">
        <dl className="agd-detail-dl">
          <dt>Main difference</dt>
          <dd>{comparison.main_difference || 'No data available'}</dd>
          <dt>Upside</dt>
          <dd>{comparison.upside || 'No data available'}</dd>
          <dt>Downside</dt>
          <dd>{comparison.downside || 'No data available'}</dd>
          <dt>Decision implication</dt>
          <dd>{comparison.decision_implication || 'No data available'}</dd>
        </dl>
      </Section>

      <Section title="Evidence Basis">
        <EvidenceList items={scenario.evidence_basis} />
      </Section>

      <Section title="Risk">
        <EvidenceList items={scenario.risk} />
      </Section>

      <Section title="Success Criteria">
        <EvidenceList items={scenario.success_criteria} />
      </Section>

      <Section title="Human Review">
        <p>{simulator.human_review_note || 'No data available'}</p>
      </Section>
    </aside>
  );
}

function PanelTitle({ node }) {
  return (
    <>
      <h2>{node.data?.title || node.id}</h2>
      <p className="agd-panel-subtitle">{node.data?.summary || 'No data available'}</p>
    </>
  );
}

function Section({ title, children }) {
  return (
    <section className="agd-detail-section">
      <h3>{title}</h3>
      {children}
    </section>
  );
}

function JsonBlock({ value }) {
  if (value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
    return <p className="agd-empty">No data available</p>;
  }

  if (typeof value === 'string') {
    return <p>{value}</p>;
  }

  return <pre>{JSON.stringify(value, null, 2)}</pre>;
}

function MetricList({ items }) {
  const entries = Object.entries(items || {});

  if (!entries.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-evidence-list">
      {entries.map(([key, value]) => (
        <div className="agd-evidence-item" key={key}>
          <strong>{key}</strong>
          <span>{stringifyValue(value)}</span>
        </div>
      ))}
    </div>
  );
}

function MetricTree({ value }) {
  const groups = Object.entries(value || {});

  if (!groups.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-evidence-list">
      {groups.map(([group, metrics]) => (
        <div className="agd-evidence-item" key={group}>
          <strong>{group}</strong>
          <span>{stringifyValue(metrics)}</span>
        </div>
      ))}
    </div>
  );
}

function MappingTable({ mapping }) {
  const rows = Object.entries(mapping || {});

  if (!rows.length) {
    return <p className="agd-empty">No data available</p>;
  }

  return (
    <div className="agd-mapping-table">
      {rows.map(([key, value]) => (
        <React.Fragment key={key}>
          <div>{key}</div>
          <div>{stringifyValue(value)}</div>
        </React.Fragment>
      ))}
    </div>
  );
}

function findSpecialistSummary(negotiation, title) {
  return asArray(negotiation?.specialist_output_summaries).find((summary) => summary.agent_name === title);
}

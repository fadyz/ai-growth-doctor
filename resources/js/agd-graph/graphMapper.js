const fallbackNodes = [
  ['checkpoint_load', 'pipelineNode', 0, 260, 'Checkpoint Load'],
  ['metrics_extraction', 'pipelineNode', 260, 260, 'Metrics Extraction'],
  ['app_data_mapping', 'pipelineNode', 520, 260, 'App Data Mapping'],
  ['guardrail_context', 'pipelineNode', 700, 260, 'Guardrail & Safe Context'],
  ['activation_agent', 'agentNode', 980, 20, 'Activation Agent'],
  ['retention_agent', 'agentNode', 980, 150, 'Retention Agent'],
  ['monetization_agent', 'agentNode', 980, 280, 'Monetization Agent'],
  ['version_agent', 'agentNode', 980, 410, 'Version Agent'],
  ['ads_agent', 'agentNode', 980, 540, 'Ads Agent'],
  ['tomorrow_forecast_agent', 'agentNode', 980, 670, 'Tomorrow Forecast Agent'],
  ['structured_negotiation', 'negotiationNode', 1340, 260, 'Single-Round Structured Negotiation'],
  ['orchestrator_evidence_assembly', 'pipelineNode', 1640, 260, 'Orchestrator Evidence Assembly'],
  ['final_decision_agent', 'decisionNode', 1940, 260, 'Final Decision Agent'],
  ['decision_scenario_simulator', 'outputNode', 2240, 260, 'Decision Scenario Simulator'],
];

export function mapGraphPayload(payload) {
  if (Array.isArray(payload?.nodes) && Array.isArray(payload?.edges)) {
    return payload;
  }

  return {
    ...payload,
    nodes: fallbackNodes.map(([id, type, x, y, title]) => ({
      id,
      type,
      position: { x, y },
      data: { title, status: 'empty', summary: 'No data available' },
    })),
    edges: [],
    details: payload?.details || {},
    summary: payload?.summary || {},
  };
}

export function readPath(source, path) {
  if (!path) {
    return undefined;
  }

  return path.split('.').reduce((value, key) => {
    if (value === undefined || value === null) {
      return undefined;
    }
    return value[key];
  }, source);
}

export function asArray(value) {
  if (!value) {
    return [];
  }
  return Array.isArray(value) ? value : [value];
}

export function stringifyValue(value) {
  if (value === undefined || value === null || value === '') {
    return 'No data available';
  }
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }
  return JSON.stringify(value, null, 2);
}

import React, { useState } from 'react';

export default function GraphToolbar({
  graphUrl,
  onFitView,
  onResetZoom,
  showMiniMap,
  onToggleMiniMap,
  showDetails,
  onToggleDetails,
  showEdgeLabels,
  onToggleEdgeLabels,
  presentationMode,
  onTogglePresentation,
  onExportPng,
  isExporting,
}) {
  const [copied, setCopied] = useState(false);

  const copyLink = () => {
    navigator.clipboard?.writeText(window.location.origin + graphUrl).then(() => {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1400);
    });
  };

  return (
    <div className="agd-toolbar">
      <button type="button" onClick={onFitView}>Fit view</button>
      <button type="button" onClick={onResetZoom}>Reset zoom</button>
      <button type="button" className={showMiniMap ? 'is-active' : ''} onClick={onToggleMiniMap}>Minimap</button>
      <button type="button" className={showDetails ? 'is-active' : ''} onClick={onToggleDetails}>Details</button>
      <button type="button" className={showEdgeLabels ? 'is-active' : ''} onClick={onToggleEdgeLabels}>Edge labels</button>
      <button type="button" className={presentationMode ? 'is-active' : ''} onClick={onTogglePresentation}>Presentation</button>
      <button type="button" onClick={onExportPng} disabled={isExporting}>
        {isExporting ? 'Exporting...' : 'Export PNG'}
      </button>
      <button type="button" onClick={copyLink}>{copied ? 'Copied' : 'Copy JSON link'}</button>
    </div>
  );
}

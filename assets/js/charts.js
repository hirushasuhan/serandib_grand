/**
 * Lightweight hand-written SVG Chart drawer for analytics.
 */
function renderBarChart(containerId, labels, dataValues, title) {
  const container = document.getElementById(containerId);
  if (!container || !dataValues.length) return;

  const maxVal = Math.max(...dataValues, 1);
  const chartHeight = 220;
  const barWidth = Math.max(20, Math.floor(500 / labels.length) - 10);

  let svgHtml = `<svg width="100%" height="${chartHeight + 40}" viewBox="0 0 ${labels.length * (barWidth + 12)} ${chartHeight + 40}" style="overflow:visible;">`;

  dataValues.forEach((val, idx) => {
    const height = Math.round((val / maxVal) * chartHeight);
    const x = idx * (barWidth + 12) + 10;
    const y = chartHeight - height + 20;

    svgHtml += `
      <rect x="${x}" y="${y}" width="${barWidth}" height="${height}" rx="4" fill="var(--color-primary)" opacity="0.85">
        <title>${labels[idx]}: ${val}</title>
      </rect>
      <text x="${x + barWidth / 2}" y="${y - 6}" text-anchor="middle" font-size="11" fill="var(--color-text)" font-weight="bold">${val}</text>
      <text x="${x + barWidth / 2}" y="${chartHeight + 35}" text-anchor="middle" font-size="11" fill="var(--color-text-muted)">${labels[idx]}</text>
    `;
  });

  svgHtml += `</svg>`;
  container.innerHTML = svgHtml;
}

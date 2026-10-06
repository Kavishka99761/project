import { motion } from 'framer-motion';
import { useState } from 'react';

const width = 1000;
const height = 640;
const cx = width / 2;
const cy = height / 2;

const boxWidth = (label, size) => Math.min(240, Math.max(70, label.length * size * 0.56 + 28));

function Node({ x, y, label, className, size = 13, onClick, delay }) {
  const w = boxWidth(label, size);
  const h = size + 18;
  return (
    <motion.g className={className} initial={{ opacity: 0, scale: 0.6 }} animate={{ opacity: 1, scale: 1 }} transition={{ delay, type: 'spring', stiffness: 260, damping: 22 }} style={{ cursor: onClick ? 'pointer' : 'default', transformOrigin: `${x}px ${y}px` }} onClick={onClick}>
      <rect x={x - w / 2} y={y - h / 2} width={w} height={h} rx={h / 2} />
      <text x={x} y={y + size * 0.36} textAnchor="middle" fontSize={size}>{label.length > 34 ? `${label.slice(0, 32)}…` : label}</text>
    </motion.g>
  );
}

/** Radial concept map: document → key concepts → related terms. */
export function MindMap({ tree }) {
  const [selected, setSelected] = useState(null);
  const children = tree.children ?? [];
  const r1 = 205;
  const r2 = 95;

  const positioned = children.map((child, i) => {
    const angle = (i / Math.max(children.length, 1)) * Math.PI * 2 - Math.PI / 2;
    const x = cx + Math.cos(angle) * r1 * 1.35;
    const y = cy + Math.sin(angle) * r1;
    const leaves = (child.children ?? []).map((leaf, j, all) => {
      const spread = 0.62;
      const leafAngle = angle + (j - (all.length - 1) / 2) * spread;
      return { ...leaf, x: x + Math.cos(leafAngle) * r2 * 1.5, y: y + Math.sin(leafAngle) * r2 };
    });
    return { ...child, x, y, leaves };
  });

  return (
    <div className="mindmap">
      <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`Mind map of ${tree.label}`}>
        {positioned.map((child) => (
          <g key={`links-${child.id}`}>
            <path className="link" d={`M${cx},${cy} Q${(cx + child.x) / 2},${cy} ${child.x},${child.y}`} />
            {child.leaves.map((leaf) => <path key={leaf.id} className="link" d={`M${child.x},${child.y} L${leaf.x},${leaf.y}`} />)}
          </g>
        ))}
        {positioned.map((child, i) => (
          <g key={child.id}>
            <Node x={child.x} y={child.y} label={child.label} className="node" size={14} delay={0.1 + i * 0.06} onClick={() => setSelected(child)} />
            {child.leaves.map((leaf, j) => <Node key={leaf.id} x={leaf.x} y={leaf.y} label={leaf.label} className="node leaf" size={11.5} delay={0.4 + i * 0.06 + j * 0.03} />)}
          </g>
        ))}
        <Node x={cx} y={cy} label={tree.label} className="node-root" size={16} delay={0} />
      </svg>
      <div className="small text-3 text-center">Click a concept to see its explanation.</div>
      {selected && (
        <div className="glass p-3 mt-2">
          <div className="d-flex justify-content-between">
            <strong>{selected.label}</strong>
            <button type="button" className="btn-close btn-sm" aria-label="Close" onClick={() => setSelected(null)} />
          </div>
          <div className="small text-2 mt-1">{selected.detail}</div>
        </div>
      )}
    </div>
  );
}

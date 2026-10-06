/**
 * Shared presentational components for the EDU-SMART mobile app.
 * Kept intentionally small and dependency-free so every module screen reuses the
 * same visual language (matching frontend-web/assets/css/style.css).
 */
import React from 'react';
import { View, Text, StyleSheet, Pressable } from 'react-native';
import { colors, radius, shadow, spacing, moduleAccent, riskColor } from '../theme';

/* ------------------------------------------------------------------- Card */

export function Card({ children, style, accent, onPress }) {
  const content = (
    <View style={[styles.card, accent ? { borderLeftColor: accent, borderLeftWidth: 4 } : null, style]}>
      {children}
    </View>
  );
  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => (pressed ? { opacity: 0.85 } : null)}>
        {content}
      </Pressable>
    );
  }
  return content;
}

/* ---------------------------------------------------------- SectionTitle */

export function SectionTitle({ children, action, onAction, accent = colors.primary }) {
  return (
    <View style={styles.sectionRow}>
      <View style={styles.sectionLeft}>
        <View style={[styles.sectionBar, { backgroundColor: accent }]} />
        <Text style={styles.sectionTitle}>{children}</Text>
      </View>
      {action ? (
        <Pressable onPress={onAction} hitSlop={8}>
          <Text style={[styles.sectionAction, { color: accent }]}>{action}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

/* --------------------------------------------------------------- RiskPill */

export function RiskPill({ level, score, size = 'md' }) {
  const color = riskColor[level] || colors.textMuted;
  const pad = size === 'sm' ? styles.pillSm : styles.pill;
  return (
    <View style={[styles.pillBase, pad, { backgroundColor: `${color}1a`, borderColor: `${color}55` }]}>
      <View style={[styles.dot, { backgroundColor: color }]} />
      <Text style={[styles.pillText, size === 'sm' ? styles.pillTextSm : null, { color }]}>
        {level}
        {typeof score === 'number' ? ` · ${score}` : ''}
      </Text>
    </View>
  );
}

/* ------------------------------------------------------------------- Stat */

export function Stat({ label, value, accent = colors.primary, sub }) {
  return (
    <View style={styles.stat}>
      <Text style={[styles.statValue, { color: accent }]}>{value}</Text>
      <Text style={styles.statLabel} numberOfLines={1}>
        {label}
      </Text>
      {sub ? <Text style={styles.statSub}>{sub}</Text> : null}
    </View>
  );
}

/* --------------------------------------------------------------- Progress */

export function Progress({ value = 0, accent = colors.primary, height = 8 }) {
  const pct = Math.max(0, Math.min(100, value));
  return (
    <View style={[styles.progressTrack, { height, borderRadius: height / 2 }]}>
      <View style={[styles.progressFill, { width: `${pct}%`, backgroundColor: accent, borderRadius: height / 2 }]} />
    </View>
  );
}

/* ------------------------------------------------------------------- Chip */

export function Chip({ label, accent = colors.primary, onPress, active }) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, { borderColor: `${accent}66`, backgroundColor: active ? accent : `${accent}12` }]}
    >
      <Text style={[styles.chipText, { color: active ? '#fff' : accent }]} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

/* ------------------------------------------------------------------ Badge */

export function Badge({ label, accent = 'primary' }) {
  const color = moduleAccent[accent] || colors.primary;
  return (
    <View style={[styles.badge, { backgroundColor: `${color}18` }]}>
      <Text style={[styles.badgeText, { color }]} numberOfLines={1}>
        {label}
      </Text>
    </View>
  );
}

/* ------------------------------------------------------------------ Empty */

export function Empty({ title = 'Nothing here yet', hint }) {
  return (
    <View style={styles.empty}>
      <Text style={styles.emptyTitle}>{title}</Text>
      {hint ? <Text style={styles.emptyHint}>{hint}</Text> : null}
    </View>
  );
}

/* ----------------------------------------------------------------- Avatar */

export function Avatar({ initials = '?', accent = colors.primary, size = 40 }) {
  return (
    <View style={[styles.avatar, { width: size, height: size, borderRadius: size / 2, backgroundColor: accent }]}>
      <Text style={[styles.avatarText, { fontSize: size * 0.4 }]}>{initials}</Text>
    </View>
  );
}

/* ----------------------------------------------------------------- styles */

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    marginBottom: spacing.md,
    borderLeftColor: 'transparent',
    ...shadow,
  },

  sectionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
    marginTop: spacing.xs,
  },
  sectionLeft: { flexDirection: 'row', alignItems: 'center' },
  sectionBar: { width: 4, height: 18, borderRadius: 2, marginRight: spacing.sm },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text },
  sectionAction: { fontSize: 13, fontWeight: '600' },

  pillBase: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: radius.pill,
  },
  pill: { paddingHorizontal: 10, paddingVertical: 5 },
  pillSm: { paddingHorizontal: 8, paddingVertical: 3 },
  dot: { width: 7, height: 7, borderRadius: 4, marginRight: 6 },
  pillText: { fontSize: 12, fontWeight: '700' },
  pillTextSm: { fontSize: 11 },

  stat: { flex: 1, alignItems: 'center', paddingVertical: spacing.xs },
  statValue: { fontSize: 22, fontWeight: '800' },
  statLabel: { fontSize: 11, color: colors.textMuted, marginTop: 2, textAlign: 'center' },
  statSub: { fontSize: 10, color: colors.textMuted, marginTop: 1 },

  progressTrack: { backgroundColor: colors.surfaceAlt, width: '100%', overflow: 'hidden' },
  progressFill: { height: '100%' },

  chip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: radius.pill,
    borderWidth: 1,
    marginRight: spacing.sm,
    marginBottom: spacing.sm,
  },
  chipText: { fontSize: 12, fontWeight: '600' },

  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm, alignSelf: 'flex-start' },
  badgeText: { fontSize: 10.5, fontWeight: '700', letterSpacing: 0.2 },

  empty: { alignItems: 'center', paddingVertical: spacing.xl },
  emptyTitle: { fontSize: 14, fontWeight: '600', color: colors.text },
  emptyHint: { fontSize: 12, color: colors.textMuted, marginTop: 4, textAlign: 'center' },

  avatar: { alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: '#fff', fontWeight: '800' },
});

export default { Card, SectionTitle, RiskPill, Stat, Progress, Chip, Badge, Empty, Avatar };

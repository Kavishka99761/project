/**
 * AssignmentsScreen — JITHMI · Assignment & Deadline Risk Management.
 *
 * Ranks every active assignment by deadline-miss risk (0-100 → Low/Medium/High/
 * Critical), explains WHY each score was given, surfaces the single most urgent
 * task with a suggested daily plan, and provides a what-if simulator.
 *
 * Risk is computed with src/api/risk.js — a 1:1 port of the Laravel
 * RiskCalculator — so scores are identical offline and online. The
 * "Start study session" action is the hand-off to PASINDU's module.
 */
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, RefreshControl, Pressable, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Screen from '../components/Screen';
import { Card, SectionTitle, RiskPill, Progress, Stat, Chip, Empty } from '../components/ui';
import { api } from '../api/client';
import { calcRisk, daysUntil, isoDate } from '../api/risk';
import { useAuth } from '../context/AuthContext';
import { colors, spacing, radius } from '../theme';
import { demoAssignments } from '../data/demo';

const FILTERS = ['Active', 'Overdue', 'Completed', 'All'];
const LEVELS = ['Critical', 'High', 'Medium', 'Low'];

/**
 * Normalise an assignment row to the flat shape this screen renders.
 *
 * GET /api/assignments wraps every row as { assignment, risk, status } (see
 * AssignmentController@index), while the bundled demo rows are already flat.
 * Unwrapping `row.assignment ?? row` makes the live API and the offline demo
 * render identically — without it each live row read a.id / a.title / a.deadline
 * as undefined and the whole list came up blank online. Risk is still recomputed
 * locally with calcRisk() (the 1:1 port of RiskCalculator) so scores stay
 * identical in both modes and the what-if simulator uses the same engine.
 */
function normalise(row) {
  const a = row.assignment ?? row;
  return {
    id: a.id,
    title: a.title,
    module: a.module?.name || a.module_name || 'Unfiled',
    deadline: String(a.deadline || '').slice(0, 10),
    priority: a.priority || 'Medium',
    estHours: Number(a.est_hours ?? a.estHours ?? 0),
    doneHours: Number(a.done_hours ?? a.doneHours ?? 0),
    progress: Number(a.progress ?? 0),
    completed: Boolean(a.completed),
  };
}

const statusOf = (a) => {
  if (a.completed) return 'completed';
  return daysUntil(a.deadline) < 0 ? 'overdue' : 'upcoming';
};

export default function AssignmentsScreen({ navigation }) {
  const { online } = useAuth();
  const [items, setItems] = useState([]);
  const [recommendation, setRecommendation] = useState(null);
  const [filter, setFilter] = useState('Active');
  const [expanded, setExpanded] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showSim, setShowSim] = useState(false);

  const load = useCallback(async () => {
    if (!online) {
      setItems(demoAssignments);
      setRecommendation(buildLocalRecommendation(demoAssignments));
      return;
    }
    try {
      const [raw, rec] = await Promise.all([api.assignments(), api.assignmentRecommendation()]);
      const list = Array.isArray(raw) ? raw.map(normalise) : demoAssignments;
      setItems(list.length ? list : demoAssignments);
      setRecommendation(
        rec?.assignment
          ? {
              title: rec.assignment.title,
              level: rec.risk?.level,
              score: rec.risk?.score,
              days: rec.days_left,
              perDay: rec.suggested_per_day,
              minutes: rec.suggested_minutes,
              message: rec.message,
              id: rec.assignment.id,
              moduleName: rec.assignment.module?.name || rec.assignment.module_name,
            }
          : buildLocalRecommendation(list.length ? list : demoAssignments),
      );
    } catch (e) {
      setItems(demoAssignments);
      setRecommendation(buildLocalRecommendation(demoAssignments));
    }
  }, [online]);

  useEffect(() => { load(); }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  /** Enrich with risk + status, then sort highest risk first (RiskCalculator@rank). */
  const ranked = useMemo(() => {
    return items
      .map((a) => ({ ...a, risk: calcRisk(a), status: statusOf(a) }))
      .sort((x, y) => y.risk.score - x.risk.score || daysUntil(x.deadline) - daysUntil(y.deadline));
  }, [items]);

  const counts = useMemo(() => {
    const active = ranked.filter((a) => !a.completed);
    const c = { Low: 0, Medium: 0, High: 0, Critical: 0 };
    active.forEach((a) => { c[a.risk.level] += 1; });
    return { levels: c, active: active.length, overdue: ranked.filter((a) => a.status === 'overdue').length };
  }, [ranked]);

  const visible = useMemo(() => {
    if (filter === 'All') return ranked;
    if (filter === 'Active') return ranked.filter((a) => !a.completed);
    if (filter === 'Overdue') return ranked.filter((a) => a.status === 'overdue');
    return ranked.filter((a) => a.completed);
  }, [ranked, filter]);

  return (
    <Screen
      title="Assignment Risk"
      subtitle="Jithmi · deadlines, ranking & what-if"
      accent={colors.jithmi}
      icon="clipboard"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.jithmi} />}
    >
      {/* Risk overview */}
      <Card>
        <View style={styles.statRow}>
          <Stat label="Active" value={counts.active} accent={colors.jithmi} />
          <Stat label="Overdue" value={counts.overdue} accent={colors.critical} />
          <Stat label="Critical" value={counts.levels.Critical} accent={colors.critical} />
          <Stat label="High" value={counts.levels.High} accent={colors.high} />
        </View>
        <View style={styles.levelBar}>
          {LEVELS.map((lvl) => (
            <View
              key={lvl}
              style={[
                styles.levelSeg,
                { backgroundColor: colorFor(lvl), flex: Math.max(counts.levels[lvl], 0.15) },
              ]}
            />
          ))}
        </View>
        <View style={styles.levelLegend}>
          {LEVELS.map((lvl) => (
            <View key={lvl} style={styles.legendItem}>
              <View style={[styles.legendDot, { backgroundColor: colorFor(lvl) }]} />
              <Text style={styles.legendText}>{lvl} {counts.levels[lvl]}</Text>
            </View>
          ))}
        </View>
      </Card>

      {/* Top priority recommendation */}
      {recommendation ? (
        <>
          <SectionTitle accent={colors.jithmi}>Focus on this first</SectionTitle>
          <Card accent={colorFor(recommendation.level)} style={styles.recCard}>
            <View style={styles.recHead}>
              <Ionicons name="flame" size={18} color={colorFor(recommendation.level)} />
              <Text style={styles.recTitle} numberOfLines={1}>{recommendation.title}</Text>
              <RiskPill level={recommendation.level} score={recommendation.score} size="sm" />
            </View>
            <Text style={styles.recMessage}>{recommendation.message}</Text>
            <View style={styles.recStats}>
              <View style={styles.recStat}>
                <Text style={styles.recStatValue}>{recommendation.perDay}h</Text>
                <Text style={styles.recStatLabel}>per day</Text>
              </View>
              <View style={styles.recStat}>
                <Text style={styles.recStatValue}>{recommendation.days}</Text>
                <Text style={styles.recStatLabel}>days left</Text>
              </View>
              <View style={styles.recStat}>
                <Text style={styles.recStatValue}>{recommendation.minutes}m</Text>
                <Text style={styles.recStatLabel}>daily plan</Text>
              </View>
            </View>
            {/* Integration point (§4.3 Jithmi -> Pasindu): hand the urgent task,
                its id and module to PASINDU's timer, so the session it starts is
                linked back to this assignment (§4.4 progress write-back). */}
            <Pressable
              style={styles.recBtn}
              onPress={() =>
                navigation.navigate('Study', {
                  assignmentId: recommendation.id,
                  assignmentTitle: recommendation.title,
                  moduleName: recommendation.moduleName,
                })
              }
            >
              <Ionicons name="play-circle-outline" size={17} color="#fff" />
              <Text style={styles.recBtnText}>Start study session</Text>
            </Pressable>
          </Card>
        </>
      ) : null}

      {/* What-if simulator */}
      <SectionTitle
        accent={colors.jithmi}
        action={showSim ? 'Hide' : 'Simulate'}
        onAction={() => setShowSim((v) => !v)}
      >
        What-if simulator
      </SectionTitle>
      {showSim ? <WhatIfSimulator assignments={items} /> : null}

      {/* Filters */}
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
        {FILTERS.map((f) => (
          <Chip key={f} label={f} accent={colors.jithmi} active={filter === f} onPress={() => setFilter(f)} />
        ))}
      </ScrollView>

      {/* Ranked list */}
      <SectionTitle accent={colors.jithmi}>
        {visible.length} assignment{visible.length === 1 ? '' : 's'} · ranked by risk
      </SectionTitle>

      {visible.length === 0 ? (
        <Empty title="Nothing here" hint="You're all caught up in this view." />
      ) : (
        visible.map((a, i) => {
          const open = expanded === a.id;
          return (
            <Card key={a.id} accent={colorFor(a.risk.level)} onPress={() => setExpanded(open ? null : a.id)}>
              <View style={styles.row}>
                <View style={[styles.rankBadge, { backgroundColor: `${colorFor(a.risk.level)}18` }]}>
                  <Text style={[styles.rankText, { color: colorFor(a.risk.level) }]}>{i + 1}</Text>
                </View>
                <View style={styles.rowMain}>
                  <Text style={styles.rowTitle} numberOfLines={1}>{a.title}</Text>
                  <Text style={styles.rowMeta} numberOfLines={1}>
                    {a.module} · due {a.deadline} · {deadlineLabel(a.risk.days, a.status)}
                  </Text>
                </View>
                <RiskPill level={a.risk.level} score={a.risk.score} size="sm" />
              </View>

              <View style={styles.progressRow}>
                <Progress value={a.progress} accent={colorFor(a.risk.level)} height={7} />
                <Text style={styles.progressText}>{a.progress}%</Text>
              </View>

              {open ? (
                <View style={styles.detail}>
                  <View style={styles.detailGrid}>
                    <DetailCell label="Estimated" value={`${a.estHours}h`} />
                    <DetailCell label="Completed" value={`${a.doneHours}h`} />
                    <DetailCell label="Remaining" value={`${Math.max(a.estHours - a.doneHours, 0).toFixed(1)}h`} />
                    <DetailCell label="Needed" value={`${a.risk.hoursPerDay}h/day`} />
                  </View>

                  <Text style={styles.reasonsTitle}>Why this score</Text>
                  {a.risk.reasons.map((r) => (
                    <View key={r} style={styles.reasonRow}>
                      <Ionicons name="alert-circle" size={12} color={colorFor(a.risk.level)} />
                      <Text style={styles.reasonText}>{r}</Text>
                    </View>
                  ))}

                  <View style={styles.priorityRow}>
                    <Text style={styles.priorityLabel}>Priority:</Text>
                    <View style={[styles.priorityPill, { backgroundColor: `${priorityColor(a.priority)}18` }]}>
                      <Text style={[styles.priorityText, { color: priorityColor(a.priority) }]}>{a.priority}</Text>
                    </View>
                    {a.status === 'overdue' ? (
                      <View style={styles.overduePill}>
                        <Text style={styles.overdueText}>OVERDUE</Text>
                      </View>
                    ) : null}
                    {a.completed ? (
                      <View style={styles.donePill}>
                        <Text style={styles.doneText}>COMPLETED</Text>
                      </View>
                    ) : null}
                  </View>
                </View>
              ) : null}
            </Card>
          );
        })
      )}
    </Screen>
  );
}

/* ------------------------------------------------------------ what-if sim */

function WhatIfSimulator({ assignments }) {
  const active = assignments.filter((a) => !a.completed);
  const [idx, setIdx] = useState(0);
  const base = active[idx] || demoAssignments[0];

  const [extraDays, setExtraDays] = useState(0);
  const [extraHours, setExtraHours] = useState(0);
  const [progressBump, setProgressBump] = useState(0);

  const current = base ? calcRisk(base) : null;

  const simulated = useMemo(() => {
    if (!base) return null;
    return calcRisk({
      ...base,
      // isoDate(), not toISOString(): the simulation must reproduce `current`
      // exactly when every delta is zero, and a UTC date string lands one day
      // early for anyone east of UTC.
      deadline: isoDate(extraDays, new Date(`${base.deadline}T00:00:00`)),
      estHours: Math.max(0, base.estHours + extraHours),
      progress: Math.min(100, Math.max(0, base.progress + progressBump)),
    });
  }, [base, extraDays, extraHours, progressBump]);

  if (!base || !current || !simulated) {
    return <Empty title="No active assignment to simulate" />;
  }

  const delta = simulated.score - current.score;

  return (
    <Card>
      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        {active.map((a, i) => (
          <Chip key={a.id} label={a.title} accent={colors.jithmi} active={i === idx} onPress={() => { setIdx(i); reset(); }} />
        ))}
      </ScrollView>

      <Stepper label="Extend deadline" unit="days" value={extraDays} onChange={setExtraDays} step={1} min={-7} max={21} />
      <Stepper label="Add estimate" unit="hours" value={extraHours} onChange={setExtraHours} step={1} min={-10} max={30} />
      <Stepper label="Increase progress" unit="%" value={progressBump} onChange={setProgressBump} step={5} min={0} max={100 - base.progress} />

      <View style={styles.simResult}>
        <View style={styles.simCol}>
          <Text style={styles.simColLabel}>Current</Text>
          <RiskPill level={current.level} score={current.score} />
        </View>
        <Ionicons name="arrow-forward" size={18} color={colors.textMuted} />
        <View style={styles.simCol}>
          <Text style={styles.simColLabel}>Simulated</Text>
          <RiskPill level={simulated.level} score={simulated.score} />
        </View>
      </View>

      <View style={[styles.deltaBox, { backgroundColor: `${deltaColor(delta)}12` }]}>
        <Ionicons
          name={delta < 0 ? 'trending-down' : delta > 0 ? 'trending-up' : 'remove'}
          size={15}
          color={deltaColor(delta)}
        />
        <Text style={[styles.deltaText, { color: deltaColor(delta) }]}>
          {delta === 0
            ? 'No change in risk score.'
            : `Risk ${delta < 0 ? 'drops' : 'rises'} by ${Math.abs(delta)} point${Math.abs(delta) === 1 ? '' : 's'}.`}
        </Text>
      </View>
      <Text style={styles.simNote}>Scenario only — nothing is saved.</Text>
    </Card>
  );

  function reset() {
    setExtraDays(0);
    setExtraHours(0);
    setProgressBump(0);
  }
}

function Stepper({ label, unit, value, onChange, step = 1, min = -99, max = 99 }) {
  return (
    <View style={styles.stepper}>
      <Text style={styles.stepperLabel}>{label}</Text>
      <View style={styles.stepperControls}>
        <Pressable
          style={[styles.stepBtn, value <= min && styles.stepBtnDisabled]}
          onPress={() => onChange(Math.max(min, value - step))}
          disabled={value <= min}
        >
          <Ionicons name="remove" size={16} color={value <= min ? colors.border : colors.jithmi} />
        </Pressable>
        <Text style={styles.stepValue}>
          {value > 0 ? '+' : ''}{value} {unit}
        </Text>
        <Pressable
          style={[styles.stepBtn, value >= max && styles.stepBtnDisabled]}
          onPress={() => onChange(Math.min(max, value + step))}
          disabled={value >= max}
        >
          <Ionicons name="add" size={16} color={value >= max ? colors.border : colors.jithmi} />
        </Pressable>
      </View>
    </View>
  );
}

function DetailCell({ label, value }) {
  return (
    <View style={styles.detailCell}>
      <Text style={styles.detailValue}>{value}</Text>
      <Text style={styles.detailLabel}>{label}</Text>
    </View>
  );
}

/* ---------------------------------------------------------------- helpers */

function buildLocalRecommendation(list) {
  const active = (list || []).filter((a) => !a.completed);
  if (!active.length) return null;

  const ranked = active
    .map((a) => ({ ...a, risk: calcRisk(a) }))
    .sort((x, y) => y.risk.score - x.risk.score);

  const top = ranked[0];
  const remaining = Math.max(top.estHours - top.doneHours, 0);
  const days = Math.max(top.risk.days, 1);
  const perDay = +Math.min(remaining / days, 6).toFixed(1);

  return {
    id: top.id,
    title: top.title,
    moduleName: top.module,
    level: top.risk.level,
    score: top.risk.score,
    days: top.risk.days,
    perDay,
    minutes: Math.round(perDay * 60),
    message: `Focus on "${top.title}" — ${top.risk.level.toLowerCase()} risk. Aim for ~${perDay}h/day over the next ${days} day(s).`,
  };
}

const colorFor = (level) =>
  ({ Low: colors.low, Medium: colors.medium, High: colors.high, Critical: colors.critical }[level] || colors.textMuted);

const priorityColor = (p) => ({ Low: colors.low, Medium: colors.medium, High: colors.high }[p] || colors.textMuted);

const deltaColor = (d) => (d < 0 ? colors.success : d > 0 ? colors.critical : colors.textMuted);

const deadlineLabel = (days, status) => {
  if (status === 'completed') return 'completed';
  if (days < 0) return `${Math.abs(days)} day(s) overdue`;
  if (days === 0) return 'due today';
  return `${days} day${days === 1 ? '' : 's'} left`;
};

/* ----------------------------------------------------------------- styles */

const styles = StyleSheet.create({
  statRow: { flexDirection: 'row', marginBottom: spacing.md },

  levelBar: { flexDirection: 'row', height: 8, borderRadius: 4, overflow: 'hidden' },
  levelSeg: { height: '100%' },
  levelLegend: { flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm },
  legendItem: { flexDirection: 'row', alignItems: 'center', marginRight: spacing.md, marginBottom: 4 },
  legendDot: { width: 7, height: 7, borderRadius: 4, marginRight: 5 },
  legendText: { fontSize: 10.5, color: colors.textMuted, fontWeight: '600' },

  recCard: { backgroundColor: colors.surface },
  recHead: { flexDirection: 'row', alignItems: 'center', marginBottom: spacing.sm },
  recTitle: { flex: 1, fontSize: 15, fontWeight: '800', color: colors.text, marginHorizontal: spacing.sm },
  recMessage: { fontSize: 12.5, color: colors.textMuted, lineHeight: 18 },
  recStats: { flexDirection: 'row', marginTop: spacing.md },
  recStat: { flex: 1, alignItems: 'center' },
  recStatValue: { fontSize: 18, fontWeight: '800', color: colors.text },
  recStatLabel: { fontSize: 10, color: colors.textMuted, marginTop: 1 },
  recBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    height: 44,
    borderRadius: radius.md,
    backgroundColor: colors.pasindu,
    marginTop: spacing.md,
  },
  recBtnText: { color: '#fff', fontSize: 13.5, fontWeight: '700', marginLeft: 6 },

  filterScroll: { flexGrow: 0, marginBottom: spacing.sm },

  row: { flexDirection: 'row', alignItems: 'center' },
  rankBadge: {
    width: 30,
    height: 30,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  rankText: { fontSize: 14, fontWeight: '900' },
  rowMain: { flex: 1, marginRight: spacing.sm },
  rowTitle: { fontSize: 14, fontWeight: '700', color: colors.text },
  rowMeta: { fontSize: 11, color: colors.textMuted, marginTop: 2 },

  progressRow: { flexDirection: 'row', alignItems: 'center', marginTop: spacing.md },
  progressText: { fontSize: 11, fontWeight: '700', color: colors.textMuted, marginLeft: spacing.sm, width: 34, textAlign: 'right' },

  detail: { marginTop: spacing.md, paddingTop: spacing.md, borderTopWidth: 1, borderTopColor: colors.border },
  detailGrid: { flexDirection: 'row' },
  detailCell: { flex: 1, alignItems: 'center' },
  detailValue: { fontSize: 14, fontWeight: '800', color: colors.text },
  detailLabel: { fontSize: 9.5, color: colors.textMuted, marginTop: 1 },

  reasonsTitle: {
    fontSize: 10.5,
    fontWeight: '800',
    color: colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginTop: spacing.md,
    marginBottom: 6,
  },
  reasonRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 4 },
  reasonText: { fontSize: 11.5, color: colors.text, marginLeft: 6, flex: 1 },

  priorityRow: { flexDirection: 'row', alignItems: 'center', marginTop: spacing.md },
  priorityLabel: { fontSize: 11.5, color: colors.textMuted, marginRight: spacing.sm },
  priorityPill: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: radius.pill },
  priorityText: { fontSize: 10.5, fontWeight: '700' },
  overduePill: { backgroundColor: `${colors.critical}18`, paddingHorizontal: 9, paddingVertical: 3, borderRadius: radius.pill, marginLeft: spacing.sm },
  overdueText: { fontSize: 9.5, fontWeight: '800', color: colors.critical, letterSpacing: 0.5 },
  donePill: { backgroundColor: `${colors.success}18`, paddingHorizontal: 9, paddingVertical: 3, borderRadius: radius.pill, marginLeft: spacing.sm },
  doneText: { fontSize: 9.5, fontWeight: '800', color: colors.success, letterSpacing: 0.5 },

  stepper: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  stepperLabel: { fontSize: 12.5, color: colors.text, flex: 1 },
  stepperControls: { flexDirection: 'row', alignItems: 'center' },
  stepBtn: {
    width: 30,
    height: 30,
    borderRadius: radius.sm,
    backgroundColor: `${colors.jithmi}12`,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepBtnDisabled: { backgroundColor: colors.surfaceAlt },
  stepValue: { fontSize: 12, fontWeight: '700', color: colors.text, minWidth: 74, textAlign: 'center' },

  simResult: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    marginTop: spacing.lg,
  },
  simCol: { alignItems: 'center' },
  simColLabel: { fontSize: 10.5, color: colors.textMuted, marginBottom: 6, fontWeight: '600' },

  deltaBox: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.sm,
    padding: spacing.sm + 2,
    marginTop: spacing.md,
  },
  deltaText: { fontSize: 12, fontWeight: '700', marginLeft: 6, flex: 1 },
  simNote: { fontSize: 10.5, color: colors.textMuted, textAlign: 'center', marginTop: spacing.sm, fontStyle: 'italic' },
});

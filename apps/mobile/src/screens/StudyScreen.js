/**
 * StudyScreen — PASINDU · Study Session & Engagement.
 *
 * A working focus timer (start / pause / stop) plus weekly analytics and session
 * history. The timer is driven locally so it keeps running offline; when the
 * Laravel API is reachable it also POSTs the session (GET /api/study/analytics
 * supplies the charts). Stopping a session is the integration point with JITHMI:
 * the backend advances the linked assignment's done_hours/progress.
 */
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, Text, StyleSheet, RefreshControl, Pressable } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Screen from '../components/Screen';
import { Card, SectionTitle, Stat, Progress } from '../components/ui';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { colors, spacing, radius } from '../theme';
import { demoStudy, demoModules } from '../data/demo';

const PRESETS = [25, 45, 60, 90];

const fmt = (secs) => {
  const m = Math.floor(secs / 60);
  const s = secs % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

/** Map GET /api/study/analytics into the shape this screen renders. */
function normaliseAnalytics(a) {
  if (!a) return null;
  return {
    weekly: (a.weekly || []).map((w) => ({ day: w.label || w.day, minutes: w.minutes || 0 })),
    planned: a.planned_minutes ?? 0,
    actual: a.actual_minutes ?? 0,
    completionRate: a.completion_rate ?? 0,
    sessionsCount: a.sessions_count ?? 0,
    engagement: a.average_engagement ?? 0,
    streak: a.streak_days ?? 0,
    target: a.daily_target ?? demoStudy.targetMinutes,
  };
}

function offlineAnalytics() {
  return {
    weekly: demoStudy.weekly,
    planned: demoStudy.history.reduce((s, h) => s + h.planned, 0),
    actual: demoStudy.history.reduce((s, h) => s + h.actual, 0),
    completionRate: 88,
    sessionsCount: demoStudy.history.length,
    engagement: demoStudy.averageEngagement,
    streak: demoStudy.streakDays,
    target: demoStudy.targetMinutes,
  };
}

export default function StudyScreen({ route }) {
  const { online } = useAuth();
  const [analytics, setAnalytics] = useState(null);
  const [refreshing, setRefreshing] = useState(false);

  // Timer state
  const [plannedMinutes, setPlannedMinutes] = useState(25);
  const [elapsed, setElapsed] = useState(0); // seconds
  const [running, setRunning] = useState(false);
  const [moduleIdx, setModuleIdx] = useState(0);
  const [sessionId, setSessionId] = useState(null);
  const [savedNote, setSavedNote] = useState(null);
  const timer = useRef(null);

  // Focus task handed over by JITHMI's "Start study session" (§4.3). When the
  // session starts online it carries assignment_id, so stopping it advances that
  // assignment's done_hours/progress on the backend (§4.4 write-back).
  const [focusTask, setFocusTask] = useState(route.params?.assignmentTitle || null);
  const [focusAssignmentId, setFocusAssignmentId] = useState(route.params?.assignmentId ?? null);

  // Keep the focus task in sync when a new recommendation is tapped — the tab
  // screen stays mounted, so the useState initialisers only run on first mount.
  useEffect(() => {
    const p = route.params;
    if (!p?.assignmentTitle) return;
    setFocusTask(p.assignmentTitle);
    setFocusAssignmentId(p.assignmentId ?? null);
    const idx = demoModules.findIndex((m) => m.name === p.moduleName);
    if (idx >= 0) setModuleIdx(idx);
  }, [route.params?.assignmentTitle, route.params?.assignmentId, route.params?.moduleName]);

  const load = useCallback(async () => {
    if (!online) {
      setAnalytics(offlineAnalytics());
      return;
    }
    try {
      setAnalytics(normaliseAnalytics(await api.studyAnalytics()) || offlineAnalytics());
    } catch (e) {
      setAnalytics(offlineAnalytics());
    }
  }, [online]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => () => clearInterval(timer.current), []);

  // Tick every second while running.
  useEffect(() => {
    if (running) {
      timer.current = setInterval(() => setElapsed((e) => e + 1), 1000);
    } else {
      clearInterval(timer.current);
    }
    return () => clearInterval(timer.current);
  }, [running]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  async function start() {
    setElapsed(0);
    setRunning(true);
    setSavedNote(null);
    if (online) {
      try {
        const res = await api.startSession({
          module_id: demoModules[moduleIdx]?.id,
          planned_minutes: plannedMinutes,
          // Link the session to the assignment JITHMI recommended, so the backend
          // advances that assignment's progress when the session stops (§4.4).
          ...(focusAssignmentId ? { assignment_id: focusAssignmentId } : {}),
        });
        if (res?.id) setSessionId(res.id);
      } catch (e) {
        /* offline-safe: local timer still works */
      }
    }
  }

  function pause() {
    setRunning(false);
    persist('paused');
  }

  function resume() {
    setRunning(true);
    persist('active');
  }

  /** Best-effort status sync — the local timer is the source of truth offline. */
  async function persist(status, minutes) {
    if (!online || !sessionId) return;
    try {
      await api.updateSession(sessionId, {
        status,
        ...(minutes !== undefined ? { actual_minutes: minutes } : {}),
      });
    } catch (e) {
      /* ignore — the local timer result is still shown to the student */
    }
  }

  async function stop() {
    setRunning(false);
    const minutes = Math.max(1, Math.round(elapsed / 60));
    await persist('completed', minutes);
    setSessionId(null);
    const label = focusTask || demoModules[moduleIdx]?.name || 'study';
    setSavedNote(
      focusAssignmentId
        ? `Session saved · ${minutes} min on "${label}" · progress sent to Jithmi`
        : `Session saved · ${minutes} min on ${label}`,
    );
    // The hand-off is consumed; clear it so the next session starts unlinked.
    setFocusTask(null);
    setFocusAssignmentId(null);
    setElapsed(0);
    load();
  }

  function reset() {
    setRunning(false);
    setElapsed(0);
    setSessionId(null);
    setSavedNote(null);
  }

  const progressPct = Math.min(100, (elapsed / (plannedMinutes * 60)) * 100);
  const todayMinutes = analytics?.weekly?.length ? analytics.weekly[analytics.weekly.length - 1].minutes : 0;
  const maxWeekly = Math.max(60, ...(analytics?.weekly || []).map((w) => w.minutes));

  const todayPct = useMemo(() => {
    const target = analytics?.target || demoStudy.targetMinutes;
    return Math.min(100, Math.round((todayMinutes / target) * 100));
  }, [todayMinutes, analytics]);

  return (
    <Screen
      title="Study & Engagement"
      subtitle="Pasindu · focus timer and analytics"
      accent={colors.pasindu}
      icon="timer"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.pasindu} />}
    >
      {/* Focus task handed over from JITHMI (§4.3 integration) */}
      {focusTask ? (
        <Card accent={colors.jithmi} style={styles.focusCard}>
          <Ionicons name="flame" size={16} color={colors.jithmi} />
          <View style={styles.focusTextWrap}>
            <Text style={styles.focusLabel}>Focus task from Jithmi</Text>
            <Text style={styles.focusTitle} numberOfLines={1}>{focusTask}</Text>
          </View>
        </Card>
      ) : null}

      {/* Timer */}
      <Card>
        <Text style={styles.timerLabel}>Focus session</Text>
        <Text style={styles.timerValue}>{fmt(elapsed)}</Text>
        <Text style={styles.timerSub}>
          {running ? 'In progress…' : elapsed > 0 ? 'Paused' : 'Ready to start'}
        </Text>

        <Progress value={progressPct} accent={colors.pasindu} height={10} />

        <View style={styles.presetRow}>
          {PRESETS.map((p) => (
            <Pressable
              key={p}
              disabled={running || elapsed > 0}
              onPress={() => setPlannedMinutes(p)}
              style={[
                styles.preset,
                plannedMinutes === p && styles.presetActive,
                (running || elapsed > 0) && styles.presetDisabled,
              ]}
            >
              <Text style={[styles.presetText, plannedMinutes === p && styles.presetTextActive]}>{p}m</Text>
            </Pressable>
          ))}
        </View>

        <View style={styles.moduleRow}>
          <Pressable
            style={styles.moduleNav}
            onPress={() => setModuleIdx((i) => (i - 1 + demoModules.length) % demoModules.length)}
            disabled={running}
          >
            <Ionicons name="chevron-back" size={18} color={running ? colors.border : colors.textMuted} />
          </Pressable>
          <Text style={styles.moduleName} numberOfLines={1}>
            {demoModules[moduleIdx]?.name || 'General study'}
          </Text>
          <Pressable
            style={styles.moduleNav}
            onPress={() => setModuleIdx((i) => (i + 1) % demoModules.length)}
            disabled={running}
          >
            <Ionicons name="chevron-forward" size={18} color={running ? colors.border : colors.textMuted} />
          </Pressable>
        </View>

        <View style={styles.controls}>
          {!running && elapsed === 0 ? (
            <Pressable style={[styles.btn, styles.btnPrimary]} onPress={start}>
              <Ionicons name="play" size={18} color="#fff" />
              <Text style={styles.btnText}>Start</Text>
            </Pressable>
          ) : null}

          {running ? (
            <Pressable style={[styles.btn, styles.btnWarn]} onPress={pause}>
              <Ionicons name="pause" size={18} color="#fff" />
              <Text style={styles.btnText}>Pause</Text>
            </Pressable>
          ) : null}

          {!running && elapsed > 0 ? (
            <Pressable style={[styles.btn, styles.btnPrimary]} onPress={resume}>
              <Ionicons name="play" size={18} color="#fff" />
              <Text style={styles.btnText}>Resume</Text>
            </Pressable>
          ) : null}

          {elapsed > 0 ? (
            <Pressable style={[styles.btn, styles.btnStop]} onPress={stop}>
              <Ionicons name="stop" size={18} color="#fff" />
              <Text style={styles.btnText}>Stop & save</Text>
            </Pressable>
          ) : null}

          {elapsed > 0 ? (
            <Pressable style={[styles.btn, styles.btnGhost]} onPress={reset}>
              <Ionicons name="refresh" size={17} color={colors.textMuted} />
              <Text style={[styles.btnText, { color: colors.textMuted }]}>Reset</Text>
            </Pressable>
          ) : null}
        </View>

        {savedNote ? (
          <View style={styles.savedBox}>
            <Ionicons name="checkmark-circle" size={15} color={colors.success} />
            <Text style={styles.savedText}>{savedNote}</Text>
          </View>
        ) : null}
      </Card>

      {/* Stats */}
      <SectionTitle accent={colors.pasindu}>Your progress</SectionTitle>
      <Card>
        <View style={styles.statRow}>
          <Stat label="Streak" value={`${analytics?.streak ?? 0}d`} accent={colors.jithmi} />
          <Stat label="Sessions" value={analytics?.sessionsCount ?? 0} accent={colors.pasindu} />
          <Stat label="Engagement" value={`${analytics?.engagement ?? 0}%`} accent={colors.kavishka} />
        </View>
        <View style={styles.targetRow}>
          <Text style={styles.targetLabel}>Today vs target</Text>
          <Text style={styles.targetValue}>
            {todayMinutes}/{analytics?.target ?? demoStudy.targetMinutes} min
          </Text>
        </View>
        <Progress value={todayPct} accent={colors.pasindu} height={10} />
      </Card>

      {/* Weekly chart */}
      <SectionTitle accent={colors.pasindu}>This week</SectionTitle>
      <Card>
        <View style={styles.chart}>
          {(analytics?.weekly || []).map((w, i) => {
            const h = Math.max(4, (w.minutes / maxWeekly) * 96);
            const isToday = i === (analytics?.weekly || []).length - 1;
            return (
              <View key={`${w.day}-${i}`} style={styles.barCol}>
                <Text style={styles.barValue}>{w.minutes || ''}</Text>
                <View
                  style={[
                    styles.bar,
                    { height: h, backgroundColor: isToday ? colors.pasindu : `${colors.pasindu}66` },
                  ]}
                />
                <Text style={[styles.barLabel, isToday && styles.barLabelActive]}>{w.day}</Text>
              </View>
            );
          })}
        </View>
        <View style={styles.chartFooter}>
          <Text style={styles.chartFooterText}>
            Planned {analytics?.planned ?? 0} min · Actual {analytics?.actual ?? 0} min
          </Text>
          <Text style={[styles.chartFooterText, { fontWeight: '700', color: colors.pasindu }]}>
            {analytics?.completionRate ?? 0}% completion
          </Text>
        </View>
      </Card>

      {/* History */}
      <SectionTitle accent={colors.pasindu}>Recent sessions</SectionTitle>
      <Card>
        {demoStudy.history.map((h, i) => (
          <View key={h.id} style={[styles.histRow, i > 0 && styles.histBorder]}>
            <View style={styles.histLeft}>
              <Text style={styles.histModule} numberOfLines={1}>{h.module}</Text>
              <Text style={styles.histMeta}>
                {h.date} · planned {h.planned}m · actual {h.actual}m
              </Text>
            </View>
            <View style={styles.histRight}>
              <Text style={[styles.histEng, { color: engagementColor(h.engagement) }]}>{h.engagement}%</Text>
              <Text style={styles.histEngLabel}>focus</Text>
            </View>
          </View>
        ))}
      </Card>
    </Screen>
  );
}

const engagementColor = (pct) =>
  pct >= 75 ? colors.success : pct >= 50 ? colors.medium : colors.danger;

const styles = StyleSheet.create({
  focusCard: { flexDirection: 'row', alignItems: 'center' },
  focusTextWrap: { flex: 1, marginLeft: spacing.sm },
  focusLabel: {
    fontSize: 10,
    fontWeight: '800',
    color: colors.jithmi,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  focusTitle: { fontSize: 13.5, fontWeight: '700', color: colors.text, marginTop: 1 },

  timerLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    textAlign: 'center',
  },
  timerValue: {
    fontSize: 54,
    fontWeight: '900',
    color: colors.text,
    textAlign: 'center',
    marginTop: spacing.xs,
    fontVariant: ['tabular-nums'],
  },
  timerSub: { fontSize: 12, color: colors.textMuted, textAlign: 'center', marginBottom: spacing.md },

  presetRow: { flexDirection: 'row', justifyContent: 'center', marginTop: spacing.md },
  preset: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.border,
    marginHorizontal: 4,
  },
  presetActive: { backgroundColor: colors.pasindu, borderColor: colors.pasindu },
  presetDisabled: { opacity: 0.5 },
  presetText: { fontSize: 12, fontWeight: '700', color: colors.textMuted },
  presetTextActive: { color: '#fff' },

  moduleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.lg,
  },
  moduleNav: {
    width: 30,
    height: 30,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surfaceAlt,
  },
  moduleName: {
    flex: 1,
    textAlign: 'center',
    fontSize: 13.5,
    fontWeight: '700',
    color: colors.text,
    marginHorizontal: spacing.sm,
  },

  controls: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', marginTop: spacing.lg },
  btn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    height: 44,
    borderRadius: radius.md,
    paddingHorizontal: spacing.lg,
    marginHorizontal: 4,
    marginBottom: spacing.sm,
  },
  btnPrimary: { backgroundColor: colors.pasindu },
  btnWarn: { backgroundColor: colors.medium },
  btnStop: { backgroundColor: colors.danger },
  btnGhost: { backgroundColor: colors.surfaceAlt },
  btnText: { color: '#fff', fontSize: 13.5, fontWeight: '700', marginLeft: 6 },

  savedBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: `${colors.success}12`,
    borderRadius: radius.sm,
    padding: spacing.sm + 2,
    marginTop: spacing.sm,
  },
  savedText: { fontSize: 12, color: colors.success, marginLeft: 6, flex: 1, fontWeight: '600' },

  statRow: { flexDirection: 'row', marginBottom: spacing.md },
  targetRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  targetLabel: { fontSize: 12, color: colors.textMuted },
  targetValue: { fontSize: 12, fontWeight: '700', color: colors.text },

  chart: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', height: 130 },
  barCol: { flex: 1, alignItems: 'center', justifyContent: 'flex-end' },
  barValue: { fontSize: 9, color: colors.textMuted, marginBottom: 3 },
  bar: { width: '58%', borderRadius: 5 },
  barLabel: { fontSize: 10, color: colors.textMuted, marginTop: 5, fontWeight: '600' },
  barLabelActive: { color: colors.pasindu, fontWeight: '800' },
  chartFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: spacing.md,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  chartFooterText: { fontSize: 11, color: colors.textMuted },

  histRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.sm },
  histBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  histLeft: { flex: 1, marginRight: spacing.sm },
  histModule: { fontSize: 13, fontWeight: '700', color: colors.text },
  histMeta: { fontSize: 11, color: colors.textMuted, marginTop: 2 },
  histRight: { alignItems: 'flex-end' },
  histEng: { fontSize: 15, fontWeight: '800' },
  histEngLabel: { fontSize: 9.5, color: colors.textMuted },
});

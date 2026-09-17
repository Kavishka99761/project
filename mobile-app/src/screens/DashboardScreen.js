/**
 * DashboardScreen — Common Platform Layer (the mobile "hub").
 *
 * Renders the same "day at a glance" payload as backend GET /api/dashboard:
 * greeting, today's study (Pasindu), risk summary (Jithmi), upcoming deadlines
 * (Jithmi) and academic dates (Kavishka). When the API is unreachable it builds
 * an equivalent payload from the bundled demo data, so the shape and the UI are
 * identical in both modes.
 */
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, RefreshControl, Pressable } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Screen, { HeaderButton } from '../components/Screen';
import { Card, SectionTitle, RiskPill, Progress, Avatar, Stat } from '../components/ui';
import { api } from '../api/client';
import { calcRisk, daysUntil } from '../api/risk';
import { useAuth } from '../context/AuthContext';
import { colors, moduleAccent, spacing, radius } from '../theme';
import {
  demoUser, demoModules, demoStudy, demoAssignments, demoAcademicDates, demoNotifications,
} from '../data/demo';

/** Build a dashboard payload locally — same keys as DashboardController@index. */
function offlineDashboard(user) {
  const enriched = demoAssignments.map((a) => ({ ...a, ...calcRisk(a) }));
  const active = enriched.filter((a) => (a.progress || 0) < 100);
  const counts = { Low: 0, Medium: 0, High: 0, Critical: 0 };
  active.forEach((a) => { counts[a.level] += 1; });

  const upcoming = active
    .filter((a) => a.days <= 7)
    .map((a) => ({
      id: a.id, title: a.title, deadline: a.deadline,
      days_left: a.days, level: a.level, score: a.score,
    }))
    .sort((x, y) => x.days_left - y.days_left)
    .slice(0, 5);

  const hour = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

  return {
    greeting,
    user: { ...user, daily_target_minutes: demoStudy.targetMinutes },
    modules: demoModules,
    study_today: {
      minutes: demoStudy.studiedMinutes,
      target: demoStudy.targetMinutes,
      percent: Math.min(100, Math.round((demoStudy.studiedMinutes / demoStudy.targetMinutes) * 100)),
      average_engagement: demoStudy.averageEngagement,
      active_session: null,
    },
    risk_summary: counts,
    upcoming_deadlines: upcoming,
    academic_dates: demoAcademicDates.map((d) => ({
      id: d.id, title: d.title, event_date: d.date, type: d.type, days_left: daysUntil(d.date),
    })),
    counts: { documents: 6, assignments: active.length, overdue: enriched.filter((a) => a.days < 0).length },
  };
}

const RISK_ORDER = ['Critical', 'High', 'Medium', 'Low'];

export default function DashboardScreen({ navigation }) {
  const { user, online, logout } = useAuth();
  const [data, setData] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);

  const load = useCallback(async () => {
    let payload = null;
    if (online) {
      try {
        payload = await api.dashboard();
      } catch (e) {
        payload = null;
      }
    }
    setData(payload || offlineDashboard(user || demoUser));
  }, [online, user]);

  useEffect(() => { load(); }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const greetingName = useMemo(() => {
    const name = data?.user?.name || user?.name || demoUser.name;
    return String(name).split(' ')[0];
  }, [data, user]);

  const study = data?.study_today || {};
  const risks = data?.risk_summary || {};
  const deadlines = data?.upcoming_deadlines || [];
  const dates = data?.academic_dates || [];
  const totalAtRisk = RISK_ORDER.reduce((sum, lvl) => sum + (risks[lvl] || 0), 0);

  return (
    <Screen
      title={`${data?.greeting || 'Hello'}, ${greetingName}`}
      subtitle={data?.user?.program || demoUser.program}
      accent={colors.primary}
      icon="school"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      right={
        <>
          <HeaderButton icon="notifications-outline" label="Notifications" onPress={() => setShowNotifications((v) => !v)} />
          <HeaderButton icon="log-out-outline" label="Sign out" onPress={logout} />
        </>
      }
    >
      {/* Profile strip */}
      <Card style={styles.profileCard}>
        <View style={styles.profileRow}>
          <Avatar initials={initials(data?.user?.name || user?.name)} accent={colors.primary} size={46} />
          <View style={styles.profileText}>
            <Text style={styles.profileName}>{data?.user?.name || user?.name || demoUser.name}</Text>
            <Text style={styles.profileMeta}>{data?.user?.email || demoUser.email}</Text>
            <Text style={styles.profileMeta}>{data?.user?.year || demoUser.year}</Text>
          </View>
        </View>
      </Card>

      {showNotifications ? <NotificationsPanel online={online} /> : null}

      {/* Today at a glance */}
      <SectionTitle accent={colors.pasindu}>Today at a glance</SectionTitle>
      <Card>
        <View style={styles.statRow}>
          <Stat label="Minutes studied" value={study.minutes ?? 0} accent={colors.pasindu} sub={`of ${study.target ?? 0} target`} />
          <Stat label="Engagement" value={`${study.average_engagement ?? 0}%`} accent={colors.kavishka} sub="today avg" />
          <Stat label="Streak" value={`${demoStudy.streakDays}d`} accent={colors.jithmi} sub="consecutive" />
        </View>
        <View style={styles.progressLabelRow}>
          <Text style={styles.progressLabel}>Daily target</Text>
          <Text style={styles.progressValue}>{study.percent ?? 0}%</Text>
        </View>
        <Progress value={study.percent ?? 0} accent={colors.pasindu} height={10} />
      </Card>

      {/* Risk summary */}
      <SectionTitle accent={colors.jithmi} action="View all" onAction={() => navigation.navigate('Assignments')}>
        Deadline risk
      </SectionTitle>
      <Card>
        <View style={styles.riskRow}>
          {RISK_ORDER.map((lvl) => (
            <View key={lvl} style={styles.riskCell}>
              <Text style={[styles.riskCount, { color: colorFor(lvl) }]}>{risks[lvl] ?? 0}</Text>
              <Text style={styles.riskLabel}>{lvl}</Text>
            </View>
          ))}
        </View>
        <Text style={styles.riskFootnote}>
          {totalAtRisk} active assignment{totalAtRisk === 1 ? '' : 's'} ·{' '}
          {data?.counts?.overdue ?? 0} overdue
        </Text>
      </Card>

      {/* Upcoming deadlines */}
      {deadlines.length ? (
        <>
          <SectionTitle accent={colors.jithmi}>Due within 7 days</SectionTitle>
          {deadlines.map((d) => (
            <Card key={d.id} accent={colorFor(d.level)} onPress={() => navigation.navigate('Assignments')}>
              <View style={styles.listRow}>
                <View style={styles.listMain}>
                  <Text style={styles.listTitle} numberOfLines={1}>{d.title}</Text>
                  <Text style={styles.listMeta}>
                    {d.deadline} · {d.days_left === 0 ? 'due today' : `${d.days_left} day${d.days_left === 1 ? '' : 's'} left`}
                  </Text>
                </View>
                <RiskPill level={d.level} score={d.score} size="sm" />
              </View>
            </Card>
          ))}
        </>
      ) : null}

      {/* Academic dates (Kavishka) */}
      {dates.length ? (
        <>
          <SectionTitle accent={colors.kavishka} action="Ask assistant" onAction={() => navigation.navigate('Assistant')}>
            Academic calendar
          </SectionTitle>
          <Card>
            {dates.map((d, i) => (
              <View key={d.id} style={[styles.dateRow, i > 0 && styles.dateRowBorder]}>
                <View style={styles.dateBadge}>
                  <Text style={styles.dateDay}>{String(d.event_date).slice(8, 10)}</Text>
                  <Text style={styles.dateMonth}>{monthShort(d.event_date)}</Text>
                </View>
                <View style={styles.listMain}>
                  <Text style={styles.listTitle} numberOfLines={1}>{d.title}</Text>
                  <Text style={styles.listMeta}>
                    {d.type} · in {d.days_left} day{d.days_left === 1 ? '' : 's'}
                  </Text>
                </View>
                <Ionicons name="chevron-forward" size={16} color={colors.textMuted} />
              </View>
            ))}
          </Card>
        </>
      ) : null}

      {/* Modules */}
      <SectionTitle accent={colors.bethmi}>Your modules</SectionTitle>
      <View style={styles.moduleGrid}>
        {(data?.modules || demoModules).map((m) => (
          <Pressable
            key={m.id ?? m.code}
            style={({ pressed }) => [styles.moduleTile, { opacity: pressed ? 0.85 : 1 }]}
            onPress={() => navigation.navigate(tabFor(m))}
          >
            <View style={[styles.moduleDot, { backgroundColor: m.color || moduleAccent[accentKey(m)] || colors.primary }]} />
            <Text style={styles.moduleCode}>{m.code}</Text>
            <Text style={styles.moduleName} numberOfLines={2}>{m.name}</Text>
          </Pressable>
        ))}
      </View>
    </Screen>
  );
}

/* ------------------------------------------------------------ notifications */

function NotificationsPanel({ online }) {
  const [items, setItems] = useState(demoNotifications);

  // Live notifications come from GET /api/notifications (Common Platform Layer);
  // offline, the bundled demoNotifications are shown so the panel is never empty.
  useEffect(() => {
    let mounted = true;
    if (!online) {
      setItems(demoNotifications);
      return () => { mounted = false; };
    }
    (async () => {
      try {
        const res = await api.notifications();
        if (!mounted) return;
        setItems(Array.isArray(res) && res.length ? res.map(normaliseNotification) : demoNotifications);
      } catch (e) {
        if (mounted) setItems(demoNotifications);
      }
    })();
    return () => { mounted = false; };
  }, [online]);

  return (
    <Card style={styles.notifCard}>
      <Text style={styles.notifTitle}>Recent notifications</Text>
      {items.map((n) => (
        <View key={n.id} style={styles.notifRow}>
          <View style={[styles.notifDot, { backgroundColor: moduleAccent[n.source] || colors.primary }]} />
          <View style={styles.listMain}>
            <Text style={styles.notifHead}>{n.title}</Text>
            <Text style={styles.notifBody} numberOfLines={2}>{n.message}</Text>
          </View>
          <Text style={styles.notifTime}>{timeAgo(n.minutesAgo)}</Text>
        </View>
      ))}
    </Card>
  );
}

/**
 * Normalise a GET /api/notifications row (Notification model: module_source,
 * title, message, created_at) into the flat shape the panel renders — the same
 * shape as the bundled demoNotifications (source, minutesAgo).
 */
function normaliseNotification(n) {
  return {
    id: n.id,
    source: n.module_source || n.source || 'common',
    title: n.title,
    message: n.message || '',
    minutesAgo: n.minutesAgo ?? minutesSince(n.created_at),
  };
}

const minutesSince = (isoStr) => {
  if (!isoStr) return 0;
  const t = new Date(isoStr).getTime();
  return Number.isNaN(t) ? 0 : Math.max(0, Math.round((Date.now() - t) / 60000));
};

/* ------------------------------------------------------------------ helpers */

const initials = (name) =>
  String(name || '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('');

const colorFor = (level) =>
  ({ Low: colors.low, Medium: colors.medium, High: colors.high, Critical: colors.critical }[level] || colors.textMuted);

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const monthShort = (dateStr) => MONTHS[Number(String(dateStr).slice(5, 7)) - 1] || '';

const accentKey = (m) => m.accent || m.owner || String(m.code || '').toLowerCase();

function tabFor(m) {
  const key = accentKey(m);
  if (key.includes('bethmi') || key.includes('note')) return 'Learning';
  if (key.includes('pasindu') || key.includes('study')) return 'Study';
  if (key.includes('kavishka') || key.includes('assist')) return 'Assistant';
  if (key.includes('jithmi') || key.includes('assign')) return 'Assignments';
  return 'Learning';
}

const timeAgo = (mins) => (mins < 60 ? `${mins}m` : mins < 1440 ? `${Math.round(mins / 60)}h` : `${Math.round(mins / 1440)}d`);

/* ------------------------------------------------------------------- styles */

const styles = StyleSheet.create({
  profileCard: { padding: spacing.md },
  profileRow: { flexDirection: 'row', alignItems: 'center' },
  profileText: { marginLeft: spacing.md, flex: 1 },
  profileName: { fontSize: 15, fontWeight: '800', color: colors.text },
  profileMeta: { fontSize: 11.5, color: colors.textMuted, marginTop: 1 },

  statRow: { flexDirection: 'row', marginBottom: spacing.md },

  progressLabelRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  progressLabel: { fontSize: 12, color: colors.textMuted },
  progressValue: { fontSize: 12, fontWeight: '700', color: colors.text },

  riskRow: { flexDirection: 'row' },
  riskCell: { flex: 1, alignItems: 'center' },
  riskCount: { fontSize: 24, fontWeight: '900' },
  riskLabel: { fontSize: 11, color: colors.textMuted, marginTop: 2 },
  riskFootnote: {
    fontSize: 11.5,
    color: colors.textMuted,
    textAlign: 'center',
    marginTop: spacing.md,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },

  listRow: { flexDirection: 'row', alignItems: 'center' },
  listMain: { flex: 1, marginRight: spacing.sm },
  listTitle: { fontSize: 14, fontWeight: '700', color: colors.text },
  listMeta: { fontSize: 11.5, color: colors.textMuted, marginTop: 2 },

  dateRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.sm },
  dateRowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  dateBadge: {
    width: 42,
    height: 42,
    borderRadius: radius.md,
    backgroundColor: `${colors.kavishka}14`,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  dateDay: { fontSize: 15, fontWeight: '800', color: colors.kavishka, lineHeight: 17 },
  dateMonth: { fontSize: 9.5, fontWeight: '700', color: colors.kavishka, textTransform: 'uppercase' },

  moduleGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
  moduleTile: {
    width: '48%',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
  },
  moduleDot: { width: 8, height: 8, borderRadius: 4, marginBottom: spacing.sm },
  moduleCode: { fontSize: 11, fontWeight: '800', color: colors.textMuted, letterSpacing: 0.5 },
  moduleName: { fontSize: 13.5, fontWeight: '700', color: colors.text, marginTop: 2 },

  notifCard: { backgroundColor: colors.surfaceAlt },
  notifTitle: { fontSize: 13, fontWeight: '800', color: colors.text, marginBottom: spacing.sm },
  notifRow: { flexDirection: 'row', alignItems: 'flex-start', paddingVertical: 7 },
  notifDot: { width: 8, height: 8, borderRadius: 4, marginTop: 5, marginRight: spacing.sm },
  notifHead: { fontSize: 12.5, fontWeight: '700', color: colors.text },
  notifBody: { fontSize: 11, color: colors.textMuted, marginTop: 1 },
  notifTime: { fontSize: 10, color: colors.textMuted, marginLeft: spacing.sm },
});

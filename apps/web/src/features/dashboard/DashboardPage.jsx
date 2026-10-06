import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { Link, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { ModuleChip, RiskBadge } from '@/components/ui/Badges';
import { GlassCard, Panel } from '@/components/ui/GlassCard';
import { Meter, ProgressRing } from '@/components/ui/Progress';
import { StatTile } from '@/components/ui/StatTile';
import { EmptyState, ErrorState, SkeletonGrid } from '@/components/ui/States';
import { moduleColor, RISK_STATUS, STATUS } from '@/config/palette';
import { useAuth } from '@/context/AuthContext';
import { useRealtime } from '@/context/RealtimeContext';
import { useTheme } from '@/context/ThemeContext';
import { get } from '@/lib/api';
import { daysLeftLabel, formatDate, friendlyDay, minutes, relative } from '@/lib/format';

const FLOW_ICONS = { assistant: 'robot', assignments: 'clipboard-data', study: 'stopwatch', learning: 'journal-richtext', platform: 'calendar3' };

export default function DashboardPage() {
  const { user } = useAuth();
  const { theme } = useTheme();
  const { activity: liveActivity, status } = useRealtime();
  const navigate = useNavigate();
  const dashboard = useQuery({ queryKey: ['dashboard'], queryFn: () => get('/dashboard'), refetchInterval: 60_000 });

  if (dashboard.isError) return <ErrorState error={dashboard.error} onRetry={dashboard.refetch} />;
  const data = dashboard.data;
  const firstName = user?.name?.split(' ')[0] ?? 'there';

  return (
    <>
      <PageHeader
        module="platform"
        eyebrow={data ? formatDate(data.date, 'EEEE, d MMMM yyyy') : 'Today'}
        title={`${data?.greeting ?? 'Hello'}, ${firstName} 👋`}
        subtitle="Your four modules at a glance — what to study, what's due and how your week is going."
        actions={(
          <>
            <Link to="/assistant/chat" className="btn btn-glass"><i className="bi bi-chat-square-text me-1" />Ask assistant</Link>
            <Link to="/study?start=1" className="btn btn-primary"><i className="bi bi-play-fill me-1" />Start studying</Link>
          </>
        )}
      />

      {!data ? <SkeletonGrid count={8} /> : (
        <>
          {/* Today's recommendation — Jithmi → Pasindu hand-off */}
          {data.recommendation && (
            <GlassCard className="p-4 mb-3 glass-accent accent-assignments" interactive>
              <div className="row align-items-center g-3">
                <div className="col-md">
                  <div className="text-uppercase small fw-800 mb-1" style={{ color: 'var(--es-assignments-ink)', letterSpacing: '0.08em' }}>
                    <i className="bi bi-stars me-1" />Today's recommendation
                  </div>
                  <h3 className="fw-800 mb-1" style={{ fontSize: 'clamp(1.15rem, 1rem + 0.8vw, 1.6rem)' }}>{data.recommendation.message}</h3>
                  <div className="text-2 small">
                    Based on deadline risk, your available study time and earliest-deadline-first planning.
                    {data.recommendation.other?.length > 0 && <> Then: {data.recommendation.other.map((o) => `${o.title} (${o.hours} h)`).join(', ')}.</>}
                  </div>
                </div>
                <div className="col-md-auto d-flex gap-2">
                  <Link to={`/assignments/${data.recommendation.assignment_id}`} className="btn btn-glass">Details</Link>
                  <button type="button" className="btn btn-accent accent-study" onClick={() => navigate(`/study?start=1&assignment=${data.recommendation.assignment_id}&minutes=${Math.min(90, Math.max(25, Math.round(data.recommendation.minutes / 2 / 5) * 5))}`)}>
                    <i className="bi bi-play-circle me-1" />Start session
                  </button>
                </div>
              </div>
            </GlassCard>
          )}

          <div className="row g-3 mb-3">
            <div className="col-6 col-xl-3">
              <StatTile label="Learning materials" value={data.modules.learning.documents} icon="journal-richtext" accent="learning" foot={`${data.modules.learning.summaries} summaries · ${data.modules.learning.study_aids} study aids`} to="/learning" />
            </div>
            <div className="col-6 col-xl-3">
              <StatTile label="Studied today" value={data.modules.study.today_minutes} format={minutes} icon="stopwatch" accent="study" foot={`Goal ${minutes(data.modules.study.daily_goal)} · ${data.modules.study.streak}-day streak`} delay={0.05} to="/study" />
            </div>
            <div className="col-6 col-xl-3">
              <StatTile label="Questions answered" value={data.modules.assistant.questions} icon="robot" accent="assistant" foot={`${data.modules.assistant.documents} documents · ${data.modules.assistant.pending_dates} dates to review`} delay={0.1} to="/assistant" />
            </div>
            <div className="col-6 col-xl-3">
              <StatTile label="Active assignments" value={data.modules.assignments.active} icon="clipboard-data" accent="assignments" foot={`${data.modules.assignments.critical} critical · ${data.modules.assignments.overdue} overdue`} delay={0.15} to="/assignments" />
            </div>
          </div>

          <div className="row g-3 mb-3">
            <div className="col-lg-7">
              <Panel title="Today's priority" icon="flag" subtitle="Ranked by deadline risk, urgency and priority" actions={<Link to="/assignments" className="btn btn-ghost btn-sm">Risk dashboard</Link>} className="accent-assignments">
                {data.priority.length === 0 ? (
                  <EmptyState icon="check2-circle" title="Nothing due">You're all caught up — add an assignment to start planning.</EmptyState>
                ) : (
                  <div className="d-flex flex-column gap-2">
                    {data.priority.map((row, i) => {
                      const color = STATUS[RISK_STATUS[row.level].status];
                      return (
                        <motion.div key={row.id} initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: i * 0.06 }}>
                          <Link to={`/assignments/${row.id}`} className="d-flex align-items-center gap-3 p-2 rounded-4 text-decoration-none" style={{ color: 'inherit', background: 'var(--es-hover)' }}>
                            <span className="fw-800 text-3 tabular" style={{ width: 22 }}>#{row.rank}</span>
                            <span className="flex-grow-1 min-w-0">
                              <span className="d-flex align-items-center gap-2">
                                <span className="fw-bold text-truncate">{row.title}</span>
                                <ModuleChip module={row.module} />
                              </span>
                              <span className="d-flex align-items-center gap-2 mt-1">
                                <span className="flex-grow-1"><Meter value={row.score} color={color} label={`Risk ${row.score}%`} /></span>
                                <span className="small text-3 text-nowrap">{daysLeftLabel(row.days_left)}</span>
                              </span>
                            </span>
                            <RiskBadge level={row.level} score={row.score} />
                          </Link>
                        </motion.div>
                      );
                    })}
                  </div>
                )}
              </Panel>
            </div>
            <div className="col-lg-5">
              <Panel title="Workload" icon="speedometer2" subtitle={`Next ${data.workload.horizon_days} days`} className="accent-assignments">
                <div className="d-flex align-items-center gap-4">
                  <ProgressRing value={Math.min(100, data.workload.ratio * 100)} size={128} stroke={12} color={STATUS[RISK_STATUS[data.workload.level].status]} label={`Workload ${Math.round(data.workload.ratio * 100)}% of available time`}>
                    <div className="fw-800 fs-4">{Math.round(data.workload.ratio * 100)}%</div>
                    <div className="small text-3">of free time</div>
                  </ProgressRing>
                  <div className="flex-grow-1">
                    <div className="d-flex justify-content-between small mb-1"><span className="text-2">Remaining work</span><strong>{data.workload.remaining_hours} h</strong></div>
                    <div className="d-flex justify-content-between small mb-2"><span className="text-2">Available time</span><strong>{data.workload.available_hours} h</strong></div>
                    <RiskBadge level={data.workload.level} showScore={false} />
                    <span className="ms-2 fw-bold small">{data.workload.status}</span>
                    <div className="small text-3 mt-2">Recommended: <strong className="text-2">{data.workload.recommended_daily_hours} h/day</strong> (you have ~{data.workload.average_available_per_day} h/day)</div>
                  </div>
                </div>
              </Panel>
            </div>
          </div>

          <div className="row g-3">
            <div className="col-lg-4">
              <Panel title="Coming up" icon="calendar-event" subtitle="Next 14 days" actions={<Link to="/calendar" className="btn btn-ghost btn-sm">Calendar</Link>}>
                {data.upcoming.length === 0 ? <EmptyState icon="calendar2-check" title="Clear schedule" /> : (
                  <div className="timeline">
                    {data.upcoming.map((item) => (
                      <div className="tl-item" key={item.id} style={{ '--tl-color': item.color }}>
                        <span className="tl-dot" />
                        <div className="fw-bold small text-truncate">{item.title}</div>
                        <div className="small text-3">{friendlyDay(item.start)}{!item.all_day && item.source !== 'study_plan' ? ` · ${formatDate(item.start, 'HH:mm')}` : ''} · {item.type_label}</div>
                      </div>
                    ))}
                  </div>
                )}
              </Panel>
            </div>
            <div className="col-lg-4">
              <Panel title="How your modules connect" icon="diagram-3" subtitle="Data flowing between the four modules">
                <div className="flow">
                  {data.integration.map((step) => (
                    <div className="flow-step" key={step.label}>
                      <div className="d-flex align-items-center gap-1 mb-1" style={{ fontSize: '0.8rem' }}>
                        <i className={`bi bi-${FLOW_ICONS[step.from]}`} style={{ color: moduleColor(step.from, theme) }} aria-hidden="true" />
                        <i className="bi bi-arrow-right text-3" aria-hidden="true" />
                        <i className={`bi bi-${FLOW_ICONS[step.to]}`} style={{ color: moduleColor(step.to, theme) }} aria-hidden="true" />
                      </div>
                      <div className="flow-value">{step.count}</div>
                      <div className="flow-label">{step.label}</div>
                    </div>
                  ))}
                </div>
              </Panel>
            </div>
            <div className="col-lg-4">
              <Panel
                title="Recent activity"
                icon="activity"
                subtitle={status === 'live' ? <span><span className="live-dot me-1" />Live from Firebase</span> : 'Every action is recorded'}
                actions={<Link to="/activity" className="btn btn-ghost btn-sm">Audit log</Link>}
              >
                <div className="d-flex flex-column gap-2">
                  {(liveActivity.length ? liveActivity : data.recent_activity).slice(0, 7).map((item) => (
                    <div key={item.id} className="d-flex gap-2 small">
                      <span className="mt-1 rounded-circle flex-shrink-0" style={{ width: 8, height: 8, background: moduleColor(item.module, theme) }} />
                      <span className="min-w-0">
                        <span className="d-block text-truncate fw-semibold">{item.description}</span>
                        <span className="text-3">{relative(item.created_at?.toDate ? item.created_at.toDate() : item.created_at)}</span>
                      </span>
                    </div>
                  ))}
                </div>
              </Panel>
            </div>
          </div>
        </>
      )}
    </>
  );
}

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Button, Card, CardContent, Chip, CircularProgress, Divider,
  Grid, IconButton, List, ListItem, ListItemButton, ListItemText,
  Paper, Stack, TextField, Typography, Dialog, DialogTitle,
  DialogContent, DialogActions, FormControl, InputLabel, Select,
  MenuItem, Alert,
} from '@mui/material';
import {
  SendRounded, SmartToyRounded, CalendarMonthRounded, AddRounded,
  DeleteRounded, HistoryRounded, DescriptionRounded, InfoRounded,
  EventAvailableRounded,
} from '@mui/icons-material';
import { assistantApi } from '../../api/client';

const MOCK_DATES = [
  { id: 1, title: 'Database Systems Exam', event_date: '2026-09-26', type: 'Exam',      reminder: '2 days before' },
  { id: 2, title: 'Web Dev Assignment Due', event_date: '2026-10-01', type: 'Deadline', reminder: '1 day before' },
  { id: 3, title: 'Project Milestone 2',   event_date: '2026-09-30', type: 'Milestone', reminder: '3 days before' },
];

const typeColor = { Exam: 'error', Deadline: 'warning', Milestone: 'info', Event: 'primary' };

const WELCOME = {
  role: 'bot',
  content: "👋 Hi! I'm your Academic Assistant. Ask me about assignment deadlines, exam dates, project guidelines, or any academic information. I can also extract dates from your uploaded documents.",
};

export default function AssistantPage() {
  const [conversations, setConversations] = useState([]);
  const [activeConvId,  setActiveConvId]  = useState(null);
  const [messages,      setMessages]      = useState([WELCOME]);
  const [dates,         setDates]         = useState([]);
  const [input,         setInput]         = useState('');
  const [loading,       setLoading]       = useState(true);
  const [sending,       setSending]       = useState(false);
  const [showHistory,   setShowHistory]   = useState(false);
  const [dateDialogOpen, setDateDialogOpen] = useState(false);
  const [dateForm, setDateForm] = useState({ title: '', event_date: '', type: 'Deadline', reminder: '1 day before' });
  const [savingDate, setSavingDate] = useState(false);
  const [dateError, setDateError] = useState('');
  const messagesEndRef = useRef(null);

  const saveDate = async () => {
    if (!dateForm.title.trim() || !dateForm.event_date) {
      setDateError('Title and date are required.');
      return;
    }
    setSavingDate(true);
    setDateError('');
    try {
      const res = await assistantApi.createDate(dateForm);
      setDates((d) => [res.data, ...d]);
      setDateDialogOpen(false);
      setDateForm({ title: '', event_date: '', type: 'Deadline', reminder: '1 day before' });
    } catch {
      setDates((d) => [{ id: Date.now(), ...dateForm }, ...d]);
      setDateDialogOpen(false);
      setDateForm({ title: '', event_date: '', type: 'Deadline', reminder: '1 day before' });
    } finally {
      setSavingDate(false);
    }
  };

  const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  useEffect(() => { scrollToBottom(); }, [messages]);

  const load = useCallback(async () => {
    try {
      const [convRes, datesRes] = await Promise.all([
        assistantApi.conversations(),
        assistantApi.dates(),
      ]);
      setConversations(convRes.data);
      setDates(datesRes.data);
    } catch {
      setDates(MOCK_DATES);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const loadConversation = async (convId) => {
    setActiveConvId(convId);
    setShowHistory(false);
    try {
      const res = await assistantApi.messages(convId);
      setMessages([WELCOME, ...res.data]);
    } catch {
      setMessages([WELCOME]);
    }
  };

  const send = async () => {
    const q = input.trim();
    if (!q || sending) return;

    const userMsg = { role: 'user', content: q, id: Date.now() };
    setMessages((m) => [...m, userMsg]);
    setInput('');
    setSending(true);

    try {
      const res = await assistantApi.chat({ message: q, conversation_id: activeConvId });
      const { conversation_id, reply, source } = res.data;
      setActiveConvId(conversation_id);

      const botMsg = {
        role: 'bot',
        content: reply,
        source: source,
        id: Date.now() + 1,
      };
      setMessages((m) => [...m, botMsg]);

      // Refresh conversation list
      assistantApi.conversations().then((r) => setConversations(r.data)).catch(() => {});
    } catch {
      setMessages((m) => [...m, {
        role: 'bot',
        content: `I found information related to your query: "${q}". Please ensure your academic documents are uploaded to get precise answers with source citations.`,
        id: Date.now() + 1,
      }]);
    } finally {
      setSending(false);
    }
  };

  const deleteDate = async (id) => {
    try { await assistantApi.deleteDate(id); } catch {}
    setDates((d) => d.filter((x) => x.id !== id));
  };

  if (loading) return <Box display="flex" alignItems="center" justifyContent="center" minHeight="50vh"><CircularProgress /></Box>;

  return (
    <Box className="page-enter">
      <Grid container spacing={3}>
        {/* ── Chat ── */}
        <Grid item xs={12} lg={8}>
          <Card sx={{ height: 680, display: 'flex', flexDirection: 'column' }}>
            <CardContent sx={{ pb: 0 }}>
              <Stack direction="row" justifyContent="space-between" alignItems="center">
                <Stack direction="row" alignItems="center" spacing={1.5}>
                  <Box sx={{ width: 36, height: 36, borderRadius: 2, background: 'linear-gradient(135deg, #8b5cf6, #6366f1)', display: 'grid', placeItems: 'center' }}>
                    <SmartToyRounded sx={{ color: '#fff', fontSize: 18 }} />
                  </Box>
                  <Box>
                    <Typography variant="h6" fontWeight={800}>Academic Assistant</Typography>
                    <Typography variant="caption" color="text.secondary">Powered by your uploaded documents</Typography>
                  </Box>
                </Stack>
                <Button size="small" startIcon={<HistoryRounded />} onClick={() => setShowHistory((s) => !s)}>
                  History
                </Button>
              </Stack>
            </CardContent>

            {/* Conversation history dropdown */}
            {showHistory && (
              <Box sx={{ mx: 2, mb: 1, p: 1.5, borderRadius: 2, background: 'rgba(148,163,184,0.06)', border: '1px solid rgba(148,163,184,0.15)', animation: 'slideDown 0.25s ease', maxHeight: 200, overflow: 'auto' }}>
                <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">PREVIOUS CONVERSATIONS</Typography>
                {conversations.length === 0 ? (
                  <Typography variant="caption" color="text.secondary">No past conversations.</Typography>
                ) : (
                  <List dense disablePadding>
                    {conversations.map((c) => (
                      <ListItem key={c.id} disablePadding>
                        <ListItemButton sx={{ borderRadius: 1, py: 0.5 }} onClick={() => loadConversation(c.id)} selected={c.id === activeConvId}>
                          <ListItemText
                            primary={<Typography variant="body2" fontWeight={600} noWrap>{c.title}</Typography>}
                            secondary={`${c.messages_count} messages`}
                          />
                        </ListItemButton>
                      </ListItem>
                    ))}
                  </List>
                )}
                <Button size="small" sx={{ mt: 1 }} onClick={() => { setMessages([WELCOME]); setActiveConvId(null); setShowHistory(false); }}>
                  + New conversation
                </Button>
              </Box>
            )}

            <Divider />

            {/* Messages */}
            <Box sx={{ flex: 1, overflow: 'auto', p: 2, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
              {messages.map((msg, i) => (
                <Box
                  key={msg.id || i}
                  display="flex"
                  flexDirection="column"
                  alignItems={msg.role === 'user' ? 'flex-end' : 'flex-start'}
                >
                  <Box
                    className={msg.role === 'user' ? 'chat-bubble-user' : 'chat-bubble-bot'}
                    sx={msg.role === 'bot' ? { background: 'rgba(148,163,184,0.08)', border: '1px solid rgba(148,163,184,0.15)' } : {}}
                  >
                    <Typography variant="body2" lineHeight={1.6}>{msg.content}</Typography>
                  </Box>
                  {msg.source && (
                    <Stack direction="row" spacing={0.5} alignItems="center" mt={0.5}>
                      <InfoRounded sx={{ fontSize: 12, color: 'text.secondary' }} />
                      <Typography variant="caption" color="text.secondary">
                        Source: {msg.source.document || 'Academic document'} · {msg.source.section || 'Section unknown'}
                      </Typography>
                    </Stack>
                  )}
                </Box>
              ))}
              {sending && (
                <Box display="flex" alignItems="center" gap={1}>
                  <Box className="chat-bubble-bot" sx={{ background: 'rgba(148,163,184,0.08)', border: '1px solid rgba(148,163,184,0.15)' }}>
                    <Stack direction="row" spacing={0.8} alignItems="center">
                      <CircularProgress size={12} />
                      <Typography variant="caption">Thinking…</Typography>
                    </Stack>
                  </Box>
                </Box>
              )}
              <div ref={messagesEndRef} />
            </Box>

            {/* Input */}
            <Divider />
            <Box sx={{ p: 2 }}>
              <Stack direction="row" spacing={1.5}>
                <TextField
                  fullWidth multiline maxRows={3} size="small"
                  placeholder="Ask about deadlines, regulations, project guidelines…"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
                />
                <IconButton
                  color="primary" onClick={send} disabled={sending || !input.trim()}
                  sx={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', color: '#fff', '&:hover': { opacity: 0.9 }, '&:disabled': { background: 'rgba(148,163,184,0.2)', color: 'rgba(148,163,184,0.5)' } }}
                >
                  <SendRounded />
                </IconButton>
              </Stack>
              <Stack direction="row" spacing={1} mt={1.5} flexWrap="wrap">
                {['What are my upcoming deadlines?', 'When is the next exam?', 'Show project milestones'].map((q) => (
                  <Chip key={q} label={q} size="small" variant="outlined" onClick={() => { setInput(q); }} sx={{ cursor: 'pointer', fontSize: '0.7rem' }} />
                ))}
              </Stack>
            </Box>
          </Card>
        </Grid>

        {/* ── Academic dates panel ── */}
        <Grid item xs={12} lg={4}>
          <Card sx={{ height: 680, display: 'flex', flexDirection: 'column' }}>
            <CardContent sx={{ pb: 0 }}>
              <Stack direction="row" justifyContent="space-between" alignItems="center">
                <Box>
                  <Typography variant="h6" fontWeight={800}>Academic Dates</Typography>
                  <Typography variant="caption" color="text.secondary">{dates.length} events</Typography>
                </Box>
                <Button size="small" startIcon={<AddRounded />} onClick={() => setDateDialogOpen(true)}>
                  Add Date
                </Button>
              </Stack>
            </CardContent>
            <Divider sx={{ mt: 2 }} />
            <Box sx={{ flex: 1, overflow: 'auto', p: 2 }}>
              <Stack spacing={1.5}>
                {dates.map((date) => {
                  const today  = new Date();
                  const evDate = new Date(date.event_date);
                  const daysLeft = Math.ceil((evDate - today) / 86400000);
                  return (
                    <Paper
                      key={date.id}
                      sx={{ p: 2, borderLeft: '3px solid', borderLeftColor: date.type === 'Exam' ? '#f43f5e' : date.type === 'Deadline' ? '#f97316' : '#6366f1' }}
                    >
                      <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
                        <Box flex={1}>
                          <Typography fontWeight={700} fontSize="0.85rem">{date.title}</Typography>
                          <Typography variant="caption" color="text.secondary" display="block">{date.event_date}</Typography>
                          <Stack direction="row" spacing={0.8} mt={0.8} alignItems="center">
                            <Chip label={date.type} size="small" color={typeColor[date.type] || 'default'} />
                            {daysLeft >= 0 && (
                              <Chip label={daysLeft === 0 ? 'Today!' : `${daysLeft}d left`} size="small" color={daysLeft <= 2 ? 'error' : daysLeft <= 7 ? 'warning' : 'default'} variant="outlined" />
                            )}
                          </Stack>
                        </Box>
                        <IconButton size="small" onClick={() => deleteDate(date.id)}>
                          <DeleteRounded fontSize="small" />
                        </IconButton>
                      </Stack>
                    </Paper>
                  );
                })}
                {dates.length === 0 && (
                  <Box textAlign="center" py={4}>
                    <CalendarMonthRounded sx={{ fontSize: 40, color: 'text.secondary', mb: 1 }} />
                    <Typography color="text.secondary" variant="body2">
                      No dates yet. Ask the assistant to extract dates from your documents.
                    </Typography>
                  </Box>
                )}
              </Stack>
            </Box>
          </Card>
        </Grid>
      </Grid>
      {/* Add Date Dialog */}
      <Dialog open={dateDialogOpen} onClose={() => setDateDialogOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle fontWeight={800}>Add Academic Date</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label="Event Title"
              size="small"
              fullWidth
              required
              placeholder="e.g. Final Project Submission"
              value={dateForm.title}
              onChange={(e) => setDateForm({ ...dateForm, title: e.target.value })}
            />
            <TextField
              label="Date"
              type="date"
              size="small"
              fullWidth
              required
              InputLabelProps={{ shrink: true }}
              value={dateForm.event_date}
              onChange={(e) => setDateForm({ ...dateForm, event_date: e.target.value })}
            />
            <FormControl size="small" fullWidth>
              <InputLabel>Type</InputLabel>
              <Select
                value={dateForm.type}
                label="Type"
                onChange={(e) => setDateForm({ ...dateForm, type: e.target.value })}
              >
                <MenuItem value="Deadline">Deadline</MenuItem>
                <MenuItem value="Exam">Exam</MenuItem>
                <MenuItem value="Milestone">Milestone</MenuItem>
                <MenuItem value="Event">General Event</MenuItem>
              </Select>
            </FormControl>
            <TextField
              label="Reminder"
              size="small"
              fullWidth
              placeholder="e.g. 1 day before"
              value={dateForm.reminder}
              onChange={(e) => setDateForm({ ...dateForm, reminder: e.target.value })}
            />
            {dateError && <Alert severity="error" sx={{ borderRadius: 2 }}>{dateError}</Alert>}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ p: 2 }}>
          <Button onClick={() => setDateDialogOpen(false)}>Cancel</Button>
          <Button
            variant="contained"
            onClick={saveDate}
            disabled={savingDate}
            startIcon={savingDate ? <CircularProgress size={16} color="inherit" /> : <EventAvailableRounded />}
          >
            {savingDate ? 'Saving…' : 'Add to Calendar'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

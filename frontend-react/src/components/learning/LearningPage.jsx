import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Button, Card, CardContent, Chip, CircularProgress, Dialog,
  DialogActions, DialogContent, DialogTitle, Divider, FormControl,
  Grid, IconButton, InputAdornment, InputLabel, LinearProgress,
  MenuItem, Paper, Radio, RadioGroup, FormControlLabel,
  Select, Stack, TextField, Typography, Alert, Tab, Tabs,
} from '@mui/material';
import {
  UploadRounded, SearchRounded, BookRounded, DeleteRounded,
  VisibilityRounded, EditRounded, DownloadRounded, AutoAwesomeRounded,
  FilterAltRounded, LabelRounded, CloudUploadRounded, SchoolRounded,
} from '@mui/icons-material';
import { documentsApi, modulesApi, summariesApi } from '../../api/client';
import { useApp } from '../../context/AppContext';
import ModuleManagementDialog from './ModuleManagementDialog';

// ─── Helpers ─────────────────────────────────────────────────────────────────
const fmtBytes = (n) => {
  if (!n) return '—';
  if (n < 1024) return `${n} B`;
  if (n < 1048576) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1048576).toFixed(1)} MB`;
};

const MOCK_DOCS = [
  { id: 1, title: 'Database Design Lecture 4', topic: 'Normalisation', type: 'PDF', pages: 24, size_bytes: 2400000, module: { id: 1, name: 'Database Systems' } },
  { id: 2, title: 'React Hooks Deep Dive', topic: 'useEffect, useMemo', type: 'PDF', pages: 18, size_bytes: 1800000, module: { id: 2, name: 'Web Development' } },
  { id: 3, title: 'ML Regression Algorithms', topic: 'Linear & Logistic', type: 'Word', pages: 32, size_bytes: 3100000, module: { id: 3, name: 'Machine Learning' } },
];
const MOCK_MODS = [
  { id: 1, name: 'Database Systems' }, { id: 2, name: 'Web Development' },
  { id: 3, name: 'Machine Learning' }, { id: 4, name: 'Computer Networks' },
];
const MOCK_SUMS = [
  { id: 1, title: 'DB Design Lecture 4 Summary', length: 'Medium', keywords: ['normalisation','3NF','entity','relation'], text: 'This summary covers database normalisation forms from 1NF to 3NF, entity relationships, and key constraints...', created_at: '2026-09-17' },
];

// ─── Upload Dialog ────────────────────────────────────────────────────────────
function UploadDialog({ open, onClose, modules, onUploaded }) {
  const [form, setForm] = useState({ title: '', topic: '', module_id: '', type: 'PDF' });
  const [file, setFile]       = useState(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError]     = useState('');
  const [dragOver, setDragOver] = useState(false);
  const fileRef = useRef(null);

  const reset = () => { setForm({ title: '', topic: '', module_id: '', type: 'PDF' }); setFile(null); setError(''); };

  const handleFile = (f) => {
    if (!f) return;
    setFile(f);
    if (!form.title) setForm((p) => ({ ...p, title: f.name.replace(/\.[^.]+$/, '') }));
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    handleFile(e.dataTransfer.files[0]);
  };

  const submit = async () => {
    if (!form.title.trim()) { setError('Document title is required.'); return; }
    setUploading(true);
    setError('');
    try {
      const fd = new FormData();
      Object.entries(form).forEach(([k, v]) => v && fd.append(k, v));
      if (file) fd.append('file', file);
      const res = await documentsApi.upload(fd);
      onUploaded(res.data);
      onClose();
      reset();
    } catch (err) {
      setError(err.response?.data?.message || 'Upload failed.');
    } finally {
      setUploading(false);
    }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle fontWeight={800}>Upload Document</DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 1 }}>
          {/* Dropzone */}
          <Box
            className={`dropzone ${dragOver ? 'active' : ''}`}
            sx={{ p: 4, textAlign: 'center' }}
            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={handleDrop}
            onClick={() => fileRef.current?.click()}
          >
            <input ref={fileRef} type="file" accept=".pdf,.doc,.docx,.txt" hidden onChange={(e) => handleFile(e.target.files[0])} />
            <CloudUploadRounded sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
            {file ? (
              <Typography fontWeight={700}>{file.name}</Typography>
            ) : (
              <>
                <Typography fontWeight={600}>Drop PDF, Word or TXT file here</Typography>
                <Typography variant="caption" color="text.secondary">or click to browse · max 20 MB</Typography>
              </>
            )}
          </Box>

          <TextField label="Document title" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} fullWidth required />
          <TextField label="Topic / Lecture name" value={form.topic} onChange={(e) => setForm((p) => ({ ...p, topic: e.target.value }))} fullWidth />
          <FormControl fullWidth>
            <InputLabel>Module</InputLabel>
            <Select value={form.module_id} label="Module" onChange={(e) => setForm((p) => ({ ...p, module_id: e.target.value }))}>
              <MenuItem value=""><em>No module</em></MenuItem>
              {modules.map((m) => <MenuItem key={m.id} value={m.id}>{m.name}</MenuItem>)}
            </Select>
          </FormControl>
          <RadioGroup row value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}>
            {['PDF', 'Word', 'Text'].map((t) => <FormControlLabel key={t} value={t} control={<Radio />} label={t} />)}
          </RadioGroup>

          {error && <Alert severity="error" sx={{ borderRadius: 2 }}>{error}</Alert>}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ p: 2.5 }}>
        <Button onClick={() => { onClose(); reset(); }}>Cancel</Button>
        <Button variant="contained" onClick={submit} disabled={uploading} startIcon={uploading ? <CircularProgress size={16} color="inherit" /> : <UploadRounded />}>
          {uploading ? 'Uploading…' : 'Upload'}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

// ─── Document Viewer Dialog ───────────────────────────────────────────────────
function DocViewerDialog({ doc, onClose }) {
  const [keywords, setKeywords] = useState([]);
  useEffect(() => {
    if (!doc?.id) return;
    documentsApi.keywords(doc.id).then((r) => setKeywords(r.data.keywords || [])).catch(() => {});
  }, [doc?.id]);

  if (!doc) return null;
  return (
    <Dialog open={Boolean(doc)} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle fontWeight={800}>{doc.title}</DialogTitle>
      <DialogContent>
        <Stack spacing={1.5} sx={{ mt: 1 }}>
          <Typography><strong>Module:</strong> {doc.module?.name || '—'}</Typography>
          <Typography><strong>Topic:</strong> {doc.topic || '—'}</Typography>
          <Typography><strong>Type:</strong> {doc.type} · {doc.pages} pages · {fmtBytes(doc.size_bytes)}</Typography>
          {keywords.length > 0 && (
            <>
              <Divider />
              <Typography fontWeight={700} display="flex" alignItems="center" gap={1}>
                <LabelRounded fontSize="small" color="primary" /> Keywords
              </Typography>
              <Box display="flex" flexWrap="wrap" gap={1}>
                {keywords.map((kw) => <Chip key={kw} label={kw} size="small" color="primary" variant="outlined" />)}
              </Box>
            </>
          )}
          {doc.extracted_text && (
            <>
              <Divider />
              <Typography fontWeight={700}>Extracted text (preview)</Typography>
              <Typography variant="body2" color="text.secondary" sx={{ maxHeight: 200, overflow: 'auto', fontFamily: 'JetBrains Mono', fontSize: '0.75rem', whiteSpace: 'pre-wrap' }}>
                {doc.extracted_text.substring(0, 800)}{doc.extracted_text.length > 800 ? '…' : ''}
              </Typography>
            </>
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ p: 2 }}>
        <Button onClick={onClose}>Close</Button>
      </DialogActions>
    </Dialog>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────
export default function LearningPage() {
  const [tab,      setTab]      = useState(0);
  const [docs,     setDocs]     = useState([]);
  const [modules,  setModules]  = useState([]);
  const [sums,     setSums]     = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [search,   setSearch]   = useState('');
  const [modFilter,setModFilter]= useState('');
  const [sumLength,setSumLength]= useState('Medium');
  const [selDocId, setSelDocId] = useState('');
  const [viewDoc,  setViewDoc]  = useState(null);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [modulesOpen, setModulesOpen] = useState(false);
  const [genLoading,setGenLoading] = useState(false);

  const load = useCallback(async () => {
    try {
      const [docsRes, modsRes, sumsRes] = await Promise.all([
        documentsApi.list(),
        modulesApi.list(),
        summariesApi.list(),
      ]);
      setDocs(docsRes.data);
      setModules(modsRes.data);
      setSums(sumsRes.data);
    } catch {
      setDocs(MOCK_DOCS);
      setModules(MOCK_MODS);
      setSums(MOCK_SUMS);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const filteredDocs = docs.filter((d) => {
    const t = search.toLowerCase();
    return (!t || d.title.toLowerCase().includes(t) || (d.topic || '').toLowerCase().includes(t))
        && (!modFilter || d.module?.id === Number(modFilter));
  });

  const generateSummary = async () => {
    if (!selDocId) return;
    setGenLoading(true);
    try {
      const res = await summariesApi.generate(selDocId, { length: sumLength });
      setSums((s) => [res.data, ...s]);
    } catch {
      // Offline fallback
      const doc = docs.find((d) => String(d.id) === String(selDocId));
      setSums((s) => [{
        id: Date.now(),
        title: `${doc?.title || 'Document'} – ${sumLength} Summary`,
        length: sumLength,
        keywords: ['concept','key','revision'],
        text: `This ${sumLength.toLowerCase()} summary of "${doc?.title}" covers the main concepts, key definitions, and provides a structured overview for revision purposes.`,
        created_at: new Date().toISOString().split('T')[0],
      }, ...s]);
    } finally {
      setGenLoading(false);
    }
  };

  const deleteSummary = async (id) => {
    try { await summariesApi.destroy(id); } catch {}
    setSums((s) => s.filter((x) => x.id !== id));
  };

  const downloadSummary = (sum) => {
    const blob = new Blob([`${sum.title}\n\n${sum.text}\n\nKeywords: ${(sum.keywords || []).join(', ')}`], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${sum.title.replace(/\s+/g, '_')}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const deleteDoc = async (id) => {
    try { await documentsApi.destroy(id); } catch {}
    setDocs((d) => d.filter((x) => x.id !== id));
  };

  if (loading) return <Box display="flex" alignItems="center" justifyContent="center" minHeight="50vh"><CircularProgress /></Box>;

  return (
    <Box className="page-enter">
      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3 }}>
        <Tab label="Documents" />
        <Tab label={`Summaries (${sums.length})`} />
      </Tabs>

      {tab === 0 && (
        <Grid container spacing={3}>
          {/* Document list */}
          <Grid item xs={12} lg={8}>
            <Card>
              <CardContent>
                <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={2} mb={2.5}>
                  <Typography variant="h6" fontWeight={800}>Learning materials</Typography>
                  <Stack direction="row" spacing={1} flexWrap="wrap">
                    <TextField
                      size="small" value={search} onChange={(e) => setSearch(e.target.value)}
                      placeholder="Search…"
                      InputProps={{ startAdornment: <InputAdornment position="start"><SearchRounded fontSize="small" /></InputAdornment> }}
                    />
                    <FormControl size="small" sx={{ minWidth: 160 }}>
                      <InputLabel>Module</InputLabel>
                      <Select value={modFilter} label="Module" onChange={(e) => setModFilter(e.target.value)}
                        startAdornment={<InputAdornment position="start"><FilterAltRounded fontSize="small" /></InputAdornment>}
                      >
                        <MenuItem value=""><em>All modules</em></MenuItem>
                        {modules.map((m) => <MenuItem key={m.id} value={m.id}>{m.name}</MenuItem>)}
                      </Select>
                    </FormControl>
                    <Button variant="outlined" startIcon={<SchoolRounded />} onClick={() => setModulesOpen(true)}>
                      Modules
                    </Button>
                    <Button variant="contained" startIcon={<UploadRounded />} onClick={() => setUploadOpen(true)}>
                      Upload
                    </Button>
                  </Stack>
                </Stack>

                {filteredDocs.length === 0 ? (
                  <Box textAlign="center" py={6}>
                    <BookRounded sx={{ fontSize: 48, color: 'text.secondary', mb: 1 }} />
                    <Typography color="text.secondary">No documents found.</Typography>
                  </Box>
                ) : (
                  <Stack spacing={1.5}>
                    {filteredDocs.map((doc) => (
                      <Paper key={doc.id} sx={{ p: 2, display: 'flex', alignItems: 'center', gap: 2, transition: 'all 0.2s ease', '&:hover': { boxShadow: 3 } }}>
                        <Box sx={{ width: 44, height: 44, borderRadius: 2, display: 'grid', placeItems: 'center', background: doc.type === 'PDF' ? '#dbeafe' : doc.type === 'Word' ? '#dcfce7' : '#fef3c7', color: doc.type === 'PDF' ? '#2563eb' : doc.type === 'Word' ? '#16a34a' : '#d97706', flexShrink: 0 }}>
                          <BookRounded fontSize="small" />
                        </Box>
                        <Box flex={1} minWidth={0}>
                          <Typography fontWeight={700} noWrap>{doc.title}</Typography>
                          <Typography variant="caption" color="text.secondary">
                            {doc.module?.name} · {doc.topic || '—'} · {doc.pages} pages · {fmtBytes(doc.size_bytes)}
                          </Typography>
                        </Box>
                        <Stack direction="row" spacing={0.5} flexShrink={0}>
                          <IconButton size="small" onClick={() => setViewDoc(doc)}><VisibilityRounded fontSize="small" /></IconButton>
                          <IconButton size="small" onClick={() => deleteDoc(doc.id)}><DeleteRounded fontSize="small" /></IconButton>
                        </Stack>
                      </Paper>
                    ))}
                  </Stack>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Summary generator */}
          <Grid item xs={12} lg={4}>
            <Card>
              <CardContent>
                <Stack direction="row" alignItems="center" spacing={1} mb={2.5}>
                  <AutoAwesomeRounded color="primary" />
                  <Typography variant="h6" fontWeight={800}>Summary generator</Typography>
                </Stack>

                <FormControl fullWidth sx={{ mb: 2 }}>
                  <InputLabel>Select document</InputLabel>
                  <Select value={selDocId} label="Select document" onChange={(e) => setSelDocId(e.target.value)}>
                    {docs.map((d) => <MenuItem key={d.id} value={d.id}>{d.title}</MenuItem>)}
                  </Select>
                </FormControl>

                <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">SUMMARY LENGTH</Typography>
                <RadioGroup row value={sumLength} onChange={(e) => setSumLength(e.target.value)} sx={{ mb: 2 }}>
                  {['Short', 'Medium', 'Detailed'].map((l) => <FormControlLabel key={l} value={l} control={<Radio size="small" />} label={l} />)}
                </RadioGroup>

                <Button
                  fullWidth variant="contained" onClick={generateSummary}
                  disabled={!selDocId || genLoading}
                  startIcon={genLoading ? <CircularProgress size={16} color="inherit" /> : <AutoAwesomeRounded />}
                >
                  {genLoading ? 'Generating…' : 'Generate summary'}
                </Button>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      {tab === 1 && (
        <Grid container spacing={3}>
          {sums.length === 0 ? (
            <Grid item xs={12}>
              <Box textAlign="center" py={8}>
                <AutoAwesomeRounded sx={{ fontSize: 48, color: 'text.secondary', mb: 1 }} />
                <Typography color="text.secondary">No summaries yet. Generate one from the Documents tab.</Typography>
              </Box>
            </Grid>
          ) : sums.map((sum) => (
            <Grid item xs={12} md={6} lg={4} key={sum.id}>
              <Card className="card-hover-lift">
                <CardContent>
                  <Stack direction="row" justifyContent="space-between" alignItems="flex-start" mb={1.5}>
                    <Typography fontWeight={800} lineHeight={1.3}>{sum.title}</Typography>
                    <Chip label={sum.length} size="small" color="primary" />
                  </Stack>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 2, display: '-webkit-box', WebkitLineClamp: 4, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                    {sum.text}
                  </Typography>
                  {(sum.keywords || []).length > 0 && (
                    <Box display="flex" flexWrap="wrap" gap={0.5} mb={2}>
                      {(sum.keywords || []).slice(0, 5).map((kw) => (
                        <Chip key={kw} label={kw} size="small" variant="outlined" />
                      ))}
                    </Box>
                  )}
                  <Stack direction="row" spacing={1}>
                    <IconButton size="small" onClick={() => downloadSummary(sum)} title="Download"><DownloadRounded fontSize="small" /></IconButton>
                    <IconButton size="small" onClick={() => deleteSummary(sum.id)} title="Delete"><DeleteRounded fontSize="small" /></IconButton>
                  </Stack>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}

      <UploadDialog open={uploadOpen} onClose={() => setUploadOpen(false)} modules={modules} onUploaded={(doc) => setDocs((d) => [doc, ...d])} />
      <ModuleManagementDialog open={modulesOpen} onClose={() => setModulesOpen(false)} modules={modules} onModulesChanged={setModules} />
      <DocViewerDialog doc={viewDoc} onClose={() => setViewDoc(null)} />
    </Box>
  );
}

/**
 * LearningScreen — BETHMI · Smart Notes & Document Management.
 *
 * Lists uploaded learning materials with search + module filter, and opens a
 * document's generated revision summary with its keywords. Live data comes from
 * GET /api/documents and GET /api/summaries; when the backend is unreachable the
 * bundled demo dataset is normalised into exactly the same shape.
 */
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { View, Text, TextInput, StyleSheet, RefreshControl, Pressable, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Screen from '../components/Screen';
import { Card, SectionTitle, Chip, Badge, Empty } from '../components/ui';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { colors, moduleAccent, spacing, radius } from '../theme';
import { demoDocuments, demoSummaries, demoModules } from '../data/demo';

const TYPE_ICON = { PDF: 'document-text', Word: 'document', Text: 'reader' };

/** Normalise an API document row to the shape the UI renders. */
function normaliseDoc(d) {
  return {
    id: d.id,
    title: d.title,
    module: d.module?.name || d.module_name || d.module || 'Unfiled',
    type: d.type || 'Text',
    pages: d.pages ?? 0,
    size: d.size || formatBytes(d.size_bytes),
    topic: d.topic || '',
    uploaded: String(d.uploaded || d.created_at || '').slice(0, 10),
  };
}

/** Normalise an API summary row (Summary model: title, length_type, body, keywords). */
function normaliseSummary(s) {
  return {
    id: s.id,
    docId: s.docId ?? s.document_id,
    title: s.title,
    length: s.length || s.length_type || 'Medium',
    keywords: Array.isArray(s.keywords) ? s.keywords : parseKeywords(s.keywords),
    text: s.text || s.body || '',
  };
}

const parseKeywords = (raw) => {
  if (!raw) return [];
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return String(raw).split(',').map((k) => k.trim()).filter(Boolean);
  }
};

function formatBytes(bytes) {
  if (!bytes) return '—';
  const kb = bytes / 1024;
  return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
}

export default function LearningScreen() {
  const { online } = useAuth();
  const [docs, setDocs] = useState([]);
  const [summaries, setSummaries] = useState([]);
  const [query, setQuery] = useState('');
  const [moduleFilter, setModuleFilter] = useState('All');
  const [selected, setSelected] = useState(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!online) {
      setDocs(demoDocuments);
      setSummaries(demoSummaries);
      return;
    }
    try {
      const [rawDocs, rawSummaries] = await Promise.all([api.documents(), api.summaries()]);
      setDocs(Array.isArray(rawDocs) ? rawDocs.map(normaliseDoc) : demoDocuments);
      setSummaries(Array.isArray(rawSummaries) ? rawSummaries.map(normaliseSummary) : demoSummaries);
    } catch (e) {
      setDocs(demoDocuments);
      setSummaries(demoSummaries);
    }
  }, [online]);

  useEffect(() => { load(); }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const modules = useMemo(() => ['All', ...Array.from(new Set(docs.map((d) => d.module)))], [docs]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return docs.filter((d) => {
      const matchesModule = moduleFilter === 'All' || d.module === moduleFilter;
      const matchesQuery =
        !q ||
        String(d.title).toLowerCase().includes(q) ||
        String(d.topic).toLowerCase().includes(q) ||
        String(d.module).toLowerCase().includes(q);
      return matchesModule && matchesQuery;
    });
  }, [docs, query, moduleFilter]);

  const summaryFor = (docId) => summaries.find((s) => String(s.docId) === String(docId)) || null;

  const stats = useMemo(() => {
    const totalPages = docs.reduce((sum, d) => sum + (d.pages || 0), 0);
    return { count: docs.length, pages: totalPages, summarised: summaries.length };
  }, [docs, summaries]);

  return (
    <Screen
      title="Learning Materials"
      subtitle="Bethmi · notes, documents & summaries"
      accent={colors.bethmi}
      icon="library"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.bethmi} />}
    >
      {/* Stats */}
      <Card>
        <View style={styles.statRow}>
          <StatCell value={stats.count} label="Documents" />
          <View style={styles.statDivider} />
          <StatCell value={stats.pages} label="Total pages" />
          <View style={styles.statDivider} />
          <StatCell value={stats.summarised} label="Summaries" />
        </View>
      </Card>

      {/* Search */}
      <View style={styles.searchWrap}>
        <Ionicons name="search" size={17} color={colors.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          value={query}
          onChangeText={setQuery}
          placeholder="Search title, topic or module…"
          placeholderTextColor={colors.textMuted}
        />
        {query ? (
          <Pressable onPress={() => setQuery('')} hitSlop={8}>
            <Ionicons name="close-circle" size={17} color={colors.textMuted} />
          </Pressable>
        ) : null}
      </View>

      {/* Module filter */}
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
        {modules.map((m) => (
          <Chip
            key={m}
            label={m}
            accent={colors.bethmi}
            active={moduleFilter === m}
            onPress={() => setModuleFilter(m)}
          />
        ))}
      </ScrollView>

      {/* Document list */}
      <SectionTitle accent={colors.bethmi}>
        {filtered.length} document{filtered.length === 1 ? '' : 's'}
      </SectionTitle>

      {filtered.length === 0 ? (
        <Empty title="No documents match" hint="Try a different search term or module filter." />
      ) : (
        filtered.map((d) => {
          const summary = summaryFor(d.id);
          const isOpen = selected === d.id;
          return (
            <Card key={d.id} accent={colors.bethmi} onPress={() => setSelected(isOpen ? null : d.id)}>
              <View style={styles.docRow}>
                <View style={styles.docIcon}>
                  <Ionicons name={TYPE_ICON[d.type] || 'document'} size={20} color={colors.bethmi} />
                </View>
                <View style={styles.docMain}>
                  <Text style={styles.docTitle} numberOfLines={2}>{d.title}</Text>
                  <View style={styles.docMetaRow}>
                    <Badge label={d.module} accent="bethmi" />
                    <Text style={styles.docMeta}>
                      {d.type} · {d.pages} pages · {d.size}
                    </Text>
                  </View>
                </View>
                <Ionicons
                  name={isOpen ? 'chevron-up' : 'chevron-down'}
                  size={17}
                  color={colors.textMuted}
                />
              </View>

              {isOpen ? (
                <View style={styles.detail}>
                  {d.topic ? (
                    <View style={styles.detailRow}>
                      <Ionicons name="pricetag-outline" size={13} color={colors.textMuted} />
                      <Text style={styles.detailText}>Topic: {d.topic}</Text>
                    </View>
                  ) : null}
                  {d.uploaded ? (
                    <View style={styles.detailRow}>
                      <Ionicons name="calendar-outline" size={13} color={colors.textMuted} />
                      <Text style={styles.detailText}>Uploaded {d.uploaded}</Text>
                    </View>
                  ) : null}

                  {summary ? (
                    <View style={styles.summaryBox}>
                      <View style={styles.summaryHead}>
                        <Ionicons name="sparkles" size={14} color={colors.bethmi} />
                        <Text style={styles.summaryTitle}>{summary.title}</Text>
                        <View style={styles.lengthPill}>
                          <Text style={styles.lengthText}>{summary.length}</Text>
                        </View>
                      </View>
                      <Text style={styles.summaryText}>{summary.text}</Text>
                      {summary.keywords.length ? (
                        <View style={styles.keywordRow}>
                          {summary.keywords.map((k) => (
                            <View key={k} style={styles.keyword}>
                              <Text style={styles.keywordText}>{k}</Text>
                            </View>
                          ))}
                        </View>
                      ) : null}
                    </View>
                  ) : (
                    <View style={styles.noSummary}>
                      <Ionicons name="information-circle-outline" size={14} color={colors.textMuted} />
                      <Text style={styles.noSummaryText}>
                        No revision summary generated for this document yet.
                      </Text>
                    </View>
                  )}
                </View>
              ) : null}
            </Card>
          );
        })
      )}
    </Screen>
  );
}

function StatCell({ value, label }) {
  return (
    <View style={styles.statCell}>
      <Text style={styles.statValue}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  statRow: { flexDirection: 'row', alignItems: 'center' },
  statCell: { flex: 1, alignItems: 'center' },
  statDivider: { width: 1, height: 30, backgroundColor: colors.border },
  statValue: { fontSize: 20, fontWeight: '800', color: colors.bethmi },
  statLabel: { fontSize: 10.5, color: colors.textMuted, marginTop: 2 },

  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.md,
    height: 44,
    marginBottom: spacing.sm,
  },
  searchIcon: { marginRight: spacing.sm },
  searchInput: { flex: 1, fontSize: 14, color: colors.text, paddingVertical: 0 },

  filterScroll: { flexGrow: 0, marginBottom: spacing.sm },

  docRow: { flexDirection: 'row', alignItems: 'center' },
  docIcon: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: `${colors.bethmi}14`,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  docMain: { flex: 1, marginRight: spacing.sm },
  docTitle: { fontSize: 14, fontWeight: '700', color: colors.text, lineHeight: 19 },
  docMetaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 5, flexWrap: 'wrap' },
  docMeta: { fontSize: 11, color: colors.textMuted, marginLeft: spacing.sm },

  detail: {
    marginTop: spacing.md,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  detailRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 5 },
  detailText: { fontSize: 11.5, color: colors.textMuted, marginLeft: 6 },

  summaryBox: {
    backgroundColor: `${colors.bethmi}0a`,
    borderRadius: radius.md,
    padding: spacing.md,
    marginTop: spacing.sm,
    borderLeftWidth: 3,
    borderLeftColor: colors.bethmi,
  },
  summaryHead: { flexDirection: 'row', alignItems: 'center', marginBottom: 6 },
  summaryTitle: { fontSize: 13, fontWeight: '800', color: colors.text, flex: 1, marginLeft: 6 },
  lengthPill: {
    backgroundColor: `${colors.bethmi}1f`,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: radius.pill,
  },
  lengthText: { fontSize: 10, fontWeight: '700', color: colors.bethmi },
  summaryText: { fontSize: 12.5, color: colors.text, lineHeight: 19 },

  keywordRow: { flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm },
  keyword: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: `${colors.bethmi}44`,
    borderRadius: radius.pill,
    paddingHorizontal: 8,
    paddingVertical: 3,
    marginRight: 6,
    marginBottom: 6,
  },
  keywordText: { fontSize: 10.5, fontWeight: '600', color: colors.bethmi },

  noSummary: { flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm },
  noSummaryText: { fontSize: 11.5, color: colors.textMuted, marginLeft: 6, flex: 1 },
});

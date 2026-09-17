/**
 * AssistantScreen — KAVISHKA · AI Academic Assistant.
 *
 * A grounded chatbot: every answer cites the academic document, section and page
 * it came from. Online, questions go to POST /api/assistant/chat (the Laravel
 * RetrievalService answers). Offline, src/api/retrieve.js runs the identical
 * scoring over the bundled knowledge base, so citations still appear.
 * Also lists the academic dates Kavishka extracts — the feed JITHMI's calendar
 * and the shared calendar consume.
 */
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View, Text, TextInput, StyleSheet, KeyboardAvoidingView, Platform,
  FlatList, Pressable, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Screen from '../components/Screen';
import { SectionTitle, Card, Badge } from '../components/ui';
import { api } from '../api/client';
import { answer as localAnswer, flattenKnowledgeBase } from '../api/retrieve';
import { daysUntil } from '../api/risk';
import { useAuth } from '../context/AuthContext';
import { colors, spacing, radius } from '../theme';
import { demoKnowledgeBase, demoAcademicDates, demoSuggestions, demoChatSeed } from '../data/demo';

const TYPE_COLOR = {
  Exam: colors.critical,
  Deadline: colors.jithmi,
  Milestone: colors.kavishka,
  Event: colors.pasindu,
};

export default function AssistantScreen() {
  const { online } = useAuth();
  const [messages, setMessages] = useState(demoChatSeed.map((m, i) => ({ ...m, id: `seed-${i}` })));
  const [input, setInput] = useState('');
  const [thinking, setThinking] = useState(false);
  const [conversationId, setConversationId] = useState(null);
  const [dates, setDates] = useState(demoAcademicDates);
  const [showDates, setShowDates] = useState(true);
  const listRef = useRef(null);

  const chunks = useMemo(() => flattenKnowledgeBase(demoKnowledgeBase), []);

  const loadDates = useCallback(async () => {
    if (!online) {
      setDates(demoAcademicDates);
      return;
    }
    try {
      const res = await api.academicDates();
      if (Array.isArray(res) && res.length) {
        setDates(
          res.map((d) => ({
            id: d.id,
            date: String(d.event_date || d.date || '').slice(0, 10),
            title: d.title,
            type: d.type || 'Event',
          })),
        );
      } else {
        setDates(demoAcademicDates);
      }
    } catch (e) {
      setDates(demoAcademicDates);
    }
  }, [online]);

  useEffect(() => { loadDates(); }, [loadDates]);

  useEffect(() => {
    if (listRef.current) {
      setTimeout(() => listRef.current?.scrollToEnd({ animated: true }), 80);
    }
  }, [messages, thinking]);

  async function send(text) {
    const question = (text ?? input).trim();
    if (!question || thinking) return;

    setInput('');
    setMessages((prev) => [...prev, { id: `u-${Date.now()}`, role: 'user', text: question, src: null }]);
    setThinking(true);

    let reply = null;
    let source = null;

    if (online) {
      try {
        const res = await api.chat(conversationId, question);
        if (res?.reply) {
          reply = res.reply;
          source = res.source || null;
          if (res.conversation_id) setConversationId(res.conversation_id);
        }
      } catch (e) {
        reply = null; // fall through to the local engine
      }
    }

    if (!reply) {
      const local = localAnswer(question, chunks);
      reply = local.answer;
      source = local.source;
    }

    setThinking(false);
    setMessages((prev) => [...prev, { id: `b-${Date.now()}`, role: 'bot', text: reply, src: source }]);
  }

  const upcoming = useMemo(
    () =>
      dates
        .map((d) => ({ ...d, days: daysUntil(d.date) }))
        .filter((d) => d.days >= 0)
        .sort((a, b) => a.days - b.days),
    [dates],
  );

  return (
    <Screen
      title="Academic Assistant"
      subtitle="Kavishka · grounded answers with sources"
      accent={colors.kavishka}
      icon="chatbubbles"
      scroll={false}
      right={
        <Pressable onPress={() => setShowDates((v) => !v)} style={styles.toggleBtn} hitSlop={8}>
          <Ionicons name={showDates ? 'calendar' : 'calendar-outline'} size={17} color="#fff" />
        </Pressable>
      }
    >
      {showDates && upcoming.length ? (
        <View style={styles.datesStrip}>
          <SectionTitle accent={colors.kavishka}>Upcoming academic dates</SectionTitle>
          <FlatList
            horizontal
            showsHorizontalScrollIndicator={false}
            data={upcoming}
            keyExtractor={(d) => String(d.id)}
            renderItem={({ item }) => (
              <View style={[styles.dateChip, { borderLeftColor: TYPE_COLOR[item.type] || colors.kavishka }]}>
                <Text style={styles.dateChipTitle} numberOfLines={1}>{item.title}</Text>
                <Text style={styles.dateChipMeta}>
                  {item.date} · {item.days === 0 ? 'today' : `in ${item.days}d`}
                </Text>
                <Badge label={item.type} accent="kavishka" />
              </View>
            )}
          />
        </View>
      ) : null}

      <KeyboardAvoidingView style={styles.chatArea} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <FlatList
          ref={listRef}
          data={messages}
          keyExtractor={(m) => m.id}
          contentContainerStyle={styles.chatContent}
          showsVerticalScrollIndicator={false}
          renderItem={({ item }) => <Bubble msg={item} />}
          ListHeaderComponent={
            messages.length <= 1 ? (
              <View style={styles.suggestWrap}>
                <Text style={styles.suggestTitle}>Try asking</Text>
                {demoSuggestions.map((s) => (
                  <Pressable key={s} style={styles.suggest} onPress={() => send(s)}>
                    <Ionicons name="chatbox-ellipses-outline" size={14} color={colors.kavishka} />
                    <Text style={styles.suggestText}>{s}</Text>
                  </Pressable>
                ))}
              </View>
            ) : null
          }
          ListFooterComponent={
            thinking ? (
              <View style={styles.thinkingRow}>
                <ActivityIndicator size="small" color={colors.kavishka} />
                <Text style={styles.thinkingText}>Searching your knowledge base…</Text>
              </View>
            ) : null
          }
        />

        <View style={styles.inputBar}>
          <TextInput
            style={styles.input}
            value={input}
            onChangeText={setInput}
            placeholder="Ask about handbooks, deadlines, regulations…"
            placeholderTextColor={colors.textMuted}
            multiline
            maxLength={2000}
            onSubmitEditing={() => send()}
            blurOnSubmit={false}
          />
          <Pressable
            style={[styles.sendBtn, (!input.trim() || thinking) && styles.sendBtnDisabled]}
            onPress={() => send()}
            disabled={!input.trim() || thinking}
          >
            <Ionicons name="send" size={17} color="#fff" />
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </Screen>
  );
}

function Bubble({ msg }) {
  const isUser = msg.role === 'user';
  return (
    <View style={[styles.bubbleRow, isUser ? styles.bubbleRowUser : styles.bubbleRowBot]}>
      {!isUser ? (
        <View style={styles.botAvatar}>
          <Ionicons name="sparkles" size={13} color={colors.kavishka} />
        </View>
      ) : null}

      <View style={[styles.bubble, isUser ? styles.bubbleUser : styles.bubbleBot]}>
        <Text style={[styles.bubbleText, isUser && styles.bubbleTextUser]}>{msg.text}</Text>

        {msg.src ? (
          <View style={styles.sourceBox}>
            <View style={styles.sourceHead}>
              <Ionicons name="bookmark" size={11} color={colors.kavishka} />
              <Text style={styles.sourceLabel}>Source</Text>
            </View>
            <Text style={styles.sourceText} numberOfLines={2}>
              {[msg.src.document, msg.src.section, msg.src.page ? `p. ${msg.src.page}` : null]
                .filter(Boolean)
                .join(' · ')}
            </Text>
            {msg.src.category ? <Badge label={msg.src.category} accent="kavishka" /> : null}
          </View>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  toggleBtn: {
    marginLeft: spacing.sm,
    width: 32,
    height: 32,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffffff26',
  },

  datesStrip: { paddingTop: spacing.lg, paddingHorizontal: spacing.lg },
  dateChip: {
    width: 190,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    marginRight: spacing.sm,
    borderLeftWidth: 4,
  },
  dateChipTitle: { fontSize: 12.5, fontWeight: '700', color: colors.text },
  dateChipMeta: { fontSize: 10.5, color: colors.textMuted, marginVertical: 5 },

  chatArea: { flex: 1 },
  chatContent: { padding: spacing.lg, paddingBottom: spacing.md },

  suggestWrap: { marginBottom: spacing.md },
  suggestTitle: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.sm,
  },
  suggest: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    marginBottom: spacing.sm,
    borderWidth: 1,
    borderColor: `${colors.kavishka}33`,
  },
  suggestText: { fontSize: 12.5, color: colors.text, marginLeft: spacing.sm, flex: 1 },

  thinkingRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.sm },
  thinkingText: { fontSize: 12, color: colors.textMuted, marginLeft: spacing.sm, fontStyle: 'italic' },

  bubbleRow: { flexDirection: 'row', marginBottom: spacing.md, alignItems: 'flex-end' },
  bubbleRowUser: { justifyContent: 'flex-end' },
  bubbleRowBot: { justifyContent: 'flex-start' },

  botAvatar: {
    width: 26,
    height: 26,
    borderRadius: 13,
    backgroundColor: `${colors.kavishka}1f`,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.sm,
  },

  bubble: { maxWidth: '82%', borderRadius: radius.lg, padding: spacing.md },
  bubbleUser: { backgroundColor: colors.kavishka, borderBottomRightRadius: 4 },
  bubbleBot: { backgroundColor: colors.surface, borderBottomLeftRadius: 4, borderWidth: 1, borderColor: colors.border },
  bubbleText: { fontSize: 13, lineHeight: 19, color: colors.text },
  bubbleTextUser: { color: '#fff' },

  sourceBox: {
    marginTop: spacing.sm,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  sourceHead: { flexDirection: 'row', alignItems: 'center', marginBottom: 3 },
  sourceLabel: {
    fontSize: 9.5,
    fontWeight: '800',
    color: colors.kavishka,
    marginLeft: 4,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  sourceText: { fontSize: 11, color: colors.textMuted, marginBottom: 5, lineHeight: 15 },

  inputBar: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    padding: spacing.md,
    backgroundColor: colors.surface,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  input: {
    flex: 1,
    maxHeight: 96,
    minHeight: 42,
    backgroundColor: colors.surfaceAlt,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    fontSize: 13.5,
    color: colors.text,
    marginRight: spacing.sm,
  },
  sendBtn: {
    width: 42,
    height: 42,
    borderRadius: radius.md,
    backgroundColor: colors.kavishka,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendBtnDisabled: { backgroundColor: colors.border },
});

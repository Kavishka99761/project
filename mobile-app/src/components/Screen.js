/**
 * Screen — shared scaffold for every module screen.
 *
 * Renders the coloured header (module identity + offline/live badge), an optional
 * subtitle, and a scrollable content area that respects the safe area. Using one
 * scaffold keeps padding, typography and header behaviour identical across the
 * four module dashboards.
 */
import React from 'react';
import { View, Text, StyleSheet, ScrollView, Pressable } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { colors, radius, spacing } from '../theme';
import { useAuth } from '../context/AuthContext';

export default function Screen({
  title,
  subtitle,
  accent = colors.primary,
  icon,
  children,
  scroll = true,
  right,
  refreshControl,
}) {
  const insets = useSafeAreaInsets();
  const { online, user } = useAuth();

  const body = scroll ? (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + spacing.xl }]}
      showsVerticalScrollIndicator={false}
      refreshControl={refreshControl}
      keyboardShouldPersistTaps="handled"
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.content, { paddingBottom: insets.bottom + spacing.md, flex: 1 }]}>{children}</View>
  );

  return (
    <View style={styles.root}>
      <View style={[styles.header, { paddingTop: insets.top + spacing.md, backgroundColor: accent }]}>
        <View style={styles.headerRow}>
          <View style={styles.headerLeft}>
            {icon ? (
              <View style={styles.iconWrap}>
                <Ionicons name={icon} size={20} color={accent} />
              </View>
            ) : null}
            <View style={styles.headerTexts}>
              <Text style={styles.title} numberOfLines={1}>
                {title}
              </Text>
              {subtitle ? (
                <Text style={styles.subtitle} numberOfLines={1}>
                  {subtitle}
                </Text>
              ) : null}
            </View>
          </View>

          <View style={styles.headerRight}>
            <View style={[styles.statusPill, { backgroundColor: online ? '#22c55e33' : '#ffffff2e' }]}>
              <View style={[styles.statusDot, { backgroundColor: online ? '#4ade80' : '#fca5a5' }]} />
              <Text style={styles.statusText}>{online ? 'Live API' : 'Offline demo'}</Text>
            </View>
            {right}
          </View>
        </View>
      </View>
      {body}
    </View>
  );
}

/** Small header button (used for logout / refresh). */
export function HeaderButton({ icon, onPress, label }) {
  return (
    <Pressable onPress={onPress} style={styles.headerBtn} hitSlop={8} accessibilityRole="button" accessibilityLabel={label}>
      <Ionicons name={icon} size={18} color="#fff" />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  header: { paddingHorizontal: spacing.lg, paddingBottom: spacing.lg },
  headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  headerLeft: { flexDirection: 'row', alignItems: 'center', flex: 1, marginRight: spacing.sm },
  iconWrap: {
    width: 34,
    height: 34,
    borderRadius: radius.md,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  headerTexts: { flex: 1 },
  title: { fontSize: 19, fontWeight: '800', color: '#fff' },
  subtitle: { fontSize: 12, color: '#ffffffcc', marginTop: 2 },
  headerRight: { flexDirection: 'row', alignItems: 'center' },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: radius.pill,
  },
  statusDot: { width: 6, height: 6, borderRadius: 3, marginRight: 5 },
  statusText: { fontSize: 10, fontWeight: '700', color: '#fff' },
  headerBtn: {
    marginLeft: spacing.sm,
    width: 32,
    height: 32,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffffff26',
  },
  scroll: { flex: 1 },
  content: { padding: spacing.lg },
});

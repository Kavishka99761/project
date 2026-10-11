/**
 * LoginScreen — Common Platform Layer.
 *
 * Works against the Laravel Sanctum /login endpoint when the backend is up, and
 * falls back to the bundled demo account when it is not, so the app can always be
 * demonstrated. Seeded credentials are shown as a hint in offline mode.
 */
import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { colors, radius, spacing, shadow } from '../theme';
import { demoUser } from '../data/demo';

const MODULES = [
  { key: 'bethmi', name: 'Bethmi', label: 'Smart Notes & Documents', color: colors.bethmi, icon: 'library' },
  { key: 'pasindu', name: 'Pasindu', label: 'Study & Engagement', color: colors.pasindu, icon: 'timer' },
  { key: 'kavishka', name: 'Kavishka', label: 'AI Academic Assistant', color: colors.kavishka, icon: 'chatbubbles' },
  { key: 'jithmi', name: 'Jithmi', label: 'Assignment Risk', color: colors.jithmi, icon: 'clipboard' },
];

export default function LoginScreen() {
  const insets = useSafeAreaInsets();
  const { login, busy, online } = useAuth();

  const [email, setEmail] = useState(demoUser.email);
  const [password, setPassword] = useState('password');
  const [showPass, setShowPass] = useState(false);
  const [error, setError] = useState(null);

  async function onSubmit() {
    setError(null);
    const result = await login(email, password);
    if (!result.ok) setError(result.message);
  }

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={[styles.content, { paddingTop: insets.top + spacing.xl }]}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        {/* Brand */}
        <View style={styles.brand}>
          <View style={styles.logo}>
            <Ionicons name="school" size={30} color="#fff" />
          </View>
          <Text style={styles.brandTitle}>AcadeAlert</Text>
          <Text style={styles.brandSub}>Your four-module academic companion</Text>
        </View>

        {/* Form */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Sign in</Text>

          <Text style={styles.label}>Email</Text>
          <View style={styles.inputWrap}>
            <Ionicons name="mail-outline" size={17} color={colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              value={email}
              onChangeText={setEmail}
              placeholder="you@university.lk"
              placeholderTextColor={colors.textMuted}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
            />
          </View>

          <Text style={styles.label}>Password</Text>
          <View style={styles.inputWrap}>
            <Ionicons name="lock-closed-outline" size={17} color={colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              value={password}
              onChangeText={setPassword}
              placeholder="••••••••"
              placeholderTextColor={colors.textMuted}
              secureTextEntry={!showPass}
              autoCapitalize="none"
            />
            <Pressable onPress={() => setShowPass((v) => !v)} hitSlop={8}>
              <Ionicons name={showPass ? 'eye-off-outline' : 'eye-outline'} size={18} color={colors.textMuted} />
            </Pressable>
          </View>

          {error ? (
            <View style={styles.errorBox}>
              <Ionicons name="alert-circle" size={15} color={colors.danger} />
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          <Pressable
            style={({ pressed }) => [styles.submit, pressed && { opacity: 0.9 }]}
            onPress={onSubmit}
            disabled={busy}
          >
            {busy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitText}>Sign in</Text>
            )}
          </Pressable>

          <View style={styles.hintBox}>
            <Ionicons name="information-circle-outline" size={15} color={colors.primary} />
            <Text style={styles.hintText}>
              {online
                ? 'Connected to the Laravel API.'
                : `Backend offline — demo mode. Use ${demoUser.email} / password`}
            </Text>
          </View>
        </View>

        {/* Module legend */}
        <Text style={styles.modulesTitle}>What's inside</Text>
        <View style={styles.moduleGrid}>
          {MODULES.map((m) => (
            <View key={m.key} style={[styles.moduleTile, { borderLeftColor: m.color }]}>
              <View style={[styles.moduleIcon, { backgroundColor: `${m.color}1a` }]}>
                <Ionicons name={m.icon} size={16} color={m.color} />
              </View>
              <Text style={styles.moduleName}>{m.name}</Text>
              <Text style={styles.moduleLabel} numberOfLines={2}>
                {m.label}
              </Text>
            </View>
          ))}
        </View>

        <Text style={styles.footer}>Web · Mobile · Laravel · MySQL</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  content: { padding: spacing.lg, paddingBottom: spacing.xl },

  brand: { alignItems: 'center', marginBottom: spacing.xl },
  logo: {
    width: 62,
    height: 62,
    borderRadius: 20,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
    ...shadow,
  },
  brandTitle: { fontSize: 26, fontWeight: '900', color: colors.text, letterSpacing: 1.5 },
  brandSub: { fontSize: 12.5, color: colors.textMuted, marginTop: 4 },

  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    ...shadow,
  },
  cardTitle: { fontSize: 17, fontWeight: '800', color: colors.text, marginBottom: spacing.md },
  label: { fontSize: 12, fontWeight: '600', color: colors.textMuted, marginBottom: 6, marginTop: spacing.sm },
  inputWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surfaceAlt,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.md,
    height: 46,
  },
  inputIcon: { marginRight: spacing.sm },
  input: { flex: 1, fontSize: 14.5, color: colors.text, paddingVertical: 0 },

  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ef444412',
    borderRadius: radius.sm,
    padding: spacing.sm + 2,
    marginTop: spacing.md,
  },
  errorText: { fontSize: 12.5, color: colors.danger, marginLeft: 6, flex: 1 },

  submit: {
    height: 48,
    borderRadius: radius.md,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.lg,
  },
  submitText: { color: '#fff', fontSize: 15, fontWeight: '700' },

  hintBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: `${colors.primary}0d`,
    borderRadius: radius.sm,
    padding: spacing.sm + 2,
    marginTop: spacing.md,
  },
  hintText: { fontSize: 11.5, color: colors.textMuted, marginLeft: 6, flex: 1, lineHeight: 16 },

  modulesTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.textMuted,
    marginTop: spacing.xl,
    marginBottom: spacing.sm,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  moduleGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
  moduleTile: {
    width: '48%',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    borderLeftWidth: 4,
    ...shadow,
  },
  moduleIcon: {
    width: 30,
    height: 30,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.sm,
  },
  moduleName: { fontSize: 14, fontWeight: '800', color: colors.text },
  moduleLabel: { fontSize: 11, color: colors.textMuted, marginTop: 2, lineHeight: 15 },

  footer: {
    textAlign: 'center',
    fontSize: 11,
    color: colors.textMuted,
    marginTop: spacing.md,
    letterSpacing: 0.4,
  },
});

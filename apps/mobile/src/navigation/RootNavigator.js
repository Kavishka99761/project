/**
 * RootNavigator — Common Platform Layer navigation.
 *
 * Unauthenticated → Login. Authenticated → bottom tabs, one per module plus the
 * shared home dashboard. Tab accents match each module's signature colour so the
 * mobile app mirrors the web frontend's module identity.
 */
import React from 'react';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, View, Text, StyleSheet } from 'react-native';

import { useAuth } from '../context/AuthContext';
import { colors } from '../theme';

import LoginScreen from '../screens/LoginScreen';
import DashboardScreen from '../screens/DashboardScreen';
import LearningScreen from '../screens/LearningScreen';
import StudyScreen from '../screens/StudyScreen';
import AssistantScreen from '../screens/AssistantScreen';
import AssignmentsScreen from '../screens/AssignmentsScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: colors.bg,
    card: colors.surface,
    text: colors.text,
    primary: colors.primary,
    border: colors.border,
  },
};

const TAB_ICONS = {
  Home: { on: 'home', off: 'home-outline' },
  Learning: { on: 'library', off: 'library-outline' },
  Study: { on: 'timer', off: 'timer-outline' },
  Assistant: { on: 'chatbubbles', off: 'chatbubbles-outline' },
  Assignments: { on: 'clipboard', off: 'clipboard-outline' },
};

const TAB_COLORS = {
  Home: colors.primary,
  Learning: colors.bethmi,
  Study: colors.pasindu,
  Assistant: colors.kavishka,
  Assignments: colors.jithmi,
};

function Tabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: TAB_COLORS[route.name] || colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarStyle: styles.tabBar,
        tabBarLabelStyle: styles.tabLabel,
        tabBarIcon: ({ color, size, focused }) => {
          const icon = TAB_ICONS[route.name] || TAB_ICONS.Home;
          return <Ionicons name={focused ? icon.on : icon.off} size={size} color={color} />;
        },
      })}
    >
      <Tab.Screen name="Home" component={DashboardScreen} options={{ title: 'Home' }} />
      <Tab.Screen name="Learning" component={LearningScreen} options={{ title: 'Learning' }} />
      <Tab.Screen name="Study" component={StudyScreen} options={{ title: 'Study' }} />
      <Tab.Screen name="Assistant" component={AssistantScreen} options={{ title: 'Assistant' }} />
      <Tab.Screen name="Assignments" component={AssignmentsScreen} options={{ title: 'Risk' }} />
    </Tab.Navigator>
  );
}

export default function RootNavigator() {
  const { user, booting } = useAuth();

  if (booting) {
    return (
      <View style={styles.boot}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.bootText}>AcadeAlert</Text>
      </View>
    );
  }

  return (
    <NavigationContainer theme={navTheme}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <Stack.Screen name="Tabs" component={Tabs} />
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  boot: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  bootText: { marginTop: 12, fontSize: 15, fontWeight: '700', color: colors.textMuted, letterSpacing: 1 },
  tabBar: { backgroundColor: colors.surface, borderTopColor: colors.border, height: 58, paddingBottom: 6, paddingTop: 4 },
  tabLabel: { fontSize: 10.5, fontWeight: '600' },
});

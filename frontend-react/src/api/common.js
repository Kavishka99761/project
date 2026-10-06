import client from './client';

// --- Dashboard (Common) ----------------------------------------------
export async function getDashboard() {
  const { data } = await client.get('/dashboard');
  return data;
}

// --- Calendar (Common \u2014 merges Kavishka + Jithmi) ------------------
export async function getCalendar(from, to) {
  const { data } = await client.get('/calendar', { params: { from, to } });
  return data;
}

// --- Modules (Common) ---------------------------------------------------
export async function listModules() {
  const { data } = await client.get('/modules');
  return data;
}
export async function createModule(payload) {
  const { data } = await client.post('/modules', payload);
  return data;
}
export async function updateModule(id, payload) {
  const { data } = await client.put(`/modules/${id}`, payload);
  return data;
}
export async function deleteModule(id) {
  await client.delete(`/modules/${id}`);
}

// --- Notifications (Common) --------------------------------------------
export async function listNotifications(unreadOnly = false) {
  const { data } = await client.get('/notifications', {
    params: unreadOnly ? { unread: 1 } : {},
  });
  return data;
}
export async function createNotification(payload) {
  const { data } = await client.post('/notifications', payload);
  return data;
}
export async function markAllNotificationsRead() {
  await client.post('/notifications/read-all');
}
export async function markNotificationRead(id) {
  const { data } = await client.patch(`/notifications/${id}`, { is_read: true });
  return data;
}
export async function deleteNotification(id) {
  await client.delete(`/notifications/${id}`);
}

// --- Read-only aggregation used by module placeholders + Settings export --
export async function listAssignments() {
  const { data } = await client.get('/assignments');
  return data;
}
export async function listDocuments() {
  const { data } = await client.get('/documents');
  return data;
}

import client, { setToken } from './client';

export async function login(email, password) {
  const { data } = await client.post('/login', { email, password });
  setToken(data.token);
  return data.user;
}

export async function register(payload) {
  const { data } = await client.post('/register', payload);
  setToken(data.token);
  return data.user;
}

export async function logout() {
  try {
    await client.post('/logout');
  } finally {
    setToken(null);
  }
}

export async function fetchMe() {
  const { data } = await client.get('/me');
  return data;
}

export async function updateMe(payload) {
  const { data } = await client.put('/me', payload);
  return data;
}

export async function forgotPassword(email) {
  const { data } = await client.post('/forgot-password', { email });
  return data;
}

export async function resetPassword(payload) {
  const { data } = await client.post('/reset-password', payload);
  return data;
}

export async function changePassword(payload) {
  const { data } = await client.post('/me/password', payload);
  return data;
}

export async function uploadAvatar(file) {
  const form = new FormData();
  form.append('avatar', file);
  const { data } = await client.post('/me/avatar', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export async function removeAvatar() {
  const { data } = await client.delete('/me/avatar');
  return data;
}

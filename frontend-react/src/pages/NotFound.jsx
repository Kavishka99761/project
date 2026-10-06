import React from 'react';
import { Box, Button, Typography } from '@mui/material';
import { useNavigate } from 'react-router-dom';

export default function NotFound() {
  const navigate = useNavigate();
  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 2 }}>
      <Typography variant="h2" fontWeight={800}>404</Typography>
      <Typography color="text.secondary">This page doesn't exist.</Typography>
      <Button variant="contained" onClick={() => navigate('/dashboard')}>Back to dashboard</Button>
    </Box>
  );
}

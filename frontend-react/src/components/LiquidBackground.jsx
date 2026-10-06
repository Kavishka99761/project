import React from 'react';
import { Box } from '@mui/material';
import { moduleColors } from '../theme';

/**
 * Decorative, purely visual background of soft blurred color blobs used
 * behind glass auth cards (Login/Register/Forgot/Reset). Pointer-events are
 * disabled so it never intercepts clicks.
 */
export default function LiquidBackground() {
  return (
    <Box className="edu-liquid-bg" aria-hidden="true">
      <Box
        className="edu-liquid-blob"
        sx={{
          width: 420,
          height: 420,
          top: '-8%',
          left: '-6%',
          background: moduleColors.primary,
        }}
      />
      <Box
        className="edu-liquid-blob"
        sx={{
          width: 380,
          height: 380,
          bottom: '-10%',
          right: '-8%',
          background: moduleColors.kavishka,
          animationDelay: '3s',
        }}
      />
      <Box
        className="edu-liquid-blob"
        sx={{
          width: 300,
          height: 300,
          top: '35%',
          right: '15%',
          background: moduleColors.bethmi,
          animationDelay: '6s',
          opacity: 0.35,
        }}
      />
    </Box>
  );
}

import React from 'react';
import { Box, Card, CardContent, Chip, Stack, Typography, alpha, useTheme } from '@mui/material';
import { motion } from 'framer-motion';
import ConstructionRoundedIcon from '@mui/icons-material/ConstructionRounded';

/**
 * Placeholder screen for the four feature modules (Bethmi/Pasindu/Kavishka/
 * Jithmi). Each module is owned end-to-end by its own team member and will
 * be wired to its already-documented API in a follow-up build; this session
 * focused on the shared Common Platform Layer that every module sits on top
 * of (auth, profile, navigation, notifications, calendar, search, settings).
 */
export default function ModulePlaceholder({ title, owner, color, icon, description, features }) {
  const theme = useTheme();
  const accent = theme.palette.mode === 'dark' ? color : color;

  return (
    <Box>
      <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 3 }}>
        <Box
          sx={{
            width: 56,
            height: 56,
            borderRadius: 3,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            bgcolor: alpha(accent, 0.15),
            color: accent,
          }}
        >
          {icon}
        </Box>
        <Box>
          <Typography variant="h5" fontWeight={800}>
            {title}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Owned by {owner} \u00b7 API contract already defined
          </Typography>
        </Box>
      </Stack>

      <Card
        component={motion.div}
        initial={{ opacity: 0, scale: 0.98 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.3 }}
        sx={{ borderLeft: `4px solid ${accent}`, mb: 3 }}
      >
        <CardContent>
          <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1.5 }}>
            <ConstructionRoundedIcon sx={{ color: accent }} fontSize="small" />
            <Typography variant="subtitle1" fontWeight={700}>
              Coming soon on the Common Platform Layer
            </Typography>
          </Stack>
          <Typography variant="body2" color="text.secondary">
            {description}
          </Typography>
        </CardContent>
      </Card>

      <Typography variant="subtitle2" sx={{ mb: 1.5 }} color="text.secondary">
        Planned capabilities
      </Typography>
      <Stack direction="row" flexWrap="wrap" gap={1}>
        {features.map((f) => (
          <Chip key={f} label={f} size="small" sx={{ bgcolor: alpha(accent, 0.1), color: accent }} />
        ))}
      </Stack>
    </Box>
  );
}

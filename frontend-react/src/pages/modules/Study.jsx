import React from 'react';
import TimerRoundedIcon from '@mui/icons-material/TimerRounded';
import ModulePlaceholder from '../../components/ModulePlaceholder';
import { moduleColors } from '../../theme';

export default function Study() {
  return (
    <ModulePlaceholder
      title="Study Session & Engagement"
      owner="Pasindu"
      color={moduleColors.pasindu}
      icon={<TimerRoundedIcon fontSize="large" />}
      description="Start, pause and stop focused study sessions per module/activity, monitor engagement, log concentration manually, and review daily/weekly study time, streaks and productivity trends."
      features={[
        'Study timer: start / pause / resume / stop',
        'Engagement monitoring & manual reporting',
        'Break & reminder recommendations',
        'Daily & weekly study time charts',
        'Planned vs. actual study time',
        'Personalised study-period suggestions',
      ]}
    />
  );
}

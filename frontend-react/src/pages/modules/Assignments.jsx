import React from 'react';
import AssignmentRoundedIcon from '@mui/icons-material/AssignmentRounded';
import ModulePlaceholder from '../../components/ModulePlaceholder';
import { moduleColors } from '../../theme';

export default function Assignments() {
  return (
    <ModulePlaceholder
      title="Assignment & Deadline Risk"
      owner="Jithmi"
      color={moduleColors.jithmi}
      icon={<AssignmentRoundedIcon fontSize="large" />}
      description="Track assignments with deadlines, priority and progress. A live risk score (Low/Medium/High/Critical) is recalculated as progress and deadlines change, with urgency ranking, a daily study plan and what-if scenarios."
      features={[
        'Add / edit / complete assignments',
        'Deadline-miss risk scoring (0\u2013100)',
        'Automatic priority ranking',
        'Daily study-hour recommendations',
        'What-if risk scenarios',
        'Overdue / completed history',
      ]}
    />
  );
}

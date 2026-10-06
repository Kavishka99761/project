import React from 'react';
import MenuBookRoundedIcon from '@mui/icons-material/MenuBookRounded';
import ModulePlaceholder from '../../components/ModulePlaceholder';
import { moduleColors } from '../../theme';

export default function Learning() {
  return (
    <ModulePlaceholder
      title="Smart Notes & Document Management"
      owner="Bethmi"
      color={moduleColors.bethmi}
      icon={<MenuBookRoundedIcon fontSize="large" />}
      description="Upload lecture notes (PDF/Word/text), extract and search their content, organise by module and topic, and generate short/medium/detailed AI summaries with keyword extraction and revision notes."
      features={[
        'Upload PDF / Word documents',
        'Extract text automatically',
        'Organise by module & topic',
        'Search & filter documents',
        'Short / Medium / Detailed summaries',
        'Keyword & key-concept extraction',
        'Save, download & delete summaries',
      ]}
    />
  );
}

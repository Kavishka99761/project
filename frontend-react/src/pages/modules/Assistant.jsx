import React from 'react';
import SmartToyRoundedIcon from '@mui/icons-material/SmartToyRounded';
import ModulePlaceholder from '../../components/ModulePlaceholder';
import { moduleColors } from '../../theme';

export default function Assistant() {
  return (
    <ModulePlaceholder
      title="AI Academic Assistant"
      owner="Kavishka"
      color={moduleColors.kavishka}
      icon={<SmartToyRoundedIcon fontSize="large" />}
      description="Ask natural-language questions about handbooks, project guidelines and regulations. Answers are grounded in retrieved passages with a visible source document, section and page \u2014 and academic dates are auto-extracted onto the shared calendar."
      features={[
        'Natural-language Q&A',
        'Retrieval-grounded answers (RAG)',
        'Source document / section / page citation',
        'Conversation history',
        'Automatic academic date extraction',
        'Deadlines & exams pushed to calendar',
      ]}
    />
  );
}

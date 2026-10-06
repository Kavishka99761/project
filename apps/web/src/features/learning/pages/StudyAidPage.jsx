import { Link, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/layout/PageHeader';
import { GlassCard } from '@/components/ui/GlassCard';
import { ErrorState, Skeleton } from '@/components/ui/States';
import { useToast } from '@/context/ToastContext';
import { useRecordAttempt, useStudyAid } from '../api';
import { Flashcards } from '../components/Flashcards';
import { MindMap } from '../components/MindMap';
import { QuizPlayer } from '../components/QuizPlayer';

export default function StudyAidPage() {
  const { id } = useParams();
  const toast = useToast();
  const { data: aid, isError, error, refetch } = useStudyAid(id);
  const record = useRecordAttempt();

  if (isError) return <ErrorState error={error} onRetry={refetch} />;
  if (!aid) return <Skeleton height={420} rounded={24} />;

  const finish = (score, total) => {
    record.mutate({ id: aid.id, score, total });
    toast.success('Practice recorded', `${score} / ${total}`);
  };

  return (
    <>
      <PageHeader
        module="learning"
        title={aid.title}
        subtitle={aid.document && <>Generated from <Link to={`/learning/documents/${aid.document.id}`}>{aid.document.title}</Link></>}
        actions={<Link to="/learning/study-aids" className="btn btn-glass"><i className="bi bi-arrow-left me-1" />All study aids</Link>}
      />
      <div className="mx-auto" style={{ maxWidth: aid.type === 'mindmap' ? 1100 : 760 }}>
        {aid.type === 'flashcards' && <Flashcards cards={aid.content} onFinish={finish} />}
        {aid.type === 'quiz' && <QuizPlayer questions={aid.content} onFinish={finish} />}
        {aid.type === 'mindmap' && <GlassCard variant="solid" className="p-3"><MindMap tree={aid.content} /></GlassCard>}
      </div>
    </>
  );
}

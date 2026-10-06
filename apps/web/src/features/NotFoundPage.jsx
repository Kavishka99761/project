import { Link } from 'react-router-dom';
import { GlassCard } from '@/components/ui/GlassCard';
import { EmptyState } from '@/components/ui/States';

export default function NotFoundPage() {
  return (
    <GlassCard className="p-4 mt-4">
      <EmptyState icon="signpost-split" title="This page does not exist" action={<Link to="/dashboard" className="btn btn-primary">Go to dashboard</Link>}>
        The link may be outdated, or the item was deleted. Deleted items can be restored from the trash.
      </EmptyState>
    </GlassCard>
  );
}

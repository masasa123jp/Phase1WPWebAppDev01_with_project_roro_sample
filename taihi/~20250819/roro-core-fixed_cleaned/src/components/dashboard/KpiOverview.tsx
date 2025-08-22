import ActiveUsersCard from './ActiveUsersCard';
import PaidUsersCard from './PaidUsersCard';
import AdSlot from '@/components/ads/AdSlot';

export default function KpiOverview() {
  return (
    <section className="grid gap-4 md:grid-cols-3">
      <ActiveUsersCard />
      <PaidUsersCard />
      <AdSlot adUnit="dashboard_top" />
    </section>
  );
}

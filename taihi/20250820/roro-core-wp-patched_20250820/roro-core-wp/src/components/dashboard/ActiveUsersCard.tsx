import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/apiClient';
import { useLocale } from '@/hooks/useLocale';

export default function ActiveUsersCard() {
  const t = useLocale();
  const { data } = useQuery(['kpi-active'], () =>
    api('/roro/v1/dashboard').then((r) => r.json())
  );

  return (
    <div className="card">
      <h3 className="card-title">{t('Active Users (30 days)')}</h3>
      <p className="card-value text-3xl">{data?.active_30d ?? '—'}</p>
    </div>
  );
}

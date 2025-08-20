import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/apiClient';
import { useLocale } from '@/hooks/useLocale';

export default function PaidUsersCard() {
  const t = useLocale();
  const { data } = useQuery(['kpi-rev'], () =>
    api('/roro/v1/dashboard').then((r) => r.json())
  );

  return (
    <div className="card">
      <h3 className="card-title">{t('Monthly Revenue')}</h3>
      <p className="card-value text-3xl">¥{data?.revenue_current.toLocaleString() ?? '—'}</p>
    </div>
  );
}

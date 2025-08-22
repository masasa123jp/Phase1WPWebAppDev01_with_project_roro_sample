import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';

export default function DashboardPage() {
  // 現在のユーザープロフィール
  const { data: profile } = useQuery({
    queryKey: ['me'],
    queryFn: () => apiClient.get('/me').then((r) => r.data),
  });
  // 管理者ダッシュボードKPI
  const { data: kpi } = useQuery({
    queryKey: ['dashboard-kpi'],
    queryFn: () => apiClient.get('/dashboard').then((r) => r.data),
  });
  return (
    <section>
      <h2>My Dashboard</h2>
      {profile ? (
        <>
          <p>Name: {profile.name}</p>
          <p>Email: {profile.email}</p>
          <p>Roles: {Array.isArray(profile.roles) ? profile.roles.join(', ') : ''}</p>
          {profile.customer_id && (
            <>
              <p>Customer ID: {profile.customer_id}</p>
              <p>Auth Provider: {profile.auth_provider}</p>
              <p>User Type: {profile.user_type}</p>
              <p>Consent Status: {profile.consent_status}</p>
            </>
          )}
        </>
      ) : (
        <p>Loading…</p>
      )}
      <hr />
      <h3>Site Metrics (last 30 days)</h3>
      {kpi ? (
        <ul>
          <li>Active Customers: {kpi.active_30d}</li>
          <li>Ad Click Rate: {kpi.ad_click_rate}</li>
          <li>Revenue (current month): ¥{kpi.revenue_current.toLocaleString()}</li>
        </ul>
      ) : (
        <p>Loading metrics…</p>
      )}
    </section>
  );
}

/**
 * 週次レポート履歴ページ – PDF ダウンロードに対応。
 * `/wp-json/roro/v1/report-history` から最新20件を取得。
 */
import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/apiClient';

export default function ReportsPage() {
  const { data, isLoading } = useQuery(['reports'], () =>
    api('/roro/v1/report-history')
  );

  if (isLoading) return <p>Loading…</p>;

  return (
    <table className="table">
      <thead><tr><th>週次</th><th>概要</th><th>PDF</th></tr></thead>
      <tbody>
        {data.map((r: any) => (
          <tr key={r.week}>
            <td>{r.week}</td>
            <td>{r.summary}</td>
            <td>
              <a href={r.pdf_url} className="link" download>
                ダウンロード
              </a>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/**
 * 犬種 × 年齢別の疾患リスク (仮) をレーダーチャートで表示。
 * WP REST `/roro/v1/breed-stats` から取得した JSON を描画する予定。
 */
import { useEffect, useState } from 'react';
import { Radar } from 'react-chartjs-2';
import { api } from '@/services/apiClient';

export default function ReportResultChart(props: { breed: string; ageMonth: number }) {
  const [chartData, setChartData] = useState<any>();

  useEffect(() => {
    api(`/roro/v1/breed-stats?breed=${props.breed}&age=${props.ageMonth}`)
      .then((d) => setChartData(d));
  }, [props.breed, props.ageMonth]);

  if (!chartData) return <p>Loading chart…</p>;

  return <Radar data={chartData} />;
}

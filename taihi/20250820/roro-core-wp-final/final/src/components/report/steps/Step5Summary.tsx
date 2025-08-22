/**
 * Step 5 – 入力内容の確認 + 送信ボタン。
 */
import { useFormContext } from 'react-hook-form';
import ReportResultChart from '../ReportResultChart';

export default function Step5Summary() {
  const { getValues, setValue } = useFormContext();
  const data = getValues();

  return (
    <section>
      <h2 className="text-xl font-bold mb-4">確認</h2>
      <pre className="bg-base-200 p-4 rounded-md text-sm mb-6">
        {JSON.stringify(data, null, 2)}
      </pre>

      {/* 簡易プレビュー – 犬種リスク可視化 */}
      <ReportResultChart breed={data.breed} ageMonth={data.age_month} />

      <div className="mt-6">
        <button type="button" onClick={() => setValue('step', 4)} className="btn">
          戻る
        </button>
        <button type="submit" className="btn btn-success ml-2">
          送信
        </button>
      </div>
    </section>
  );
}

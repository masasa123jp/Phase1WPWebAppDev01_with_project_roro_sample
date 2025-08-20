/**
 * Step 4 – 郵便番号で近隣施設提案に必要な位置情報を取得。
 */
import { useFormContext } from 'react-hook-form';

export default function Step4Location() {
  const { register, setValue } = useFormContext();

  return (
    <section>
      <h2 className="text-xl font-bold mb-4">お住まいの郵便番号</h2>

      <input
        type="text"
        pattern="\\d{3}-?\\d{4}"
        placeholder="例：160-0023"
        {...register('zipcode', { required: true })}
        className="input input-bordered w-48"
      />

      <div className="mt-6">
        <button type="button" onClick={() => setValue('step', 3)} className="btn">
          戻る
        </button>
        <button
          type="button"
          onClick={() => setValue('step', 5)}
          className="btn btn-primary ml-2"
        >
          次へ
        </button>
      </div>
    </section>
  );
}

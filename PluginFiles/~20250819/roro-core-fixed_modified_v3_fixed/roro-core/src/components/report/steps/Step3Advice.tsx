/**
 * Step 3 – 関心事（学びたいこと）を複数選択。
 * チェックしたタグで提案優先度を調整。
 */
import { useFormContext } from 'react-hook-form';

const tags = ['しつけ', '食事', '運動', 'シニアケア', '災害対策'] as const;

export default function Step3Advice() {
  const { register, setValue } = useFormContext();

  return (
    <section>
      <h2 className="text-xl font-bold mb-4">興味のあるテーマ</h2>
      <div className="flex flex-wrap gap-4">
        {tags.map((t) => (
          <label key={t} className="cursor-pointer">
            <input
              type="checkbox"
              value={t}
              {...register('topics')}
              className="checkbox mr-1"
            />
            {t}
          </label>
        ))}
      </div>

      <div className="mt-6">
        <button type="button" onClick={() => setValue('step', 2)} className="btn">
          戻る
        </button>
        <button
          type="button"
          onClick={() => setValue('step', 4)}
          className="btn btn-primary ml-2"
        >
          次へ
        </button>
      </div>
    </section>
  );
}

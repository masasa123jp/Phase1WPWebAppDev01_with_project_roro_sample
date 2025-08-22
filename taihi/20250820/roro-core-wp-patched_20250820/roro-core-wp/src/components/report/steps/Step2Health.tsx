/**
 * Step 2 – 健康状態・疾患履歴などを入力。
 */
import { useFormContext } from 'react-hook-form';

export default function Step2Health() {
  const { register, setValue } = useFormContext();

  return (
    <section>
      <h2 className="text-xl font-bold mb-4">健康情報</h2>

      <label className="block mb-2">
        体重 (kg)
        <input
          type="number"
          step="0.1"
          {...register('weight', { required: true })}
          className="input input-bordered w-40"
        />
      </label>

      <label className="block mb-4">
        既往症
        <textarea
          {...register('history')}
          className="textarea textarea-bordered w-full"
        />
      </label>

      <button type="button" onClick={() => setValue('step', 1)} className="btn">
        戻る
      </button>
      <button
        type="button"
        onClick={() => setValue('step', 3)}
        className="btn btn-primary ml-2"
      >
        次へ
      </button>
    </section>
  );
}

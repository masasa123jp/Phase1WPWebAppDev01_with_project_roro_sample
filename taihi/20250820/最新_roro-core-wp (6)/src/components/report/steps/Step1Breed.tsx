/**
 * Step 1 – 犬種と年齢入力。
 */
import { useFormContext } from 'react-hook-form';

export default function Step1Breed() {
  const { register, setValue } = useFormContext();

  return (
    <section>
      <h2 className="text-xl font-bold mb-4">犬種と年齢</h2>
      <label className="block mb-2">
        犬種
        <input
          {...register('breed', { required: true })}
          list="breed-master"
          className="input input-bordered w-full"
        />
        {/* datalist は CPT dog_breed から埋め込む予定 */}
      </label>

      <label className="block mb-4">
        年齢（月）
        <input
          type="number"
          {...register('age_month', { required: true, min: 1 })}
          className="input input-bordered w-40"
        />
      </label>

      <button
        type="button"
        onClick={() => setValue('step', 2)}
        className="btn btn-primary"
      >
        次へ
      </button>
    </section>
  );
}

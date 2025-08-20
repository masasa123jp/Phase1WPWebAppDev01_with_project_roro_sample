/**
 * 5 ステップの犬種別レポート作成ウィザード。
 * React‑Hook‑Form で状態管理し、最終的に `/wp-json/roro/v1/report` へ POST。
 */
import { useForm, FormProvider } from 'react-hook-form';
import Step1Breed     from './steps/Step1Breed';
import Step2Health    from './steps/Step2Health';
import Step3Advice    from './steps/Step3Advice';
import Step4Location  from './steps/Step4Location';
import Step5Summary   from './steps/Step5Summary';
import { api } from '@/services/apiClient';

export default function ReportWizard() {
  const methods = useForm({ mode: 'onBlur' });
  const { handleSubmit, watch } = methods;
  const step = watch('step') ?? 1;

  async function onSubmit(data: any) {
    await api('/roro/v1/report', { method: 'POST', body: JSON.stringify(data) });
    // TODO: Success toast & redirect
  }

  return (
    <FormProvider {...methods}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {step === 1 && <Step1Breed    />}
        {step === 2 && <Step2Health   />}
        {step === 3 && <Step3Advice   />}
        {step === 4 && <Step4Location />}
        {step === 5 && <Step5Summary  />}
      </form>
    </FormProvider>
  );
}

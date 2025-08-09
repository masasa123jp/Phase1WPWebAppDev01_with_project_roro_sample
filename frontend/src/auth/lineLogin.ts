import liff from '@line/liff';
import { api } from '@/libs/api';

/** LINE ログイン → WP セッション確立 */
export async function lineLogin() {
  await liff.init({ liffId: import.meta.env.VITE_LIFF_ID! });
  if (!liff.isLoggedIn()) liff.login();

  const accessToken = liff.getAccessToken();
  const res = await api('/roro/v1/auth/line', {
    method: 'POST',
    body: JSON.stringify({ accessToken })
  });
  if (!res.ok) throw new Error('LINE login failed');
  console.log('LINE login success');
}

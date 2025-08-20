/**
 * FCM WebPush 用のカスタムフック。
 * - Notification.permission をチェック
 * - FCM VAPID キーは `process.env.NEXT_PUBLIC_FCM_KEY`
 */
import { useState } from 'react';
import { getMessaging, getToken } from 'firebase/messaging';

export function useNotification() {
  const [ready, setReady] = useState(false);

  async function requestPermission() {
    if (Notification.permission !== 'granted') {
      await Notification.requestPermission();
    }
    const messaging = getMessaging();
    await getToken(messaging, {
      vapidKey: process.env.NEXT_PUBLIC_FCM_KEY,
    });
    setReady(true);
  }

  return { ready: Notification.permission === 'granted' && ready, requestPermission };
}

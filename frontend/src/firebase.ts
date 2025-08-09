import { initializeApp } from 'firebase/app';
import { getMessaging, getToken } from 'firebase/messaging';
import axios from 'axios';
import { useAuthStore } from './store/useAuthStore';

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  messagingSenderId: import.meta.env.VITE_FIREBASE_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
};
const vapid = import.meta.env.VITE_FIREBASE_VAPID;

const app = initializeApp(firebaseConfig);
export const messaging = getMessaging(app);

export async function registerFcmToken() {
  try {
    const currentToken = await getToken(messaging, { vapidKey: vapid });
    const { token } = useAuthStore.getState();
    if (currentToken && token) {
      await axios.post('/wp-json/roro/v1/push/token', { token: currentToken }, {
        headers: { Authorization: `Bearer ${token}` },
      });
    }
  } catch (e) {
    console.error('FCM token error', e);
  }
}

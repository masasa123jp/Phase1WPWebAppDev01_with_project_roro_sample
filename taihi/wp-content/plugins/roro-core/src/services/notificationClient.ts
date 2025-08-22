/**
 * LINE Messaging API と FCM を抽象化した送信クライアント。
 */
import axios from 'axios';

export async function sendLine(token: string, message: string) {
  return axios.post('https://api.line.me/v2/bot/message/push', {
    to: token,
    messages: [{ type: 'text', text: message }],
  }, {
    headers: { Authorization: `Bearer ${process.env.NEXT_PUBLIC_LINE_CHANNEL}` },
  });
}

export async function subscribeFCM(fcmToken: string) {
  return axios.post('/wp-json/roro/v1/fcm-token', { token: fcmToken });
}

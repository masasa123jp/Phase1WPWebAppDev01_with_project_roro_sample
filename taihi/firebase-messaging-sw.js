/* global self, firebase, importScripts */
/* Firebase WebPush service worker */

importScripts('https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.11.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: self.env.VITE_FIREBASE_API_KEY,
  appId: self.env.VITE_FIREBASE_APP_ID,
  messagingSenderId: self.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  projectId: 'roro-dev'
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  const { title, body, icon } = payload.notification;
  self.registration.showNotification(title, { body, icon });
});

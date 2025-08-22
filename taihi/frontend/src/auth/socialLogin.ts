import {
  getAuth,
  GoogleAuthProvider,
  FacebookAuthProvider,
  TwitterAuthProvider,
  OAuthProvider,
  signInWithPopup
} from 'firebase/auth';
import { api } from '@/libs/api';

/** 汎用ソーシャルログイン */
export async function socialLogin(provider: 'google' | 'facebook' | 'twitter' | 'microsoft' | 'yahoo') {
  const auth = getAuth();
  const prov = {
    google: new GoogleAuthProvider(),
    facebook: new FacebookAuthProvider(),
    twitter: new TwitterAuthProvider(),
    microsoft: new OAuthProvider('microsoft.com'),
    yahoo: new OAuthProvider('yahoo.com')
  }[provider];

  const { user } = await signInWithPopup(auth, prov);
  const idToken  = await user.getIdToken();

  await api('/roro/v1/auth/firebase', {
    method: 'POST',
    body: JSON.stringify({ idToken })
  });
}

import React, { useEffect, useState } from 'react';
import GachaWheel from './components/GachaWheel';
import liff from '@line/liff';
import axios from 'axios';

interface IUser {
  userId: string;
  displayName: string;
  pictureUrl: string;
}

export default function App() {
  const [profile, setProfile] = useState<IUser | null>(null);

  // LIFF 初期化
  useEffect(() => {
    liff
      .init({ liffId: import.meta.env.VITE_LIFF_ID as string })
      .then(async () => {
        if (!liff.isLoggedIn()) liff.login();
        const p = await liff.getProfile();
        setProfile(p);
      })
      .catch(console.error);
  }, []);

  const spin = async () => {
    // Phase 1.6: species と category を指定してガチャを回します。
    // UIから変更することも可能です。
    await axios.post('/wp-json/roro/v1/gacha', {
      species: 'dog',
      category: 'cafe',
    });
  };

  return (
    <div style={{ textAlign: 'center', marginTop: 40 }}>
      {profile && (
        <>
          <img src={profile.pictureUrl} width={80} style={{ borderRadius: '50%' }} />
          <h2>{profile.displayName}</h2>
        </>
      )}
      <GachaWheel onSpinEnd={spin} />
    </div>
  );
}

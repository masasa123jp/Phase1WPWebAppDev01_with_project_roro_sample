import React, { useState } from 'react';

export default function GachaWheel({ onSpinEnd }: { onSpinEnd: () => void }) {
  const [spinning, setSpinning] = useState(false);

  const spin = () => {
    if (spinning) return;
    setSpinning(true);
    // 回転アニメーションを 2 秒で終了
    setTimeout(() => {
      setSpinning(false);
      onSpinEnd();
      alert('🎉 おめでとう！アドバイスを獲得しました');
    }, 2000);
  };

  return (
    <button
      onClick={spin}
      style={{
        width: 200,
        height: 200,
        borderRadius: '50%',
        fontSize: 24,
        transform: spinning ? 'rotate(1080deg)' : undefined,
        transition: 'transform 2s cubic-bezier(.17,.67,.83,.67)',
      }}
    >
      {spinning ? 'Spinning…' : 'GACHA!'}
    </button>
  );
}

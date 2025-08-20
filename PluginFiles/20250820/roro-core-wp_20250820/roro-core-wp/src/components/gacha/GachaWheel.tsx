import { useState } from 'react';
import cls from 'clsx';
import '@/styles/gachaWheel.css';

interface Props {
  onSpinComplete: (result: { prize_type: string; item: Record<string, unknown> }) => void;
}

const SEGMENTS = ['cafe', 'hospital', 'salon', 'park', 'hotel', 'school', 'store'];

export default function GachaWheel({ onSpinComplete }: Props) {
  const [spinning, setSpinning] = useState(false);
  const [selected, setSelected] = useState<number | null>(null);

  async function spin() {
    if (spinning) return;
    setSpinning(true);

    // call backend
    const res = await fetch('/wp-json/roro/v1/gacha', { method: 'POST', credentials: 'include' });
    const data = await res.json();

    const idx = SEGMENTS.indexOf(data.prize_type === 'facility' ? data.item.category : 'cafe');
    setSelected(idx);

    setTimeout(() => {
      setSpinning(false);
      onSpinComplete(data);
    }, 4000);
  }

  return (
    <div className="relative flex flex-col items-center gap-4">
      <div className={cls('wheel', { spinning })} data-selected={selected}>
        {SEGMENTS.map((s, i) => (
          <span key={s} style={{ '--i': i } as React.CSSProperties}>
            {s}
          </span>
        ))}
      </div>
      <button onClick={spin} className="btn btn-primary min-w-[120px]" disabled={spinning}>
        {spinning ? '...' : 'Spin'}
      </button>
    </div>
  );
}

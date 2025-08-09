/**
 * 管理者が React ベース UI で Maps / AdSense Key を編集するフォーム。
 * WP の Settings API と REST `options` エンドポイントを利用。
 */
import { useState, useEffect } from 'react';
import { api } from '@/services/apiClient';

export default function ApiKeyForm() {
  const [data, setData] = useState<{maps_key:string, adsense_id:string}>({ maps_key:'', adsense_id:'' });
  const [status,setStatus] = useState<'idle'|'saving'>('idle');

  useEffect(() => {
    api('/wp/v2/settings').then((d) =>
      setData({ maps_key: d.roro_maps_key ?? '', adsense_id: d.roro_adsense_id ?? '' }),
    );
  }, []);

  async function save() {
    setStatus('saving');
    await api('/wp/v2/settings', {
      method: 'POST',
      body: JSON.stringify({
        roro_maps_key: data.maps_key,
        roro_adsense_id: data.adsense_id,
      }),
    });
    setStatus('idle');
  }

  return (
    <div className="space-y-4">
      <label className="block">
        Maps API Key
        <input
          className="input input-bordered w-full"
          value={data.maps_key}
          onChange={(e) => setData({ ...data, maps_key: e.target.value })}
        />
      </label>
      <label className="block">
        AdSense ID
        <input
          className="input input-bordered w-full"
          value={data.adsense_id}
          onChange={(e) => setData({ ...data, adsense_id: e.target.value })}
        />
      </label>
      <button onClick={save} className="btn btn-primary" disabled={status==='saving'}>
        {status==='saving' ? '保存中…':'保存'}
      </button>
    </div>
  );
}

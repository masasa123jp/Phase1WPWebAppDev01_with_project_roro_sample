/**
 * 画像アップロードフォーム。受領後 `/photo` エンドポイントへ送信。
 */
import { useState } from 'react';
import { api } from '@/services/apiClient';

export default function PhotoUploadForm() {
  const [file, setFile] = useState<File | null>(null);
  const [status, setStatus] = useState<'idle' | 'sending' | 'done'>('idle');

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;

    setStatus('sending');
    const body = new FormData();
    body.append('file', file);

    const res = await api('/roro/v1/photo', { method: 'POST', body });
    if (res.id) setStatus('done');
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <input
        type="file"
        accept="image/*"
        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        className="file-input w-full"
      />
      <button type="submit" className="btn btn-primary" disabled={status !== 'idle'}>
        {status === 'sending' ? '送信中…' : 'アップロード'}
      </button>
      {status === 'done' && <p className="text-success">アップロードしました！</p>}
    </form>
  );
}

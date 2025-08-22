/**
 * 通知チャネル（LINE / Email / WebPush）を切り替える UI。
 * - 現在の設定を GET `/wp-json/roro/v1/preferences`
 * - 保存は POST 同エンドポイント
 * - WebPush は FCM 登録トークン取得後に自動 ON
 */
import { useState, useEffect } from 'react';
import { api } from '@/services/apiClient';
import { useNotification } from '@/hooks/useNotification';

export default function NotificationSettings() {
  const [pref, setPref] = useState({ line: true, email: true, fcm: false });
  const [saving, setSaving] = useState(false);
  const { requestPermission, ready } = useNotification();

  /* 取得 */
  useEffect(() => {
    api('/roro/v1/preferences').then(setPref);
  }, []);

  async function save() {
    setSaving(true);
    await api('/roro/v1/preferences', {
      method: 'POST',
      body: JSON.stringify(pref),
    });
    setSaving(false);
  }

  return (
    <section className="space-y-4">
      <label className="flex items-center gap-2">
        <input type="checkbox" checked={pref.email}
          onChange={e => setPref({ ...pref, email: e.target.checked })} />
        Email 通知
      </label>

      <label className="flex items-center gap-2">
        <input type="checkbox" checked={pref.line}
          onChange={e => setPref({ ...pref, line: e.target.checked })} />
        LINE 通知
      </label>

      <label className="flex items-center gap-2">
        <input type="checkbox" checked={pref.fcm}
          onChange={async e => {
            if (e.target.checked && !ready) await requestPermission();
            setPref({ ...pref, fcm: e.target.checked });
          }}
        />
        ブラウザ通知 (FCM)
      </label>

      <button className="btn btn-primary" onClick={save} disabled={saving}>
        {saving ? '保存中…' : '保存'}
      </button>
    </section>
  );
}

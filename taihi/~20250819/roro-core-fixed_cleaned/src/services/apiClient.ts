/**
 * Simple fetch wrapper with WP nonce & cookie.
 */
export async function api(
  path: string,
  init: RequestInit = {}
): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set('Content-Type', 'application/json');
  const res = await fetch(`${import.meta.env.VITE_API_BASE}${path}`, {
    credentials: 'include',
    headers,
    ...init
  });
  if (!res.ok) {
    throw new Error(`API ${path} → ${res.status}`);
  }
  return res;
}

import { rest } from 'msw';

export const notificationHandlers = [
  rest.post('/wp-json/roro/v1/fcm-token', (_, res, ctx) =>
    res(ctx.status(200), ctx.json({ ok: true }))
  ),
];

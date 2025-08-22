/**********************************************************************
 *  フルパス: \Phase1WPWebAppDev01\app.js
 *---------------------------------------------------------------------
 *  目的   : Express アプリケーションのエントリポイント
 *  主な機能
 *    - セキュリティ: helmet / csurf / rate-limit
 *    - セッション & Cookie
 *    - i18n ミドルウェア
 *    - ルーティング (customers, touristSpots ほか)
 *    - エラー処理
 *********************************************************************/

require('dotenv').config(); // .env 読み込み（JWT_SECRET ほか）
const path = require('path');
const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const cookieParser = require('cookie-parser');
const csurf = require('csurf');
const morgan = require('morgan');
const bodyParser = require('body-parser');
const cors = require('cors');

const { i18nMiddleware } = require('./i18n'); // i18n.js で export
const { sequelize } = require('./models');   // DB 接続 (models/index.js)

// ルート定義
const customersRouter = require('./routes/customers');
const touristSpotsRouter = require('./routes/touristSpots');

// ─────────────────────────────────────────────────────────
// Express アプリ生成
// ─────────────────────────────────────────────────────────
const app = express();

// ── セキュリティ系 ──────────────────────────────────
app.use(helmet());
app.disable('x-powered-by'); // サーバ情報隠蔽
app.set('trust proxy', 1);   // もしリバースプロキシ越しの場合

// レートリミット (API 全体に 100req/15min)
app.use(
  '/api',
  rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 100,
  })
);

// ── CORS ─────────────────────────────────────────────
app.use(
  cors({
    origin: process.env.CORS_ORIGIN || '*',
    credentials: true,
  })
);

// ── ロギング ───────────────────────────────────────
app.use(morgan('dev'));

// ── ボディパーサ ──────────────────────────────────
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// ── Cookie & Session ────────────────────────────────
app.use(cookieParser());
app.use(
  session({
    secret: process.env.SESSION_SECRET || 'session-secret',
    resave: false,
    saveUninitialized: false,
    cookie: { secure: process.env.NODE_ENV === 'production', httpOnly: true },
  })
);

// ── i18n (言語検出: クエリ lang / Cookie / Accept-Language) ──
app.use(i18nMiddleware);

// ── CSRF ────────────────────────────────────────────
// SPA の場合 double-submit cookie 等を検討。ここでは HTML form 用に csurf。
app.use(
  csurf({
    cookie: true,
  })
);

// CSRF エラーを捕捉し、JSON レスポンスで返却
app.use((err, req, res, next) => {
  if (err.code !== 'EBADCSRFTOKEN') return next(err);
  return res.status(403).json({ message: req.t('error.csrf') });
});

// ── 静的ファイル ────────────────────────────────────
app.use('/public', express.static(path.join(__dirname, 'public')));

// ── API ルート ─────────────────────────────────────
app.use('/api/customers', customersRouter);
app.use('/api/tourist-spots', touristSpotsRouter);

// ヘルスチェック
app.get('/health', (req, res) => res.json({ status: 'ok' }));

// ── 404 ハンドラ ──────────────────────────────────
app.use((req, res) => {
  res.status(404).json({ message: req.t('error.notFoundPage') });
});

// ── エラーハンドラ (最後尾) ─────────────────────────
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(err.status || 500).json({
    message: err.message || 'Internal Server Error',
  });
});

// ── サーバ起動ヘルパ ──────────────────────────────
const PORT = process.env.PORT || 3000;
async function start() {
  try {
    await sequelize.authenticate();
    console.log('DB ✔  Connection established');

    app.listen(PORT, () => {
      console.log(`Server ✔  Running on http://localhost:${PORT}`);
    });
  } catch (err) {
    console.error('Failed to start server:', err);
    process.exit(1);
  }
}

if (require.main === module) {
  start();
}

module.exports = app; // テストで supertest が利用

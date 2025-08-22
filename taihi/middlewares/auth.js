/**********************************************************************
 *  フルパス: \Phase1WPWebAppDev01\middlewares\auth.js
 *---------------------------------------------------------------------
 *  目的   : JWT 認証とロールベース権限チェックを共通化するミドルウェア
 *  機能概要
 *    1) authenticate()
 *         - Authorization ヘッダー または cookie に含まれる JWT を検証
 *         - デコード結果からユーザ ID を取得し DB lookup（User, Role）
 *         - req.user にユーザオブジェクトを格納
 *    2) authorize(requiredRoles)
 *         - requiredRoles を満たすかチェック
 *         - 無権限なら 403 応答
 *
 *  備考
 *    - JWT_SECRET は .env で管理（例: JWT_SECRET="********"）
 *    - トークンは「Bearer <token>」形式 or cookie("token")
 *********************************************************************/

const jwt = require('jsonwebtoken');
const { User, Role } = require('../models'); // Sequelize インデックス (models/index.js) を想定

// ------------------------------------------------------------------
//   1) 認証ミドルウェア
// ------------------------------------------------------------------
exports.authenticate = async (req, res, next) => {
  try {
    // 1) Authorization ヘッダ優先
    let token = null;
    const authHeader = req.headers['authorization'];

    if (authHeader && authHeader.startsWith('Bearer ')) {
      token = authHeader.slice(7); // "Bearer " 以降
    } else if (req.cookies && req.cookies.token) {
      // 2) cookie に保存している場合
      token = req.cookies.token;
    }

    if (!token) {
      return res.status(401).json({ message: req.t('error.noAuth') });
    }

    // 3) JWT 検証
    const payload = jwt.verify(token, process.env.JWT_SECRET);

    // 4) DB からユーザ取得（ロールも JOIN）
    const user = await User.findByPk(payload.id, {
      include: [{ model: Role, through: { attributes: [] } }],
    });

    if (!user) {
      return res.status(401).json({ message: req.t('error.invalidUser') });
    }

    // 5) req.user に格納し次へ
    req.user = {
      id: user.id,
      username: user.username,
      roles: user.Roles.map((r) => r.name), // ['admin', 'editor', ...]
    };
    next();
  } catch (err) {
    // JWT 失敗や DB エラー
    console.error('Auth error:', err);
    return res.status(401).json({ message: req.t('error.invalidToken') });
  }
};

// ------------------------------------------------------------------
//   2) 権限チェックミドルウェア
//     引数は文字列 or 配列で OK 例) authorize('admin')  /  authorize(['admin','editor'])
// ------------------------------------------------------------------
exports.authorize = (requiredRoles) => {
  // 配列化
  const roles = Array.isArray(requiredRoles) ? requiredRoles : [requiredRoles];

  return (req, res, next) => {
    if (!req.user) {
      // authenticate を通過していない
      return res.status(500).json({ message: 'authorize() before authenticate()!' });
    }

    // ユーザロールと requiredRoles の交差判定
    const hasRole = req.user.roles.some((r) => roles.includes(r));
    if (!hasRole) {
      return res.status(403).json({ message: req.t('error.forbidden') });
    }
    next();
  };
};

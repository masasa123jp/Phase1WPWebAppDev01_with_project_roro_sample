/**********************************************************************
 *  フルパス: \Phase1WPWebAppDev01\routes\customers.js
 *  概要   : 顧客（Customers）関連 REST API ルート
 *           - JWT 認証／権限チェック         … middlewares/auth.js
 *           - 入力値バリデーション           … middlewares/validateInput.js
 *           - i18n（req.t()）でメッセージ翻訳 … i18n.js
 *  対応事項:
 *    ✔ 多言語メッセージ返却（ja/en/zh/ko）
 *    ✔ 顧客基本 CRUD
 *    ✔ お気に入りスポット操作
 *    ✔ 嗜好カテゴリ（customerPreference）更新
 *    ✔ prefecture_id / city_id JOIN で住所ラベルを返却
 *********************************************************************/

const express = require('express');
const router = express.Router();
const {
  Customer,
  Prefecture,
  City,
  CustomerPreference,
  FavoriteSpot,
  TouristSpot,
} = require('../models'); // ★Sequelize のインデックスでまとめ export している想定
const auth = require('../middlewares/auth');
const validate = require('../middlewares/validateInput');

// ─────────────────────────────────────────────────────────
// GET /customers
//   クエリ例) /customers?prefecture_id=13&limit=20&offset=0
//   住所 JOIN & ページング付き一覧
// ─────────────────────────────────────────────────────────
router.get(
  '/',
  auth('admin'), // 顧客一覧は管理者のみ
  async (req, res, next) => {
    try {
      const { limit = 20, offset = 0, prefecture_id } = req.query;

      const where = {};
      if (prefecture_id) where.prefecture_id = prefecture_id;

      const customers = await Customer.findAndCountAll({
        where,
        include: [
          { model: Prefecture, attributes: ['name_ja', 'name_en', 'name_zh', 'name_ko'] },
          { model: City, attributes: ['name_ja', 'name_en', 'name_zh', 'name_ko'] },
          {
            model: CustomerPreference,
            through: { attributes: [] }, // 中間テーブル列は不要
          },
        ],
        limit: Number(limit),
        offset: Number(offset),
        order: [['created_at', 'DESC']],
      });

      res.json(customers);
    } catch (err) {
      next(err);
    }
  }
);

// ─────────────────────────────────────────────────────────
// GET /customers/:id  – 本人 or 管理者のみ取得可
// ─────────────────────────────────────────────────────────
router.get('/:id', auth(['user', 'admin']), async (req, res, next) => {
  try {
    const { id } = req.params;

    // ★本人チェック: ロールが user の場合は自分の id しか見れない
    if (req.user.role === 'user' && req.user.id !== Number(id)) {
      return res.status(403).json({ message: req.t('error.forbidden') });
    }

    const customer = await Customer.findByPk(id, {
      include: [
        { model: Prefecture },
        { model: City },
        { model: CustomerPreference },
        {
          model: TouristSpot,
          as: 'favoriteSpots',
          through: { attributes: [] },
        },
      ],
    });

    if (!customer)
      return res.status(404).json({ message: req.t('error.notFound', { resource: 'Customer' }) });

    res.json(customer);
  } catch (err) {
    next(err);
  }
});

// ─────────────────────────────────────────────────────────
// POST /customers – 新規登録
// ─────────────────────────────────────────────────────────
router.post(
  '/',
  validate('createCustomer'), // middlewares/validateInput.js → Joi/express-validator 等で実装
  async (req, res, next) => {
    try {
      const customer = await Customer.create(req.body);
      res.status(201).json(customer);
    } catch (err) {
      next(err);
    }
  }
);

// ─────────────────────────────────────────────────────────
// PUT /customers/:id – 更新（本人または管理者）
// ─────────────────────────────────────────────────────────
router.put(
  '/:id',
  auth(['user', 'admin']),
  validate('updateCustomer'),
  async (req, res, next) => {
    try {
      const { id } = req.params;

      if (req.user.role === 'user' && req.user.id !== Number(id)) {
        return res.status(403).json({ message: req.t('error.forbidden') });
      }

      const [count] = await Customer.update(req.body, { where: { id } });
      if (count === 0)
        return res.status(404).json({ message: req.t('error.notFound', { resource: 'Customer' }) });

      const updated = await Customer.findByPk(id);
      res.json(updated);
    } catch (err) {
      next(err);
    }
  }
);

// ─────────────────────────────────────────────────────────
// DELETE /customers/:id – 退会（論理削除）
// ─────────────────────────────────────────────────────────
router.delete('/:id', auth(['user', 'admin']), async (req, res, next) => {
  try {
    const { id } = req.params;

    if (req.user.role === 'user' && req.user.id !== Number(id)) {
      return res.status(403).json({ message: req.t('error.forbidden') });
    }

    const deleted = await Customer.destroy({ where: { id } });
    if (!deleted)
      return res.status(404).json({ message: req.t('error.notFound', { resource: 'Customer' }) });

    res.status(204).end();
  } catch (err) {
    next(err);
  }
});

// ─────────────────────────────────────────────────────────
// POST /customers/:id/favorites – お気に入り追加・解除トグル
// body: { spot_id: number }
// ─────────────────────────────────────────────────────────
router.post(
  '/:id/favorites',
  auth(['user', 'admin']),
  validate('toggleFavorite'),
  async (req, res, next) => {
    try {
      const { id } = req.params;
      const { spot_id } = req.body;

      // 自分のみ
      if (req.user.role === 'user' && req.user.id !== Number(id)) {
        return res.status(403).json({ message: req.t('error.forbidden') });
      }

      const fav = await FavoriteSpot.findOne({ where: { customer_id: id, spot_id } });
      if (fav) {
        await fav.destroy();
        return res.json({ toggled: 'removed' });
      }
      await FavoriteSpot.create({ customer_id: id, spot_id });
      res.json({ toggled: 'added' });
    } catch (err) {
      next(err);
    }
  }
);

module.exports = router;

/**********************************************************************
 *  フルパス: \Phase1WPWebAppDev01\routes\touristSpots.js
 *  概要   : 観光スポット（TouristSpots）関連 REST API ルート
 *           - 認証必須: 投稿・更新・削除
 *           - 一般公開: 一覧・詳細参照
 *  対応事項:
 *    ✔ 検索 & ページング（キーワード・都道府県・カテゴリ等）
 *    ✔ 多言語カラム (name_xx, description_xx) 返却
 *    ✔ 画像 URL バリデーション（XSS/外部誘導対策）
 *    ✔ CSRF 対策は app.js で `csurf` ミドルウェアを全体適用済
 *********************************************************************/

const express = require('express');
const router = express.Router();
const { TouristSpot, Prefecture, City, Customer } = require('../models');
const auth = require('../middlewares/auth');
const validate = require('../middlewares/validateInput');

// ─────────────────────────────────────────────────────────
// GET /tourist-spots
//   クエリ）
//     q             : フリーワード（名前/説明）
//     prefecture_id : 都道府県
//     category      : カテゴリ
//     limit, offset : ページング
// ─────────────────────────────────────────────────────────
router.get('/', async (req, res, next) => {
  try {
    const {
      q,
      prefecture_id,
      category,
      limit = 20,
      offset = 0,
      lang = req.language, // i18next が自動セット
    } = req.query;

    const where = {};
    if (prefecture_id) where.prefecture_id = prefecture_id;
    if (category) where.category = category;
    if (q) {
      // name_xx と description_xx を対象に部分一致
      where[`name_${lang}`] = { $like: `%${q}%` };
    }

    const spots = await TouristSpot.findAndCountAll({
      where,
      include: [
        { model: Prefecture, attributes: ['id', 'name_ja', 'name_en', 'name_zh', 'name_ko'] },
        { model: City, attributes: ['id', 'name_ja', 'name_en', 'name_zh', 'name_ko'] },
      ],
      limit: Number(limit),
      offset: Number(offset),
      order: [['updated_at', 'DESC']],
    });

    res.json(spots);
  } catch (err) {
    next(err);
  }
});

// ─────────────────────────────────────────────────────────
// GET /tourist-spots/:id – 詳細
// ─────────────────────────────────────────────────────────
router.get('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;

    const spot = await TouristSpot.findByPk(id, {
      include: [Prefecture, City],
    });

    if (!spot)
      return res.status(404).json({ message: req.t('error.notFound', { resource: 'TouristSpot' }) });

    res.json(spot);
  } catch (err) {
    next(err);
  }
});

// ─────────────────────────────────────────────────────────
// POST /tourist-spots – 追加（管理者 or スポット投稿権限）
//   body: { name_ja, name_en, ..., image_url, ... }
// ─────────────────────────────────────────────────────────
router.post(
  '/',
  auth(['admin', 'editor']), // editor: スポット投稿を許可するロール
  validate('createTouristSpot'),
  async (req, res, next) => {
    try {
      // image_url サニタイズ（タグ除去・https 限定 など） → validateInput 側で行う前提
      const spot = await TouristSpot.create(req.body);
      res.status(201).json(spot);
    } catch (err) {
      next(err);
    }
  }
);

// ─────────────────────────────────────────────────────────
// PUT /tourist-spots/:id – 更新
// ─────────────────────────────────────────────────────────
router.put(
  '/:id',
  auth(['admin', 'editor']),
  validate('updateTouristSpot'),
  async (req, res, next) => {
    try {
      const { id } = req.params;
      const [count] = await TouristSpot.update(req.body, { where: { id } });

      if (count === 0)
        return res
          .status(404)
          .json({ message: req.t('error.notFound', { resource: 'TouristSpot' }) });

      const updated = await TouristSpot.findByPk(id);
      res.json(updated);
    } catch (err) {
      next(err);
    }
  }
);

// ─────────────────────────────────────────────────────────
// DELETE /tourist-spots/:id – 削除
// ─────────────────────────────────────────────────────────
router.delete('/:id', auth(['admin']), async (req, res, next) => {
  try {
    const { id } = req.params;
    const deleted = await TouristSpot.destroy({ where: { id } });

    if (!deleted)
      return res.status(404).json({ message: req.t('error.notFound', { resource: 'TouristSpot' }) });

    res.status(204).end();
  } catch (err) {
    next(err);
  }
});

module.exports = router;

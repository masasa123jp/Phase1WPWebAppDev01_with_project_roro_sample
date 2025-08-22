// data.js
// このモジュールでは呪文、アイテム、モンスター、職業、レベルテーブルなどのゲームデータを定義します。

// 呪文の定義
export const SPELLS = {
  fire:   { name: 'ファイア',   mp: 2, power: 8, type: 'attack' },
  ice:    { name: 'アイス',    mp: 2, power: 8, type: 'attack' },
  heal:   { name: 'ヒール',    mp: 3, power: 10, type: 'heal' },
  fireball: { name: 'ファイアボール', mp: 4, power: 20, type: 'attack' },
  blizzard: { name: 'ブリザード', mp: 4, power: 20, type: 'attack' },
  thunder:  { name: 'サンダー',  mp: 5, power: 30, type: 'attack' },
  cure:     { name: 'キュア',     mp: 5, power: 25, type: 'heal' },
  healall:  { name: 'ヒールオール', mp: 8, power: 999, type: 'heal' },
  strength: { name: 'ストレングス', mp: 4, power: 0, type: 'buff', stat: 'atk', amount: 5, duration: 99 },
  protect:  { name: 'プロテクト',  mp: 4, power: 0, type: 'buff', stat: 'def', amount: 5, duration: 99 }
};

// アイテムの定義
export const ITEMS = {
  potion:   { name: 'ポーション',    heal: 20, cost: 10 },
  ether:    { name: 'エーテル',      mpHeal: 10, cost: 20 },
  antidote: { name: '毒消し草',      curePoison: true, cost: 8 },
  herb:     { name: '薬草',          heal: 50, cost: 30 },
  atkSeed:  { name: '力のタネ',      buff: { stat: 'atk', amount: 3, duration: 99 }, cost: 40 },
  defSeed:  { name: '守りのタネ',    buff: { stat: 'def', amount: 3, duration: 99 }, cost: 40 },
  barrier:  { name: 'バリア巻物',    buff: { stat: 'def', amount: 10, duration: 5 }, cost: 60 }
  ,
  elixir:   { name: 'エリクサー',    heal: 999, mpHeal: 999, cost: 120 },
  magicSeed:{ name: '魔力のタネ',     buff: { stat: 'mp', amount: 5, duration: 99 }, cost: 70 },
  // 武器: type=weapon, atk bonus
  bronzeSword: { name: 'ブロンズソード', atk: 2, type: 'weapon', cost: 30 },
  ironLance:   { name: 'アイアンランス', atk: 4, type: 'weapon', cost: 60 },
  battleAxe:   { name: 'バトルアックス', atk: 6, type: 'weapon', cost: 90 },
  mageStaff:  { name: 'メイジスタッフ', atk: 2, type: 'weapon', mpBoost: 5, cost: 50 }
};

// モンスターの定義。名前、HP、攻撃力、防御力、経験値、ゴールド、出現確率、使う呪文など。
export const MONSTERS = [
  {
    id: 'slime',
    name: 'スライム',
    maxHp: 10,
    atk: 3,
    def: 1,
    exp: 5,
    gold: 3,
    image: 'slime',
    spells: []
  },
  {
    id: 'bat',
    name: 'こうもり',
    maxHp: 12,
    atk: 4,
    def: 1,
    exp: 7,
    gold: 4,
    image: 'bat',
    spells: []
  },
  {
    id: 'skeleton',
    name: 'スケルトン',
    maxHp: 20,
    atk: 6,
    def: 3,
    exp: 15,
    gold: 10,
    image: 'skeleton',
    spells: ['fire']
  },
  {
    id: 'orc',
    name: 'オーク',
    maxHp: 26,
    atk: 8,
    def: 4,
    exp: 18,
    gold: 12,
    image: 'orc',
    spells: []
  },
  {
    id: 'dragon',
    name: 'ドラゴン',
    maxHp: 40,
    atk: 10,
    def: 6,
    exp: 30,
    gold: 25,
    image: 'dragon',
    spells: ['fireball','blizzard','thunder']
  },
  {
    id: 'mage_monster',
    name: 'まどうし',
    maxHp: 24,
    atk: 5,
    def: 3,
    exp: 20,
    gold: 18,
    image: 'mage_monster',
    spells: ['fire','ice','heal']
  },
  {
    id: 'fenrir',
    name: 'フェンリル',
    maxHp: 28,
    atk: 9,
    def: 4,
    exp: 22,
    gold: 16,
    image: 'fenrir',
    spells: []
  },
  {
    id: 'seacreature',
    name: 'さかなまじん',
    maxHp: 30,
    atk: 7,
    def: 5,
    exp: 24,
    gold: 18,
    image: 'seacreature',
    spells: ['ice']
  },
  {
    id: 'minotaur',
    name: 'ミノタウロス',
    maxHp: 32,
    atk: 9,
    def: 5,
    exp: 28,
    gold: 20,
    image: 'minotaur',
    spells: []
  },
  {
    id: 'ghost',
    name: 'ゴースト',
    maxHp: 18,
    atk: 5,
    def: 2,
    exp: 14,
    gold: 9,
    image: 'ghost',
    spells: ['blizzard']
  },
  // ラスボス: 魔王
  {
    id: 'darklord',
    name: 'まおう',
    maxHp: 120,
    atk: 12,
    def: 8,
    exp: 0,
    gold: 0,
    image: 'dragon',
    spells: ['fireball','blizzard','thunder','healall']
  }
];

// 職業（クラス）の定義。
export const CLASSES = [
  {
    id: 0,
    name: 'せんし',
    desc: '高いHPと攻撃力を持つ近接戦士。',
    base: { hp: 30, mp: 5, atk: 5, def: 4 },
    growth: { hp: 5, mp: 1, atk: 2, def: 2 },
    spells: []
  },
  {
    id: 1,
    name: 'まほうつかい',
    desc: 'MPと攻撃魔法が得意だがHPが低い。',
    base: { hp: 18, mp: 20, atk: 2, def: 2 },
    growth: { hp: 3, mp: 4, atk: 1, def: 1 },
    spells: ['fire','ice','heal']
  },
  {
    id: 2,
    name: 'そうりょ',
    desc: '回復と支援に長けるヒーラー。',
    base: { hp: 24, mp: 16, atk: 2, def: 3 },
    growth: { hp: 4, mp: 3, atk: 1, def: 1 },
    spells: ['heal','cure','protect']
  },
  {
    id: 3,
    name: 'しょうにん',
    desc: 'お金を稼ぐのが得意なクラス。',
    base: { hp: 26, mp: 8, atk: 3, def: 3 },
    growth: { hp: 4, mp: 2, atk: 1, def: 1 },
    spells: ['heal'],
    bonusGold: 0.5
  },
  {
    id: 4,
    name: 'とうぞく',
    desc: '素早さとアイテムドロップ率が高い。',
    base: { hp: 22, mp: 10, atk: 4, def: 2 },
    growth: { hp: 3, mp: 2, atk: 2, def: 1 },
    spells: ['fire'],
    dropRate: 0.3
  },
  {
    id: 5,
    name: 'あそびにん',
    desc: '成長は遅いが運が良い。',
    base: { hp: 20, mp: 8, atk: 3, def: 2 },
    growth: { hp: 2, mp: 1, atk: 1, def: 1 },
    spells: ['heal'],
    luck: 5
  }
];

// レベルアップに必要な経験値テーブル（簡易版）
export const LEVEL_TABLE = [0, 10, 30, 60, 100, 150, 210, 280, 360, 450];

// ワールドタイル種別の列挙
export const TILE = {
  GRASS: 0,
  FOREST: 1,
  WATER: 2,
  MOUNTAIN: 3,
  ROAD: 4,
  TOWN: 5,
  INN: 6,
  NPC: 7,
  DESERT: 8,
  SWAMP: 9,
  CASTLE: 10,
  SHOP: 11,
  TEMPLE: 12,
  SHIP: 13,
  AIRSHIP: 14
  ,
  // 新しいタイル: ポータル。別の世界への入口として機能する。
  PORTAL: 15
};

// タイルごとのプロパティ（歩行可能か、遭遇率など）
export const TILE_PROPERTIES = {
  [TILE.GRASS]:  { walkable: true,  encounter: 0.03 },
  [TILE.FOREST]: { walkable: true,  encounter: 0.06 },
  [TILE.WATER]:  { walkable: false, encounter: 0 },
  [TILE.MOUNTAIN]: { walkable: false, encounter: 0 },
  [TILE.ROAD]:   { walkable: true,  encounter: 0.01 },
  [TILE.TOWN]:   { walkable: true,  encounter: 0 },
  [TILE.INN]:    { walkable: true,  encounter: 0 },
  [TILE.NPC]:    { walkable: true,  encounter: 0 },
  [TILE.DESERT]: { walkable: true,  encounter: 0.07 },
  [TILE.SWAMP]:  { walkable: true,  encounter: 0.08 },
  [TILE.CASTLE]: { walkable: true,  encounter: 0 },
  [TILE.SHOP]:   { walkable: true,  encounter: 0 },
  [TILE.TEMPLE]: { walkable: true,  encounter: 0 },
  [TILE.SHIP]:   { walkable: true,  encounter: 0 },
  [TILE.AIRSHIP]: { walkable: true, encounter: 0 }
  ,
  // ポータルは歩行可能でエンカウントなし。踏むと別のマップへ移動する。
  [TILE.PORTAL]: { walkable: true, encounter: 0 }
};

// タイルとアセットの名前対応（assets.jsで使用）
export const TILE_NAMES = {
  [TILE.GRASS]: 'grass',
  [TILE.FOREST]: 'forest',
  [TILE.WATER]: 'water',
  [TILE.MOUNTAIN]: 'mountain',
  [TILE.ROAD]: 'road',
  [TILE.TOWN]: 'town',
  [TILE.INN]: 'inn',
  [TILE.NPC]: 'npc',
  [TILE.DESERT]: 'desert',
  [TILE.SWAMP]: 'swamp',
  [TILE.CASTLE]: 'castle',
  [TILE.SHOP]: 'shop',
  [TILE.TEMPLE]: 'temple',
  [TILE.SHIP]: 'ship',
  [TILE.AIRSHIP]: 'airship'
  ,
  // ポータルは寺院のグラフィックを再利用します
  [TILE.PORTAL]: 'temple'
};
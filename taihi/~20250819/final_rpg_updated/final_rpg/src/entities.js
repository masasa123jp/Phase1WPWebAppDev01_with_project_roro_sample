// entities.js
// プレイヤーやモンスターなどのゲーム内キャラクターのクラスを定義します。

import { CLASSES, SPELLS, ITEMS, LEVEL_TABLE } from './data.js';

// バフ情報を表すクラス
class Buff {
  constructor(stat, amount, duration) {
    this.stat = stat;
    this.amount = amount;
    this.duration = duration; // 戦闘ターン数
  }
  tick() {
    this.duration--;
  }
  isExpired() {
    return this.duration <= 0;
  }
}

// プレイヤーキャラクター
export class Player {
  constructor(classId) {
    const cls = CLASSES[classId];
    this.classId = classId;
    this.name = cls.name;
    this.level = 1;
    this.exp = 0;
    this.hp = cls.base.hp;
    this.maxHp = cls.base.hp;
    this.mp = cls.base.mp;
    this.maxMp = cls.base.mp;
    this.atk = cls.base.atk;
    this.def = cls.base.def;
    this.spells = [...cls.spells];
    this.inventory = { potion: 1, ether: 1, antidote: 0, herb: 0, atkSeed: 0, defSeed: 0, barrier: 0 };
    this.gold = 0;
    this.buffs = [];
    this.poison = false;
    this.orbs = 0;
    this.questStage = 0;
    this.hasShip = false;
    this.hasAirship = false;
    // flags for battle buff resets
    // 装備中の武器キー（なしの場合は null）
    this.weapon = null;
    // ラスボス討伐フラグ
    this.finalBossDefeated = false;
  }

  // 現在の攻撃力・防御力（バフ込み）
  get atkTotal() {
    let bonus = 0;
    this.buffs.forEach(b => {
      if (b.stat === 'atk') bonus += b.amount;
    });
    // 武器攻撃力を加算
    const weaponAtk = this.weapon && ITEMS[this.weapon] && ITEMS[this.weapon].atk ? ITEMS[this.weapon].atk : 0;
    return this.atk + weaponAtk + bonus;
  }
  get defTotal() {
    let bonus = 0;
    this.buffs.forEach(b => {
      if (b.stat === 'def') bonus += b.amount;
    });
    return this.def + bonus;
  }

  // MP増加バフ合計
  get mpBuffAmount() {
    let bonus = 0;
    this.buffs.forEach(b => {
      if (b.stat === 'mp') bonus += b.amount;
    });
    return bonus;
  }

  // 現在の最大MP（バフ・装備込み）
  get maxMpTotal() {
    const weaponBoost = this.weapon && ITEMS[this.weapon] && ITEMS[this.weapon].mpBoost ? ITEMS[this.weapon].mpBoost : 0;
    return this.maxMp + weaponBoost + this.mpBuffAmount;
  }
  // レベルアップ処理
  levelUp() {
    const cls = CLASSES[this.classId];
    this.level++;
    this.maxHp += cls.growth.hp;
    this.maxMp += cls.growth.mp;
    this.atk += cls.growth.atk;
    this.def += cls.growth.def;
    this.hp = this.maxHp;
    this.mp = this.maxMp;
  }
  // 経験値を加算しレベルアップチェック
  addExp(amount) {
    this.exp += amount;
    while (this.level < LEVEL_TABLE.length && this.exp >= LEVEL_TABLE[this.level]) {
      this.levelUp();
    }
  }
  // ゴールド加算
  addGold(amount) {
    // merchant bonus
    const cls = CLASSES[this.classId];
    let bonus = amount;
    if (cls.bonusGold) bonus += amount * cls.bonusGold;
    this.gold += Math.floor(bonus);
  }
  // ダメージを受ける
  takeDamage(amount) {
    this.hp -= amount;
    if (this.hp < 0) this.hp = 0;
  }
  // 回復
  heal(amount) {
    this.hp += amount;
    if (this.hp > this.maxHp) this.hp = this.maxHp;
  }
  // MP回復
  restoreMp(amount) {
    this.mp += amount;
    // 武器やバフ込みの最大MPで上限
    const maxMp = this.maxMpTotal;
    if (this.mp > maxMp) this.mp = maxMp;
  }
  // 状態異常解除
  curePoison() {
    this.poison = false;
  }
  // バフの追加
  applyBuff(buff) {
    this.buffs.push(new Buff(buff.stat, buff.amount, buff.duration));
  }
  // 戦闘中に呼ばれる：バフの減衰処理
  tickBuffs() {
    this.buffs.forEach(b => b.tick());
    this.buffs = this.buffs.filter(b => !b.isExpired());
  }
  // アイテム使用
  useItem(key) {
    const item = ITEMS[key];
    if (!item || this.inventory[key] <= 0) return { message: 'アイテムがありません' };
    // consume
    this.inventory[key]--;
    let message = '';
    // 武器の場合は装備する
    if (item.type === 'weapon') {
      this.weapon = key;
      message = `${item.name}を装備した！`;
      // mpブーストがある武器の場合、現在MPの回復上限も調整
      // 現在MPが新しい最大MPを超えないよう調整
      const maxMp = this.maxMpTotal;
      if (this.mp > maxMp) this.mp = maxMp;
      return { message };
    }
    if (item.heal) {
      this.heal(item.heal);
      message = `${item.name}で${item.heal}回復した！`;
    }
    if (item.mpHeal) {
      this.restoreMp(item.mpHeal);
      message = `${item.name}でMPが${item.mpHeal}回復した！`;
    }
    if (item.curePoison) {
      this.curePoison();
      message = `${item.name}で毒が治った！`;
    }
    if (item.buff) {
      this.applyBuff(item.buff);
      message = `${item.name}で${item.buff.stat.toUpperCase()}が上がった！`;
    }
    return { message };
  }
  // 呪文使用
  castSpell(spellKey, target) {
    const spell = SPELLS[spellKey];
    if (!spell) return { message: '呪文を知らない' };
    if (this.mp < spell.mp) return { message: 'MPが足りない！' };
    this.mp -= spell.mp;
    let message = '';
    if (spell.type === 'attack') {
      const dmg = spell.power + Math.floor(Math.random() * 4);
      target.takeDamage(dmg);
      message = `${spell.name}を唱えた！敵に${dmg}のダメージ！`;
    } else if (spell.type === 'heal') {
      this.heal(spell.power === 999 ? this.maxHp : spell.power);
      message = `${spell.name}で回復した！`;
    } else if (spell.type === 'buff') {
      this.applyBuff({ stat: spell.stat, amount: spell.amount, duration: spell.duration });
      message = `${spell.name}で${spell.stat.toUpperCase()}を上げた！`;
    }
    return { message };
  }
}

// 敵モンスター
export class Monster {
  constructor(def) {
    this.id = def.id;
    this.name = def.name;
    this.maxHp = def.maxHp;
    this.hp = def.maxHp;
    this.atk = def.atk;
    this.def = def.def;
    this.exp = def.exp;
    this.gold = def.gold;
    this.spells = def.spells || [];
    this.image = def.image;
  }
  isAlive() {
    return this.hp > 0;
  }
  takeDamage(amount) {
    this.hp -= amount;
    if (this.hp < 0) this.hp = 0;
  }
}
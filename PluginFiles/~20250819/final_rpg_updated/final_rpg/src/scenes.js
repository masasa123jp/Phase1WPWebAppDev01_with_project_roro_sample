// scenes.js
// さまざまなシーン（タイトル、クラス選択、フィールド、戦闘など）を定義します。

import { CLASSES, SPELLS, ITEMS, MONSTERS, TILE, TILE_PROPERTIES, TILE_NAMES } from './data.js';
import { Player, Monster } from './entities.js';
import { audioManager } from './audio.js';
import { generateWorld, canWalk } from './world.js';

// ユーティリティ：文字列を行単位に折り返す
function wrapText(text, width) {
  const words = text.split(/\s+/);
  let lines = [];
  let current = '';
  words.forEach(word => {
    if ((current + word).length > width) {
      lines.push(current.trim());
      current = word + ' ';
    } else {
      current += word + ' ';
    }
  });
  if (current.trim()) lines.push(current.trim());
  return lines;
}

// 基底シーン
export class Scene {
  constructor(game) {
    this.game = game;
  }
  update(dt) {}
  draw(ctx) {}
  onKeyDown(key) {}
}

// 安全にlocalStorageへアクセスするユーティリティ
function safeGet(key) {
  try {
    return window.localStorage ? localStorage.getItem(key) : null;
  } catch (e) {
    return null;
  }
}
function safeSet(key, value) {
  try {
    if (window.localStorage) localStorage.setItem(key, value);
  } catch (e) {
    // ignore
  }
}

// タイトルシーン
export class TitleScene extends Scene {
  constructor(game) {
    super(game);
    this.options = ['ニューゲーム', 'つづきから'];
    this.selected = 0;
    // タイトル画面ではBGMはユーザー操作後に再生します
    audioManager.stopBgm();
  }
  update(dt) {
    // 初めての入力でタイトルBGMを開始
    if (!audioManager.currentTuneName && Object.keys(this.game.keysDown).length > 0) {
      audioManager.playBgm('title');
    }
    // 入力処理
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.selected = (this.selected + this.options.length - 1) % this.options.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.selected = (this.selected + 1) % this.options.length;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      audioManager.beep(880, 0.1);
      const choice = this.options[this.selected];
      if (choice === 'ニューゲーム') {
        this.game.replaceScene(new ClassSelectionScene(this.game));
      } else if (choice === 'つづきから') {
        const saved = safeGet('sfc_rpg_save');
        if (saved) {
          const data = JSON.parse(saved);
          const player = Object.assign(new Player(0), data.player);
          // restore dynamic fields like buffs as empty
          player.buffs = [];
          this.game.replaceScene(new FieldScene(this.game, data.world, player));
        }
      }
    }
  }
  draw(ctx) {
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, this.game.width, this.game.height);
    ctx.fillStyle = '#fff';
    ctx.font = '20px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('SFC風RPG Pixel', this.game.width / 2, 60);
    ctx.font = '14px monospace';
    this.options.forEach((opt, i) => {
      ctx.fillStyle = i === this.selected ? '#ff0' : '#fff';
      ctx.fillText(opt, this.game.width / 2, 120 + i * 24);
    });
    // セーブデータが無ければ続きからをグレーアウト
    const saved = safeGet('sfc_rpg_save');
    if (!saved) {
      ctx.fillStyle = '#555';
      ctx.fillText('つづきから', this.game.width / 2, 120 + 1 * 24);
    }

    // 操作方法を画面下部に表示してプレイヤーにガイドを提供
    ctx.font = '10px monospace';
    ctx.fillStyle = '#888';
    ctx.textAlign = 'center';
    ctx.fillText('矢印キー: 移動    Z/Enter: 決定    X/Esc: キャンセル    M: メニュー', this.game.width / 2, this.game.height - 10);
  }
}

// クラス選択シーン
export class ClassSelectionScene extends Scene {
  constructor(game) {
    super(game);
    this.selected = 0;
  }
  update(dt) {
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.selected = (this.selected + CLASSES.length - 1) % CLASSES.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.selected = (this.selected + 1) % CLASSES.length;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      audioManager.beep(880, 0.1);
      const clsId = this.selected;
      const player = new Player(clsId);
      const world = generateWorld();
      this.game.replaceScene(new FieldScene(this.game, world, player));
    }
  }
  draw(ctx) {
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, this.game.width, this.game.height);
    ctx.fillStyle = '#fff';
    ctx.font = '18px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('職業を選んでください', this.game.width / 2, 40);
    ctx.font = '14px monospace';
    CLASSES.forEach((cls, i) => {
      ctx.fillStyle = i === this.selected ? '#ff0' : '#fff';
      ctx.fillText(`${cls.name}`, this.game.width / 2, 80 + i * 20);
    });
    // 説明文
    const desc = CLASSES[this.selected].desc;
    ctx.fillStyle = '#ccc';
    ctx.font = '12px monospace';
    ctx.textAlign = 'left';
    const lines = wrapText(desc, 20);
    lines.forEach((line, j) => {
      ctx.fillText(line, 20, 180 + j * 16);
    });
  }
}

// 汎用ウィンドウ描画関数。DQ3風に濃い背景と白枠で描画します。
function drawWindow(ctx, x, y, w, h) {
  // 背景をやや透過の黒で塗る
  ctx.fillStyle = 'rgba(0,0,0,0.9)';
  ctx.fillRect(x, y, w, h);
  // 白枠を太めに描く
  ctx.strokeStyle = '#fff';
  ctx.lineWidth = 2;
  ctx.strokeRect(x + 1, y + 1, w - 2, h - 2);
  // 内側に薄い枠を描き込むことでSFC風の立体感を出す
  ctx.strokeStyle = 'rgba(255,255,255,0.3)';
  ctx.lineWidth = 1;
  ctx.strokeRect(x + 3, y + 3, w - 6, h - 6);
}

// メッセージ表示ユーティリティ：下部のウィンドウにテキストを表示
function drawMessageWindow(ctx, message) {
  const width = ctx.canvas.width;
  const height = ctx.canvas.height;
  const lines = wrapText(message, 30);
  // ウィンドウ高さは行数に応じて調整（最低2行分）
  const winH = Math.max(32 + lines.length * 16, 48);
  drawWindow(ctx, 0, height - winH, width, winH);
  ctx.fillStyle = '#fff';
  ctx.font = '12px monospace';
  ctx.textAlign = 'left';
  lines.forEach((line, i) => {
    ctx.fillText(line, 4, height - winH + 16 + i * 16);
  });
}

// フィールドシーン
export class FieldScene extends Scene {
  constructor(game, world, player) {
    super(game);
    this.world = world;
    this.player = player;
    this.x = world.startX;
    this.y = world.startY;
    this.timeCount = 0;
    // 一日の長さ（歩数ベース）
    this.dayLength = 200;
    this.message = null;
    this.showingMessage = false;
    this.messageTimer = 0;
    // アニメーション用フレームカウンタ：0=立ち、1=歩行
    this.walkFrame = 0;
    // フィールドBGMを開始
    audioManager.stopBgm();
    audioManager.playBgm('field');
  }
  update(dt) {
    // フィールドBGMが止まっている場合は再開（戦闘から戻ったとき）
    if (audioManager.currentTuneName !== 'field') {
      audioManager.playBgm('field');
    }
    // メッセージ表示中はボタンで閉じる
    if (this.showingMessage) {
      if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
        this.showingMessage = false;
        this.message = null;
      }
      return;
    }
    // 毒状態で歩く度にダメージ
    if (this.player.poison) {
      this.player.takeDamage(1);
    }
    // 移動
    let moved = false;
    let nx = this.x;
    let ny = this.y;
    if (this.game.isKeyJustPressed('ArrowLeft')) { nx--; moved = true; }
    if (this.game.isKeyJustPressed('ArrowRight')) { nx++; moved = true; }
    if (this.game.isKeyJustPressed('ArrowUp')) { ny--; moved = true; }
    if (this.game.isKeyJustPressed('ArrowDown')) { ny++; moved = true; }
    if (moved) {
      if (canWalk(this.world.map, nx, ny) || this.player.hasShip || this.player.hasAirship) {
        this.x = nx;
        this.y = ny;
        audioManager.beep(440, 0.05);
        this.timeCount++;
        // 移動したらフレームを切り替え
        this.walkFrame = 1 - this.walkFrame;
        // ランダムエンカウント
        const tile = this.world.map[this.y][this.x];
        // ポータルの上ではエンカウントせず、マップ切替処理を行う
        if (tile === TILE.PORTAL) {
          // 現在位置に対応するポータルペアを検索
          const pair = this.world.portalPairs.find(p => p.from.map === this.world.currentMapIndex && p.from.x === this.x && p.from.y === this.y);
          if (pair) {
            // ワールド情報更新
            this.world.currentMapIndex = pair.to.map;
            this.world.map = this.world.maps[pair.to.map];
            // 新しい座標に移動
            this.x = pair.to.x;
            this.y = pair.to.y;
            // メッセージ表示
            this.showMessage('別の世界へワープした！');
            return;
          }
        }
        // 船・飛行機の場合は遭遇率低減
        let rate = TILE_PROPERTIES[tile].encounter;
        // 夜は遭遇率増加
        if (this.isNight()) rate *= 1.5;
        if (this.player.hasAirship) rate = 0; // 空飛ぶ乗り物ではエンカウント無し
        if (Math.random() < rate) {
          // 適当なモンスターを生成
          const def = MONSTERS[Math.floor(Math.random() * MONSTERS.length)];
          const monster = new Monster(def);
          this.game.pushScene(new BattleScene(this.game, this.player, monster));
          return;
        }
        // イベント処理
        this.handleTileEvent(tile);
      }
    }
    // メニュー
    if (this.game.isKeyJustPressed('Escape') || this.game.isKeyJustPressed('KeyM')) {
      this.game.pushScene(new MenuScene(this.game, this.player, this.world, this));
    }
  }
  handleTileEvent(tile) {
    switch (tile) {
      case TILE.TOWN:
        this.showMessage('ここは町だ。人々が暮らしている。');
        break;
      case TILE.INN:
        this.showMessage('宿屋に泊まりますか？ HP/MPが全回復します。');
        this.player.heal(this.player.maxHp);
        this.player.restoreMp(this.player.maxMp);
        this.player.curePoison();
        break;
      case TILE.SHOP:
        // 夜は閉店
        if (this.isNight()) {
          this.showMessage('お店は夜閉まっています。朝まで待ちましょう。');
        } else {
          this.game.pushScene(new ShopScene(this.game, this.player));
        }
        break;
      case TILE.TEMPLE:
        this.game.pushScene(new TempleScene(this.game, this.player, this.world));
        break;
      case TILE.CASTLE:
        this.game.pushScene(new CastleScene(this.game, this.player, this.world));
        break;
      case TILE.NPC:
        this.showMessage('道しるべ: 寺院で石を集め、城へ行け！');
        break;
      case TILE.SHIP:
        if (!this.player.hasShip) {
          this.player.hasShip = true;
          this.showMessage('船を手に入れた！水上を移動できる。');
        }
        break;
      case TILE.AIRSHIP:
        if (this.player.orbs >= 6 && !this.player.hasAirship) {
          this.player.hasAirship = true;
          this.showMessage('黄金の鳥を手に入れた！空を飛べる。');
        }
        break;
    }
  }
  // 夜かどうか判定
  isNight() {
    return (this.timeCount % this.dayLength) >= this.dayLength / 2;
  }
  showMessage(msg) {
    this.message = msg;
    this.showingMessage = true;
    this.messageTimer = 0;
  }
  draw(ctx) {
    // 背景
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, this.game.width, this.game.height);
    const tileSize = 16;
    // 描画範囲を計算：プレイヤーを中央に
    const viewW = Math.floor(this.game.width / tileSize);
    const viewH = Math.floor((this.game.height - 32) / tileSize);
    const offsetX = Math.floor(this.x - viewW / 2);
    const offsetY = Math.floor(this.y - viewH / 2);
    for (let vy = 0; vy < viewH; vy++) {
      for (let vx = 0; vx < viewW; vx++) {
        const mx = offsetX + vx;
        const my = offsetY + vy;
        let tile = TILE.GRASS;
        if (my >= 0 && my < this.world.map.length && mx >= 0 && mx < this.world.map[0].length) {
          tile = this.world.map[my][mx];
        }
        const name = TILE_NAMES[tile];
        const img = this.game.assets.tiles[name];
        ctx.drawImage(img, vx * tileSize, vy * tileSize, tileSize, tileSize);
      }
    }
    // プレイヤー描画
    const px = (viewW / 2) * tileSize;
    const py = (viewH / 2) * tileSize;
    // フレームに応じて立ち姿と歩き姿を切り替え
    const heroImg = this.walkFrame === 0 ? this.game.assets.characters.hero : (this.game.assets.characters.hero_walk || this.game.assets.characters.hero);
    ctx.drawImage(heroImg, px, py, tileSize, tileSize);
    // 夜の場合は画面を暗くする
    if (this.isNight()) {
      ctx.fillStyle = 'rgba(0,0,0,0.4)';
      ctx.fillRect(0, 0, this.game.width, this.game.height - 32);
    }
    // HUDを画面上部に表示
    const hudH = 32;
    drawWindow(ctx, 0, 0, this.game.width, hudH);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.textAlign = 'left';
    const clsName = CLASSES[this.player.classId].name;
    ctx.fillText(`Lv${this.player.level} ${clsName} HP:${this.player.hp}/${this.player.maxHp} MP:${this.player.mp}/${this.player.maxMp} EXP:${this.player.exp} G:${this.player.gold}`, 4, 20);
    // メッセージ
    if (this.showingMessage && this.message) {
      drawMessageWindow(ctx, this.message);
    }
  }
}

// 戦闘シーン
export class BattleScene extends Scene {
  constructor(game, player, monster) {
    super(game);
    this.player = player;
    this.monster = monster;
    this.phase = 'intro';
    this.menuIndex = 0;
    this.messageQueue = [];
    this.turnPlayer = true;
    this.action = null;
    // バトルBGM開始
    audioManager.stopBgm();
    audioManager.playBgm('battle');
  }
  update(dt) {
    // メッセージキューがあれば表示続行
    if (this.messageQueue.length > 0) {
      if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
        this.messageQueue.shift();
      }
      return;
    }
    // 戦闘終了チェック
    if (this.phase !== 'end' && this.phase !== 'gameover') {
      if (!this.monster.isAlive()) {
        // 勝利処理（1回だけ行う）
        this.messageQueue.push(`${this.monster.name}を倒した！`);
        this.player.addExp(this.monster.exp);
        this.player.addGold(this.monster.gold);
        // ドロップ
        const cls = CLASSES[this.player.classId];
        if (cls.dropRate && Math.random() < cls.dropRate) {
          const keys = Object.keys(ITEMS);
          const itemKey = keys[Math.floor(Math.random() * keys.length)];
          this.player.inventory[itemKey] = (this.player.inventory[itemKey] || 0) + 1;
          this.messageQueue.push(`${ITEMS[itemKey].name}を拾った！`);
        }
        // ラスボスの場合は特別なメッセージ
        if (this.monster.id === 'darklord') {
          this.player.finalBossDefeated = true;
          this.messageQueue.push('魔王を倒した！世界に平和が戻った！');
          this.messageQueue.push('エンディング！');
          // エンディングBGM
          audioManager.stopBgm();
          audioManager.playBgm('victory');
        } else {
          this.messageQueue.push('勝利！');
        }
        this.phase = 'end';
      } else if (this.player.hp <= 0) {
        this.messageQueue.push('あなたは倒れてしまった…');
        this.phase = 'gameover';
      }
    }
    switch (this.phase) {
      case 'intro':
        this.messageQueue.push(`${this.monster.name}があらわれた！`);
        this.phase = 'menu';
        break;
      case 'menu':
        // コマンド選択
        if (this.game.isKeyJustPressed('ArrowUp')) {
          audioManager.beep(660, 0.05);
          this.menuIndex = (this.menuIndex + 3) % 4;
        }
        if (this.game.isKeyJustPressed('ArrowDown')) {
          audioManager.beep(660, 0.05);
          this.menuIndex = (this.menuIndex + 1) % 4;
        }
        if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
          audioManager.beep(880, 0.1);
          const option = ['こうげき', 'まほう', 'アイテム', 'にげる'][this.menuIndex];
          if (option === 'こうげき') {
            this.performAttack();
          } else if (option === 'まほう') {
            if (this.player.spells.length === 0) {
              this.messageQueue.push('呪文を覚えていない！');
            } else {
              this.game.pushScene(new MagicSelectScene(this.game, this.player, this.monster, this));
              return;
            }
          } else if (option === 'アイテム') {
            this.game.pushScene(new ItemSelectScene(this.game, this.player, this));
            return;
          } else if (option === 'にげる') {
            if (Math.random() < 0.5) {
              this.messageQueue.push('逃げ出した！');
              this.phase = 'run';
            } else {
              this.messageQueue.push('しかし逃げられなかった！');
              this.turnPlayer = false;
              this.phase = 'enemy';
            }
          }
        }
        break;
      case 'player':
        break;
      case 'enemy':
        // 敵のターン
        const dmg = Math.max(1, this.monster.atk - this.player.defTotal + Math.floor(Math.random() * 3));
        this.player.takeDamage(dmg);
        this.messageQueue.push(`${this.monster.name}の攻撃！${dmg}のダメージ！`);
        // 毒攻撃の確率: snake spawns cause poison sometimes
        if (this.monster.id === 'snake' && Math.random() < 0.2 && !this.player.poison) {
          this.player.poison = true;
          this.messageQueue.push('毒を受けた！歩くとダメージを受ける。');
        }
        // バフ減衰
        this.player.tickBuffs();
        // プレイヤーに戻す
        this.phase = 'menu';
        break;
      case 'run':
        // 逃走成功後、フィールドに戻る
        this.game.popScene();
        break;
      case 'end':
        // 勝利後フィールドに戻る
        this.game.popScene();
        break;
      case 'gameover':
        if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
          this.game.replaceScene(new TitleScene(this.game));
        }
        break;
    }
  }
  performAttack() {
    // プレイヤーの攻撃処理
    const dmg = Math.max(1, this.player.atkTotal - this.monster.def + Math.floor(Math.random() * 3));
    this.monster.takeDamage(dmg);
    this.messageQueue.push(`こうげき！ ${this.monster.name}に${dmg}のダメージ！`);
    // バフ減衰
    this.player.tickBuffs();
    // 次のターンは敵
    this.phase = 'enemy';
  }
  draw(ctx) {
    // 背景
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, this.game.width, this.game.height);
    // 敵スプライト
    const tileSize = 32;
    const img = this.game.assets.monsters[this.monster.image];
    ctx.drawImage(img, (this.game.width - tileSize) / 2, 60, tileSize, tileSize);
    // UI: モンスター名、HPバー
    ctx.fillStyle = '#fff';
    ctx.font = '16px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`${this.monster.name}`, this.game.width / 2, 50);
    // HPバー背景
    ctx.fillStyle = '#444';
    ctx.fillRect(this.game.width / 2 - 50, 70, 100, 8);
    ctx.fillStyle = '#a00';
    const hpW = 100 * (this.monster.hp / this.monster.maxHp);
    ctx.fillRect(this.game.width / 2 - 50, 70, hpW, 8);
    // プレイヤー情報
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'left';
    ctx.fillText(`あなた`, 10, 50);
    ctx.fillText(`HP ${this.player.hp}/${this.player.maxHp}`, 10, 70);
    ctx.fillText(`MP ${this.player.mp}/${this.player.maxMp}`, 10, 90);
    // コマンド
    if (this.phase === 'menu') {
      const options = ['こうげき', 'まほう', 'アイテム', 'にげる'];
      options.forEach((opt, i) => {
        ctx.fillStyle = i === this.menuIndex ? '#ff0' : '#fff';
        ctx.fillText(opt, 10, 130 + i * 20);
      });
    }
    // メッセージウィンドウ
    if (this.messageQueue.length > 0) {
      drawMessageWindow(ctx, this.messageQueue[0]);
    }
    if (this.phase === 'gameover') {
      ctx.fillStyle = '#f00';
      ctx.font = '20px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Game Over', this.game.width / 2, this.game.height / 2);
      ctx.font = '12px sans-serif';
      ctx.fillText('Enterでタイトルへ', this.game.width / 2, this.game.height / 2 + 24);
    }
  }
}

// 魔法選択サブシーン
export class MagicSelectScene extends Scene {
  constructor(game, player, monster, parent) {
    super(game);
    this.player = player;
    this.monster = monster;
    this.parent = parent;
    this.index = 0;
  }
  update(dt) {
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + this.player.spells.length - 1) % this.player.spells.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + 1) % this.player.spells.length;
    }
    if (this.game.isKeyJustPressed('Escape') || this.game.isKeyJustPressed('Backspace')) {
      this.game.popScene();
      return;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      const key = this.player.spells[this.index];
      const result = this.player.castSpell(key, this.monster);
      this.parent.messageQueue.push(result.message);
      // バフ減衰
      this.player.tickBuffs();
      // 敵のターン
      this.parent.phase = 'enemy';
      this.game.popScene();
    }
  }
  draw(ctx) {
    this.parent.draw(ctx);
    // オーバーレイウィンドウを描画
    const x = 40;
    const y = 100;
    const w = 176;
    const h = 100;
    drawWindow(ctx, x, y, w, h);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('まほう', x + 8, y + 16);
    this.player.spells.forEach((key, i) => {
      const spell = SPELLS[key];
      ctx.fillStyle = i === this.index ? '#ff0' : '#fff';
      ctx.fillText(`${spell.name} MP${spell.mp}`, x + 8, y + 36 + i * 16);
    });
  }
}

// アイテム選択サブシーン
export class ItemSelectScene extends Scene {
  constructor(game, player, parent) {
    super(game);
    this.player = player;
    this.parent = parent;
    this.items = Object.keys(ITEMS);
    this.index = 0;
  }
  update(dt) {
    if (this.items.length === 0) {
      this.parent.messageQueue.push('アイテムを持っていない！');
      this.game.popScene();
      return;
    }
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + this.items.length - 1) % this.items.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + 1) % this.items.length;
    }
    if (this.game.isKeyJustPressed('Escape') || this.game.isKeyJustPressed('Backspace')) {
      this.game.popScene();
      return;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      const key = this.items[this.index];
      if (this.player.inventory[key] > 0) {
        const result = this.player.useItem(key);
        this.parent.messageQueue.push(result.message);
        // バフ減衰
        this.player.tickBuffs();
        // 敵のターン
        this.parent.phase = 'enemy';
        this.game.popScene();
      } else {
        this.parent.messageQueue.push('在庫がない！');
        this.game.popScene();
      }
    }
  }
  draw(ctx) {
    this.parent.draw(ctx);
    const x = 40;
    const y = 100;
    const w = 176;
    const h = 100;
    drawWindow(ctx, x, y, w, h);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('アイテム', x + 8, y + 16);
    this.items.forEach((key, i) => {
      const item = ITEMS[key];
      const count = this.player.inventory[key] || 0;
      ctx.fillStyle = i === this.index ? '#ff0' : '#fff';
      ctx.fillText(`${item.name} x${count}`, x + 8, y + 36 + i * 16);
    });
  }
}

// メニューシーン
export class MenuScene extends Scene {
  constructor(game, player, world, fieldScene) {
    super(game);
    this.player = player;
    this.world = world;
    this.fieldScene = fieldScene;
    this.options = ['ステータス','アイテム','セーブ','もどる'];
    this.index = 0;
  }
  update(dt) {
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + this.options.length - 1) % this.options.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + 1) % this.options.length;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      const opt = this.options[this.index];
      audioManager.beep(880, 0.1);
      if (opt === 'ステータス') {
        this.game.pushScene(new StatsScene(this.game, this.player));
      } else if (opt === 'アイテム') {
        this.game.pushScene(new ItemSelectScene(this.game, this.player, this.fieldScene));
      } else if (opt === 'セーブ') {
        const saveData = {
          player: this.player,
          world: this.world
        };
        safeSet('sfc_rpg_save', JSON.stringify(saveData));
        this.fieldScene.showMessage('セーブしました！');
        this.game.popScene();
      } else if (opt === 'もどる') {
        this.game.popScene();
      }
    }
    if (this.game.isKeyJustPressed('Escape')) {
      this.game.popScene();
    }
  }
  draw(ctx) {
    this.fieldScene.draw(ctx);
    const x = 40;
    const y = 80;
    const w = 176;
    const h = 96;
    drawWindow(ctx, x, y, w, h);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.fillText('メニュー', x + 8, y + 16);
    this.options.forEach((opt, i) => {
      ctx.fillStyle = i === this.index ? '#ff0' : '#fff';
      ctx.fillText(opt, x + 8, y + 32 + i * 16);
    });
  }
}

// ステータス表示シーン
export class StatsScene extends Scene {
  constructor(game, player) {
    super(game);
    this.player = player;
  }
  update(dt) {
    if (this.game.isKeyJustPressed('Escape') || this.game.isKeyJustPressed('Enter')) {
      this.game.popScene();
    }
  }
  draw(ctx) {
    const field = this.game.sceneStack[this.game.sceneStack.length - 2];
    if (field && field.draw) field.draw(ctx);
    const x = 40;
    const y = 80;
    const w = 176;
    const h = 128;
    drawWindow(ctx, x, y, w, h);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.fillText('ステータス', x + 8, y + 16);
    const lines = [];
    lines.push(`職業: ${CLASSES[this.player.classId].name}`);
    lines.push(`レベル: ${this.player.level}`);
    lines.push(`経験値: ${this.player.exp}`);
    lines.push(`HP: ${this.player.hp}/${this.player.maxHp}`);
    // 最大MPは装備やバフ込みで表示
    lines.push(`MP: ${this.player.mp}/${this.player.maxMpTotal}`);
    // 攻撃力・防御力は武器とバフ込みの数値を表示
    lines.push(`攻撃力: ${this.player.atkTotal}`);
    lines.push(`防御力: ${this.player.defTotal}`);
    lines.push(`ゴールド: ${this.player.gold}`);
    lines.push(`呪文: ${this.player.spells.map(k => SPELLS[k].name).join('、') || 'なし'}`);
    lines.push(`所持オーブ: ${this.player.orbs}`);
    lines.push(`武器: ${this.player.weapon ? ITEMS[this.player.weapon].name : 'なし'}`);
    lines.forEach((ln, i) => {
      ctx.fillText(ln, x + 8, y + 32 + i * 16);
    });
  }
}

// ショップシーン
export class ShopScene extends Scene {
  constructor(game, player) {
    super(game);
    this.player = player;
    this.items = Object.keys(ITEMS);
    this.index = 0;
  }
  update(dt) {
    if (this.game.isKeyJustPressed('ArrowUp')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + this.items.length - 1) % this.items.length;
    }
    if (this.game.isKeyJustPressed('ArrowDown')) {
      audioManager.beep(660, 0.05);
      this.index = (this.index + 1) % this.items.length;
    }
    if (this.game.isKeyJustPressed('Escape') || this.game.isKeyJustPressed('Backspace')) {
      this.game.popScene();
      return;
    }
    if (this.game.isKeyJustPressed('Enter') || this.game.isKeyJustPressed('Space')) {
      const key = this.items[this.index];
      const item = ITEMS[key];
      if (this.player.gold >= item.cost) {
        this.player.gold -= item.cost;
        this.player.inventory[key] = (this.player.inventory[key] || 0) + 1;
        this.game.sceneStack[this.game.sceneStack.length - 2].showMessage(`${item.name}を購入した！`);
      } else {
        this.game.sceneStack[this.game.sceneStack.length - 2].showMessage('お金が足りない！');
      }
      this.game.popScene();
    }
  }
  draw(ctx) {
    const field = this.game.sceneStack[this.game.sceneStack.length - 2];
    field.draw(ctx);
    const x = 32;
    const y = 64;
    const w = 192;
    const h = 160;
    drawWindow(ctx, x, y, w, h);
    ctx.fillStyle = '#fff';
    ctx.font = '12px monospace';
    ctx.fillText('ショップ', x + 8, y + 16);
    this.items.forEach((key, i) => {
      const item = ITEMS[key];
      ctx.fillStyle = i === this.index ? '#ff0' : '#fff';
      ctx.fillText(`${item.name} ${item.cost}G`, x + 8, y + 32 + i * 16);
    });
    ctx.fillStyle = '#ccc';
    ctx.fillText(`所持金: ${this.player.gold}G`, x + 8, y + h - 20);
  }
}

// 寺院シーン
export class TempleScene extends Scene {
  constructor(game, player, world) {
    super(game);
    this.player = player;
    this.world = world;
    this.stage = 0;
    // stage 0: 初回セリフ、1: 石受け取り、2: すでに取得済み
  }
  update(dt) {
    // シンプルに：全回復してオーブを入手
    if (this.player.questStage >= 1) {
      if (this.player.orbs < 6) {
        this.player.orbs++;
        this.game.sceneStack[this.game.sceneStack.length - 2].showMessage('精霊石を見つけた！');
      } else {
        this.game.sceneStack[this.game.sceneStack.length - 2].showMessage('祈りが力になる…');
      }
    } else {
      this.game.sceneStack[this.game.sceneStack.length - 2].showMessage('神殿で祈り、力を得た');
    }
    // 全回復
    this.player.heal(this.player.maxHp);
    this.player.restoreMp(this.player.maxMp);
    this.player.curePoison();
    this.game.popScene();
  }
  draw(ctx) {
    const field = this.game.sceneStack[this.game.sceneStack.length - 2];
    field.draw(ctx);
    // ダイアログはイベントが処理するため描画不要
  }
}

// 城シーン
export class CastleScene extends Scene {
  constructor(game, player, world) {
    super(game);
    this.player = player;
    this.world = world;
    this.stage = 0;
  }
  update(dt) {
    const fieldScene = this.game.sceneStack[this.game.sceneStack.length - 2];
    // クエスト未開始
    if (this.player.questStage === 0) {
      fieldScene.showMessage('王様: 魔王を倒すため6つの石を集めよ！');
      this.player.questStage = 1;
      this.game.popScene();
      return;
    }
    // 石が揃っていない状態
    if (this.player.questStage === 1 && this.player.orbs >= 6) {
      fieldScene.showMessage('王様: よくぞ石を集めた！伝説の鳥を授けよう。');
      this.player.questStage = 2;
      this.game.popScene();
      return;
    }
    // ラスボス戦の条件：石が6個、飛行機取得済み、まだ討伐していない
    if (this.player.questStage >= 2 && this.player.orbs >= 6 && this.player.hasAirship && !this.player.finalBossDefeated) {
      // ラスボス戦開始
      // 城を抜けてすぐ戦闘
      this.game.popScene();
      const darkDef = MONSTERS.find(m => m.id === 'darklord');
      const boss = new Monster(darkDef);
      this.game.pushScene(new BattleScene(this.game, this.player, boss));
      return;
    }
    // すでに魔王を倒した場合
    if (this.player.finalBossDefeated) {
      fieldScene.showMessage('王様: よくぞ魔王を倒した！世界は平和だ。');
      this.game.popScene();
      return;
    }
    // その他の場合
    fieldScene.showMessage('王様: 旅を急げ！');
    this.game.popScene();
  }
  draw(ctx) {
    const field = this.game.sceneStack[this.game.sceneStack.length - 2];
    field.draw(ctx);
  }
}
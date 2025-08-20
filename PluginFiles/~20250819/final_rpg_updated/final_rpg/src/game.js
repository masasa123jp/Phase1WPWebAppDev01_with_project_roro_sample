// game.js
// Game クラス：キャンバスとシーンスタックを管理し、メインループを動かします。

import { loadAssets } from './assets.js';

export class Game {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.width = canvas.width;
    this.height = canvas.height;
    this.assets = null;
    this.sceneStack = [];
    this.keysDown = {};
    // フェード演出用
    this.fadeAlpha = 0;
    this.fadeSpeed = 1.5; // フェードが1.5秒で終了する速度
    // キーイベント
    window.addEventListener('keydown', e => {
      if (!this.keysDown[e.code]) {
        this.keysDown[e.code] = { pressed: true, just: true };
      }
      // prevent arrow key scrolling
      if ([ 'ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Space','Enter' ].includes(e.code)) {
        e.preventDefault();
      }
    });
    window.addEventListener('keyup', e => {
      delete this.keysDown[e.code];
    });
  }
  // 一度だけ呼び出してアセットを読み込む
  async init() {
    this.assets = await loadAssets();
  }
  // メインループ開始
  start() {
    let last = 0;
    const loop = (timestamp) => {
      const dt = (timestamp - last) / 1000;
      last = timestamp;
      this.update(dt);
      this.draw();
      requestAnimationFrame(loop);
    };
    requestAnimationFrame(loop);
  }
  // キーが押された瞬間かどうか
  isKeyJustPressed(code) {
    const obj = this.keysDown[code];
    if (obj && obj.just) {
      obj.just = false;
      return true;
    }
    return false;
  }
  // シーン管理
  pushScene(scene) {
    this.sceneStack.push(scene);
    // 新しいシーンを表示するときにフェードイン開始
    this.startFade(1);
  }
  popScene() {
    const removed = this.sceneStack.pop();
    // フェードインを開始
    this.startFade(1);
    return removed;
  }
  replaceScene(scene) {
    this.sceneStack = [scene];
    this.startFade(1);
  }
  /**
   * フェードを開始します。引数はフェード開始時のアルファ値（0〜1）です。
   * @param {number} startAlpha
   */
  startFade(startAlpha = 1) {
    this.fadeAlpha = Math.max(0, Math.min(1, startAlpha));
  }
  update(dt) {
    if (this.sceneStack.length === 0) return;
    const scene = this.sceneStack[this.sceneStack.length - 1];
    if (scene.update) scene.update(dt);
    // フェードアルファを減少
    if (this.fadeAlpha > 0) {
      this.fadeAlpha -= this.fadeSpeed * dt;
      if (this.fadeAlpha < 0) this.fadeAlpha = 0;
    }
  }
  draw() {
    if (this.sceneStack.length === 0) return;
    const scene = this.sceneStack[this.sceneStack.length - 1];
    if (scene.draw) scene.draw(this.ctx);
    // フェードオーバーレイを描画
    if (this.fadeAlpha > 0) {
      this.ctx.save();
      this.ctx.globalAlpha = this.fadeAlpha;
      this.ctx.fillStyle = 'black';
      this.ctx.fillRect(0, 0, this.width, this.height);
      this.ctx.restore();
    }
  }
}
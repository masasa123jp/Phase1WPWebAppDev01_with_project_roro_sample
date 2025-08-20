// main.js
// エントリーポイント。Gameを初期化し、タイトルシーンをプッシュします。

import { Game } from './game.js';
import { TitleScene } from './scenes.js';

async function init() {
  const canvas = document.getElementById('gameCanvas');
  const game = new Game(canvas);
  await game.init();
  // タイトルシーンをセット
  game.pushScene(new TitleScene(game));
  game.start();
}

init();
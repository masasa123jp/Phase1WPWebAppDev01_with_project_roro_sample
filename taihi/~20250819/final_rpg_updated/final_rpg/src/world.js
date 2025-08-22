// world.js
// ワールドの地形生成と関連する処理を提供します。
import { TILE, TILE_PROPERTIES } from './data.js';

/**
 * ランダムなワールドを生成します。
 * 64x64 のタイルマップで、草原や森、山、水面などを配置します。
 * また、町や城、寺院などを定位置に設置します。
 * 戻り値は { map, startX, startY, towns, temples } のようなオブジェクトです。
 */
export function generateWorld() {
  /**
   * 二つのマップを生成して返します。マップは256×256サイズで、
   * それぞれ独立した地形や施設を持ちます。ポータルタイルを
   * 各マップに1つ配置し、踏むともう片方のマップへワープします。
   * 戻り値は以下のプロパティを持ちます:
   * - maps: [map0, map1] 二つの2次元配列
   * - map: 現在使用中のマップへの参照
   * - currentMapIndex: 現在のマップインデックス(0 または 1)
   * - startX, startY: 初期座標
   * - portalPairs: ポータルの位置ペア配列
   * - その他: towns, temples, castle, ship, airship
   */
  const size = 256;

  // ヘルパー関数: 単一のマップを生成
  function createMap() {
    const map = Array.from({ length: size }, () => Array(size).fill(TILE.GRASS));
    const towns = [];
    const temples = [];
    const shops = [];
    const inns = [];
    // 地形生成: ランダムに水・山・森・沼・砂漠を配置
    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        const r = Math.random();
        if (r < 0.05) map[y][x] = TILE.WATER;
        else if (r < 0.10) map[y][x] = TILE.MOUNTAIN;
        else if (r < 0.20) map[y][x] = TILE.FOREST;
        else if (r < 0.25) map[y][x] = TILE.SWAMP;
        else if (r < 0.30) map[y][x] = TILE.DESERT;
      }
    }
    // 主要な町を8箇所ランダムに配置（等間隔に散らす）
    const townPositions = [];
    const positions = [32, 96, 160, 224];
    positions.forEach(px => {
      positions.forEach(py => {
        townPositions.push({ x: px, y: py });
      });
    });
    // シャッフルして4つだけ町にする
    while (townPositions.length > 8) townPositions.splice(Math.floor(Math.random() * townPositions.length), 1);
    townPositions.forEach(pos => {
      map[pos.y][pos.x] = TILE.TOWN;
      towns.push(pos);
      // 周囲を道路で囲む
      for (let dx = -2; dx <= 2; dx++) {
        for (let dy = -2; dy <= 2; dy++) {
          const nx = pos.x + dx;
          const ny = pos.y + dy;
          if (nx >= 0 && ny >= 0 && nx < size && ny < size) {
            if (map[ny][nx] === TILE.GRASS) map[ny][nx] = TILE.ROAD;
          }
        }
      }
    });
    // 宿屋・ショップを町の周辺に配置
    towns.forEach(pos => {
      const innPos = { x: pos.x + 1, y: pos.y + 2 };
      if (innPos.x < size && innPos.y < size) {
        map[innPos.y][innPos.x] = TILE.INN;
        inns.push(innPos);
      }
    });
    towns.forEach(pos => {
      const shopPos = { x: pos.x - 2, y: pos.y + 2 };
      if (shopPos.x >= 0 && shopPos.y < size) {
        map[shopPos.y][shopPos.x] = TILE.SHOP;
        shops.push(shopPos);
      }
    });
    // 寺院を数ヶ所設置
    const templePositions = [
      { x: 40, y: 20 }, { x: 20, y: 40 }, { x: 216, y: 40 }, { x: 40, y: 216 }, { x: 120, y: 120 }, { x: 200, y: 200 }
    ];
    templePositions.forEach(pos => {
      map[pos.y][pos.x] = TILE.TEMPLE;
      temples.push(pos);
    });
    // 城を中央に配置
    const castle = { x: Math.floor(size / 2), y: Math.floor(size / 2) };
    map[castle.y][castle.x] = TILE.CASTLE;
    // 船を海の近くに配置
    const ship = { x: 3, y: 10 };
    map[ship.y][ship.x] = TILE.SHIP;
    // エアシップを遠くに配置
    const airship = { x: size - 10, y: size - 20 };
    map[airship.y][airship.x] = TILE.AIRSHIP;
    // NPC看板を町の北側に配置
    towns.forEach(pos => {
      const npcPos = { x: pos.x, y: pos.y - 2 };
      if (npcPos.y >= 0) map[npcPos.y][npcPos.x] = TILE.NPC;
    });
    // 城から町へ道路を敷く
    towns.forEach(pos => {
      let x = castle.x;
      let y = castle.y;
      while (x !== pos.x || y !== pos.y) {
        if (map[y][x] === TILE.GRASS) map[y][x] = TILE.ROAD;
        if (x < pos.x) x++;
        else if (x > pos.x) x--;
        if (map[y][x] === TILE.GRASS) map[y][x] = TILE.ROAD;
        if (y < pos.y) y++;
        else if (y > pos.y) y--;
      }
    });
    return { map, towns, temples, shops, inns, castle, ship, airship };
  }

  // 2つのマップを生成
  const m0 = createMap();
  const m1 = createMap();

  // ポータルの位置を決める
  const portal0 = { x: 16, y: 16 };
  const portal1 = { x: size - 17, y: size - 17 };
  // マップにポータルを配置
  m0.map[portal0.y][portal0.x] = TILE.PORTAL;
  m1.map[portal1.y][portal1.x] = TILE.PORTAL;
  // スタート位置はマップ0の城の下
  const startX = m0.castle.x;
  const startY = m0.castle.y + 1;
  return {
    maps: [m0.map, m1.map],
    map: m0.map,
    currentMapIndex: 0,
    startX,
    startY,
    towns: m0.towns.concat(m1.towns),
    temples: m0.temples.concat(m1.temples),
    castle: m0.castle,
    ship: m0.ship,
    airship: m0.airship,
    portalPairs: [
      { from: { map: 0, x: portal0.x, y: portal0.y }, to: { map: 1, x: portal1.x, y: portal1.y } },
      { from: { map: 1, x: portal1.x, y: portal1.y }, to: { map: 0, x: portal0.x, y: portal0.y } }
    ]
  };
}

// マップ上の移動可能性チェック
export function canWalk(map, x, y) {
  if (y < 0 || y >= map.length || x < 0 || x >= map[0].length) return false;
  const tile = map[y][x];
  return TILE_PROPERTIES[tile].walkable;
}
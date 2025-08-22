const fs = require('fs');
const sharp = require('sharp');

// Ensure output directories exist
function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

// Helper to create image from a pixel generator function
function createImage(width, height, generator) {
  const data = new Uint8Array(width * height * 4);
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const [r, g, b, a] = generator(x, y);
      const idx = (y * width + x) * 4;
      data[idx] = r;
      data[idx + 1] = g;
      data[idx + 2] = b;
      data[idx + 3] = a;
    }
  }
  return sharp(data, { raw: { width, height, channels: 4 } });
}

// Define colors as RGBA arrays
const Colors = {
  transparent: [0, 0, 0, 0],
  grassLight: [85, 170, 85, 255],       // #55AA55
  grassDark: [68, 136, 68, 255],        // #448844
  forestLight: [51, 85, 51, 255],       // #335533
  forestDark: [34, 68, 34, 255],        // #224422
  waterLight: [0, 102, 170, 255],       // #0066AA
  waterDark: [0, 68, 136, 255],         // #004488
  mountainLight: [136, 136, 136, 255],  // #888888
  mountainDark: [102, 102, 102, 255],   // #666666
  desertLight: [216, 193, 143, 255],    // #D8C18F
  desertDark: [200, 175, 127, 255],     // slightly darker
  swampLight: [68, 51, 34, 255],        // #443322
  swampDark: [51, 34, 17, 255],         // #332211
  roadLight: [194, 178, 128, 255],      // #C2B280
  roadDark: [170, 150, 100, 255],       // darker
  castleWall: [68, 68, 136, 255],       // #444488
  castleWindow: [136, 136, 204, 255],   // #8888CC
  templeBase: [85, 45, 90, 255],        // #552D5A
  templeCross: [255, 221, 0, 255],      // yellow cross
  townBase: [136, 136, 136, 255],       // #888888
  townRoof: [170, 68, 68, 255],         // reddish roof
  innBase: [170, 102, 204, 255],        // #AA66CC
  innDoor: [68, 34, 102, 255],          // dark purple
  shopBase: [204, 85, 0, 255],          // #CC5500
  shopSign: [255, 221, 0, 255],         // yellow sign
  npcBase: [215, 163, 75, 255],         // #D7A34B
  npcBorder: [136, 102, 51, 255],       // brown border
  shipBase: [138, 79, 44, 255],         // #8A4F2C
  shipSail: [221, 221, 221, 255],       // light sail
  airshipBase: [245, 197, 66, 255],     // #F5C542
  airshipWing: [255, 220, 120, 255],    // lighter wing
  skin: [255, 220, 178, 255],           // peach skin tone
  hair: [139, 69, 19, 255],             // brown hair
  shirt: [0, 0, 128, 255],              // navy shirt
  pants: [0, 0, 0, 255],                // black pants
  boots: [139, 69, 19, 255],            // brown boots
  npcSkin: [255, 220, 178, 255],        // same skin
  npcShirt: [128, 0, 0, 255],           // dark red
  slimeBody: [204, 51, 51, 255],        // red slime
  slimeShade: [170, 34, 34, 255],       // darker red
  eyeWhite: [255, 255, 255, 255],
  eyeBlack: [0, 0, 0, 255],
  snakeBody: [34, 170, 34, 255],        // green body
  snakeShade: [17, 136, 17, 255],       // darker green
  skeletonBone: [200, 200, 200, 255],   // light grey
  skeletonShadow: [150, 150, 150, 255]  // dark grey

  // オリジナルキャラクターのための新しい色定義
  ,heroHair: [192, 112, 62, 255]        // ヒーローの髪色（茶色味のあるオレンジ）
  ,heroBand: [230, 213, 50, 255]        // ヒーローのヘッドバンド/アクセント色
  ,heroBody: [60, 100, 200, 255]        // ヒーローの服の色（ブルー系）
  ,heroBoots: [92, 50, 20, 255]         // ヒーローのブーツ色（ブラウン）
  ,npcHair: [160, 80, 40, 255]          // NPCの髪色
  ,npcBody: [218, 90, 90, 255]          // NPCの服の色（赤系）
  ,npcBoots: [92, 50, 20, 255]          // NPCのブーツ色
};

// Tile generators
const tileGenerators = {
  grass: (x, y) => {
    // checkerboard pattern
    return (x + y) % 2 === 0 ? Colors.grassLight : Colors.grassDark;
  },
  forest: (x, y) => {
    // noise pattern with darker/ lighter greens
    return (x * y) % 4 < 2 ? Colors.forestLight : Colors.forestDark;
  },
  water: (x, y) => {
    // waves: horizontal stripes of lighter blue every 3 rows
    return (y % 4 === 0) ? Colors.waterLight : Colors.waterDark;
  },
  mountain: (x, y) => {
    // diagonal stripes to suggest rocky texture
    return ((x + y) % 3 === 0) ? Colors.mountainLight : Colors.mountainDark;
  },
  desert: (x, y) => {
    // random noise between light and dark
    return ((x * 7 + y * 13) % 5 < 3) ? Colors.desertLight : Colors.desertDark;
  },
  swamp: (x, y) => {
    // irregular dark and light patches
    return ((x * 5 + y * 9) % 6 < 3) ? Colors.swampLight : Colors.swampDark;
  },
  road: (x, y) => {
    // simple noise for road texture
    return ((x + y) % 4 === 0) ? Colors.roadDark : Colors.roadLight;
  },
  castle: (x, y) => {
    // walls with windows: top border dark, middle with windows, bottom dark
    if (y < 3) return Colors.mountainDark;
    if (y === 7 && x % 4 === 0) return Colors.castleWindow;
    return Colors.castleWall;
  },
  temple: (x, y) => {
    // purple base with yellow cross at center
    if (x === 8 && y === 8) return Colors.templeCross;
    if (x === 7 && y === 8) return Colors.templeCross;
    if (x === 8 && y === 7) return Colors.templeCross;
    if (x === 9 && y === 8) return Colors.templeCross;
    if (x === 8 && y === 9) return Colors.templeCross;
    return Colors.templeBase;
  },
  town: (x, y) => {
    // grey walls with red roof at top
    if (y < 3) return Colors.townRoof;
    return Colors.townBase;
  },
  inn: (x, y) => {
    // purple base with dark door at bottom center
    if (y > 9 && x >= 6 && x <= 9) return Colors.innDoor;
    return Colors.innBase;
  },
  shop: (x, y) => {
    // orange base with yellow sign at top middle
    if (y === 1 && x >= 6 && x <= 9) return Colors.shopSign;
    return Colors.shopBase;
  },
  npc: (x, y) => {
    // square sign with border
    if (x === 0 || y === 0 || x === 15 || y === 15) return Colors.npcBorder;
    return Colors.npcBase;
  },
  ship: (x, y) => {
    // brown hull with light sail
    if (y < 3) return Colors.shipSail;
    return Colors.shipBase;
  },
  airship: (x, y) => {
    // golden bird shape: wings and body
    const dx = x - 8;
    const dy = y - 8;
    const dist = Math.sqrt(dx * dx + dy * dy);
    if (dist < 3) return Colors.airshipBase;
    if ((Math.abs(dx) < 5 && dy < 0 && dy > -2)) return Colors.airshipWing;
    return Colors.desertLight; // background in case
  }
};

// Character generator for hero
function heroGenerator(x, y) {
  // 新しいヒーロースプライト: 鳥山明風にインスパイアされたデザイン。
  // 上部はスパイキーな髪型、特徴的な大きな瞳とシンプルな体格。
  // 透明背景がデフォルト
  // 髪（スパイキー）: 上4行に配置
  if (y === 0 && x >= 5 && x <= 10) return Colors.heroHair;
  if (y === 1 && x >= 4 && x <= 11) return Colors.heroHair;
  if (y === 2 && x >= 5 && x <= 10) return Colors.heroHair;
  if (y === 3 && x >= 6 && x <= 9) return Colors.heroHair;
  // 頭と顔: rows 4-6
  if (y >= 4 && y <= 6 && x >= 5 && x <= 10) {
    // 目の白部分と瞳
    // 左目白
    if ((x === 6 || x === 7) && (y === 5 || y === 6)) return Colors.eyeWhite;
    // 右目白
    if ((x === 9 || x === 10) && (y === 5 || y === 6)) return Colors.eyeWhite;
    // 左瞳
    if (x === 7 && y === 6) return Colors.eyeBlack;
    // 右瞳
    if (x === 9 && y === 6) return Colors.eyeBlack;
    // 顔の肌
    return Colors.skin;
  }
  // 首・ヘッドバンド: row 7
  if (y === 7 && x >= 5 && x <= 10) return Colors.heroBand;
  // 体: rows 8-10
  if (y >= 8 && y <= 10 && x >= 6 && x <= 9) return Colors.heroBody;
  // 腕: rows 8-10, 左右1列
  if (y >= 8 && y <= 10 && (x === 5 || x === 10)) return Colors.skin;
  // 腰・パンツ: rows 11-12
  if (y >= 11 && y <= 12 && x >= 6 && x <= 9) return Colors.pants;
  // ブーツ: rows 13-14
  if (y >= 13 && y <= 14 && x >= 6 && x <= 9) return Colors.heroBoots;
  return Colors.transparent;
}

// Alternative hero frame to simulate a walking animation.
// This variant raises one leg and lowers the other to give the impression of movement.
function heroWalkGenerator(x, y) {
  // Copy of the base hero generator with subtle leg position changes.
  // Hair and head remain the same as the base hero.
  if (y === 0 && x >= 5 && x <= 10) return Colors.heroHair;
  if (y === 1 && x >= 4 && x <= 11) return Colors.heroHair;
  if (y === 2 && x >= 5 && x <= 10) return Colors.heroHair;
  if (y === 3 && x >= 6 && x <= 9) return Colors.heroHair;
  if (y >= 4 && y <= 6 && x >= 5 && x <= 10) {
    if ((x === 6 || x === 7) && (y === 5 || y === 6)) return Colors.eyeWhite;
    if ((x === 9 || x === 10) && (y === 5 || y === 6)) return Colors.eyeWhite;
    if (x === 7 && y === 6) return Colors.eyeBlack;
    if (x === 9 && y === 6) return Colors.eyeBlack;
    return Colors.skin;
  }
  if (y === 7 && x >= 5 && x <= 10) return Colors.heroBand;
  if (y >= 8 && y <= 10 && x >= 6 && x <= 9) return Colors.heroBody;
  if (y >= 8 && y <= 10 && (x === 5 || x === 10)) return Colors.skin;
  // Pants row: adjust to simulate one leg forward (right leg raised)
  if (y === 11) {
    // raise left leg (hero's right) by leaving pants shorter on this side
    if ((x === 6 || x === 7) && y === 11) return Colors.transparent;
    if (x >= 8 && x <= 9) return Colors.pants;
  }
  if (y === 12) {
    // draw the raised left leg pants one pixel higher (y=11 handled above)
    if ((x === 6 || x === 7)) return Colors.pants;
    if (x >= 8 && x <= 9) return Colors.pants;
  }
  // Boots rows: adjust similarly; one boot higher
  if (y === 13) {
    // draw boots for the right (player left) foot one row lower (normal), left foot raised (skip)
    if (x >= 8 && x <= 9) return Colors.heroBoots;
  }
  if (y === 14) {
    // raised foot boots drawn here (for x=6,7)
    if (x >= 6 && x <= 7) return Colors.heroBoots;
  }
  return Colors.transparent;
}

// NPC sprite generator: small person with hat
function npcGenerator(x, y) {
  // NPCデザイン: 少し背が低く、丸いシルエットで大きな瞳。
  // 髪: rows 0-1
  if (y === 0 && x >= 5 && x <= 10) return Colors.npcHair;
  if (y === 1 && x >= 6 && x <= 9) return Colors.npcHair;
  // 顔: rows 2-4
  if (y >= 2 && y <= 4 && x >= 6 && x <= 9) {
    // 目白
    if ((x === 7 || x === 8) && y === 3) return Colors.eyeWhite;
    if ((x === 7 || x === 8) && y === 4) return Colors.eyeWhite;
    // 瞳
    if (x === 8 && y === 4) return Colors.eyeBlack;
    return Colors.skin;
  }
  // 体: rows 5-8
  if (y >= 5 && y <= 8 && x >= 6 && x <= 9) return Colors.npcBody;
  // 腕: rows 5-7, 左右1列
  if (y >= 5 && y <= 7 && (x === 5 || x === 10)) return Colors.skin;
  // 足: rows 9-10
  if (y >= 9 && y <= 10 && x >= 7 && x <= 8) return Colors.pants;
  // ブーツ: rows 11-12
  if (y >= 11 && y <= 12 && x >= 7 && x <= 8) return Colors.npcBoots;
  return Colors.transparent;
}

// Monster: slime
function slimeGenerator(x, y) {
  const dx = x - 8;
  const dy = y - 10;
  // Body: circular shape
  const dist = Math.sqrt(dx * dx + dy * dy);
  if (dist < 5) {
    // shade lower part darker
    return dy > 0 ? Colors.slimeShade : Colors.slimeBody;
  }
  // Eyes
  if ((x === 7 || x === 9) && y === 8) return Colors.eyeWhite;
  if ((x === 7 || x === 9) && y === 9) return Colors.eyeWhite;
  if ((x === 7 || x === 9) && y === 8) return Colors.eyeWhite;
  if ((x === 7 || x === 9) && y === 9) return Colors.eyeWhite;
  return Colors.transparent;
}

// Monster: snake
function snakeGenerator(x, y) {
  // create snake body along diagonal
  if ((x + y) % 5 === 0 && y > 2 && y < 13) {
    return Colors.snakeBody;
  }
  if ((x + y) % 5 === 1 && y > 2 && y < 13) {
    return Colors.snakeShade;
  }
  // eyes at head
  if (x === 3 && y === 3) return Colors.eyeWhite;
  if (x === 3 && y === 4) return Colors.eyeWhite;
  if (x === 3 && y === 3) return Colors.eyeWhite;
  return Colors.transparent;
}

// Monster: skeleton
function skeletonGenerator(x, y) {
  // skull: top half
  if (y < 5) {
    if (x > 4 && x < 11) return Colors.skeletonBone;
    return Colors.transparent;
  }
  // eye sockets
  if ((x === 6 || x === 9) && y === 4) return Colors.eyeBlack;
  // ribs and spine
  if (y >= 5 && y <= 11) {
    if ((y % 2 === 1 && x > 5 && x < 10) || (x === 7 || x === 8)) {
      return Colors.skeletonBone;
    }
  }
  // pelvis
  if (y === 12 && x > 5 && x < 10) return Colors.skeletonBone;
  return Colors.transparent;
}

// Monster: orc
function orcGenerator(x, y) {
  // green orc with horns and big body
  // horns at top corners
  if ((y === 2) && (x === 4 || x === 11)) return Colors.skeletonBone;
  // head
  if (y >= 3 && y <= 5 && x >= 5 && x <= 10) return Colors.snakeBody;
  // eyes
  if (y === 4 && (x === 6 || x === 9)) return Colors.eyeBlack;
  // body
  if (y >= 6 && y <= 10 && x >= 5 && x <= 10) return Colors.snakeShade;
  // belt
  if (y === 8 && x >= 5 && x <= 10) return Colors.desertDark;
  // arms
  if ((x === 4 || x === 11) && y >= 6 && y <= 8) return Colors.snakeShade;
  // legs
  if (y >= 11 && y <= 13 && (x === 6 || x === 9)) return Colors.snakeShade;
  return Colors.transparent;
}

// Monster: dragon (simple head and wings)
function dragonGenerator(x, y) {
  // central body: big circle
  const dx = x - 8;
  const dy = y - 8;
  const dist = Math.sqrt(dx * dx + dy * dy);
  if (dist < 5) {
    return Colors.slimeBody;
  }
  // wings: symmetrical triangles
  if (y >= 4 && y <= 10 && (x === 2 || x === 14)) return Colors.slimeShade;
  if (y === 3 && (x === 3 || x === 13)) return Colors.slimeShade;
  // eyes
  if (y === 6 && (x === 7 || x === 9)) return Colors.eyeBlack;
  return Colors.transparent;
}

// Monster: mage
function mageGenerator(x, y) {
  // hat
  if (y <= 3 && x >= 5 && x <= 10) return Colors.templeBase;
  if (y === 4 && x >= 6 && x <= 9) return Colors.templeBase;
  // head
  if (y === 5 && x >= 6 && x <= 9) return Colors.skin;
  // body (robe)
  if (y >= 6 && y <= 10 && x >= 5 && x <= 10) return Colors.templeBase;
  // arms
  if (y >= 7 && y <= 9 && (x === 4 || x === 11)) return Colors.templeBase;
  // staff: vertical line on right
  if (x === 12 && y >= 5 && y <= 10) return Colors.skeletonBone;
  // base (feet)
  if (y === 11 && x >= 6 && x <= 9) return Colors.mountainDark;
  return Colors.transparent;
}

async function generate() {
  const baseDir = __dirname + '/assets';
  ensureDir(baseDir);
  ensureDir(baseDir + '/tiles');
  ensureDir(baseDir + '/characters');
  ensureDir(baseDir + '/monsters');
  const tasks = [];
  // Generate tiles
  for (const [name, generator] of Object.entries(tileGenerators)) {
    tasks.push(
      createImage(16, 16, generator)
        .png()
        .toFile(`${baseDir}/tiles/${name}.png`)
    );
  }
  // Generate hero and npc
  tasks.push(
    createImage(16, 16, heroGenerator).png().toFile(`${baseDir}/characters/hero.png`)
  );
  // Generate alternate walking frame for hero for simple animation
  tasks.push(
    createImage(16, 16, heroWalkGenerator).png().toFile(`${baseDir}/characters/hero_walk.png`)
  );
  tasks.push(
    createImage(16, 16, npcGenerator).png().toFile(`${baseDir}/characters/npc.png`)
  );
  // Generate monsters
  tasks.push(
    createImage(16, 16, slimeGenerator).png().toFile(`${baseDir}/monsters/slime.png`)
  );
  tasks.push(
    createImage(16, 16, snakeGenerator).png().toFile(`${baseDir}/monsters/snake.png`)
  );
  tasks.push(
    createImage(16, 16, skeletonGenerator).png().toFile(`${baseDir}/monsters/skeleton.png`)
  );
  tasks.push(
    createImage(16, 16, orcGenerator).png().toFile(`${baseDir}/monsters/orc.png`)
  );
  tasks.push(
    createImage(16, 16, dragonGenerator).png().toFile(`${baseDir}/monsters/dragon.png`)
  );
  tasks.push(
    createImage(16, 16, mageGenerator).png().toFile(`${baseDir}/monsters/mage.png`)
  );
  await Promise.all(tasks);
  console.log('Assets generated successfully');
}

generate().catch(err => console.error(err));
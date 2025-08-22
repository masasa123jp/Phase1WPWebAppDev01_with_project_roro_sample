// assets.js
// assets_data.js に埋め込まれたBase64データから Image オブジェクトを生成します。

import assetsData from './assets_data.js';

// Imageオブジェクトを作成するユーティリティ
function createImageFromDataUri(uri) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.src = uri;
    img.onload = () => resolve(img);
    img.onerror = (err) => reject(err);
  });
}

/**
 * すべてのアセットを読み込み、Imageオブジェクトを返します。
 * データURIから読み込むためファイルI/Oが不要です。
 */
export async function loadAssets() {
  const result = { tiles: {}, characters: {}, monsters: {} };
  // tiles
  const tilePromises = Object.entries(assetsData.tiles).map(async ([key, uri]) => {
    result.tiles[key] = await createImageFromDataUri(uri);
  });
  const characterPromises = Object.entries(assetsData.characters).map(async ([key, uri]) => {
    result.characters[key] = await createImageFromDataUri(uri);
  });
  const monsterPromises = Object.entries(assetsData.monsters).map(async ([key, uri]) => {
    result.monsters[key] = await createImageFromDataUri(uri);
  });
  await Promise.all([...tilePromises, ...characterPromises, ...monsterPromises]);
  return result;
}
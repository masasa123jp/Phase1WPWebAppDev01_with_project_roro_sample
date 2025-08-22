// audio.js
// WebAudio API を用いた簡単なサウンド生成。

class AudioManager {
  constructor() {
    this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    // BGM 再生用の設定
    this.currentOscillators = [];
    this.bgmTimeout = null;
    this.currentTuneName = null;
    // シンプルなメロディ定義。各要素は { freq: 周波数, duration: 再生時間(秒) }。
    // More elaborate tunes inspired by classic RPG melodies. Each tune contains a longer sequence of notes.
    this.tunes = {
      // Title theme: uplifting arpeggio reminiscent of the opening fanfare
      title: [
        { freq: 659, duration: 0.3 }, // E5
        { freq: 784, duration: 0.3 }, // G5
        { freq: 880, duration: 0.3 }, // A5
        { freq: 988, duration: 0.3 }, // B5
        { freq: 1047, duration: 0.4 }, // C6
        { freq: 880, duration: 0.3 }, // A5
        { freq: 784, duration: 0.3 }, // G5
        { freq: 659, duration: 0.4 }, // E5
        { freq: 523, duration: 0.5 } // C5
      ],
      // Field theme: gentle flowing melody
      field: [
        { freq: 440, duration: 0.4 }, // A4
        { freq: 494, duration: 0.4 }, // B4
        { freq: 523, duration: 0.4 }, // C5
        { freq: 587, duration: 0.4 }, // D5
        { freq: 659, duration: 0.4 }, // E5
        { freq: 587, duration: 0.4 }, // D5
        { freq: 523, duration: 0.4 }, // C5
        { freq: 494, duration: 0.4 }, // B4
        { freq: 440, duration: 0.8 }  // A4
      ],
      // Battle theme: energetic and tense
      battle: [
        { freq: 392, duration: 0.3 }, // G4
        { freq: 440, duration: 0.3 }, // A4
        { freq: 494, duration: 0.3 }, // B4
        { freq: 523, duration: 0.3 }, // C5
        { freq: 587, duration: 0.3 }, // D5
        { freq: 494, duration: 0.3 }, // B4
        { freq: 440, duration: 0.3 }, // A4
        { freq: 392, duration: 0.6 }, // G4
        { freq: 330, duration: 0.6 }  // E4
      ],
      // Victory jingle: short triumphant sequence
      victory: [
        { freq: 523, duration: 0.4 }, // C5
        { freq: 659, duration: 0.4 }, // E5
        { freq: 784, duration: 0.4 }, // G5
        { freq: 880, duration: 0.6 }, // A5
        { freq: 988, duration: 0.5 }, // B5
        { freq: 784, duration: 0.5 }, // G5
        { freq: 659, duration: 0.6 }, // E5
        { freq: 523, duration: 0.8 }  // C5
      ]
    };
  }

  /**
   * ビープ音を再生します。
   * @param {number} freq 周波数（Hz）
   * @param {number} duration 再生時間（秒）
   */
  beep(freq = 440, duration = 0.1) {
    try {
      // オーディオコンテキストが停止している場合は再開する
      if (this.ctx.state === 'suspended') {
        // resume() は Promise を返すが非同期でも問題ない
        this.ctx.resume().catch(() => {});
      }
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.frequency.value = freq;
      osc.type = 'square';
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);
      osc.start();
      osc.stop(this.ctx.currentTime + duration);
    } catch (e) {
      // オーディオコンテキストの制約でエラーになる場合は無視
    }
  }

  /**
   * 指定したメロディをループ再生します。別のBGMが再生中の場合は停止してから開始します。
   * @param {string} name メロディ名 (title, field, battle, victory)
   */
  playBgm(name) {
    if (!this.tunes[name]) return;
    // すでに同じ曲が鳴っている場合は何もしない
    if (this.currentTuneName === name) return;
    this.stopBgm();
    this.currentTuneName = name;
    // オーディオコンテキストが停止している場合は再開する
    if (this.ctx.state === 'suspended') {
      this.ctx.resume().catch(() => {});
    }
    const notes = this.tunes[name];
    const playSequence = () => {
      let startTime = this.ctx.currentTime;
      // スケジュール
      notes.forEach(note => {
        try {
          const osc = this.ctx.createOscillator();
          const gain = this.ctx.createGain();
          osc.frequency.value = note.freq;
          osc.type = 'square';
          osc.connect(gain);
          gain.connect(this.ctx.destination);
          gain.gain.setValueAtTime(0.05, startTime);
          gain.gain.exponentialRampToValueAtTime(0.001, startTime + note.duration);
          osc.start(startTime);
          osc.stop(startTime + note.duration);
          this.currentOscillators.push(osc);
        } catch (e) {
          // オーディオコンテキストの制約でエラーになる場合は無視
        }
        startTime += note.duration;
      });
      // 次のループをスケジュール
      this.bgmTimeout = setTimeout(playSequence, notes.reduce((sum, n) => sum + n.duration, 0) * 1000);
    };
    playSequence();
  }

  /**
   * BGMを停止します。
   */
  stopBgm() {
    // 停止
    if (this.bgmTimeout) {
      clearTimeout(this.bgmTimeout);
      this.bgmTimeout = null;
    }
    this.currentTuneName = null;
    // 既存のオシレーターを停止
    this.currentOscillators.forEach(osc => {
      try {
        osc.stop();
      } catch (e) {
        // ignore
      }
    });
    this.currentOscillators = [];
  }
}

export const audioManager = new AudioManager();
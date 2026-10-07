const fs = require('fs');
const path = require('path');

const sr = 44100;
const duration = 1.38;
const n = Math.floor(sr * duration);
const samples = new Float32Array(n);

const tanh = (x) => Math.tanh(x);
const rand = () => Math.random() * 2 - 1;

function bandpass(freq, q) {
    const w0 = (2 * Math.PI * freq) / sr;
    const alpha = Math.sin(w0) / (2 * q);
    const b0 = alpha;
    const b1 = 0;
    const b2 = -alpha;
    const a0 = 1 + alpha;
    const a1 = -2 * Math.cos(w0);
    const a2 = 1 - alpha;
    let x1 = 0;
    let x2 = 0;
    let y1 = 0;
    let y2 = 0;

    return (x) => {
        const y = (b0 / a0) * x + (b1 / a0) * x1 + (b2 / a0) * x2 - (a1 / a0) * y1 - (a2 / a0) * y2;
        x2 = x1;
        x1 = x;
        y2 = y1;
        y1 = y;
        return y;
    };
}

const formants = [
    bandpass(780, 7),
    bandpass(1250, 8),
    bandpass(1750, 9),
    bandpass(2650, 10),
    bandpass(3900, 6),
    bandpass(5200, 4),
];

let phase = 0;
let growlPhase = 0;
let hp = 0;

for (let i = 0; i < n; i += 1) {
    const t = i / sr;
    const attack = Math.min(1, t / 0.006);
    const hold = t < 0.18 ? 1 : Math.exp(-2.15 * (t - 0.18));
    const env = attack * hold;

    const f0 = 540 * Math.exp(-1.85 * t) + 120;
    const vibrato = 1 + 0.045 * Math.sin(2 * Math.PI * 22 * t) + 0.09 * rand();
    phase += (f0 * vibrato) / sr;
    phase -= Math.floor(phase);

    const glottal = phase < 0.28 ? Math.sin((phase / 0.28) * Math.PI) ** 1.6 : 0;
    const saw = 2 * phase - 1;
    const source = glottal * 0.75 + saw * 0.45 + rand() * 0.22;

    let voice = 0;
    for (const filter of formants) {
        voice += filter(source);
    }

    growlPhase += (62 + 28 * Math.sin(2 * Math.PI * 8 * t)) / sr;
    growlPhase -= Math.floor(growlPhase);
    const growl = Math.sin(2 * Math.PI * growlPhase) * 0.55 + (2 * growlPhase - 1) * 0.2;

    const grit = rand() * (0.62 * Math.exp(-3.1 * t) + 0.14);
    const scrape = Math.sin(2 * Math.PI * (3100 + 900 * Math.sin(2 * Math.PI * 18 * t)) * t) * 0.18 * Math.exp(-2.4 * t);

    let x = voice * 1.15 + growl * 0.42 + grit + scrape;

    hp = 0.92 * (hp + x - (samples[i - 1] ?? 0));
    x = hp * 0.82 + x * 0.35;

    x = tanh(x * (7.2 + 3.4 * env));
    x = Math.max(-1, Math.min(1, x * 1.55));
    x = tanh(x * 2.4);
    x = Math.max(-0.98, Math.min(0.98, x * 1.12));

    samples[i] = x * env;
}

let peak = 0;
for (const sample of samples) {
    peak = Math.max(peak, Math.abs(sample));
}

const gain = peak > 0 ? 0.94 / peak : 1;
const pcm = Buffer.alloc(n * 2);

for (let i = 0; i < n; i += 1) {
    const v = Math.max(-1, Math.min(1, samples[i] * gain));
    pcm.writeInt16LE(Math.round(v * 32767), i * 2);
}

const header = Buffer.alloc(44);
header.write('RIFF', 0);
header.writeUInt32LE(36 + pcm.length, 4);
header.write('WAVE', 8);
header.write('fmt ', 12);
header.writeUInt32LE(16, 16);
header.writeUInt16LE(1, 20);
header.writeUInt16LE(1, 22);
header.writeUInt32LE(sr, 24);
header.writeUInt32LE(sr * 2, 28);
header.writeUInt16LE(2, 32);
header.writeUInt16LE(16, 34);
header.write('data', 36);
header.writeUInt32LE(pcm.length, 40);

const out = path.join(__dirname, '..', 'public', 'sounds', 'jumpscare-scream.wav');
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, Buffer.concat([header, pcm]));

console.log(`wrote ${out} (${44 + pcm.length} bytes, peak ${peak.toFixed(3)})`);

"""Create the promotion's original music/effects. Requires numpy and ffmpeg."""

from pathlib import Path
import subprocess
import wave

import numpy as np


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "public/audio/golden-egg"
WORK = ROOT / "storage/app/private/golden-egg-preview/audio-source"
RATE = 44100
RNG = np.random.default_rng(20261015)
OUTPUT.mkdir(parents=True, exist_ok=True)
WORK.mkdir(parents=True, exist_ok=True)


def note(midi, seconds, softness=1.0):
    t = np.arange(int(seconds * RATE)) / RATE
    frequency = 440 * 2 ** ((midi - 69) / 12)
    phase = 2 * np.pi * frequency * t
    body = np.sin(phase + 0.45 * np.sin(2 * phase) * np.exp(-4 * t))
    body += 0.16 * np.sin(2 * phase) * np.exp(-2 * t)
    body += 0.035 * np.sin(3 * phase) * np.exp(-5 * t)
    envelope = (1 - np.exp(-t / 0.008)) * np.exp(-t / softness)
    envelope *= np.minimum(1, (seconds - t) / 0.1)
    return body * envelope


def place(track, signal, start, volume, pan=0.0, loop=False):
    indices = np.arange(len(signal)) + round(start * RATE)
    if loop:
        indices %= len(track)
    else:
        keep = indices < len(track)
        indices, signal = indices[keep], signal[keep]
    gains = np.sqrt([(1 - pan) / 2, (1 + pan) / 2])
    track[indices] += signal[:, None] * gains * volume


def export(name, signal, peak):
    # A short stereo room tail; music tails wrap into the start for a clean loop.
    wet = signal.copy()
    for delay, amount in [(0.083, 0.11), (0.157, 0.07), (0.263, 0.04)]:
        shift = round(delay * RATE)
        if name == "ambience":
            wet += np.roll(signal[:, ::-1], shift, axis=0) * amount
        else:
            wet[shift:] += signal[:-shift, ::-1] * amount
    wet *= peak / max(0.001, np.max(np.abs(wet)))
    pcm = (wet * 32767).astype("<i2")
    source = WORK / f"{name}.wav"
    with wave.open(str(source), "wb") as audio:
        audio.setnchannels(2)
        audio.setsampwidth(2)
        audio.setframerate(RATE)
        audio.writeframes(pcm.tobytes())
    subprocess.run([
        "ffmpeg", "-hide_banner", "-loglevel", "error", "-y", "-i", str(source),
        "-codec:a", "libmp3lame", "-b:a", "128k", "-ar", str(RATE),
        "-map_metadata", "-1", str(OUTPUT / f"{name}.mp3"),
    ], check=True)
    print(f"{name}: {len(signal) / RATE:.3f}s, peak {np.max(np.abs(wet)):.3f}")


# Eight bars at 72 BPM: mellow electric piano, extended chords, soft bass and brushes.
beat = 60 / 72
length = 32 * beat
music = np.zeros((round(length * RATE), 2))
chords = [
    [48, 55, 59, 62, 64], [45, 52, 55, 59, 60],
    [50, 57, 60, 64, 65], [43, 53, 57, 59, 64],
    [48, 55, 59, 62, 64], [45, 52, 55, 59, 64],
    [50, 57, 60, 64, 65], [43, 53, 57, 59, 62],
]
melodies = [[76, 74], [71, 72], [69, 72], [71, 69], [76, 79], [76, 72], [74, 72], [71, 74]]
for bar, chord in enumerate(chords):
    start = bar * 4 * beat
    place(music, note(chord[0] - 12, 2.8, 0.85), start, 0.24, loop=True)
    for index, pitch in enumerate(chord[1:]):
        pan = -0.35 + index * 0.23
        place(music, note(pitch, 4.2, 1.3), start + index * 0.035, 0.09, pan, True)
        place(music, note(pitch, 2.9, 0.95), start + 2.5 * beat + index * 0.018, 0.038, -pan, True)
    for pitch, offset in zip(melodies[bar], [1.35, 3.1]):
        place(music, note(pitch, 2.4, 0.8), start + offset * beat, 0.065, 0.16, True)
    for offset in [0.58, 1.58, 2.58, 3.58]:
        brush = RNG.normal(0, 1, int(0.09 * RATE))
        brush = np.diff(brush, prepend=0) * np.exp(-np.arange(len(brush)) / (RATE * 0.018))
        place(music, brush, start + offset * beat, 0.0035, -0.2, True)
export("ambience", music, 0.66)

# A solid hammer contact with a brief muted metallic resonance.
t = np.arange(round(0.48 * RATE)) / RATE
hit = np.sin(2 * np.pi * (115 * t + 14 * (1 - np.exp(-28 * t)))) * np.exp(-25 * t)
hit += 0.22 * np.sin(2 * np.pi * 710 * t) * np.exp(-15 * t)
hit += 0.1 * np.sin(2 * np.pi * 1433 * t) * np.exp(-20 * t)
hit += RNG.normal(0, 0.28, len(t)) * np.exp(-110 * t)
export("hammer-hit", np.column_stack([hit, hit]), 0.82)

# Several tiny brittle fractures and falling fragments, rather than a single pop.
crack = np.zeros((round(0.9 * RATE), 2))
for index, start in enumerate([0, 0.024, 0.061, 0.11, 0.18, 0.29, 0.43, 0.6]):
    t = np.arange(round(0.16 * RATE)) / RATE
    fragment = RNG.normal(0, 1, len(t))
    fragment = np.diff(fragment, prepend=0) * np.exp(-t * (75 - index * 3))
    fragment += 0.16 * np.sin(2 * np.pi * (1300 + index * 173) * t) * np.exp(-35 * t)
    place(crack, fragment, start, 0.3 * np.exp(-index * 0.16), RNG.uniform(-0.55, 0.55))
export("shell-crack", crack, 0.75)

# A short ascending major-nine chime with a soft, spacious tail.
reveal = np.zeros((round(2.2 * RATE), 2))
for index, pitch in enumerate([72, 76, 79, 83, 86]):
    place(reveal, note(pitch, 1.65, 0.8), index * 0.09, 0.18, -0.45 + index * 0.22)
export("prize-reveal", reveal, 0.72)

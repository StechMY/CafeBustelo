# Golden egg audio

Original synthesized music and effects created for Café Bustelo, without third-party recordings or samples. These files may be used, modified and distributed with this project and its native apps.

Regenerate with `python scripts/generate-golden-egg-audio.py` (NumPy and ffmpeg/libmp3lame). The deterministic source seed is 20261015. Working WAV masters are saved privately under `storage/app/private/golden-egg-preview/audio-source/`; MP3 distribution files are here.

| File | Duration | Usage |
| --- | --- | --- |
| `ambience.mp3` | 26.667 s decoded | Loop; original 72 BPM electric-piano café arrangement, eight bars, wrapped room tails |
| `hammer-hit.mp3` | 0.480 s decoded | Each of the three hammer contacts |
| `shell-crack.mp3` | 0.900 s decoded | Fracture layers; quieter first two contacts, full level at the final opening |
| `prize-reveal.mp3` | 2.200 s decoded | Ascending chime when the actual prize appears |

All files: stereo, 44.1 kHz, 128 kbit/s MP3. Recommended music gain 0.22; effects are mixed separately. Match cue times to the animation, respect the sound toggle, and pause on page/app background or exit. The App implementation details are in `docs/flutter-golden-egg-handoff.md`.

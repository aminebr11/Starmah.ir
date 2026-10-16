#!/usr/bin/env bash
# Whisper (large-v3, fa) check of each chosen narration take. Output: vo_raw/transcripts/L{n}.txt
HF=${HF_DIR:-/tmp/claude-0/-home-user/d9b15659-30bb-5d97-b55e-4e81155c08ab/scratchpad/hf}
HERE="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "$HERE/vo_raw/transcripts"
for n in "${@:-1 2 3 4 5 6 7}"; do
  for id in $n; do
    d=$(mktemp -d)
    ffmpeg -v error -y -i "$HERE/vo_raw/L$id.wav" -ar 16000 -ac 1 "$d/L$id.wav"
    (cd "$HF" && npx --no-install hyperframes transcribe "$d/L$id.wav" -d "$d/out" -m large-v3 -l fa --engine whisper --json --timeout 900000) > "$HERE/vo_raw/transcripts/L$id.json" 2>&1
    echo "L$id done"
  done
done

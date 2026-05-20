#!/bin/bash

INPUT="$1"

OUTPUT="${INPUT%.flv}.mp4"

ffmpeg -y \
    -i "$INPUT" \
    -c:v copy \
    -c:a aac \
    -movflags +faststart \
    "$OUTPUT"

if [ $? -eq 0 ]; then
    rm -f "$INPUT"
fi


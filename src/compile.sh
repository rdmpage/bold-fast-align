#!/bin/bash
# Compile the alignment tool with optimization

g++ -O3 -std=c++11 -o align align.cpp

if [ $? -eq 0 ]; then
    echo "Compilation successful! Binary created: ./align"
    echo "Usage: ./align <sequence1> <sequence2>"
else
    echo "Compilation failed!"
    exit 1
fi

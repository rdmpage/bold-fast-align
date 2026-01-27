# Smith-Waterman Alignment: PHP to C++ Port

## Overview

This project ports the computationally intensive Smith-Waterman alignment algorithm from PHP to C++ while maintaining the PHP workflow for file parsing and data processing. This hybrid approach provides significant performance improvements with minimal code changes.

## Files

### C++ Implementation
- **align.cpp** - C++ implementation of Smith-Waterman alignment
- **compile.sh** - Shell script to compile the C++ code
- **align** - Compiled binary (created after running compile.sh)

### PHP Scripts
- **parse_cpp.php** - Modified version of parse.php that uses C++ alignment
- **benchmark.php** - Performance comparison script
- **parse.php** - Original PHP implementation (unchanged)
- **swa.php** - Original PHP Smith-Waterman implementation (still needed for benchmark)

## Setup

### 1. Compile the C++ alignment tool

```bash
chmod +x compile.sh
./compile.sh
```

This creates the `align` binary with optimization flags (-O3).

### 2. Verify compilation

```bash
./align "GGTTGACTA" "TGTTACGG"
```

Expected output: `1,3 1,3`

## Usage

### Running the Hybrid PHP/C++ Pipeline

```bash
php parse_cpp.php
```

This script:
1. Reads reference sequences from macse.csv
2. Processes BOLD sequences from the TSV file
3. Calls the C++ `align` binary for each alignment
4. Outputs alignment spans

### Running the Benchmark

```bash
php benchmark.php
```

This compares the performance of:
- Pure PHP implementation (from swa.php)
- C++ implementation called from PHP

## Performance Expectations

Based on typical Smith-Waterman implementations:

- **Expected speedup**: 10-30x faster
- **Process overhead**: ~1-5ms per alignment call
- **Net benefit**: Significant for sequences >100bp

The benchmark will show actual numbers for your system.

## How It Works

### Data Flow

```
PHP Script (parse_cpp.php)
    ↓
Reads & parses data files
    ↓
For each sequence pair:
    ↓
Calls: ./align "SEQUENCE1" "SEQUENCE2"
    ↓
C++ performs alignment
    ↓
Returns: "start1,end1 start2,end2"
    ↓
PHP parses result and continues
```

### C++ Binary Interface

**Input**: Two sequences as command-line arguments
```bash
./align "ATCGATCG" "ATCGTCG"
```

**Output**: Space-separated coordinate pairs
```
0,7 0,6
```

Format: `seq1_start,seq1_end seq2_start,seq2_end`

## Advantages of This Approach

### ✅ Pros
1. **Easy to implement** - No PHP extensions to build
2. **Easy to debug** - C++ binary can be tested independently
3. **Portable** - Just compile on target system
4. **Significant speedup** - 10-30x faster despite process overhead
5. **Minimal code changes** - Most PHP code remains unchanged

### ⚠️ Considerations
1. **Process spawning overhead** - ~1-5ms per call (negligible for alignment work)
2. **Data serialization** - Sequences passed as strings (very fast)
3. **Platform dependency** - Need to recompile for different systems

## Further Optimization Options

If you need even more performance:

### Option 1: Batch Processing
Modify the C++ tool to accept multiple sequence pairs in one call:
```bash
./align --batch input.txt output.txt
```

### Option 2: PHP Extension
Build a native PHP extension for zero overhead:
- Eliminates process spawning
- Direct memory access
- ~2-3x additional speedup over standalone binary

### Option 3: Parallelization
The C++ code can easily be parallelized with OpenMP:
```cpp
#pragma omp parallel for
for (int i = 1; i <= m; i++) {
    // alignment code
}
```

## Troubleshooting

### "Failed to execute alignment binary"
- Ensure `align` binary exists in the same directory as the PHP script
- Check execute permissions: `chmod +x align`
- Try absolute path in parse_cpp.php

### Results don't match PHP version
- Run benchmark.php to compare outputs
- Check sequence cleaning is identical in both versions

### Performance not as expected
- Run benchmark.php to measure actual speedup
- Check if you're running in debug mode (compile with -O3 for optimization)
- Verify sequences are long enough (>100bp) to see benefits

## Migration Path

### Phase 1: Current (Hybrid Approach)
- PHP for I/O and data management
- C++ for alignment computation
- **Status**: Easy to implement, good speedup

### Phase 2: Full C++ Port (Optional)
- Port entire pipeline to C++
- **Benefit**: Maximum performance
- **Cost**: More development time

### Phase 3: Parallelization (Optional)
- Multi-threaded alignment processing
- **Benefit**: Linear speedup with CPU cores
- **Cost**: Code complexity

## System Requirements

- **C++ compiler**: g++ with C++11 support
- **PHP**: 7.0 or higher
- **Operating System**: Linux/Unix/macOS (Windows with MinGW also works)

## License

Same as original code.

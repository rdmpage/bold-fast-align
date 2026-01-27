<?php

// Benchmark: PHP vs C++ alignment

require_once('swa.php');

// Path to C++ alignment binary
define('ALIGN_BINARY', './align');

//----------------------------------------------------------------------------------------
// Call C++ alignment tool
function cpp_align($seq1, $seq2)
{
	$seq1_escaped = escapeshellarg($seq1);
	$seq2_escaped = escapeshellarg($seq2);
	
	$cmd = ALIGN_BINARY . " $seq1_escaped $seq2_escaped";
	$output = shell_exec($cmd);
	
	if ($output === null) {
		throw new Exception("Failed to execute alignment binary");
	}
	
	$output = trim($output);
	$parts = explode(' ', $output);
	
	$seq1_span = explode(',', $parts[0]);
	$seq2_span = explode(',', $parts[1]);
	
	return array(
		'seq1' => array((int)$seq1_span[0], (int)$seq1_span[1]),
		'seq2' => array((int)$seq2_span[0], (int)$seq2_span[1])
	);
}

//----------------------------------------------------------------------------------------

// Test sequences from the original code
$seq1 = 'ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
$seq2 = 'AATATAAGATTTTGATTACTNCCCCCCTCTCTAACATTATTAATTTCAAGAAGAATTGTAGAAAATGGGGCAGGT';

// Longer sequences for better benchmarking
$long_seq1 = str_repeat($seq1, 15); // ~1500 bp
$long_seq2 = str_repeat($seq2, 13); // ~1000 bp

// Number of iterations
$iterations = 100;

echo "Benchmark: PHP vs C++ Smith-Waterman Alignment\n";
echo "==============================================\n\n";

// Benchmark PHP implementation
echo "Testing PHP implementation...\n";
$start_php = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
	$alignment = swa('ref', 'seq', $long_seq1, $long_seq2);
}

$end_php = microtime(true);
$time_php = $end_php - $start_php;

echo "PHP: {$iterations} alignments in " . number_format($time_php, 3) . " seconds\n";
echo "     Average: " . number_format($time_php / $iterations * 1000, 2) . " ms per alignment\n\n";

// Benchmark C++ implementation
echo "Testing C++ implementation...\n";
$start_cpp = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
	$alignment = cpp_align($long_seq1, $long_seq2);
}

$end_cpp = microtime(true);
$time_cpp = $end_cpp - $start_cpp;

echo "C++: {$iterations} alignments in " . number_format($time_cpp, 3) . " seconds\n";
echo "     Average: " . number_format($time_cpp / $iterations * 1000, 2) . " ms per alignment\n\n";

// Calculate speedup
$speedup = $time_php / $time_cpp;

echo "Results:\n";
echo "--------\n";
echo "Speedup: " . number_format($speedup, 1) . "x faster\n";
echo "Time saved per alignment: " . number_format(($time_php - $time_cpp) / $iterations * 1000, 2) . " ms\n\n";

// Verify results match
echo "Verification:\n";
echo "-------------\n";
$php_result = swa('ref', 'seq', $seq1, $seq2);
$cpp_result = cpp_align($seq1, $seq2);

echo "PHP spans: [{$php_result->spans[0][0]},{$php_result->spans[0][1]}],[{$php_result->spans[1][0]},{$php_result->spans[1][1]}]\n";
echo "C++ spans: [{$cpp_result['seq1'][0]},{$cpp_result['seq1'][1]}],[{$cpp_result['seq2'][0]},{$cpp_result['seq2'][1]}]\n";

if ($php_result->spans[0][0] == $cpp_result['seq1'][0] &&
    $php_result->spans[0][1] == $cpp_result['seq1'][1] &&
    $php_result->spans[1][0] == $cpp_result['seq2'][0] &&
    $php_result->spans[1][1] == $cpp_result['seq2'][1]) {
    echo "✓ Results match!\n";
} else {
    echo "✗ Results differ - implementation may need adjustment\n";
}

?>

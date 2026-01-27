<?php

// Parse BOLD file and explore alignments - using C++ alignment for speed

ini_set('memory_limit', '-1');

// Path to C++ alignment binary
define('ALIGN_BINARY', './src/align');

//----------------------------------------------------------------------------------------
// http://stackoverflow.com/a/5996888/9684
function translate_quoted($string) {
  $search  = array("\\t", "\\n", "\\r");
  $replace = array( "\t",  "\n",  "\r");
  return str_replace($search, $replace, $string);
}

//----------------------------------------------------------------------------------------
// Clean sequence (to match what C++ does)
function swa_clean_sequence($sequence)
{
	$sequence = strtoupper($sequence);
	
	// whitespace and numbers
	$sequence = preg_replace('/[0-9\s]/', '', $sequence);
	$sequence = preg_replace('/\R/u', '', $sequence);
	
	// replace ambiguity codes with 'N';	
	$sequence = preg_replace('/[RYKMSWBHNDV]/', 'N', $sequence);

	// remove gaps
	$sequence = preg_replace('/-/', '', $sequence);
	
	// remove crap
	$sequence = preg_replace('/I/', '', $sequence);

	return $sequence;
}

//----------------------------------------------------------------------------------------
// Call C++ alignment tool
function cpp_align($seq1, $seq2)
{
	// Escape sequences for shell
	$seq1_escaped = escapeshellarg($seq1);
	$seq2_escaped = escapeshellarg($seq2);
	
	// Call C++ binary
	$cmd = ALIGN_BINARY . " $seq1_escaped $seq2_escaped";
	$output = shell_exec($cmd);
	
	if ($output === null) {
		throw new Exception("Failed to execute alignment binary");
	}
	
	// Parse output: "seq1_start,seq1_end seq2_start,seq2_end"
	$output = trim($output);
	$parts = explode(' ', $output);
	
	if (count($parts) != 2) {
		throw new Exception("Invalid output from alignment binary: $output");
	}
	
	$seq1_span = explode(',', $parts[0]);
	$seq2_span = explode(',', $parts[1]);
	
	return array(
		'seq1' => array((int)$seq1_span[0], (int)$seq1_span[1]),
		'seq2' => array((int)$seq2_span[0], (int)$seq2_span[1])
	);
}

//----------------------------------------------------------------------------------------
// Step 1. Get list of reference sequences for major taxonomic groups

$reference_family  = [];
$reference_order   = [];
$reference_class   = [];
$reference_phylum  = [];
$reference_kingdom = [];
$reference_acc     = [];
$reference_seq     = [];
$reference_len     = [];

$row_count = 0;

$filename = "macse.csv";

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
		
	$row = fgetcsv(
		$file_handle, 
		0, 
		translate_quoted(','),
		translate_quoted('"') 
		);
	
	$go = is_array($row) && count($row) > 1;
	
	if ($go)
	{
		if ($row_count == 0)
		{
			$headings = $row;		
		}
		else
		{
			$data = new stdclass;
		
			foreach ($row as $k => $v)
			{
				if (trim($v) != '' && $v != "None")
				{
					$data->{$headings[$k]} = $v;
				}
			}
			
			// get key
			$key = $data->taxon;
			
			if (isset($data->bold_taxon))
			{
				$key = $data->bold_taxon;
			}
			
			switch ($data->bold_taxon_rank)
			{
				case 'family':
					$reference_family[$key] = $data->ref_accession;
					break;
					
				case 'order':
					$reference_order[$key] = $data->ref_accession;
					break;
				
				case 'class':
					$reference_class[$key] = $data->ref_accession;
					break;
				
				case 'phylum':
				default:
					$reference_phylum[$key] = $data->ref_accession;
					break;
			}
			
			$reference_seq[$data->ref_accession] = $data->ref_sequence;
			$reference_len[$data->ref_accession] = strlen($data->ref_sequence);
		}
	}	
	
	$row_count++;
}	

// Step 2. Read BOLD TSV file and align each sequence

$headings = array();

$debug = false;

$row_count = 0;

$filename = "BOLD_Public.06-Sep-2024/BOLD_Public.06-Sep-2024.tsv";

// Check if file exists
if (!file_exists($filename)) {
	echo "Error: Cannot find $filename\n";
	echo "Please ensure the BOLD data file is in the correct location.\n";
	exit(1);
}

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$line = trim(fgets($file_handle));
		
	$row = explode("\t",$line);
	
	$go = is_array($row) && count($row) > 1;
	
	if ($go)
	{
		if ($row_count == 0)
		{
			$headings = $row;		
		}
		else
		{
			$data = new stdclass;
		
			foreach ($row as $k => $v)
			{
				if (trim($v) != '' && $v != "None")
				{
					$data->{$headings[$k]} = $v;
				}
			}
		
			// get spans of alignment w.r.t. reference sequence
			if (isset($data->nuc) && $data->marker_code == 'COI-5P')
			{
				// Default reference sequence is Drosophila NC_046603
				$ref_acc = 'NC_046603';
				
				if (isset($data->family))
				{
					if (isset($reference_family[$data->family]))
					{
						$ref_acc = $reference_family[$data->family];
					}
				}
				elseif (isset($data->order))
				{
					if (isset($reference_order[$data->order]))
					{
						$ref_acc = $reference_order[$data->order];
					}
				}
				elseif (isset($data->class))
				{
					if (isset($reference_class[$data->class]))
					{
						$ref_acc = $reference_class[$data->class];
					}
				}
				elseif (isset($data->phylum))
				{
					if (isset($reference_phylum[$data->phylum]))
					{
						$ref_acc = $reference_phylum[$data->phylum];
					}
				}
				elseif (isset($data->kingdom))
				{
					if (isset($reference_kingdom[$data->kingdom]))
					{
						$ref_acc = $reference_kingdom[$data->kingdom];
					}
				}				
				
				$seq1 = $reference_seq[$ref_acc];
				$seq1 = swa_clean_sequence($seq1);
				$seq2 = swa_clean_sequence($data->nuc);
				
				// align just start and end parts of barcode (speeds things up)
				$sequence_length = strlen($seq2);
				$subsequence_length = 100;
				
				$spans = array(
					[0, 0], [0, 0]
				);
										
				// align start of barcode using C++
				$prefix = substr($seq2, 0, $subsequence_length);
				
				try {
					$alignment = cpp_align($seq1, $prefix);
					
					$spans[0][0] = $alignment['seq1'][0];
					$spans[1][0] = $alignment['seq2'][0];
					
					// align end of barcode using C++
					$suffix = substr($seq2, -$subsequence_length);
					$suffix_length = strlen($suffix);
					
					$alignment = cpp_align($seq1, $suffix);
					
					$spans[0][1] = $alignment['seq1'][1];
					$spans[1][1] = $sequence_length - $suffix_length + $alignment['seq2'][1];
					
					echo $data->processid . " " . $ref_acc . " " . $reference_len[$ref_acc] 
					     . " [" . join(",", $spans[0]) . "],[" . join(",", $spans[1]) . "]\n";
				}
				catch (Exception $e) {
					echo "Error aligning {$data->processid}: " . $e->getMessage() . "\n";
				}
			}
		}
	}	
	
	$row_count++;
	
	// Process fewer records for testing
	if ($row_count > 10)
	{
		break;
	}
}	

echo "\nProcessed $row_count records\n";

?>

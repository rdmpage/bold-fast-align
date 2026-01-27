#include <iostream>
#include <string>
#include <vector>
#include <algorithm>
#include <cctype>

using namespace std;

// Alignment weights
const int MATCH = 3;
const int MISMATCH = -1;
const int DELETION = -6;
const int INSERTION = -6;

struct AlignmentResult {
    int score;
    int seq1_start;
    int seq1_end;
    int seq2_start;
    int seq2_end;
};

// Clean sequence (convert to uppercase, remove ambiguity codes, gaps, etc.)
string clean_sequence(const string& seq) {
    string cleaned;
    cleaned.reserve(seq.length());
    
    for (char c : seq) {
        char upper_c = toupper(c);
        
        // Skip whitespace, numbers, gaps, and 'I'
        if (isspace(upper_c) || isdigit(upper_c) || upper_c == '-' || upper_c == 'I') {
            continue;
        }
        
        // Replace ambiguity codes with 'N'
        if (upper_c == 'R' || upper_c == 'Y' || upper_c == 'K' || 
            upper_c == 'M' || upper_c == 'S' || upper_c == 'W' || 
            upper_c == 'B' || upper_c == 'H' || upper_c == 'D' || 
            upper_c == 'V') {
            cleaned += 'N';
        }
        // Keep valid nucleotides (A, T, G, C, N)
        else if (upper_c == 'A' || upper_c == 'T' || upper_c == 'G' || 
                 upper_c == 'C' || upper_c == 'N') {
            cleaned += upper_c;
        }
    }
    
    return cleaned;
}

// Smith-Waterman local alignment
AlignmentResult smith_waterman(const string& seq1, const string& seq2) {
    int m = seq1.length();
    int n = seq2.length();
    
    // Create scoring matrix H and predecessor matrix P
    vector<vector<int>> H(m + 1, vector<int>(n + 1, 0));
    vector<vector<char>> P(m + 1, vector<char>(n + 1, ' '));
    
    int max_score = 0;
    int max_i = 0;
    int max_j = 0;
    
    // Fill matrices
    for (int i = 1; i <= m; i++) {
        for (int j = 1; j <= n; j++) {
            int match_score = H[i-1][j-1];
            
            // Score for match/mismatch
            if (toupper(seq1[i-1]) == toupper(seq2[j-1])) {
                match_score += MATCH;
            } else {
                match_score += MISMATCH;
            }
            
            int delete_score = H[i-1][j] + DELETION;
            int insert_score = H[i][j-1] + INSERTION;
            
            // Find maximum
            int max_val = 0;
            char pred = ' ';
            
            if (match_score > max_val) {
                max_val = match_score;
                pred = 'D';  // Diagonal
            }
            if (delete_score > max_val) {
                max_val = delete_score;
                pred = 'U';  // Up
            }
            if (insert_score > max_val) {
                max_val = insert_score;
                pred = 'L';  // Left
            }
            
            H[i][j] = max_val;
            P[i][j] = pred;
            
            // Track maximum score position
            if (H[i][j] > max_score) {
                max_score = H[i][j];
                max_i = i;
                max_j = j;
            }
        }
    }
    
    // Traceback to find alignment span
    int i = max_i;
    int j = max_j;
    
    while (i > 0 && j > 0 && H[i][j] > 0) {
        char pred = P[i][j];
        if (pred == 'D') {
            i--;
            j--;
        } else if (pred == 'U') {
            i--;
        } else if (pred == 'L') {
            j--;
        } else {
            break;
        }
    }
    
    AlignmentResult result;
    result.score = max_score;
    result.seq1_start = i;
    result.seq1_end = max_i - 1;
    result.seq2_start = j;
    result.seq2_end = max_j - 1;
    
    return result;
}

int main(int argc, char* argv[]) {
    if (argc != 3) {
        cerr << "Usage: " << argv[0] << " <seq1> <seq2>" << endl;
        return 1;
    }
    
    string seq1 = clean_sequence(argv[1]);
    string seq2 = clean_sequence(argv[2]);
    
    AlignmentResult result = smith_waterman(seq1, seq2);
    
    // Output in format: seq1_start,seq1_end seq2_start,seq2_end
    cout << result.seq1_start << "," << result.seq1_end 
         << " " << result.seq2_start << "," << result.seq2_end << endl;
    
    return 0;
}

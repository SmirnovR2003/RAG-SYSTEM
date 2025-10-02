<?php

function cosineSimilarity($vec1, $vec2) {
    $dot = 0;
    $norm1 = 0;
    $norm2 = 0;
    foreach ($vec1 as $key => $val1) {
        $val2 = isset($vec2[$key]) ? $vec2[$key] : 0;
        $dot += $val1 * $val2;
        $norm1 += $val1 * $val1;
        $norm2 += $val2 * $val2;
    }
    if ($norm1 == 0 || $norm2 == 0) return 0;
    return $dot / (sqrt($norm1) * sqrt($norm2));
}

function tfidfVectorize($texts) {
    $allWords = [];
    $tf = [];
    $df = [];
    foreach ($texts as $i => $text) {
        $words = preg_split('/\s+/', strtolower($text));
        $wordCount = array_count_values($words);
        $tf[$i] = $wordCount;
        foreach ($wordCount as $word => $count) {
            if (!isset($df[$word])) $df[$word] = 0;
            $df[$word]++;
            $allWords[$word] = true;
        }
    }
    $idf = [];
    $N = count($texts);
    foreach ($allWords as $word => $dummy) {
        $idf[$word] = log($N / (1 + $df[$word]));
    }
    $vectors = [];
    foreach ($tf as $i => $wordCount) {
        $vector = [];
        foreach ($allWords as $word => $dummy) {
            $tfVal = isset($wordCount[$word]) ? $wordCount[$word] : 0;
            $vector[$word] = $tfVal * $idf[$word];
        }
        $vectors[$i] = $vector;
    }
    return [$vectors, array_keys($allWords)];
}

$docs = file_get_contents('data/bitrix_docs.txt');
$docs = preg_replace('/\s+/', ' ', $docs); // Normalize whitespace

function chunkText($text, $maxChunkSize = 50) {
    $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $currentChunk = '';
    foreach ($sentences as $sentence) {
        if (strlen($currentChunk . ' ' . $sentence) > $maxChunkSize) {
            if ($currentChunk !== '') {
                $chunks[] = trim($currentChunk);
            }
            $currentChunk = $sentence;
        } else {
            $currentChunk .= ' ' . $sentence;
        }
    }
    if ($currentChunk !== '') {
        $chunks[] = trim($currentChunk);
    }
    return $chunks;
}

$chunks = chunkText($docs);
list($vectors, $vocab) = tfidfVectorize($chunks);
$chunkVectors = [];
foreach ($chunks as $i => $chunk) {
    $chunkVectors[] = ['chunk' => $chunk, 'embedding' => $vectors[$i]];
}


while (true) {
    echo "Введите запрос: ";
    $query = trim(fgets(STDIN));
    if ($query == 'quit') break;

    // Vectorize query
    $queryWords = preg_split('/\s+/', strtolower($query));
    $queryWordCount = array_count_values($queryWords);
    $queryVector = [];
    foreach ($vocab as $word) {
        $tfVal = isset($queryWordCount[$word]) ? $queryWordCount[$word] : 0;
        // For simplicity, use IDF from docs or 0 if missing
        $idfVal = 0;
        foreach ($chunkVectors as $cv) {
            if (isset($cv['embedding'][$word])) {
                $idfVal = max($idfVal, log(count($chunkVectors) / (1 + 1))); // approximate
            }
        }
        $queryVector[$word] = $tfVal * $idfVal;
    }
    // Compute similarities
    $similarities = [];
    foreach ($chunkVectors as $vec) {
        $sim = cosineSimilarity($queryVector, $vec['embedding']);
        $similarities[] = ['sim' => $sim, 'chunk' => $vec['chunk']];
    }
    usort($similarities, fn($a, $b) => $b['sim'] <=> $a['sim']);
    $context = implode("\n", array_slice(array_column($similarities, 'chunk'), 0, 3));

    // Simple generation: use the top retrieved chunk as the answer
    $topChunk = $similarities[0]['chunk'];
    print_r($context);
    echo "\n\n";
    echo "Ответ: $topChunk\n\n";
}
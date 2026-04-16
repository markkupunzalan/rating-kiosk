<?php
/**
 * feedback.php — Feedback endpoint
 *
 * GET    /api/feedback.php                    → paginated list with filters
 * GET    /api/feedback.php?export=csv         → CSV download
 * POST   /api/feedback.php                    → submit new feedback
 * POST   /api/feedback.php?action=recalculate → re-score all rows (admin only)
 * DELETE /api/feedback.php                    → delete by id
 */

header('Content-Type: application/json');
// SEC-2 FIX: Removed wildcard CORS — this endpoint uses session auth.
// Same-origin requests work without CORS headers; wildcard would allow
// cross-site requests that piggyback on the admin's session cookie.
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: send JSON response ────────────────────────────────
function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function failWith(string $msg, int $code = 400): void
{
    respond(['success' => false, 'error' => $msg], $code);
}

// ─────────────────────────────────────────────────────────────
//  Shared Sentiment Classifier
//  Used by both new submissions and bulk-recalculate.
// ─────────────────────────────────────────────────────────────
/**
 * Compute the canonical sentiment for a set of 1-5 ratings + comment.
 *
 * STAR ANCHOR — applied first, covers all clear-cut cases:
 *   avg >= 3.5  → positive   ("mostly good": e.g. 3×Good + 2×Neutral = 3.6★)
 *   avg <= 2.5  → negative   ("mostly bad":  e.g. 2×Bad  + 3×Neutral = 2.6★ → negative is debatable
 *                              but avg=2.5 means at best all 3s with two 1s, clearly poor)
 *   2.5 < avg < 3.5  → nuanced scoring (genuinely ambiguous middle band)
 *
 * NUANCED BAND thresholds (after weighted score + variance + keyword adjustments):
 *   score >= 3.0  → positive
 *   score >= 2.5  → neutral
 *   score <  2.5  → negative
 *
 * Star rating → expected sentiment cheat-sheet:
 *   5★ all         avg 5.0  → positive  (anchor)
 *   4★ all         avg 4.0  → positive  (anchor)
 *   3×4★ + 2×3★   avg 3.6  → positive  (anchor)
 *   3×3★ + 2×4★   avg 3.4  → nuanced  (weighted score decides)
 *   3★ all         avg 3.0  → nuanced
 *   2★ all         avg 2.0  → negative  (anchor)
 *   1★ all         avg 1.0  → negative  (anchor)
 */
function computeSentiment(int $q1, int $q2, int $q3, int $q4, int $q5, string $comment): string
{
    $ratings = [$q1, $q2, $q3, $q4, $q5];
    $avg = ($q1 + $q2 + $q3 + $q4 + $q5) / 5.0;

    // Fast-path: star anchor
    if ($avg >= 3.5)
        return 'positive';
    if ($avg <= 2.5)
        return 'negative';

    // Nuanced band: 2.5 < avg < 3.5
    $weightedScore = ($q1 + $q2 + $q3 + $q4 + ($q5 * 2)) / 6.0;

    $variance = 0.0;
    foreach ($ratings as $r) {
        $variance += pow($r - $avg, 2);
    }
    $variance /= 5.0;
    $adjustedScore = $weightedScore - ($variance * 0.05);

    // Critical-floor: two or more 1s → always negative
    if (count(array_filter($ratings, fn($r) => $r === 1)) >= 2)
        return 'negative';

    // Keyword sentiment nudge (±0.20 max)
    $text = mb_strtolower(trim($comment));
    $bonus = 0.0;

    foreach ([
        'great',
        'excellent',
        'amazing',
        'awesome',
        'fantastic',
        'wonderful',
        'love',
        'loved',
        'perfect',
        'outstanding',
        'superb',
        'brilliant',
        'impressed',
        'happy',
        'satisfied',
        'pleased',
        'helpful',
        'friendly',
        'fast',
        'clean',
        'recommend',
        'best',
        'nice',
        'good',
        'thank',
        'thanks',
        'appreciate',
        'well done',
        'keep it up',
        'top',
        'exceptional',
        'delightful',
        'enjoyable',
        'smooth',
        'efficient',
        'quality',
        'professional',
        'polite',
    ] as $kw) {
        if ($text !== '' && mb_strpos($text, $kw) !== false)
            $bonus += 0.07;
    }
    foreach ([
        'bad',
        'terrible',
        'awful',
        'horrible',
        'worst',
        'poor',
        'dirty',
        'slow',
        'rude',
        'unfriendly',
        'unhelpful',
        'disappointed',
        'disappointing',
        'disgusting',
        'unacceptable',
        'broken',
        'wrong',
        'mistake',
        'error',
        'problem',
        'issue',
        'complaint',
        'never',
        'hate',
        'hated',
        'waited',
        'waiting',
        'long wait',
        'waste',
        'expensive',
        'overpriced',
        'cold',
        'stale',
        'disgusted',
        'unprofessional',
        'impolite',
        'careless',
        'ignored',
        'mediocre',
        'lacking',
    ] as $kw) {
        if ($text !== '' && mb_strpos($text, $kw) !== false)
            $bonus -= 0.08;
    }

    $finalScore = $adjustedScore + max(-0.5, min(0.5, $bonus));
    if ($finalScore >= 4.0)
        return 'positive';
    if ($finalScore >= 3.0)
        return 'neutral';
    return 'negative';
}

// ─────────────────────────────────────────────────────────────
//  POST — Submit new feedback
//  POST ?action=recalculate — Re-score all existing rows (admin)
// ─────────────────────────────────────────────────────────────
if ($method === 'POST') {

    // ── Bulk recalculate action ───────────────────────────────
    if (isset($_GET['action']) && $_GET['action'] === 'recalculate') {
        require_once __DIR__ . '/authMiddleware.php';

        $rows = $conn->query(
            'SELECT id, q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, comment FROM feedback'
        );
        if (!$rows) {
            failWith('DB query failed: ' . $conn->error, 500);
        }

        $total = 0;
        $updated = 0;
        $errors = [];

        $stmt = $conn->prepare('UPDATE feedback SET sentiment = ? WHERE id = ?');
        if (!$stmt) {
            failWith('DB prepare failed: ' . $conn->error, 500);
        }

        while ($row = $rows->fetch_assoc()) {
            $total++;
            $newSentiment = computeSentiment(
                (int) $row['q1_rating'],
                (int) $row['q2_rating'],
                (int) $row['q3_rating'],
                (int) $row['q4_rating'],
                (int) $row['q5_rating'],
                (string) ($row['comment'] ?? '')
            );
            $stmt->bind_param('si', $newSentiment, $row['id']);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0)
                    $updated++;
            } else {
                $errors[] = $row['id'];
            }
        }
        $stmt->close();

        respond([
            'success' => true,
            'total' => $total,
            'updated' => $updated,
            'errors' => $errors,
            'message' => "Recalculated $total records. $updated sentiment labels updated.",
        ]);
    }

    // ── Normal submission ─────────────────────────────────────
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!$body) {
        failWith('Invalid JSON body');
    }

    $fields = ['q1_rating', 'q2_rating', 'q3_rating', 'q4_rating', 'q5_rating'];
    foreach ($fields as $f) {
        $v = isset($body[$f]) ? intval($body[$f]) : 0;
        if ($v < 1 || $v > 5) {
            failWith("Field $f must be 1–5");
        }
    }

    $q1 = intval($body['q1_rating']);
    $q2 = intval($body['q2_rating']);
    $q3 = intval($body['q3_rating']);
    $q4 = intval($body['q4_rating']);
    $q5 = intval($body['q5_rating']);

    $comment = isset($body['comment']) ? mb_substr(trim($body['comment']), 0, 300) : '';
    $language = isset($body['language']) ? mb_substr(trim($body['language']), 0, 10) : 'en';
    if (!$language)
        $language = 'en';

    $sentiment = computeSentiment($q1, $q2, $q3, $q4, $q5, $comment);

    $stmt = $conn->prepare(
        "INSERT INTO feedback (q1_rating, q2_rating, q3_rating, q4_rating, q5_rating,
                               sentiment, comment, language)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        failWith('DB prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('iiiiisss', $q1, $q2, $q3, $q4, $q5, $sentiment, $comment, $language);
    if (!$stmt->execute()) {
        failWith('DB execute failed: ' . $stmt->error, 500);
    }

    $insertId = $stmt->insert_id;
    $stmt->close();

    respond(['success' => true, 'id' => $insertId, 'sentiment' => $sentiment], 201);
}

// ─────────────────────────────────────────────────────────────
//  DELETE — Remove a feedback entry
// ─────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    require_once __DIR__ . '/authMiddleware.php';

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $id = isset($body['id']) ? intval($body['id']) : 0;

    if ($id <= 0) {
        failWith('Invalid id');
    }

    $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    if (!$stmt) {
        failWith('DB prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        failWith('DB execute failed: ' . $stmt->error, 500);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        failWith('Record not found', 404);
    }
    respond(['success' => true]);
}

// ─────────────────────────────────────────────────────────────
//  GET — List feedback (paginated, filtered)
// ─────────────────────────────────────────────────────────────
if ($method === 'GET') {
    require_once __DIR__ . '/authMiddleware.php';

    // CSV export mode
    $exportCSV = isset($_GET['export']) && $_GET['export'] === 'csv';

    // Filter params
    $search = trim($_GET['search'] ?? '');
    $rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
    $sentiment = trim($_GET['sentiment'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Build WHERE clause dynamically
    $where = [];
    $types = '';
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(comment LIKE ? OR language LIKE ?)";
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }
    if ($rating > 0) {
        $where[] = "ROUND(overall_rating) = ?";
        $types .= 'i';
        $params[] = $rating;
    }
    if (in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
        $where[] = "sentiment = ?";
        $types .= 's';
        $params[] = $sentiment;
    }
    if ($from !== '') {
        $where[] = "DATE(submitted_at) >= ?";
        $types .= 's';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = "DATE(submitted_at) <= ?";
        $types .= 's';
        $params[] = $to;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ── CSV export ───────────────────────────────────────────
    if ($exportCSV) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="feedback-export.csv"');

        // Calculate summary data
        $sqlSummary = "SELECT q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, sentiment FROM feedback $whereSQL";
        $stmtSummary = $conn->prepare($sqlSummary);
        if ($types && $params) {
            $stmtSummary->bind_param($types, ...$params);
        }
        $stmtSummary->execute();
        $resSummary = $stmtSummary->get_result();

        $questionCounts = ['q1' => [1=>0,2=>0,3=>0,4=>0,5=>0], 'q2' => [1=>0,2=>0,3=>0,4=>0,5=>0], 'q3' => [1=>0,2=>0,3=>0,4=>0,5=>0], 'q4' => [1=>0,2=>0,3=>0,4=>0,5=>0], 'q5' => [1=>0,2=>0,3=>0,4=>0,5=>0]];
        $sentimentCounts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        $totalRecords = 0;

        while ($row = $resSummary->fetch_assoc()) {
            $totalRecords++;
            for ($i = 1; $i <= 5; $i++) {
                $rating = $row["q{$i}_rating"];
                if ($rating >= 1 && $rating <= 5) {
                    $questionCounts["q{$i}"][$rating]++;
                }
            }
            $sentiment = $row['sentiment'];
            if (isset($sentimentCounts[$sentiment])) {
                $sentimentCounts[$sentiment]++;
            }
        }
        $stmtSummary->close();

        $out = fopen('php://output', 'w');

        // Question Breakdown Section
        fputcsv($out, ['Question Breakdown']);
        fputcsv($out, ['Response', 'Cleanliness', 'Staff', 'Speed', 'Quality', 'Overall', 'Total']);
        $ratingLabels = [1 => 'Very Bad', 2 => 'Bad', 3 => 'Neutral', 4 => 'Good', 5 => 'Excellent'];
        for ($r = 1; $r <= 5; $r++) {
            $row = [$ratingLabels[$r]];
            $rowTotal = 0;
            for ($q = 1; $q <= 5; $q++) {
                $count = $questionCounts["q{$q}"][$r];
                $row[] = $count;
                $rowTotal += $count;
            }
            $row[] = $rowTotal;
            fputcsv($out, $row);
        }
        // Total row
        $totalRow = ['Total'];
        $grandTotal = 0;
        for ($q = 1; $q <= 5; $q++) {
            $colTotal = array_sum($questionCounts["q{$q}"]);
            $totalRow[] = $colTotal;
            $grandTotal += $colTotal;
        }
        $totalRow[] = $grandTotal;
        fputcsv($out, $totalRow);

        // Empty row
        fputcsv($out, []);

        // Overall Sentiment Section
        fputcsv($out, ['Overall Sentiment']);
        fputcsv($out, ['Sentiment', 'Count']);
        fputcsv($out, ['Positive', $sentimentCounts['positive']]);
        fputcsv($out, ['Neutral', $sentimentCounts['neutral']]);
        fputcsv($out, ['Negative', $sentimentCounts['negative']]);
        fputcsv($out, ['Total', $totalRecords]);

        // Empty row
        fputcsv($out, []);

        // Individual Records Section
        fputcsv($out, ['Individual Records']);
        fputcsv($out, ['ID', 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Overall', 'Sentiment', 'Comment', 'Language', 'Submitted At']);

        $sql = "SELECT id, q1_rating, q2_rating, q3_rating, q4_rating, q5_rating,
                        overall_rating, sentiment, comment, language, submitted_at
                 FROM feedback $whereSQL ORDER BY submitted_at DESC";
        $stmt = $conn->prepare($sql);
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['q1_rating'],
                $row['q2_rating'],
                $row['q3_rating'],
                $row['q4_rating'],
                $row['q5_rating'],
                $row['overall_rating'],
                $row['sentiment'],
                $row['comment'],
                $row['language'],
                $row['submitted_at']
            ]);
        }
        fclose($out);
        $stmt->close();
        exit;
    }

    // ── Count total matching rows ────────────────────────────
    $countSQL = "SELECT COUNT(*) AS total FROM feedback $whereSQL";
    $stmtCount = $conn->prepare($countSQL);
    if ($types && $params) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $total = $stmtCount->get_result()->fetch_assoc()['total'];
    $stmtCount->close();
    $totalPages = max(1, (int) ceil($total / $limit));

    // ── Fetch page rows ──────────────────────────────────────
    $dataSQL = "SELECT id, q1_rating, q2_rating, q3_rating, q4_rating, q5_rating,
                        overall_rating, sentiment, comment, language, submitted_at
                 FROM feedback $whereSQL ORDER BY submitted_at DESC LIMIT ? OFFSET ?";

    $stmtData = $conn->prepare($dataSQL);
    $allTypes = $types . 'ii';
    $allParams = [...$params, $limit, $offset];
    $stmtData->bind_param($allTypes, ...$allParams);
    $stmtData->execute();
    $res = $stmtData->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['q1_rating'] = (int) $row['q1_rating'];
        $row['q2_rating'] = (int) $row['q2_rating'];
        $row['q3_rating'] = (int) $row['q3_rating'];
        $row['q4_rating'] = (int) $row['q4_rating'];
        $row['q5_rating'] = (int) $row['q5_rating'];
        $row['overall_rating'] = (float) $row['overall_rating'];
        $rows[] = $row;
    }
    $stmtData->close();

    respond([
        'success' => true,
        'data' => $rows,
        'total' => (int) $total,
        'pages' => $totalPages,
        'page' => $page,
    ]);
}

// Fallback for unsupported methods
failWith('Method not allowed', 405);

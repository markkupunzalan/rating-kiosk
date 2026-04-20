<?php
/**
 * analytics.php — Analytics data endpoint
 *
 * Preset mapping (all anchored to PHP-side calendar dates in APP_TIMEZONE):
 *   days=0  → Today       : today 00:00:00 … NOW()
 *   days=7  → Last 7 days : today-6 00:00:00 … NOW()  (today + prev 6 full days)
 *   days=30 → Last 30 days: today-29 00:00:00 … NOW()
 *   days=90 → Last 90 days: today-89 00:00:00 … NOW()
 *
 * KEY DESIGN DECISION:
 *   Date boundaries are computed in PHP (using the APP_TIMEZONE set in db.php)
 *   and passed to MySQL as literal date strings ('YYYY-MM-DD').
 *   This avoids any MySQL server timezone mismatch — MySQL CURDATE() and NOW()
 *   operate in whatever timezone the server is configured with, which may not
 *   match the application timezone. By computing dates in PHP we guarantee that
 *   "Today" always means today in the user's timezone, not the server's.
 */

// SEC-2 FIX: Removed wildcard CORS — analytics is an auth-gated admin endpoint.
// The authMiddleware below will reject unauthenticated requests anyway, but the
// wildcard header would still allow cross-origin credential requests to be attempted.

// ── Helper: safe JSON error exit ─────────────────────────────
function jsonError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

// db.php sets date_default_timezone_set() AND MySQL session timezone

// ── Parse & validate the `days` parameter ───────────────────
// 0  = Today preset
// >0 = Last N calendar days
$rawDays = isset($_GET['days']) ? intval($_GET['days']) : 30;
$rawDays = max(0, $rawDays);   // clamp: never negative

$isToday = ($rawDays === 0);
$days = $isToday ? 1 : $rawDays; // canonical day count (1 for Today)

// ── Compute date boundaries in PHP (timezone-safe) ───────────
//
// PHP date() now operates in APP_TIMEZONE (set in db.php).
// We compute plain 'YYYY-MM-DD' strings and pass them as literals.
//
// For "Today"    (days=0 → N=1): start = today,     window = 1 day
// For "Last 7d"  (days=7 → N=7): start = today-6,   window = 7 days
// For "Last 30d" (days=30→ N=30): start = today-29,  window = 30 days
// For "Last 90d" (days=90→ N=90): start = today-89,  window = 90 days
//
// Formula: startOffset = N - 1  (offset in days back from today)

$startOffset = $days - 1;                // e.g. 0 for Today, 6 for Last 7d
$prevEndOffset = $startOffset;             // previous period ends where current starts
$prevStartOffset = ($days * 2) - 1;         // previous period is same length

// Literal date strings — safe in SQL because they come from date() not user input
$startDate = date('Y-m-d', strtotime("-{$startOffset} days"));
$prevEndDate = date('Y-m-d', strtotime("-{$prevEndOffset} days"));
$prevStartDate = date('Y-m-d', strtotime("-{$prevStartOffset} days"));

// SQL boundary expressions using quoted literal dates
// Current period:  submitted_at >= 'YYYY-MM-DD'           (>= today/start 00:00:00)
// Previous period: submitted_at >= 'YYYY-MM-DD' AND < 'YYYY-MM-DD'
$startExpr = "'{$startDate}'";
$prevStartExp = "'{$prevStartDate}'";
$prevEndExp = "'{$prevEndDate}'";

// ── Helper: run a query and guard against DB errors ──────────
function runQuery(mysqli $db, string $sql): mysqli_result
{
    $res = $db->query($sql);
    if ($res === false) {
        jsonError('Database error: ' . $db->error, 500);
    }
    return $res;
}

// ── Summary stats ────────────────────────────────────────────
$summarySQL = "
    SELECT
        COUNT(*)                                    AS total,
        COALESCE(AVG(overall_rating), 0)            AS avg_rating,
        COALESCE(SUM(sentiment = 'positive'), 0)    AS positive,
        COALESCE(SUM(sentiment = 'neutral'),  0)    AS neutral,
        COALESCE(SUM(sentiment = 'negative'), 0)    AS negative
    FROM feedback
    WHERE submitted_at >= {$startExpr}
";
$row = runQuery($conn, $summarySQL)->fetch_assoc();

$total = (int) ($row['total'] ?? 0);
$positive = (int) ($row['positive'] ?? 0);
$neutral = (int) ($row['neutral'] ?? 0);
$negative = (int) ($row['negative'] ?? 0);
$avgRating = round((float) ($row['avg_rating'] ?? 0), 2);

$positivePct = $total > 0 ? round($positive / $total * 100) : 0;
$neutralPct = $total > 0 ? round($neutral / $total * 100) : 0;
$negativePct = $total > 0 ? round($negative / $total * 100) : 0;

// ── Previous-period summary ──────────────────────────────────
$prevSummarySQL = "
    SELECT
        COUNT(*)                                    AS total,
        COALESCE(AVG(overall_rating), 0)            AS avg_rating,
        COALESCE(SUM(sentiment = 'positive'), 0)    AS positive,
        COALESCE(SUM(sentiment = 'neutral'),  0)    AS neutral,
        COALESCE(SUM(sentiment = 'negative'), 0)    AS negative
    FROM feedback
    WHERE submitted_at >= {$prevStartExp}
      AND submitted_at <  {$prevEndExp}
";
$prevRow = runQuery($conn, $prevSummarySQL)->fetch_assoc();

$prevTotal = (int) ($prevRow['total'] ?? 0);
$prevPositive = (int) ($prevRow['positive'] ?? 0);
$prevNegative = (int) ($prevRow['negative'] ?? 0);
$prevAvgRating = round((float) ($prevRow['avg_rating'] ?? 0), 2);

$prevPositivePct = $prevTotal > 0 ? round($prevPositive / $prevTotal * 100) : 0;
$prevNegativePct = $prevTotal > 0 ? round($prevNegative / $prevTotal * 100) : 0;

// ── Daily trend ──────────────────────────────────────────────
$trendSQL = "
    SELECT
        DATE(submitted_at)                          AS date,
        COUNT(*)                                    AS total,
        COALESCE(SUM(sentiment = 'positive'), 0)    AS positive,
        COALESCE(SUM(sentiment = 'neutral'),  0)    AS neutral,
        COALESCE(SUM(sentiment = 'negative'), 0)    AS negative
    FROM feedback
    WHERE submitted_at >= {$startExpr}
    GROUP BY DATE(submitted_at)
    ORDER BY date ASC
";
$trendRes = runQuery($conn, $trendSQL);
$trend = [];
while ($r = $trendRes->fetch_assoc()) {
    $trend[] = [
        'date' => $r['date'],
        'total' => (int) $r['total'],
        'positive' => (int) $r['positive'],
        'neutral' => (int) $r['neutral'],
        'negative' => (int) $r['negative'],
    ];
}

// ── Recent feedback (last 5 — always unfiltered) ─────────────
$recentSQL = "
    SELECT id, overall_rating, sentiment, comment, language, submitted_at
    FROM feedback
    ORDER BY submitted_at DESC
    LIMIT 5
";
$recentRes = runQuery($conn, $recentSQL);
$recent = [];
while ($r = $recentRes->fetch_assoc()) {
    $r['id'] = (int) $r['id'];
    $r['overall_rating'] = (float) $r['overall_rating'];
    $recent[] = $r;
}

// ── Hourly distribution ──────────────────────────────────────
$hourlySQL = "
    SELECT HOUR(submitted_at) AS h, COUNT(*) AS cnt
    FROM feedback
    WHERE submitted_at >= {$startExpr}
    GROUP BY h
";
$hourlyRes = runQuery($conn, $hourlySQL);
$hourly = array_fill(0, 24, 0);
while ($r = $hourlyRes->fetch_assoc()) {
    $hourly[(int) $r['h']] = (int) $r['cnt'];
}

// ── Rating breakdown ─────────────────────────────────────────
$rbSQL = "
    SELECT ROUND(overall_rating) AS star, COUNT(*) AS cnt
    FROM feedback
    WHERE submitted_at >= {$startExpr}
    GROUP BY star
    HAVING star BETWEEN 1 AND 5
";
$rbRes = runQuery($conn, $rbSQL);
$ratingBreakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
while ($r = $rbRes->fetch_assoc()) {
    $s = (int) $r['star'];
    if ($s >= 1 && $s <= 5) {
        $ratingBreakdown[$s] = (int) $r['cnt'];
    }
}

// ── Label counts across all question responses ────────────────
$labelCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$questionLabelCounts = [
    'q1' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'q2' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'q3' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'q4' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'q5' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
];

$responseSQL = "
    SELECT 
        SUM(q1_rating=1) as q1_1, SUM(q1_rating=2) as q1_2, SUM(q1_rating=3) as q1_3, SUM(q1_rating=4) as q1_4, SUM(q1_rating=5) as q1_5,
        SUM(q2_rating=1) as q2_1, SUM(q2_rating=2) as q2_2, SUM(q2_rating=3) as q2_3, SUM(q2_rating=4) as q2_4, SUM(q2_rating=5) as q2_5,
        SUM(q3_rating=1) as q3_1, SUM(q3_rating=2) as q3_2, SUM(q3_rating=3) as q3_3, SUM(q3_rating=4) as q3_4, SUM(q3_rating=5) as q3_5,
        SUM(q4_rating=1) as q4_1, SUM(q4_rating=2) as q4_2, SUM(q4_rating=3) as q4_3, SUM(q4_rating=4) as q4_4, SUM(q4_rating=5) as q4_5,
        SUM(q5_rating=1) as q5_1, SUM(q5_rating=2) as q5_2, SUM(q5_rating=3) as q5_3, SUM(q5_rating=4) as q5_4, SUM(q5_rating=5) as q5_5
    FROM feedback
    WHERE submitted_at >= {$startExpr}
";
$responseRes = runQuery($conn, $responseSQL);
if ($r = $responseRes->fetch_assoc()) {
    for ($i = 1; $i <= 5; $i++) {
        for ($val = 1; $val <= 5; $val++) {
            $count = (int) ($r["q{$i}_{$val}"] ?? 0);
            $labelCounts[$val] += $count;
            $questionLabelCounts["q{$i}"][$val] = $count;
        }
    }
}

// ── JSON response ────────────────────────────────────────────
// ARCH-2 FIX: Removed 'debug' key — internal date boundaries should not be
// exposed to API consumers. Use server-side error_log() during development.
echo json_encode([
    'success' => true,
    'period' => $isToday ? 'today' : "{$days}d",
    'summary' => [
        'total' => $total,
        'avg_rating' => $avgRating,
        'positive' => $positive,
        'neutral' => $neutral,
        'negative' => $negative,
        'positive_pct' => $positivePct,
        'neutral_pct' => $neutralPct,
        'negative_pct' => $negativePct,
    ],
    'prev_summary' => [
        'total' => $prevTotal,
        'avg_rating' => $prevAvgRating,
        'positive_pct' => $prevPositivePct,
        'negative_pct' => $prevNegativePct,
    ],
    'trend' => $trend,
    'recent' => $recent,
    'hourly' => $hourly,
    'rating_breakdown' => $ratingBreakdown,
    'rating_label_counts' => $labelCounts,
    'question_label_counts' => $questionLabelCounts,
]);

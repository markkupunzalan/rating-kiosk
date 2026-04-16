<?php
/**
 * run_backend.php — Native testing for Kiosk APIs using local Apache server
 */

echo "🚀 Starting Backend Test Runner (using local Apache)...\n";

$baseUrl = "http://localhost/phpl=kios/kiosk%20pp/api/";
$passed = 0;
$failed = 0;

// Test framework helpers
function assertBool($name, $expected, $actual) {
    global $passed, $failed;
    if ($expected === $actual) {
         echo "✅ PASS: $name\n";
         $passed++;
         return true;
    } else {
         echo "❌ FAIL: $name (Expected ".var_export($expected,true).", got ".var_export($actual,true).")\n";
         $failed++;
         return false;
    }
}

function api_req($endpoint, $data = [], $method = "POST", $cookies = '') {
    global $baseUrl;
    $ch = curl_init();
    
    // Default to JSON body
    $url = $baseUrl . $endpoint;
    
    if ($method === "GET" && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'X-App-Env: testing', // VERY IMPORTANT: Tells backend to use test db
    ];

    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    
    // Capture response headers to grab cookies
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    if ($response === false) {
        die("cURL error on $url: " . curl_error($ch) . "\nMake sure XAMPP Apache is running!\n");
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch); // curl_close is deprecated, let GC handle it
    
    // Parse cookies from response
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerStr, $matches);
    $outgoingCookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $outgoingCookies = array_merge($outgoingCookies, $cookie);
    }
    
    $cookieStr = "";
    foreach($outgoingCookies as $k => $v) {
        $cookieStr .= "$k=$v;";
    }
    
    return [
        'code' => $httpCode,
        'body' => json_decode($body, true),
        'raw_body' => $body,
        'cookie' => $cookieStr
    ];
}

function reseedDb() {
    $conn = new mysqli('localhost', 'root', '', 'test_feedbackiq');
    if ($conn->connect_error) {
        die("Fatal Database setup error: " . $conn->connect_error . "\n");
    }
    $conn->query("TRUNCATE TABLE admins");
    $conn->query("TRUNCATE TABLE feedback");
    
    // Insert a default admin (admin / password123)
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$hash')");
    $conn->close();
}

reseedDb();

// ── TESTS ───────────────────────────────────────────────

echo "\n--- Authentication Tests ---\n";

// 1. Invalid Login
$res = api_req('login.php', ['username' => 'admin', 'password' => 'wrongpass']);
assertBool('Invalid login fails', 401, $res['code']);
assertBool('Returns error message', false, $res['body']['success']);

// 2. Valid Login
$res = api_req('login.php', ['username' => 'admin', 'password' => 'password123']);
assertBool('Valid login succeeds', 200, $res['code']);
assertBool('Returns success flag', true, $res['body']['success']);

// Grab cookie string to stay authenticated
$authCookie = escapeshellarg($res['cookie'] ?? '');
$authCookie = $res['cookie'];

// 3. Auth Middleware checks
$resProtected = api_req('change_password.php', ['currentPassword' => 'password123', 'newPassword' => 'newpass456'], 'POST', $authCookie);
assertBool('Can access protected route when logged in (change pass)', 200, $resProtected['code'] ?? 200); 

// Test failed auth middleware without cookie
$resProtectedNoAuth = api_req('change_password.php', ['currentPassword' => 'password123', 'newPassword' => 'newpass'], 'POST');
assertBool('Protected routes return 401 without auth', 401, $resProtectedNoAuth['code']);

echo "\n--- Data/Feedback Tests ---\n";
// Insert Feedback test
$fbData = ['q1_rating' => 5, 'q2_rating' => 5, 'q3_rating' => 5, 'q4_rating'=> 4, 'q5_rating' => 5, 'comment' => 'Great test!', 'language' => 'en'];
$resFb = api_req('feedback.php', $fbData, 'POST');
assertBool('Feedback submission returns 201 Created', 201, $resFb['code']);

$resAnalytics = api_req('analytics.php', [], 'GET', $authCookie);
assertBool('Analytics returns 200 via GET', 200, $resAnalytics['code']);
assertBool('Analytics has positive feedback', true, isset($resAnalytics['body']['summary']['positive']));

echo "\n--- Results ---\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) exit(1);
exit(0);

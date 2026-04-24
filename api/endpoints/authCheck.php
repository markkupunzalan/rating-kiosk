<?php



// Fetch the latest username from the database
$username = $_SESSION['admin_username'] ?? '';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
if ($admin_id) {
    $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $username = $row['username'];
            $_SESSION['admin_username'] = $username;
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'username' => $username]);

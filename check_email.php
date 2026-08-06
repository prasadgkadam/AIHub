<?php
/**
 * AJAX Endpoint: Check if email already exists
 * Called via AJAX from signup.php
 */

header('Content-Type: application/json');

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include "db.php";

// Get email from AJAX request
$email = trim($_GET['email'] ?? '');

$response = [
    'exists' => false,
    'message' => ''
];

// Validate email format
if (empty($email)) {
    $response['exists'] = false;
    $response['message'] = '';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['exists'] = false;
    $response['message'] = ''; // Don't show invalid email format here, let frontend validation handle it
    echo json_encode($response);
    exit;
}

try {
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        $response['exists'] = true;
        $response['message'] = '❌ This email is already registered. Please use another email or try logging in.';
    } else {
        $response['exists'] = false;
        $response['message'] = '✓ Email is available';
    }
} catch (Exception $e) {
    $response['exists'] = false;
    $response['message'] = 'Error checking email. Please try again.';
}

echo json_encode($response);
exit;
?>

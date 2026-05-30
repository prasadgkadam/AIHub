<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$tool_id = (int)($_POST['tool_id'] ?? 0);

if($tool_id <= 0) {
    echo json_encode(['error' => 'Invalid tool']);
    exit();
}

// Check if already favorited
$stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND tool_id = ?");
$stmt->execute([$user_id, $tool_id]);

if($stmt->rowCount() > 0) {
    // Remove favorite
    $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND tool_id = ?")
         ->execute([$user_id, $tool_id]);
    echo json_encode(['status' => 'removed']);
} else {
    // Add favorite
    $conn->prepare("INSERT INTO favorites (user_id, tool_id) VALUES (?, ?)")
         ->execute([$user_id, $tool_id]);
    echo json_encode(['status' => 'added']);
}
?>
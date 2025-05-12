<?php
require "inc/menu.inc.php";

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$uploadDir = 'uploads/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 2 * 1024 * 1024;

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

if ($_FILES['profile_image']['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 2MB.']);
    exit;
}

$fileExt = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
$newFileName = uniqid('profile_') . '.' . $fileExt;
$destination = $uploadDir . $newFileName;

if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

$sql = "UPDATE users SET profile_image = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $newFileName, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile image updated successfully.', 'filename' => $newFileName]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
    unlink($destination);
}

$conn->close();
?>

<?php
require "inc/menu.inc.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT id, email, profile_image, password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$smarty->assign('user', $user);

$user_has_role_club = false;
$sql = "SELECT r.name FROM roles r
        JOIN user_has_roles uhr ON r.id = uhr.role_id
        WHERE uhr.user_id = ? AND r.name = 'Club'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_has_role_club = true;
}
$smarty->assign('user_has_role_club', $user_has_role_club);

if ($user_has_role_club) {
    $sql = "SELECT * FROM clubs WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();

    $smarty->assign('club', $club ? $club : null);
}

$password_change_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_change_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $password_change_message = "New passwords do not match.";
    } elseif (!password_verify($current_password,$user['password'])) {

        $password_change_message = "Incorrect current password.";
    } elseif (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[^a-zA-Z0-9\\s]/", $new_password)) {
        $password_change_message = "Password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            $password_change_message = "Password changed successfully.";
        } else {
            $password_change_message = "Error updating password.";
        }
    }
}
$smarty->assign('password_change_message', $password_change_message);

$smarty->assign('title', 'Profile');
$smarty->display('profile.tpl.html');
exit(0);
?>

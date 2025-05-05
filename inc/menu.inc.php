<?php
require "constants.inc.php";
require "db.inc.php";

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME)))
    error(500);

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$allowed_environment_ids = [];

//Get the user's roles
$stmt = $conn->prepare("SELECT role_id FROM user_has_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$roles = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

//Get the allowed environments for each role
if (!empty($roles)) {
    $role_ids = array_column($roles, 'role_id');
    $placeholders = implode(',', array_fill(0, count($role_ids), '?'));
    $stmt = $conn->prepare("SELECT DISTINCT environment_id FROM permissions WHERE role_id IN ($placeholders)");
    if (!$stmt) error(500);

    $stmt->bind_param(str_repeat('i', count($role_ids)), ...$role_ids); // Bind parameters dynamically
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed_environments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $allowed_environment_ids = array_column($allowed_environments, 'environment_id');
}

//Get the related modules for each environment
$result = mysqli_query($conn, "SELECT * FROM modules WHERE active IS TRUE");
if (!$result) {
    error(500);
}
$modules = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

$filtered_modules = [];

foreach ($modules as $key => $module) {
    $id = $module['id'];
    $sql = "SELECT * FROM environments WHERE module_id = $id AND active IS TRUE";
    if (!empty($allowed_environment_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_environment_ids), '?'));
        $sql .= " AND id IN ($placeholders)";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) error(500);

    if (!empty($allowed_environment_ids)) {
        $stmt->bind_param(str_repeat('i', count($allowed_environment_ids)), ...$allowed_environment_ids);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $environments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($environments)) {
        $module['environments'] = $environments;
        $filtered_modules[] = $module;
    }
}

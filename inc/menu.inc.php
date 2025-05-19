<?php
require "constants.inc.php";
require "db.inc.php";
require "template.inc.php";

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

    $stmt->bind_param(str_repeat('i', count($role_ids)), ...$role_ids);
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

$smarty->assign('modules', $filtered_modules);

$sql = "SELECT profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $profile_image = $user['profile_image'];
} else {
    $profile_image = null;
}

$smarty->assign('profile_image', $profile_image);

$current_uri = strtok(substr($_SERVER['REQUEST_URI'], 1), '?');

$active_module = null;
$active_environment = null;

foreach ($filtered_modules as $key => $module) {
    foreach ($module['environments'] as $env_key => $environment) {
        if ($environment['url'] == $current_uri) {
            $active_module = $module['id'];
            $active_environment = $environment['id'];
            break 2;
        }
    }
}

$smarty->assign('active_module', $active_module);
$smarty->assign('active_environment', $active_environment);

function userHasPermission($environment, $operation) {
    global $conn;

    $user_id = $_SESSION['user_id'];

    $sql_env = "SELECT id FROM environments WHERE name = ?";
    $stmt_env = $conn->prepare($sql_env);
    $stmt_env->bind_param("s", $environment);
    $stmt_env->execute();
    $result_env = $stmt_env->get_result();

    if ($result_env->num_rows == 0) {
        return false; // Environment not found
    }

    $environment_id = $result_env->fetch_assoc()['id'];
    $stmt_env->close();

    $sql_user_roles = "SELECT role_id FROM user_has_roles WHERE user_id = ?";
    $stmt_user_roles = $conn->prepare($sql_user_roles);
    $stmt_user_roles->bind_param("i", $user_id);
    $stmt_user_roles->execute();
    $result_user_roles = $stmt_user_roles->get_result();

    $role_ids = [];
    while ($row_user_roles = $result_user_roles->fetch_assoc()) {
        $role_ids[] = $row_user_roles['role_id'];
    }
    $stmt_user_roles->close();

    if (empty($role_ids)) {
        return false; //  User has no roles, so no permission
    }

    $placeholders = implode(',', array_fill(0, count($role_ids), '?'));

    $sql_perm = "SELECT COUNT(*) FROM permissions 
                 WHERE environment_id = ? AND operation = ? AND role_id IN ($placeholders)";
    $stmt_perm = $conn->prepare($sql_perm);

    $types = 'ii' . str_repeat('i', count($role_ids));

    $stmt_perm->bind_param($types, $environment_id, $operation, ...$role_ids);
    $stmt_perm->execute();
    $result_perm = $stmt_perm->get_result();
    $has_permission = ($result_perm->fetch_row()[0] > 0);
    $stmt_perm->close();

    return $has_permission;
}

function getUnreadNotificationCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0];
}

// Funzione per ottenere le notifiche di un utente
function getUserNotifications($conn, $user_id) {
    $sql = "SELECT 
                n.id,
                n.type,
                n.content,
                n.created_at,
                un.read_at
            FROM notifications n
            JOIN user_notifications un ON n.id = un.notification_id
            WHERE un.user_id = ?
            ORDER BY n.created_at DESC"; // Ordina per data di creazione
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

// Calcola il numero di notifiche non lette e lo assegna a Smarty
if ($user_id) {
    $unread_notification_count = getUnreadNotificationCount($conn, $user_id);
    $smarty->assign('unread_notification_count', $unread_notification_count);
    $user_notifications = getUserNotifications($conn, $user_id);
    $smarty->assign('user_notifications', $user_notifications);
}

<?php
require "inc/menu.inc.php";

if (!userHasPermission('Utenti', OPERATION_READ)) {
    error(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action']) && !isset($_GET['id'])) {
    $sql = "SELECT u.id, u.email, r.name as role_name, u.active FROM users u
LEFT JOIN user_has_roles uhr ON uhr.user_id = u.id
LEFT JOIN roles r ON r.id = uhr.role_id";
    $result = $conn->query($sql);

    $users = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    $sql_roles = "SELECT id, name FROM roles";
    $result_roles = $conn->query($sql_roles);
    $roles = [];
    if ($result_roles->num_rows > 0) {
        while ($row_roles = $result_roles->fetch_assoc()) {
            $roles[] = $row_roles;
        }
    }
    $smarty->assign('roles', $roles);

    $smarty->assign('users', $users);
    $smarty->assign('mode', 'list');
    $smarty->assign('title', 'Users List');
    $smarty->display('users.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    if (!userHasPermission('Utenti', OPERATION_CREATE)) {
        error(403);
        exit();
    }
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $active = $_POST['active'];
    $role_id = $_POST['role_id'];

    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $conn->begin_transaction();

    $sql_insert_user = "INSERT INTO users (email, password, active) VALUES (?, ?, ?)";
    $stmt_insert_user = $conn->prepare($sql_insert_user);
    $stmt_insert_user->bind_param("ssi", $email, $hashed_password, $active);

    if ($stmt_insert_user->execute()) {
        $user_id = $conn->insert_id;

        $sql_insert_user_role = "INSERT INTO user_has_roles (user_id, role_id) VALUES (?, ?)";
        $stmt_insert_user_role = $conn->prepare($sql_insert_user_role);
        $stmt_insert_user_role->bind_param("ii", $user_id, $role_id);

        if ($stmt_insert_user_role->execute()) {
            $conn->commit();
            $success_message = "User created successfully!";
            header("Location: " . "/users.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $conn->rollback();
            $error_message = "Error assigning role to user: " . $stmt_insert_user_role->error;
            header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $conn->rollback();
        $error_message = "Error creating user: " . $stmt_insert_user->error;
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT u.id, u.email, u.active, uhr.role_id
            FROM users u
            LEFT JOIN user_has_roles uhr ON u.id = uhr.user_id
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($user_data);
        exit();
    } else {
        $error_message = "User not found.";
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    if (!userHasPermission('Utenti', OPERATION_UPDATE)) {
        error(403);
        exit();
    }
    $id = $_POST['id'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $active = $_POST['active'];
    $role_id = $_POST['role_id'];

    if (empty($email)) {
        $error_message = "Email is required.";
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $conn->begin_transaction();

    $sql_update_user = "UPDATE users SET email = ?, active = ? WHERE id = ?";
    $stmt_update_user = $conn->prepare($sql_update_user);
    $stmt_update_user->bind_param("sii", $email, $active, $id);

    if ($stmt_update_user->execute()) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_update_password = "UPDATE users SET password = ? WHERE id = ?";
            $stmt_update_password = $conn->prepare($sql_update_password);
            $stmt_update_password->bind_param("si", $hashed_password, $id);
            $stmt_update_password->execute();
        }

        $sql_update_user_role = "UPDATE user_has_roles SET role_id = ? WHERE user_id = ?";
        $stmt_update_user_role = $conn->prepare($sql_update_user_role);
        $stmt_update_user_role->bind_param("ii", $role_id, $id);

        if ($stmt_update_user_role->execute()) {
            $conn->commit();
            $success_message = "User updated successfully!";
            header("Location: " . "/users.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $conn->rollback();
            $error_message = "Error updating user role: " . $stmt_update_user_role->error;
            header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $conn->rollback();
        $error_message = "Error updating user: " . $stmt_update_user->error;
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_users'])) {
    if (!userHasPermission('Utenti', OPERATION_DELETE)) {
        error(403);
        exit();
    }
    $user_ids = $_POST['user_ids'];

    if (!empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $sql_delete_user_roles = "DELETE FROM user_has_roles WHERE user_id IN ($placeholders)";
        $stmt_delete_user_roles = $conn->prepare($sql_delete_user_roles);
        $types = str_repeat('i', count($user_ids));
        $stmt_delete_user_roles->bind_param($types, ...$user_ids);

        $conn->begin_transaction();

        if ($stmt_delete_user_roles->execute()) {
            $sql_delete_users = "DELETE FROM users WHERE id IN ($placeholders)";
            $stmt_delete_users = $conn->prepare($sql_delete_users);
            $stmt_delete_users->bind_param($types, ...$user_ids);

            if ($stmt_delete_users->execute()) {
                $conn->commit();
                $success_message = "Users deleted successfully!";
                header("Location: " . "/users.php?status=success&message=" . urlencode($success_message));
                exit();
            } else {
                $conn->rollback();
                $error_message = "Error deleting users: " . $stmt_delete_users->error;
                header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
                exit();
            }
        } else {
            $conn->rollback();
            $error_message = "Error deleting user roles: " . $stmt_delete_user_roles->error;
            header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $error_message = "No users selected to delete.";
        header("Location: " . "/users.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if (isset($_GET['status']) && isset($_GET['message'])) {
    $status = $_GET['status'];
    $message = urldecode($_GET['message']);

    if ($status == 'success') {
        $smarty->assign('success_message', $message);
    } elseif ($status == 'error') {
        $smarty->assign('error_message', $message);
    }
    $smarty->assign('mode', 'list');
    $smarty->assign('title', 'Users List');
    $smarty->display('users.tpl.html');
}

$sql = "SELECT u.id, u.email, r.name as role_name, u.active FROM users u
        LEFT JOIN user_has_roles uhr ON uhr.user_id = u.id
        LEFT JOIN roles r ON r.id = uhr.role_id";
$result = $conn->query($sql);

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$smarty->assign('users', $users);
$smarty->assign('title', 'Users List');
$smarty->assign('mode', 'list');
$smarty->display('users.tpl.html');
exit();
?>

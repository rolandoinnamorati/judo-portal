<?php
require "inc/menu.inc.php";

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action']) && !isset($_GET['id'])) {
    $sql = "SELECT r.id, r.name, COUNT(p.id) AS permissions_count 
            FROM roles r
            LEFT JOIN permissions p ON r.id = p.role_id
            GROUP BY r.id, r.name";
    $result = $conn->query($sql);

    $roles = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }

    $sql = "SELECT id, name FROM environments";
    $result_env = $conn->query($sql);
    $environments = [];
    if ($result_env->num_rows > 0) {
        while ($row = $result_env->fetch_assoc()) {
            $environments[] = $row;
        }
    }
    $smarty->assign('environments', $environments);


    $smarty->assign('roles', $roles);
    $smarty->assign('mode', 'list');
    $smarty->assign('title', 'Roles List');
    $smarty->display('roles.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_role'])) {
    $name = trim($_POST['name']);
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name)) {
        $error_message = "Role name is required.";
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $conn->begin_transaction();

    $sql = "INSERT INTO roles (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);

    if ($stmt->execute()) {
        $role_id = $conn->insert_id;

        if (!empty($permissions)) {
            $sql_insert_permission = "INSERT INTO permissions (role_id, environment_id, operation) VALUES (?, ?, ?)";
            $stmt_insert_permission = $conn->prepare($sql_insert_permission);

            foreach ($permissions as $permission) {
                list($environment_id, $operation) = explode('-', $permission);
                $stmt_insert_permission->bind_param("iii", $role_id, $environment_id, $operation);
                if (!$stmt_insert_permission->execute()) {
                    $conn->rollback();
                    $error_message = "Error creating permission: " . $stmt_insert_permission->error;
                    header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
                    exit();
                }
            }
            $stmt_insert_permission->close();
        }

        $conn->commit();
        $success_message = "Role and permissions created successfully!";
        header("Location: " . "/roles.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $conn->rollback();
        $error_message = "Error creating role: " . $stmt->error;
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}



if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT r.id, r.name, p.environment_id, p.operation
            FROM roles r
            LEFT JOIN permissions p ON r.id = p.role_id
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $role = $result->fetch_assoc();
        $role['role_id'] = $id;
        $permissions = [];
        while($row = $result->fetch_assoc()){
            $permissions[] = $row;
        }

        $sql_env = "SELECT id, name FROM environments";
        $result_env = $conn->query($sql_env);
        $environments = [];
        if ($result_env->num_rows > 0) {
            while ($row = $result_env->fetch_assoc()) {
                $environments[] = $row;
            }
        }
        header('Content-Type: application/json');
        echo json_encode($permissions);
        exit();
    } else {
        $error_message = "Role not found.";
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name)) {
        $error_message = "Role name is required.";
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $conn->begin_transaction();

    $sql_update_role = "UPDATE roles SET name = ? WHERE id = ?";
    $stmt_update_role = $conn->prepare($sql_update_role);
    $stmt_update_role->bind_param("si", $name, $id);

    if ($stmt_update_role->execute()) {
        $sql_delete_permissions = "DELETE FROM permissions WHERE role_id = ?";
        $stmt_delete_permissions = $conn->prepare($sql_delete_permissions);
        $stmt_delete_permissions->bind_param("i", $id);
        $stmt_delete_permissions->execute();

        if (!empty($permissions)) {
            $sql_insert_permission = "INSERT INTO permissions (role_id, environment_id, operation) VALUES (?, ?, ?)";
            $stmt_insert_permission = $conn->prepare($sql_insert_permission);
            foreach ($permissions as $permission) {
                list($environment_id, $operation) = explode('-', $permission);
                $stmt_insert_permission->bind_param("iii", $id, $environment_id, $operation);
                if (!$stmt_insert_permission->execute()) {
                    $conn->rollback();
                    $error_message = "Error updating permissions: " . $stmt_insert_permission->error;
                    header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
                    exit();
                }
            }
            $stmt_insert_permission->close();
        }
        $conn->commit();
        $success_message = "Role updated successfully!";
        header("Location: " . "/roles.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $conn->rollback();
        $error_message = "Error updating role: " . $stmt_update_role->error;
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_roles'])) {
    $role_ids = $_POST['role_ids'];

    if (!empty($role_ids)) {
        // Controlla se ci sono utenti collegati ai ruoli da eliminare
        $placeholders = implode(',', array_fill(0, count($role_ids), '?'));
        $sql_check_users = "SELECT COUNT(*) FROM user_has_roles WHERE role_id IN ($placeholders)";
        $stmt_check_users = $conn->prepare($sql_check_users);
        $types = str_repeat('i', count($role_ids));
        $stmt_check_users->bind_param($types, ...$role_ids);
        $stmt_check_users->execute();
        $user_count = $stmt_check_users->get_result()->fetch_row()[0];

        if ($user_count > 0) {
            $error_message = "Cannot delete roles while users are assigned to them.  Please update user assignments and try again.";
            header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
            exit();
        }

        // Se non ci sono utenti collegati, procedi con l'eliminazione dei permessi e dei ruoli
        $conn->begin_transaction();

        $sql_delete_permissions = "DELETE FROM permissions WHERE role_id IN ($placeholders)";
        $stmt_delete_permissions = $conn->prepare($sql_delete_permissions);
        $stmt_delete_permissions->bind_param($types, ...$role_ids);

        if ($stmt_delete_permissions->execute()) {
            $sql_delete_roles = "DELETE FROM roles WHERE id IN ($placeholders)";
            $stmt_delete_roles = $conn->prepare($sql_delete_roles);
            $stmt_delete_roles->bind_param($types, ...$role_ids);

            if ($stmt_delete_roles->execute()) {
                $conn->commit();
                $success_message = "Roles deleted successfully!";
                header("Location: " . "/roles.php?status=success&message=" . urlencode($success_message));
                exit();
            } else {
                $conn->rollback();
                $error_message = "Error deleting roles: " . $stmt_delete_roles->error;
                header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
                exit();
            }
        } else {
            $conn->rollback();
            $error_message = "Error deleting role permissions: " . $stmt_delete_permissions->error;
            header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $error_message = "No roles selected to delete.";
        header("Location: " . "/roles.php?status=error&message=" . urlencode($error_message));
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
    $smarty->assign('title', 'Roles List');
    $smarty->display('roles.tpl.html');
}

$sql = "SELECT id, name FROM roles";
$result = $conn->query($sql);

$roles = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}

$smarty->assign('roles', $roles);
$smarty->assign('title', 'Roles List');
$smarty->assign('mode', 'list');
$smarty->display('roles.tpl.html');
exit();
?>

<?php
require "inc/constants.inc.php";
require "inc/db.inc.php";

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME))) {
    die("Database connection failed: " . mysqli_connect_error());
}

function executeQuery($conn, $sql) {
    if (!mysqli_query($conn, $sql)) {
        die("SQL Error: " . mysqli_error($conn) . "\nSQL: " . $sql);
    }
}

// 1. Insert Modules
executeQuery($conn, "INSERT INTO modules (name, icon) VALUES
    ('Base','fas fa-tachometer-alt'),
    ('Utenti','fas fa-users'),
    ('Atleti','fas fa-user-friends')");

// 2. Insert Environments
executeQuery($conn, "INSERT INTO environments (name, module_id, url) VALUES
    ('Dashboard', 1, 'dashboard.php'),
    ('Utenti', 2, 'users/list.php'),
    ('Ruoli', 2, 'roles/list.php'),
    ('Atleti', 3, 'athletes/list.php')");

// 3. Insert Roles
executeQuery($conn, "INSERT INTO roles (name) VALUES
    ('Admin'),
    ('Club')");

// 4. Insert Permissions
executeQuery($conn, "INSERT INTO permissions (operation, role_id, environment_id) VALUES
    (" . OPERATION_READ . ", 1, 1), (" . OPERATION_CREATE . ", 1, 1), (" . OPERATION_UPDATE . ", 1, 1), (" . OPERATION_DELETE . ", 1, 1),
    (" . OPERATION_READ . ", 1, 2), (" . OPERATION_CREATE . ", 1, 2), (" . OPERATION_UPDATE . ", 1, 2), (" . OPERATION_DELETE . ", 1, 2),
    (" . OPERATION_READ . ", 1, 3), (" . OPERATION_CREATE . ", 1, 3), (" . OPERATION_UPDATE . ", 1, 3), (" . OPERATION_DELETE . ", 1, 3),
    (" . OPERATION_READ . ", 2, 1), (" . OPERATION_CREATE . ", 2, 1), (" . OPERATION_UPDATE . ", 2, 1), (" . OPERATION_DELETE . ", 2, 1),
    (" . OPERATION_READ . ", 2, 4), (" . OPERATION_CREATE . ", 2, 4), (" . OPERATION_UPDATE . ", 2, 4), (" . OPERATION_DELETE . ", 2, 4)
");

// 5. Insert Admin User
$email = "admin@email.it";
$password = "admin";
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $email, $hashedPassword);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$admin_user_id = $conn->insert_id;
$stmt->close();

// 6. Assign Admin Role to Admin User
$stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'Admin'");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Admin role not found!");
}
$row = $result->fetch_assoc();
$admin_role_id = $row['id'];
$stmt->close();

$stmt = $conn->prepare("INSERT INTO user_has_roles (user_id, role_id) VALUES (?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ii", $admin_user_id, $admin_role_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$stmt->close();

echo "Database seed completed successfully.\n";

$conn->close();
?>

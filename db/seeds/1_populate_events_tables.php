<?php
require "inc/constants.inc.php";
require "inc/db.inc.php";

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME))) {
    die("Database connection failed: " . mysqli_connect_error());
}

executeQuery($conn, "INSERT INTO modules (name, icon) VALUES
    ('Eventi','fa-solid fa-calendar')");

executeQuery($conn, "INSERT INTO environments (name, module_id, url) VALUES
    ('Eventi', 4, 'events.php')");

executeQuery($conn, "INSERT INTO permissions (operation, role_id, environment_id) VALUES
    (" . OPERATION_READ . ", 1, 5), (" . OPERATION_CREATE . ", 1, 5), (" . OPERATION_UPDATE . ", 1, 5), (" . OPERATION_DELETE . ", 1, 5)");

echo "Database seed completed successfully.\n";

$conn->close();
?>

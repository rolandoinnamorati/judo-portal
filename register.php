<?php
require "inc/constants.inc.php";
require "inc/db.inc.php";

define("LANDING_PAGE", "dashboard.php");

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME)))
	error(500);

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
    header("Location: " . LANDING_PAGE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        die("Tutti i campi sono obbligatori.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Email non valida.");
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        die("Email già registrata.");
    }
    $stmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $hashedPassword);

    if ($stmt->execute()) {
        $_SESSION['loggedin'] = true;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $stmt->insert_id;

        header("Location: " . LANDING_PAGE);
        exit;
    } else {
        die("Errore durante la registrazione.");
    }
}
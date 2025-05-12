<?php
require "inc/constants.inc.php";
require "inc/db.inc.php";
require_once('libs/smarty/Smarty.class.php');

$smarty = new \Smarty\Smarty();
$smarty->setTemplateDir('tpl/');
$smarty->setCompileDir('tpl_c/');
$smarty->setConfigDir('configs/');
$smarty->setCacheDir('cache/');

define("LANDING_PAGE", "dashboard.php");

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME)))
    error(500);

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
    header("Location: " . LANDING_PAGE);
    exit;
}

$reg_name_err = $reg_email_err = $reg_password_err = $register_err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($name)) {
        $reg_name_err = "Inserisci il tuo nome.";
    }
    if (empty($email)) {
        $reg_email_err = "Inserisci la tua email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reg_email_err = "Email non valida.";
    }
    if (empty($password)) {
        $reg_password_err = "Inserisci una password.";
    }

    if (empty($reg_name_err) && empty($reg_email_err) && empty($reg_password_err)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) error(500);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $reg_email_err = "Email già registrata.";
        }
        $stmt->close();
    }

    if (empty($password)) {
        $reg_password_err = "Inserisci una password.";
    } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[^a-zA-Z0-9\\s]/", $password)) {
        $reg_password_err = "La password deve contenere almeno 8 caratteri, una maiuscola, un numero e un carattere speciale.";
    }

    if (empty($reg_name_err) && empty($reg_email_err) && empty($reg_password_err)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        if (!$stmt) error(500);
        $stmt->bind_param("ss", $email, $hashedPassword);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            // Get the Club role ID
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'Club'");
            if (!$stmt) error(500);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                $register_err = "Errore: Ruolo 'Club' non trovato.";
            } else {
                $row = $result->fetch_assoc();
                $club_role_id = $row['id'];
                $stmt->close();

                // Assign the Club role to the user
                $stmt = $conn->prepare("INSERT INTO user_has_roles (user_id, role_id) VALUES (?, ?)");
                if (!$stmt) error(500);
                $stmt->bind_param("ii", $user_id, $club_role_id);

                if ($stmt->execute()) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_id'] = $user_id;

                    header("Location: " . LANDING_PAGE);
                    exit;
                } else {
                    $register_err = "Errore durante l'assegnazione del ruolo.";
                }
                $stmt->close();
            }
        } else {
            $register_err = "Errore durante la registrazione.";
        }
    }
}

$smarty->assign('name', $name ?? '');
$smarty->assign('email', $email ?? '');
$smarty->assign('reg_name_err', $reg_name_err);
$smarty->assign('reg_email_err', $reg_email_err);
$smarty->assign('reg_password_err', $reg_password_err);
$smarty->assign('register_err', $register_err);
$smarty->assign('title', 'Register');
$smarty->assign('active_tab','register');

$smarty->display('login.tpl.html');
?>

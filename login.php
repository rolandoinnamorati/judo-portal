<?php
require "inc/constants.inc.php";
require "inc/db.inc.php";
require_once('libs/smarty/Smarty.class.php');

$smarty = new \Smarty\Smarty();
$smarty->setTemplateDir('tpl/');
$smarty->setCompileDir('tpl_c/');
$smarty->setCacheDir('cache/');
$smarty->setConfigDir('configs/');

define("LANDING_PAGE", "dashboard.php");

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME)))
	error(500);

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
    header("Location: " . LANDING_PAGE);
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $email_err = $password_err = $login_err = "";
    $email = $password = null;

    if(empty(trim($_POST["email"]))){
        $email_err = "Inserisci una email valida.";
    } else{
        $email = trim($_POST["email"]);
    }

    if(empty(trim($_POST["password"]))){
        $password_err = "Inserisci una password valida.";
    } else{
        $password = trim($_POST["password"]);
    }

    if (!($stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND active=1"))) {
        error(500);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $row = $result->fetch_assoc();
    if($result->num_rows > 0 && password_verify($password, $row['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['email'] = $row['email'];
        $_SESSION['user_id'] = $row['id'];
        header("Location: " . LANDING_PAGE);
    } else {
        $error = "Invalid username or password";
        goto display_login;
    }
}

if (!isset($_POST['loggedin']) || $_POST['loggedin'] != 1)
	goto display_login;

display_login:
$smarty->assign('email_err', $email_err);
$smarty->assign('password_err', $password_err);
$smarty->assign('error', $error);
$smarty->assign('title', 'Login');
$smarty->assign('active_tab','login');
$smarty->display('login.tpl.html');
exit(0);
?>

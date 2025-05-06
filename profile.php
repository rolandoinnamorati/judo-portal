<?php
require "inc/menu.inc.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$smarty->assign('title', 'Profile');
$smarty->display('profile.tpl.html');
exit(0);
?>

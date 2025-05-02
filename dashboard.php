<?php
require "inc/constants.inc.php";
require "inc/menu.inc.php";

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$title = 'Dashboard';
require 'tpl/dashboard.tpl.html';
exit(0);
?>

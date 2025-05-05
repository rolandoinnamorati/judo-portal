<?php
require "inc/constants.inc.php";
require "inc/menu.inc.php";
require_once('libs/smarty/Smarty.class.php');

$smarty = new \Smarty\Smarty();
$smarty->setTemplateDir('tpl/');
$smarty->setCompileDir('tpl_c/');
$smarty->setCacheDir('cache/');
$smarty->setConfigDir('configs/');

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$smarty->assign('title', 'Dashboard');
$smarty->assign('modules', $modules);
$smarty->display('dashboard.tpl.html');
exit(0);
?>

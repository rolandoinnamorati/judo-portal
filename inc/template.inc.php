<?php
require_once('libs/smarty/Smarty.class.php');


$smarty = new \Smarty\Smarty();
$smarty->setTemplateDir('tpl/');
$smarty->setCompileDir('tpl_c/');
$smarty->setCacheDir('cache/');
$smarty->setConfigDir('configs/');

session_start();

$smarty->assign('logged_email', $_SESSION['email']);

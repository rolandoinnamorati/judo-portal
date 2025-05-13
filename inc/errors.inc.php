<?php
require "constants.inc.php";

define("ERROR_PAGE", APP_ROOT . "tpl/error.tpl.html");

function error($code){
    switch($code){
        case 500:
            $msg = "Internal Server Error";
            break;
        case 405:
            $msg = "Method Not Allowed";
            break;
        case 403:
            $msg = "Forbidden";
            break;
        default:
            $msg = "Unknown Error";
    }
    if (isset($conn) && $conn)
        db_close($conn);
    $title = 'Error';
    require ERROR_PAGE;
    die();
}

?>

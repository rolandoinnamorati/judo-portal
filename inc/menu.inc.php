<?php
require "constants.inc.php";
require "db.inc.php";

if (!($conn = db_open(DB_HOST, DB_USER, DB_PASS, DB_NAME)))
    error(500);

$result = mysqli_query($conn, "SELECT * FROM modules");
if (!$result) {
    error(500);
}
$modules = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
foreach($modules as $key => $module) {
    $id = $module['id'];
    $result = mysqli_query($conn, "SELECT * FROM environments WHERE module_id = $id");
    if (!$result) {
        error(500);
    }
    $environments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    $modules[$key]['environments'] = $environments;
}

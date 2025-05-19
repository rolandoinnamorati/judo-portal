<?php
require "inc/menu.inc.php";

function getAllTutorials($conn) {
    $sql = "SELECT id, name, link FROM tutorials";
    $result = $conn->query($sql);

    $tutorials = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tutorials[] = $row;
        }
    }
    return $tutorials;
}

$tutorials = getAllTutorials($conn);
$smarty->assign('tutorials', $tutorials);
$smarty->assign('title', 'Tutorials');
$smarty->display('tutorials.tpl.html');
?>

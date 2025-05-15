<?php
require "inc/menu.inc.php";

if (!userHasPermission('Eventi', OPERATION_READ)) {
    error(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action']) && !isset($_GET['id'])) {

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    if (!userHasPermission('Eventi', OPERATION_CREATE)) {
        error(403);
        exit();
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {
    if (!userHasPermission('Eventi', OPERATION_UPDATE)) {
        error(403);
        exit();
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_event'])) {
    if (!userHasPermission('Eventi', OPERATION_DELETE)) {
        error(403);
        exit();
    }

}

if (isset($_GET['status']) && isset($_GET['message'])) {
    $status = $_GET['status'];
    $message = urldecode($_GET['message']);

    if ($status == 'success') {
        $smarty->assign('success_message', $message);
    } elseif ($status == 'error') {
        $smarty->assign('error_message', $message);
    }
    $smarty->assign('mode', 'list');
    $smarty->assign('title', 'Evets List');
    $smarty->display('events.tpl.html');
}

$sql = "SELECT e.id,
       e.name,
       e.user_id,
       e.start_date,
       e.end_date,
       (SELECT COUNT(ec.id)
        FROM event_categories ec
        WHERE ec.event_id = e.id) AS categories,
       (SELECT COUNT(er.id)
        FROM event_categories ec2
        LEFT JOIN event_registrations er ON er.event_category_id = ec2.id
        WHERE ec2.event_id = e.id) AS registrations
FROM events e
GROUP BY e.id, e.name, e.user_id, e.start_date, e.end_date";
$result = $conn->query($sql);

$events = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$smarty->assign('events', $events);
$smarty->assign('title', 'Events List');
$smarty->assign('mode', 'list');
$smarty->display('events.tpl.html');
exit();
?>

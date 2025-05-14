<?php
require "inc/menu.inc.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$smarty->assign('title', 'Dashboard');

$user_id = $_SESSION['user_id'];
if (!($stmt = $conn->prepare("SELECT r.name FROM roles r
                               JOIN user_has_roles uhr ON r.id = uhr.role_id
                               WHERE uhr.user_id = ?"))) {
    error(500);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_role = $row['name'];
} else {
    $user_role = 'unknown';
}
$smarty->assign('user_role', $user_role);

if ($user_role == 'Admin') {
    $query = "SELECT COUNT(*) as total_clubs FROM clubs";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $total_clubs = $row['total_clubs'];
    $smarty->assign('total_clubs', $total_clubs);

    $query = "SELECT COUNT(*) as total_athletes FROM athletes";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $total_athletes = $row['total_athletes'];
    $smarty->assign('total_athletes', $total_athletes);

    $query = "SELECT COUNT(*) as active_athletes FROM athletes WHERE active = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $active_athletes = $row['active_athletes'];
    $smarty->assign('active_athletes', $active_athletes);

}
elseif ($user_role == 'Club') {
    $query = "SELECT id FROM clubs WHERE user_id = ?";
    if (!($stmt = $conn->prepare($query))) {
        error(500);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $club_row = $result->fetch_assoc();
    $club_id = $club_row['id'];

    $query = "SELECT active, COUNT(*) as count FROM athletes WHERE club_id = ? GROUP BY active";
    if (!($stmt = $conn->prepare($query))) {
        error(500);
    }
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $athletes_active = array();
    $athletes_inactive = array();
    while ($row = $result->fetch_assoc()) {
        if ($row['active'] == 1) {
            $athletes_active[] = $row['count'];
        } else {
            $athletes_inactive[] = $row['count'];
        }
    }
    $smarty->assign('athletes_active', json_encode($athletes_active));
    $smarty->assign('athletes_inactive', json_encode($athletes_inactive));

    $query = "SELECT gender, COUNT(*) as count FROM athletes WHERE club_id = ? GROUP BY gender";
    if (!($stmt = $conn->prepare($query))) {
        error(500);
    }
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $athletes_man = array();
    $athletes_women = array();
    while ($row = $result->fetch_assoc()) {
        if ($row['gender'] = 'Male') {
            $athletes_man[] = $row['count'];
        } else {
            $athletes_women[] = $row['count'];
        }
    }
    $smarty->assign('athletes_man', json_encode($athletes_man));
    $smarty->assign('athletes_women', json_encode($athletes_women));
}

$smarty->display('dashboard.tpl.html');
exit(0);
?>

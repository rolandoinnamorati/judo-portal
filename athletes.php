<?php
require "inc/menu.inc.php";

if (!userHasPermission('Atleti', OPERATION_READ)) {
    error(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action']) && !isset($_GET['id'])) {
    $user_id = $_SESSION['user_id'];
    $sql_club = "SELECT id FROM clubs WHERE user_id = ?";
    $stmt_club = $conn->prepare($sql_club);
    $stmt_club->bind_param("i", $user_id);
    $stmt_club->execute();
    $result_club = $stmt_club->get_result();

    if ($result_club->num_rows == 0) {
        $smarty->assign('club', true);
        $smarty->assign('mode', 'list');
        $smarty->assign('title', 'Athletes List');
        $smarty->display('athletes.tpl.html');
        exit();
    }

    $club_row = $result_club->fetch_assoc();
    $club_id = $club_row['id'];

    $sql = "SELECT 
                a.id,
                a.first_name,
                a.last_name,
                a.date_of_birth,
                a.gender,
                a.weight,
                a.active,
                a.kyu,
                a.dan,
                c.name as club_name,
                a.affiliation_number,
                a.medical_certificate_expiry_date,
                a.notes
            FROM athletes a
            LEFT JOIN clubs c ON a.club_id = c.id
            WHERE a.club_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $athletes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $athletes[] = $row;
        }
    }

    $smarty->assign('athletes', $athletes);
    $smarty->assign('mode', 'list');
    $smarty->assign('title', 'Athletes List');
    $smarty->display('athletes.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_athlete'])) {
    if (!userHasPermission('Atleti', OPERATION_CREATE)) {
        error(403);
        exit();
    }

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $active = $_POST['active'] ?? 1;
    $kyu = $_POST['kyu'] ?? null;
    $dan = $_POST['dan'] ?? null;
    $club_id = null;
    $user_id = $_SESSION['user_id'];
    // Fetch the club_id associated with the user_id
    $sql_club = "SELECT id FROM clubs WHERE user_id = ?";
    $stmt_club = $conn->prepare($sql_club);
    $stmt_club->bind_param("i", $user_id);
    $stmt_club->execute();
    $result_club = $stmt_club->get_result();

    if ($result_club->num_rows == 0) {
        $error_message = "Club not found for the logged-in user.";
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $club_row = $result_club->fetch_assoc();
    $club_id = $club_row['id'];
    $affiliation_number = $_POST['affiliation_number'] ?? null;
    $medical_certificate_expiry_date = $_POST['medical_certificate_expiry_date'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || empty($weight) || empty($club_id)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "INSERT INTO athletes (
                first_name, last_name, date_of_birth, gender, weight, active, kyu, dan, 
                club_id, affiliation_number, medical_certificate_expiry_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssdiiiisss",
        $first_name, $last_name, $date_of_birth, $gender, $weight, $active, $kyu, $dan,
        $club_id, $affiliation_number, $medical_certificate_expiry_date, $notes
    );

    if ($stmt->execute()) {
        $success_message = "Athlete created successfully!";
        header("Location: /athletes.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error creating athlete: " . $stmt->error;
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {

    $id = $_GET['id'];
    $sql = "SELECT * FROM athletes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $athlete_data = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($athlete_data);
        exit();
    } else {
        $error_message = "Athlete not found.";
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_athlete'])) {
    if (!userHasPermission('Atleti', OPERATION_UPDATE)) {
        error(403);
        exit();
    }

    $id = $_POST['id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $active = $_POST['active'] ?? 1;
    $kyu = $_POST['kyu'] ?? null;
    $dan = $_POST['dan'] ?? null;
    $affiliation_number = $_POST['affiliation_number'] ?? null;
    $medical_certificate_expiry_date = $_POST['medical_certificate_expiry_date'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || empty($weight) || empty($medical_certificate_expiry_date)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "UPDATE athletes SET
                first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, weight = ?, 
                active = ?, kyu = ?, dan = ?, affiliation_number = ?, 
                medical_certificate_expiry_date = ?, notes = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssdiiisssi",
        $first_name, $last_name, $date_of_birth, $gender, $weight, $active, $kyu, $dan,
        $affiliation_number, $medical_certificate_expiry_date, $notes, $id
    );

    if ($stmt->execute()) {
        $success_message = "Athlete updated successfully!";
        header("Location: /athletes.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error updating athlete: " . $stmt->error;
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_athletes'])) {
    if (!userHasPermission('Atleti', OPERATION_DELETE)) {
        error(403);
        exit();
    }

    $athlete_ids = $_POST['athlete_ids'];

    if (!empty($athlete_ids)) {
        $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));

        //  TODO Check for dependencies before deleting

        $conn->begin_transaction();

        $sql_delete_athletes = "DELETE FROM athletes WHERE id IN ($placeholders)";
        $stmt_delete_athletes = $conn->prepare($sql_delete_athletes);
        $types = str_repeat('i', count($athlete_ids));
        $stmt_delete_athletes->bind_param($types, ...$athlete_ids);

        if ($stmt_delete_athletes->execute()) {
            $conn->commit();
            $success_message = "Athletes deleted successfully!";
            header("Location: /athletes.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $conn->rollback();
            $error_message = "Error deleting athletes: " . $stmt_delete_athletes->error;
            header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $error_message = "No athletes selected to delete.";
        header("Location: /athletes.php?status=error&message=" . urlencode($error_message));
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
    $smarty->assign('title', 'Athletes List');
    $smarty->display('athletes.tpl.html');
}

$sql = "SELECT 
            a.id,
            a.first_name,
            a.last_name,
            a.date_of_birth,
            a.gender,
            a.weight,
            a.active,
            a.kyu,
            a.dan,
            c.name as club_name,
            a.affiliation_number,
            a.medical_certificate_expiry_date,
            a.notes
        FROM athletes a
        LEFT JOIN clubs c ON a.club_id = c.id";
$result = $conn->query($sql);

$athletes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $athletes[] = $row;
    }
}

$smarty->assign('athletes', $athletes);
$smarty->assign('title', 'Athletes List');
$smarty->assign('mode', 'list');
$smarty->display('athletes.tpl.html');
exit();
?>

<?php
require "inc/menu.inc.php";

if (!userHasPermission('Iscrizioni', OPERATION_READ)) {
    error(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action'])) {
    $user_id = $_SESSION['user_id'] ?? null;
    $sql_club = "SELECT id FROM clubs WHERE user_id = ?";
    $stmt_club = $conn->prepare($sql_club);
    $stmt_club->bind_param("i", $user_id);
    $stmt_club->execute();
    $result_club = $stmt_club->get_result();

    if ($result_club->num_rows == 0) {
        $error_message = "Club non trovato per l'utente loggato.";
        header("Location: /registrations.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $club_row = $result_club->fetch_assoc();
    $club_id = $club_row['id'];

    $sql = "SELECT 
                er.id,
                e.name as event_name,
                ec.name as category_name,
                a.first_name,
                a.last_name,
                er.registration_date
            FROM event_registrations er
            JOIN event_categories ec ON er.event_category_id = ec.id
            JOIN events e ON ec.event_id = e.id
            JOIN athletes a ON er.athlete_id = a.id
            WHERE a.club_id = ?"; // Filter by the club
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $registrations = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $registrations[] = $row;
        }
    }

    $smarty->assign('registrations', $registrations);
    $smarty->assign('title', 'Lista Iscrizioni');
    $smarty->assign('mode', 'list');
    $smarty->display('registrations.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['action'] == 'create') {
    if (!userHasPermission('Iscrizioni', OPERATION_CREATE)) {
        error(403);
        exit();
    }
    $user_id = $_SESSION['user_id'] ?? null;

    $sql_club = "SELECT id FROM clubs WHERE user_id = ?";
    $stmt_club = $conn->prepare($sql_club);
    $stmt_club->bind_param("i", $user_id);
    $stmt_club->execute();
    $result_club = $stmt_club->get_result();

    if ($result_club->num_rows == 0) {
        $error_message = "Club non trovato per l'utente loggato.";
        header("Location: /registrations.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $club_row = $result_club->fetch_assoc();
    $club_id = $club_row['id'];

    $sql_athletes = "SELECT id, first_name, last_name FROM athletes WHERE club_id = ? AND active IS TRUE";
    $stmt_athletes = $conn->prepare($sql_athletes);
    $stmt_athletes->bind_param("i", $club_id);
    $stmt_athletes->execute();
    $result_athletes = $stmt_athletes->get_result();
    $athletes = $result_athletes->fetch_all(MYSQLI_ASSOC);

    $current_date = date('Y-m-d');
    $sql_events = "SELECT id, name FROM events WHERE registration_start_date <= ? AND registration_end_date >= ?";
    $stmt_events = $conn->prepare($sql_events);
    $stmt_events->bind_param("ss", $current_date, $current_date);
    $stmt_events->execute();
    $result_events = $stmt_events->get_result();
    $events = $result_events->fetch_all(MYSQLI_ASSOC);

    $smarty->assign('athletes', $athletes);
    $smarty->assign('events', $events);
    $smarty->assign('title', 'Crea Iscrizione');
    $smarty->assign('mode', 'create');
    $smarty->display('registrations.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['action'] == 'get_categories' && isset($_GET['event_id'])) {
    $event_id = $_GET['event_id'];

    $sql_categories = "SELECT id, name FROM event_categories WHERE event_id = ?";
    $stmt_categories = $conn->prepare($sql_categories);
    $stmt_categories->bind_param("i", $event_id);
    $stmt_categories->execute();
    $result_categories = $stmt_categories->get_result();
    $categories = $result_categories->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($categories);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_registration'])) {
    if (!userHasPermission('Iscrizioni', OPERATION_CREATE)) {
        error(403);
        exit();
    }

    $athlete_id = $_POST['athlete_id'];
    $event_category_id = $_POST['event_category_id'];

    if (empty($athlete_id) || empty($event_category_id)) {
        $error_message = "Atleta e Categoria sono obbligatori.";
        header("Location: /registrations.php?action=create&status=error&message=" . urlencode($error_message));
        exit();
    }

    // Check if the athlete is already registered for this category
    $sql_check = "SELECT COUNT(*) FROM event_registrations WHERE athlete_id = ? AND event_category_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $athlete_id, $event_category_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count = $result_check->fetch_row()[0];

    if ($count > 0) {
        $error_message = "L'atleta è già iscritto a questa categoria.";
        header("Location: /registrations.php?action=create&status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql_insert = "INSERT INTO event_registrations (athlete_id, event_category_id) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ii", $athlete_id, $event_category_id);

    if ($stmt_insert->execute()) {
        $success_message = "Iscrizione creata con successo!";
        header("Location: /registrations.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Errore durante la creazione dell'iscrizione: " . $stmt_insert->error;
        header("Location: /registrations.php?action=create&status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_registrations'])) {
    if (!userHasPermission('Iscrizioni', OPERATION_DELETE)) {
        error(403);
        exit();
    }

    $registration_ids = $_POST['registration_ids'];

    if (!empty($registration_ids)) {
        $placeholders = implode(',', array_fill(0, count($registration_ids), '?'));

        $sql_delete_registrations = "DELETE FROM event_registrations WHERE id IN ($placeholders)";
        $stmt_delete_registrations = $conn->prepare($sql_delete_registrations);
        $types = str_repeat('i', count($registration_ids));
        $stmt_delete_registrations->bind_param($types, ...$registration_ids);

        if ($stmt_delete_registrations->execute()) {
            $success_message = "Iscrizioni eliminate con successo!";
            header("Location: /registrations.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $error_message = "Errore durante l'eliminazione delle iscrizioni: " . $stmt_delete_registrations->error;
            header("Location: /registrations.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $error_message = "Nessuna iscrizione selezionata per l'eliminazione.";
        header("Location: /registrations.php?status=error&message=" . urlencode($error_message));
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
}

$smarty->display('registrations.tpl.html');
?>

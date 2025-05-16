<?php
require "inc/menu.inc.php";

if (!userHasPermission('Eventi', OPERATION_READ)) {
    error(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action']) && !isset($_GET['id'])) {
    // ... (existing code to display the event list)
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
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['action'] == 'show' && isset($_GET['id'])) {
    $event_id = $_GET['id'];

    $sql_event = "SELECT * FROM events WHERE id = ?";
    $stmt_event = $conn->prepare($sql_event);
    $stmt_event->bind_param("i", $event_id);
    $stmt_event->execute();
    $result_event = $stmt_event->get_result();

    if ($result_event->num_rows == 0) {
        $error_message = "Event not found.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $event = $result_event->fetch_assoc();

    $sql_categories = "SELECT * FROM event_categories WHERE event_id = ?";
    $stmt_categories = $conn->prepare($sql_categories);
    $stmt_categories->bind_param("i", $event_id);
    $stmt_categories->execute();
    $result_categories = $stmt_categories->get_result();

    $categories = [];
    if ($result_categories->num_rows > 0) {
        while ($row = $result_categories->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    $smarty->assign('event', $event);
    $smarty->assign('categories', $categories);
    $smarty->assign('title', 'Event Details');
    $smarty->assign('mode', 'show');
    $smarty->display('events.tpl.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    if (!userHasPermission('Eventi', OPERATION_CREATE)) {
        error(403);
        exit();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $error_message = "User not logged in.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    $max_participants = $_POST['max_participants'] ?? null;
    $registration_start_date = $_POST['registration_start_date'];
    $registration_end_date = $_POST['registration_end_date'];
    $registration_fee = $_POST['registration_fee'] ?? null;

    if (empty($name) || empty($start_date) || empty($end_date) || empty($location) || empty($registration_start_date) || empty($registration_end_date)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    if (strtotime($start_date) > strtotime($end_date)) {
        $error_message = "Start date must be before end date.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    if (strtotime($registration_start_date) > strtotime($registration_end_date) || strtotime($registration_start_date) > strtotime($start_date) || strtotime($registration_end_date) > strtotime($end_date)) {
        $error_message = "Invalid registration dates.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "INSERT INTO events (
                user_id, name, description, start_date, end_date, location, 
                max_participants, registration_start_date, registration_end_date, registration_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isssssissss",
        $user_id, $name, $description, $start_date, $end_date, $location,
        $max_participants, $registration_start_date, $registration_end_date, $registration_fee
    );

    if ($stmt->execute()) {
        $success_message = "Event created successfully!";
        header("Location: /events.php?status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error creating event: " . $stmt->error;
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {
    if (!userHasPermission('Eventi', OPERATION_UPDATE)) {
        error(403);
        exit();
    }

    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    $max_participants = $_POST['max_participants'] ?? null;
    $registration_start_date = $_POST['registration_start_date'];
    $registration_end_date = $_POST['registration_end_date'];
    $registration_fee = $_POST['registration_fee'] ?? null;

    if (empty($name) || empty($start_date) || empty($end_date) || empty($location) || empty($registration_start_date) || empty($registration_end_date)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /events.php?action=show&id=" . $id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    if (strtotime($start_date) > strtotime($end_date)) {
        $error_message = "Start date must be before end date.";
        header("Location: /events.php?action=show&id=" . $id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    if (strtotime($registration_start_date) > strtotime($registration_end_date) || strtotime($registration_start_date) > strtotime($start_date) || strtotime($registration_end_date) > strtotime($end_date)) {
        $error_message = "Invalid registration dates.";
        header("Location: /events.php?action=show&id=" . $id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "UPDATE events SET 
                name = ?, description = ?, start_date = ?, end_date = ?, location = ?,
                max_participants = ?, registration_start_date = ?, registration_end_date = ?, registration_fee = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssissdi",
        $name, $description, $start_date, $end_date, $location,
        $max_participants, $registration_start_date, $registration_end_date, $registration_fee, $id
    );

    if ($stmt->execute()) {
        $success_message = "Event updated successfully!";
        header("Location: /events.php?action=show&id=" . $id . "&status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error updating event: " . $stmt->error;
        header("Location: /events.php?action=show&id=" . $id . "&status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_category'])) {
    if (!userHasPermission('Eventi', OPERATION_CREATE)) {
        error(403);
        exit();
    }

    $event_id = $_POST['event_id'];
    $name = trim($_POST['name']);
    $age_from = $_POST['age_from'];
    $age_to = $_POST['age_to'];
    $gender = $_POST['gender'];
    $kyu_from = $_POST['kyu_from'] ?? null;
    $kyu_to = $_POST['kyu_to'] ?? null;
    $dan_from = $_POST['dan_from'] ?? null;
    $dan_to = $_POST['dan_to'] ?? null;
    $max_participants = $_POST['max_participants'] ?? null;

    if (empty($name) || empty($age_from) || empty($age_to) || empty($gender)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    if ($age_from > $age_to) {
        $error_message = "Age From must be less than Age To.";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "INSERT INTO event_categories (
                event_id, name, age_from, age_to, gender, kyu_from, kyu_to, dan_from, dan_to, max_participants
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isiissiiii",
        $event_id, $name, $age_from, $age_to, $gender, $kyu_from, $kyu_to, $dan_from, $dan_to, $max_participants
    );

    if ($stmt->execute()) {
        $success_message = "Category created successfully!";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error creating category: " . $stmt->error;
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    if (!userHasPermission('Eventi', OPERATION_UPDATE)) {
        error(403);
        exit();
    }

    $id = $_POST['id'];
    $event_id = $_POST['event_id'];
    $name = trim($_POST['name']);
    $age_from = $_POST['age_from'];
    $age_to = $_POST['age_to'];
    $gender = $_POST['gender'];
    $kyu_from = $_POST['kyu_from'] ?? null;
    $kyu_to = $_POST['kyu_to'] ?? null;
    $dan_from = $_POST['dan_from'] ?? null;
    $dan_to = $_POST['dan_to'] ?? null;
    $max_participants = $_POST['max_participants'] ?? null;

    if (empty($name) || empty($age_from) || empty($age_to) || empty($gender)) {
        $error_message = "All fields marked with (*) are required.";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    if ($age_from > $age_to) {
        $error_message = "Age From must be less than Age To.";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }

    $sql = "UPDATE event_categories SET
                name = ?, age_from = ?, age_to = ?, gender = ?,
                kyu_from = ?, kyu_to = ?, dan_from = ?, dan_to = ?, max_participants = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "siisiiiiii",
        $name, $age_from, $age_to, $gender, $kyu_from, $kyu_to, $dan_from, $dan_to, $max_participants, $id
    );

    if ($stmt->execute()) {
        $success_message = "Category updated successfully!";
        header("Location: /events.php?action=show&id=" . $event_id . "&status=success&message=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Error updating category: " . $stmt->error;
        header("Location: /events.php?action=show&id=" . $event_id . "&status=error&message=" . urlencode($error_message));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_category'])) {
    if (!userHasPermission('Eventi', OPERATION_DELETE)) {
        error(403);
        exit();
    }

    $id = $_POST['id'];

    $sql_check = "SELECT COUNT(*) FROM event_registrations WHERE event_category_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count = $result_check->fetch_row()[0];

    if ($count > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete category. There are registrations associated with it.']);
        exit();
    }

    $sql = "DELETE FROM event_categories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Category deleted successfully!']);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting category: ' . $stmt->error]);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_events'])) {
    if (!userHasPermission('Eventi', OPERATION_DELETE)) {
        error(403);
        exit();
    }

    $event_ids = $_POST['event_ids'];

    if (!empty($event_ids)) {
        $placeholders = implode(',', array_fill(0, count($event_ids), '?'));

        $conn->begin_transaction();

        // Check for associated categories
        $sql_check_categories = "SELECT COUNT(*) FROM event_categories WHERE event_id IN ($placeholders)";
        $stmt_check_categories = $conn->prepare($sql_check_categories);
        $types = str_repeat('i', count($event_ids));
        $stmt_check_categories->bind_param($types, ...$event_ids);
        $stmt_check_categories->execute();
        $result_check_categories = $stmt_check_categories->get_result();
        $total_categories = $result_check_categories->fetch_row()[0];

        if ($total_categories > 0) {
            $conn->rollback();
            $error_message = "Cannot delete events. Some events have associated categories.";
            header("Location: /events.php?status=error&message=" . urlencode($error_message));
            exit();
        }

        $sql_delete_events = "DELETE FROM events WHERE id IN ($placeholders)";
        $stmt_delete_events = $conn->prepare($sql_delete_events);
        $stmt_delete_events->bind_param($types, ...$event_ids);

        if ($stmt_delete_events->execute()) {
            $conn->commit();
            $success_message = "Events deleted successfully!";
            header("Location: /events.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $conn->rollback();
            $error_message = "Error deleting events: " . $stmt_delete_events->error;
            header("Location: /events.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    } else {
        $error_message = "No events selected to delete.";
        header("Location: /events.php?status=error&message=" . urlencode($error_message));
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
    if ($_GET['action'] == 'show' && isset($_GET['id'])) {
        $event_id = $_GET['id'];

        $sql_event = "SELECT * FROM events WHERE id = ?";
        $stmt_event = $conn->prepare($sql_event);
        $stmt_event->bind_param("i", $event_id);
        $stmt_event->execute();
        $result_event = $stmt_event->get_result();

        if ($result_event->num_rows == 0) {
            $error_message = "Event not found.";
            header("Location: /events.php?status=error&message=" . urlencode($error_message));
            exit();
        }

        $event = $result_event->fetch_assoc();

        $sql_categories = "SELECT * FROM event_categories WHERE event_id = ?";
        $stmt_categories = $conn->prepare($sql_categories);
        $stmt_categories->bind_param("i", $event_id);
        $stmt_categories->execute();
        $result_categories = $stmt_categories->get_result();

        $categories = [];
        if ($result_categories->num_rows > 0) {
            while ($row = $result_categories->fetch_assoc()) {
                $categories[] = $row;
            }
        }

        $smarty->assign('event', $event);
        $smarty->assign('categories', $categories);
        $smarty->assign('title', 'Event Details');
        $smarty->assign('mode', 'show');
        $smarty->display('events.tpl.html');
        exit();

    } else {
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
    }
}
?>

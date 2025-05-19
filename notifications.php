<?php
require "inc/menu.inc.php";

function getAllNotificationsWithUserCount($conn) {
    $sql = "SELECT 
                n.id,
                n.type,
                n.content,
                n.created_at,
                COUNT(un.user_id) as recipient_count
            FROM notifications n
            LEFT JOIN user_notifications un ON n.id = un.notification_id
            GROUP BY n.id
            ORDER BY n.created_at DESC";
    $result = $conn->query($sql);

    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

function getAllUsers($conn) {
    $sql = "SELECT id, email FROM users";
    $result = $conn->query($sql);
    $users = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

function sendNotificationToUsers($conn, $type, $content, $user_ids) {
    $sql_notification = "INSERT INTO notifications (type, content, user_id) VALUES (?, ?, ?)";
    $stmt_notification = $conn->prepare($sql_notification);
    $stmt_notification->bind_param("ssi", $type, $content, $_SESSION['user_id']);

    if ($stmt_notification->execute()) {
        $notification_id = $conn->insert_id;

        $values = [];
        $placeholders = [];
        foreach ($user_ids as $user_id) {
            $values[] = $user_id;
            $values[] = $notification_id;
            $placeholders[] = "(?, ?)";
        }
        $values_string = implode(',', $placeholders);

        $sql_user_notifications = "INSERT INTO user_notifications (user_id, notification_id) VALUES $values_string";
        $stmt_user_notifications = $conn->prepare($sql_user_notifications);
        $types = str_repeat('ii', count($user_ids));
        $stmt_user_notifications->bind_param($types, ...$values);

        if ($stmt_user_notifications->execute()) {
            return true;
        } else {
            return "Errore nell'invio delle notifiche agli utenti: " . $stmt_user_notifications->error;
        }
    } else {
        return "Errore nella creazione della notifica: " . $stmt_notification->error;
    }
}

function markNotificationAsRead($conn, $user_id, $notification_id) {
    $sql = "UPDATE user_notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND notification_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $notification_id);
    if ($stmt->execute()) {
        return true;
    } else {
        return "Errore nel segnare la notifica come letta: " . $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['send_notification'])) {
        if (!userHasPermission('Notifiche', OPERATION_CREATE)) {
            error(403);
            exit();
        }
        $type = $_POST['type'];
        $content = $_POST['content'];
        $user_ids = $_POST['user_ids'];

        if (empty($user_ids)) {
            $error_message = "Devi selezionare almeno un utente a cui inviare la notifica.";
            header("Location: /notifications.php?status=error&message=" . urlencode($error_message));
            exit();
        }

        $result = sendNotificationToUsers($conn, $type, $content, $user_ids);
        if ($result === true) {
            $success_message = "Notifica inviata con successo!";
            header("Location: /notifications.php?status=success&message=" . urlencode($success_message));
            exit();
        } else {
            $error_message = $result;
            header("Location: /notifications.php?status=error&message=" . urlencode($error_message));
            exit();
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'mark_as_read') {
        $user_id = $_SESSION['user_id'];
        $notification_id = $_POST['notification_id'];
        $result = markNotificationAsRead($conn, $user_id, $notification_id);
        if ($result === true) {
            echo json_encode(['status' => 'success']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => $result]);
            exit();
        }
    }
}

if (!userHasPermission('Notifiche', OPERATION_READ)) {
    error(403);
    exit();
}

$notifications = getAllNotificationsWithUserCount($conn);
$users = getAllUsers($conn);

$smarty->assign('notifications', $notifications);
$smarty->assign('users', $users);
$smarty->assign('title', 'Gestione Notifiche');
$smarty->assign('mode', 'list');
$smarty->display('notifications.tpl.html');
?>

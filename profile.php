<?php
require "inc/menu.inc.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT id, email, profile_image, password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$smarty->assign('user', $user);

$user_has_role_club = false;
$sql = "SELECT r.name FROM roles r
        JOIN user_has_roles uhr ON r.id = uhr.role_id
        WHERE uhr.user_id = ? AND r.name = 'Club'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_has_role_club = true;
}
$smarty->assign('user_has_role_club', $user_has_role_club);

if ($user_has_role_club) {
    $sql = "SELECT * FROM clubs WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $club = $result->fetch_assoc();

    $smarty->assign('club', $club ? $club : null);
}

$password_change_message = "";
$club_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_change_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $password_change_message = "New passwords do not match.";
    } elseif (!password_verify($current_password,$user['password'])) {

        $password_change_message = "Incorrect current password.";
    } elseif (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[^a-zA-Z0-9\\s]/", $new_password)) {
        $password_change_message = "Password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            $password_change_message = "Password changed successfully.";
        } else {
            $password_change_message = "Error updating password.";
        }
    }
}
elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_club_profile'])) {
    if (!$user_has_role_club) {
        $club_message = "You do not have permission to create a club profile.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $affiliation_number = trim($_POST['affiliation_number'] ?? '');

        if (empty($name)) {
            $club_message = "Club Name is required.";
        } else {
            $sql_check_club = "SELECT id FROM clubs WHERE user_id = ?";
            $stmt_check_club = $conn->prepare($sql_check_club);
            $stmt_check_club->bind_param("i", $user_id);
            $stmt_check_club->execute();
            $result_check_club = $stmt_check_club->get_result();

            if ($result_check_club->num_rows > 0) {
                $club_message = "A club profile already exists for this user.";
            } else {
                // Prepara la query INSERT
                $sql_insert_club = "INSERT INTO clubs (user_id, name, address, city, province, contact_name, contact_phone, affiliation_number)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt_insert_club = $conn->prepare($sql_insert_club);

                if ($stmt_insert_club) {
                    $stmt_insert_club->bind_param(
                        "isssssss",
                        $user_id,
                        $name,
                        $address,
                        $city,
                        $province,
                        $contact_name,
                        $contact_phone,
                        $affiliation_number
                    );

                    if ($stmt_insert_club->execute()) {
                        $club_message = "Club profile created successfully!";
                        header("Location: profile.php?status=club_created");
                        exit;
                    } else {
                        $club_message = "Error creating club profile";
                    }
                    $stmt_insert_club->close();
                } else {
                    $club_message = "Database error: Unable to prepare statement for club creation.";
                }
            }
            $stmt_check_club->close();
        }
    }
}
elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_club_profile'])) {
    if (!$user_has_role_club || !isset($club)) {
        $club_message = "You do not have permission to update a club profile or no profile exists.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $affiliation_number = trim($_POST['affiliation_number'] ?? '');

        if (empty($name)) {
            $club_message = "Club Name is required.";
        } else {
            $sql_update_club = "UPDATE clubs SET
                                name = ?,
                                address = ?,
                                city = ?,
                                province = ?,
                                contact_name = ?,
                                contact_phone = ?,
                                affiliation_number = ?
                                WHERE user_id = ?";

            $stmt_update_club = $conn->prepare($sql_update_club);

            if ($stmt_update_club) {
                $stmt_update_club->bind_param(
                    "sssssssi",
                    $name,
                    $address,
                    $city,
                    $province,
                    $contact_name,
                    $contact_phone,
                    $affiliation_number,
                    $user_id
                );

                if ($stmt_update_club->execute()) {
                    $club_message = "Club profile updated successfully!";
                    header("Location: profile.php?status=club_updated");
                    exit;
                } else {
                    $club_message = "Error updating club profile: " . $stmt_update_club->error;
                }
                $stmt_update_club->close();
            } else {
                $club_message = "Database error: Unable to prepare statement for club update.";
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'club_created') {
        $club_message = "Club profile created successfully!";
    } elseif ($_GET['status'] == 'club_error') {
        $club_message = "An error occurred while creating the club profile.";
    }
}

$smarty->assign('password_change_message', $password_change_message);
$smarty->assign('club_message', $club_message);

$smarty->assign('title', 'Profile');
$smarty->display('profile.tpl.html');
exit(0);
?>

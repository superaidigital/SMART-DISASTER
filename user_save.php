<?php
include 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบ Action (save หรือ delete)
$action = $_REQUEST['action'] ?? '';

if ($action == 'delete') {
    $id = intval($_GET['id']);
    // ป้องกันการลบ Admin หลัก (ID 1)
    if ($id == 1) {
        $_SESSION['swal_icon'] = 'error';
        $_SESSION['swal_title'] = 'ไม่สามารถทำรายการได้';
        $_SESSION['swal_text'] = 'ไม่สามารถลบ Super Admin ได้';
    } else {
        $conn->query("DELETE FROM users WHERE id = $id");
        $_SESSION['swal_icon'] = 'success';
        $_SESSION['swal_title'] = 'ลบสำเร็จ';
        $_SESSION['swal_text'] = 'ผู้ใช้งานถูกลบออกจากระบบแล้ว';
    }
    header("Location: user_manager.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'save') {
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $shelter_id = intval($_POST['shelter_id']);
    $status = isset($_POST['status']) ? 'active' : 'inactive';

    if ($id == 0) {
        // --- ADD NEW USER ---
        // เช็คซ้ำ
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $_SESSION['swal_icon'] = 'error';
            $_SESSION['swal_title'] = 'Username ซ้ำ';
            $_SESSION['swal_text'] = 'กรุณาใช้ชื่อผู้ใช้อื่น';
            header("Location: user_form.php");
            exit();
        }

        // Hash Password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, role, shelter_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssis", $username, $password_hash, $first_name, $last_name, $role, $shelter_id, $status);
        
        if ($stmt->execute()) {
            $_SESSION['swal_icon'] = 'success';
            $_SESSION['swal_title'] = 'เพิ่มผู้ใช้สำเร็จ';
            $_SESSION['swal_text'] = "ผู้ใช้ $username พร้อมใช้งานแล้ว";
        } else {
            $_SESSION['swal_icon'] = 'error';
            $_SESSION['swal_title'] = 'เกิดข้อผิดพลาด';
            $_SESSION['swal_text'] = $stmt->error;
        }

    } else {
        // --- EDIT USER ---
        // ถ้ามีการกรอกรหัสผ่านใหม่ ให้ update ด้วย
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, role=?, shelter_id=?, status=?, password=? WHERE id=?");
            $stmt->bind_param("sssisss", $first_name, $last_name, $role, $shelter_id, $status, $password_hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, role=?, shelter_id=?, status=? WHERE id=?");
            $stmt->bind_param("sssisi", $first_name, $last_name, $role, $shelter_id, $status, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['swal_icon'] = 'success';
            $_SESSION['swal_title'] = 'แก้ไขสำเร็จ';
            $_SESSION['swal_text'] = 'ข้อมูลผู้ใช้ได้รับการอัปเดต';
        } else {
            $_SESSION['swal_icon'] = 'error';
            $_SESSION['swal_title'] = 'เกิดข้อผิดพลาด';
            $_SESSION['swal_text'] = $stmt->error;
        }
    }

    header("Location: user_manager.php");
    exit();
}
?>
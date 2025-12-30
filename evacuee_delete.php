<?php
/**
 * evacuee_delete.php
 * สคริปต์สำหรับลบข้อมูลผู้ประสบภัย (สำหรับ Admin เท่านั้น)
 * ใช้งานร่วมกับ evacuee_list.php
 */

// 1. เริ่ม Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. เรียกใช้การเชื่อมต่อฐานข้อมูล
require_once 'config/db.php';

// 3. ตรวจสอบสิทธิ์การเข้าใช้งาน (Security Check)
// ต้อง Login แล้ว
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ต้องเป็น Admin เท่านั้น (ป้องกันการพิมพ์ URL เข้ามาตรงๆ)
// ใช้ Null Coalescing Operator (??) เพื่อกัน Error กรณีไม่มี key role
if (($_SESSION['role'] ?? 'staff') !== 'admin') {
    $_SESSION['error'] = "คุณไม่มีสิทธิ์ลบข้อมูล (Access Denied)";
    header("Location: evacuee_list.php");
    exit();
}

// 4. ตรวจสอบ ID ที่ส่งมา
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // เตรียมคำสั่ง SQL (Prepared Statement)
        $sql = "DELETE FROM evacuees WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // ผูกค่าตัวแปร (i = integer)
            $stmt->bind_param("i", $id);
            
            // ประมวลผล
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success'] = "ลบข้อมูลเรียบร้อยแล้ว";
                } else {
                    $_SESSION['error'] = "ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลถูกลบไปแล้ว";
                }
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            throw new Exception("Prepare failed: " . $conn->error);
        }

    } catch (Exception $e) {
        // บันทึก Error ลง Log ของ Server (ไม่แสดงให้ User เห็นเพื่อความปลอดภัย)
        error_log("Delete Error: " . $e->getMessage());
        $_SESSION['error'] = "เกิดข้อผิดพลาดของระบบ ไม่สามารถลบข้อมูลได้";
    }
} else {
    $_SESSION['error'] = "รหัสอ้างอิงไม่ถูกต้อง";
}

// 5. ส่งกลับไปหน้าเดิม
header("Location: evacuee_list.php");
exit();
?>
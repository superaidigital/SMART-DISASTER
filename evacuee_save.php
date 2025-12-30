<?php
include 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    
    // --- รับค่าทั่วไป ---
    $id_card = trim($_POST['id_card'] ?? '');
    $id_type = $_POST['id_type'] ?? 'thai_id';
    $prefix = $_POST['prefix'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = $_POST['gender'] ?? 'male';
    
    // แปลงวันเกิด (พ.ศ. -> ค.ศ.)
    $birth_date_thai = trim($_POST['birth_date'] ?? '');
    $birth_date = NULL;
    if (!empty($birth_date_thai) && strpos($birth_date_thai, '-') !== false) {
        $parts = explode('-', $birth_date_thai);
        if(count($parts) == 3) {
            $d = $parts[0]; $m = $parts[1]; 
            $y_eng = intval($parts[2]) - 543;
            $birth_date = "$y_eng-$m-$d";
        }
    }

    // ข้อมูลศูนย์และการพัก
    $shelter_id = intval($_POST['shelter_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $stay_type = $_POST['stay_type'] ?? 'shelter';
    $stay_detail = trim($_POST['stay_detail'] ?? '');
    
    // ข้อมูลสุขภาพ
    $chronic_disease = trim($_POST['chronic_disease'] ?? '');
    $medication = trim($_POST['medication'] ?? '');
    $vulnerable_group = isset($_POST['vulnerable_group']) ? implode(',', $_POST['vulnerable_group']) : '';

    // **จัดการเวลาเข้าพัก (Check-in)**
    $check_in_input = $_POST['check_in_date'] ?? date('Y-m-d\TH:i');
    $registered_at = date('Y-m-d H:i:s', strtotime($check_in_input));

    // **จัดการเวลาออก (Check-out) อัตโนมัติ**
    $check_out_date = ($status != 'active') ? date('Y-m-d H:i:s') : NULL;

    // **สร้างที่อยู่รวม**
    $addr_no = trim($_POST['addr_no'] ?? ''); $addr_moo = trim($_POST['addr_moo'] ?? '');
    $addr_sub = trim($_POST['addr_sub'] ?? ''); $addr_dis = trim($_POST['addr_dis'] ?? ''); 
    $addr_prov = trim($_POST['addr_prov'] ?? '');
    $parts = [];
    if($addr_no) $parts[] = "บ้านเลขที่ $addr_no"; if($addr_moo) $parts[] = "หมู่ $addr_moo";
    if($addr_sub) $parts[] = "ต.$addr_sub"; if($addr_dis) $parts[] = "อ.$addr_dis"; if($addr_prov) $parts[] = "จ.$addr_prov";
    $address = implode(' ', $parts);

    $curr_no = trim($_POST['curr_addr_no'] ?? ''); $curr_moo = trim($_POST['curr_addr_moo'] ?? '');
    $curr_sub = trim($_POST['curr_addr_sub'] ?? ''); $curr_dis = trim($_POST['curr_addr_dis'] ?? ''); 
    $curr_prov = trim($_POST['curr_addr_prov'] ?? '');
    $c_parts = [];
    if($curr_no) $c_parts[] = "บ้านเลขที่ $curr_no"; if($curr_moo) $c_parts[] = "หมู่ $curr_moo";
    if($curr_sub) $c_parts[] = "ต.$curr_sub"; if($curr_dis) $c_parts[] = "อ.$curr_dis"; if($curr_prov) $c_parts[] = "จ.$curr_prov";
    $current_address = implode(' ', $c_parts);

    // --- SQL Operations ---
    if ($id == 0) {
        // Insert
        $sql = "INSERT INTO evacuees (id_type, id_card, prefix, first_name, last_name, birth_date, age, gender, phone, 
                addr_no, addr_moo, addr_sub, addr_dis, addr_prov, address,
                curr_addr_no, curr_addr_moo, curr_addr_sub, curr_addr_dis, curr_addr_prov, current_address,
                shelter_id, stay_type, stay_detail, status, check_out_date, chronic_disease, medication, vulnerable_group, registered_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssissssssssssssssissssssss", 
            $id_type, $id_card, $prefix, $first_name, $last_name, $birth_date, $age, $gender, $phone,
            $addr_no, $addr_moo, $addr_sub, $addr_dis, $addr_prov, $address,
            $curr_no, $curr_moo, $curr_sub, $curr_dis, $curr_prov, $current_address,
            $shelter_id, $stay_type, $stay_detail, $status, $check_out_date, $chronic_disease, $medication, $vulnerable_group, $registered_at
        );
    } else {
        // Update
        $sql = "UPDATE evacuees SET id_type=?, id_card=?, prefix=?, first_name=?, last_name=?, birth_date=?, age=?, gender=?, phone=?, 
                addr_no=?, addr_moo=?, addr_sub=?, addr_dis=?, addr_prov=?, address=?,
                curr_addr_no=?, curr_addr_moo=?, curr_addr_sub=?, curr_addr_dis=?, curr_addr_prov=?, current_address=?,
                shelter_id=?, stay_type=?, stay_detail=?, status=?, check_out_date=?, chronic_disease=?, medication=?, vulnerable_group=?, registered_at=? 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssissssssssssssssissssssssi", 
            $id_type, $id_card, $prefix, $first_name, $last_name, $birth_date, $age, $gender, $phone,
            $addr_no, $addr_moo, $addr_sub, $addr_dis, $addr_prov, $address,
            $curr_no, $curr_moo, $curr_sub, $curr_dis, $curr_prov, $current_address,
            $shelter_id, $stay_type, $stay_detail, $status, $check_out_date, $chronic_disease, $medication, $vulnerable_group, $registered_at, $id
        );
    }

    if ($stmt->execute()) {
        $_SESSION['swal_icon'] = 'success';
        
        // Format เวลาสำหรับแสดงผล (วว/ดด/ปปปป เวลา)
        $ts = strtotime($registered_at);
        $thai_year = date('Y', $ts) + 543;
        $show_time = date('d/m/', $ts) . $thai_year . date(' เวลา H:i น.', $ts);

        // แปลงสถานะเป็นข้อความไทย
        $status_text = "เข้าพัก (Active)";
        if ($status == 'returned') $status_text = "กลับบ้านแล้ว";
        elseif ($status == 'hospitalized') $status_text = "ส่งโรงพยาบาล";

        // ข้อความที่จะแสดงใน Popup (เหมือนกันทั้ง Insert และ Update)
        $popup_text = "สถานะ: $status_text\nเวลาเข้าพัก: $show_time";

        if ($id == 0) {
            $_SESSION['swal_title'] = 'ลงทะเบียนสำเร็จ';
            $_SESSION['swal_text'] = $popup_text;
            session_write_close();
            header("Location: evacuee_form.php?shelter_id=" . $shelter_id);
        } else {
            $_SESSION['swal_title'] = 'แก้ไขข้อมูลสำเร็จ';
            $_SESSION['swal_text'] = $popup_text; // แสดงข้อมูลเหมือนกัน
            session_write_close();
            header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
        }
    } else {
        $_SESSION['swal_icon'] = 'error';
        $_SESSION['swal_title'] = 'เกิดข้อผิดพลาด';
        $_SESSION['swal_text'] = $stmt->error;
        session_write_close();
        header("Location: evacuee_form.php?id=" . $id . "&shelter_id=" . $shelter_id);
    }
    
    exit();
}
?>
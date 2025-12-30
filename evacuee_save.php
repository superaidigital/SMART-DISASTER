<?php
/**
 * evacuee_save.php
 * ระบบบันทึกข้อมูล (Intermediate Page Version)
 * แสดง Popup Success ที่หน้านี้ก่อน แล้วค่อย Redirect ไปยังปลายทาง
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

// Check Auth
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: evacuee_list.php"); exit(); }

// --- 1. รับค่าและเตรียมข้อมูล ---
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$mode = $_POST['mode'] ?? 'add';
$shelter_id = isset($_POST['shelter_id']) ? (int)$_POST['shelter_id'] : 0;
$shelter_id = ($shelter_id > 0) ? $shelter_id : null;

// Clean Data
$id_type = $_POST['id_type'] ?? 'thai_id';
$id_card = preg_replace('/[^0-9]/', '', $_POST['id_card'] ?? '');
if ($id_type !== 'thai_id') $id_card = trim($_POST['id_card'] ?? '');

$family_code = trim($_POST['family_code'] ?? '');
$prefix = trim($_POST['prefix'] ?? '');
$first_name = trim($_POST['fname'] ?? '');
$last_name = trim($_POST['lname'] ?? '');

$birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : NULL;
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;

// Auto Age Calculation
if (!empty($birth_date)) {
    try {
        $dob = new DateTime($birth_date);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
    } catch (Exception $e) {}
}

$gender_th = $_POST['gender'] ?? 'ชาย';
$gender = ($gender_th == 'หญิง' || $gender_th == 'female') ? 'female' : 'male';
$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');

$stay_type_form = $_POST['stay_type'] ?? 'shelter';
$stay_type_db = ($stay_type_form === 'shelter') ? 'in_center' : 'out_center';
$stay_detail = trim($_POST['stay_detail'] ?? '');

// Triage
$health_status_th = $_POST['health_status'] ?? 'ปกติ';
$triage_level = 'green';
switch ($health_status_th) {
    case 'ป่วยติดเตียง/บาดเจ็บสาหัส': case 'วิกฤต': $triage_level = 'red'; break;
    case 'บาดเจ็บเล็กน้อย': case 'เฝ้าระวัง': $triage_level = 'yellow'; break;
    default: $triage_level = 'green';
}
$vulnerable_group = isset($_POST['vulnerable_group']) ? implode(',', $_POST['vulnerable_group']) : '';

// --- 2. หา Incident ID ---
$incident_id = null;
if ($shelter_id) {
    $stmt = $conn->prepare("SELECT incident_id FROM shelters WHERE id = ?");
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $incident_id = $res['incident_id'];
    $stmt->close();
}
if ($incident_id === null) {
    $res = $conn->query("SELECT id FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) $incident_id = $row['id'];
    else {
        $res2 = $conn->query("SELECT id FROM incidents ORDER BY id DESC LIMIT 1");
        if ($row2 = $res2->fetch_assoc()) $incident_id = $row2['id'];
    }
}

// Error Handling: Incident Not Found
if ($incident_id === null) {
    $_SESSION['error'] = "ไม่พบข้อมูลภารกิจ (Incident)";
    header("Location: evacuee_form.php?id=$id&shelter_id=" . ($shelter_id ?? 0) . "&mode=$mode");
    exit();
}

// --- 3. บันทึกข้อมูล ---
$fields = [
    'incident_id' => [$incident_id, 'i'], 'shelter_id' => [$shelter_id, 'i'],
    'stay_type' => [$stay_type_db, 's'], 'stay_detail' => [$stay_detail, 's'],
    'id_type' => [$id_type, 's'], 'id_card' => [$id_card, 's'],
    'family_code' => [$family_code, 's'], 'prefix' => [$prefix, 's'],
    'first_name' => [$first_name, 's'], 'last_name' => [$last_name, 's'],
    'birth_date' => [$birth_date, 's'], 'age' => [$age, 'i'],
    'gender' => [$gender, 's'], 'phone' => [$phone, 's'],
    'religion' => [trim($_POST['religion'] ?? ''), 's'], 'occupation' => [trim($_POST['occupation'] ?? ''), 's'],
    'id_card_no' => [trim($_POST['id_card_no'] ?? ''), 's'], 'id_card_moo' => [trim($_POST['id_card_moo'] ?? ''), 's'],
    'id_card_subdistrict' => [trim($_POST['id_card_subdistrict'] ?? ''), 's'],
    'id_card_district' => [trim($_POST['id_card_district'] ?? ''), 's'],
    'id_card_province' => [trim($_POST['id_card_province'] ?? ''), 's'],
    'id_card_zipcode' => [trim($_POST['id_card_zipcode'] ?? ''), 's'],
    'current_no' => [trim($_POST['current_no'] ?? ''), 's'], 'current_moo' => [trim($_POST['current_moo'] ?? ''), 's'],
    'current_subdistrict' => [trim($_POST['current_subdistrict'] ?? ''), 's'],
    'current_district' => [trim($_POST['current_district'] ?? ''), 's'],
    'current_province' => [trim($_POST['current_province'] ?? ''), 's'],
    'current_zipcode' => [trim($_POST['current_zipcode'] ?? ''), 's'],
    'triage_level' => [$triage_level, 's'], 'health_status' => [$health_status_th, 's'],
    'vulnerable_type' => [$vulnerable_group, 's'],
    'medical_condition' => [trim($_POST['medical_condition'] ?? ''), 's'],
    'drug_allergy' => [trim($_POST['drug_allergy'] ?? ''), 's'],
    'is_family_head' => [isset($_POST['is_family_head']) ? (int)$_POST['is_family_head'] : 0, 'i'],
    'registered_by' => [(int)$_SESSION['user_id'], 'i']
];

$conn->begin_transaction();

try {
    if (empty($first_name) || empty($last_name)) throw new Exception("กรุณาระบุชื่อ-นามสกุล");

    $cols = array_keys($fields);
    $vals = array_column($fields, 0);
    $types = implode('', array_column($fields, 1));

    if ($mode === 'add') {
        $q = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO evacuees (" . implode(',', $cols) . ", created_at) VALUES ($q, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
    } else {
        $set = implode('=?,', $cols) . '=?';
        $sql = "UPDATE evacuees SET $set, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $types .= 'i'; $vals[] = $id;
        $stmt->bind_param($types, ...$vals);
    }

    if (!$stmt->execute()) {
        if ($conn->errno == 1452) throw new Exception("Foreign Key Error (Shelter/Incident)");
        throw new Exception("Database Error: " . $stmt->error);
    }

    $conn->commit();

    // --- ส่วนการแสดงผล Popup และ Redirect ---
    $redirect_url = ($mode === 'edit') ? "evacuee_list.php?shelter_id=$shelter_id" : "evacuee_form.php?shelter_id=$shelter_id";
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>บันทึกสำเร็จ</title>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>body { font-family: 'Prompt', sans-serif; background-color: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }</style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'บันทึกข้อมูลสำเร็จ',
                text: 'กำลังนำทาง...',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            }).then(() => {
                window.location.href = '<?php echo $redirect_url; ?>';
            });
        </script>
    </body>
    </html>
    <?php
    exit();

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header("Location: evacuee_form.php?id=$id&shelter_id=" . ($shelter_id ?? 0) . "&mode=$mode");
    exit();
}
?>
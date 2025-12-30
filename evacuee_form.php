<?php
/**
 * evacuee_form.php
 * ฟอร์มลงทะเบียนและแก้ไขข้อมูล (Smart Address Layout)
 * Update: 
 * - เรียงลำดับที่อยู่: บ้านเลขที่ -> หมู่ -> ตำบล -> อำเภอ -> จังหวัด -> ปณ.
 * - เพิ่ม Voice Typing ให้ช่องที่อยู่
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = ($id > 0) ? 'edit' : 'add';
$shelter_id_url = isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : 0;

$data = [
    'prefix' => 'นาย', 'fname' => '', 'lname' => '', 'id_card' => '', 'id_type' => 'thai_id',
    'birth_date' => '', 'gender' => 'ชาย', 'stay_type' => 'shelter', 'stay_detail' => '',
    'age' => '', 'phone' => '', 'religion' => '', 'occupation' => '', 'family_code' => '', 
    'is_family_head' => 0, 'shelter_id' => $shelter_id_url,
    'id_card_no' => '', 'id_card_moo' => '', 'id_card_subdistrict' => '', 'id_card_district' => '', 'id_card_province' => '', 'id_card_zipcode' => '',
    'current_no' => '', 'current_moo' => '', 'current_subdistrict' => '', 'current_district' => '', 'current_province' => '', 'current_zipcode' => '',
    'vulnerable_type' => '', 'health_status' => 'ปกติ', 'medical_condition' => '', 'drug_allergy' => '', 'photo_base64' => ''
];

if ($mode == 'edit') {
    $sql = "SELECT * FROM evacuees WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) { 
            $data = array_merge($data, $row);
            $data['fname'] = $row['first_name'];
            $data['lname'] = $row['last_name'];
            if($data['gender'] == 'male') $data['gender'] = 'ชาย';
            if($data['gender'] == 'female') $data['gender'] = 'หญิง';
            if($data['stay_type'] == 'in_center') $data['stay_type'] = 'shelter';
            if($data['stay_type'] == 'out_center') $data['stay_type'] = 'outside';
        }
        mysqli_stmt_close($stmt);
    }
}

$selected_vulnerable = explode(',', $data['vulnerable_type'] ?? '');
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.th.min.js"></script>
<script src="https://jojosati.github.io/bootstrap-datepicker-thai/js/bootstrap-datepicker-thai.js"></script>
<script src="https://jojosati.github.io/bootstrap-datepicker-thai/js/locales/bootstrap-datepicker.th.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { --primary: #0f172a; --accent: #2563eb; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; }
    body { background-color: #f1f5f9; font-family: 'Prompt', sans-serif; }
    .container-intel { max-width: 1550px; margin: 0 auto; }
    .form-card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .progress-top { position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #e2e8f0; z-index: 10; }
    #formProgress { height: 100%; background: var(--success); width: 0%; transition: width 0.3s ease; }
    .segmented-toggle { display: flex; background: #f1f5f9; padding: 4px; border-radius: 8px; }
    .segmented-toggle .btn-check + label { flex: 1; border: none; border-radius: 6px !important; color: #64748b; font-weight: 600; }
    .segmented-toggle .btn-check:checked + label { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: var(--accent); }
    .v-badge { border: 1px solid #e2e8f0; padding: 8px; border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
    .v-badge:hover { background: #f8fafc; }
    .input-wrapper { position: relative; }
    .valid-feedback-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--success); display: none; pointer-events: none; }
    .invalid-feedback-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--danger); display: none; pointer-events: none; }
    .mic-btn { cursor: pointer; color: #64748b; transition: 0.2s; }
    .mic-btn:hover { color: var(--accent); }
    .mic-btn.listening { color: var(--danger); animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
</style>

<div class="container-fluid py-3 container-intel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-dark mb-0">
            <i class="fas fa-robot text-primary me-2"></i>
            <?php echo $mode == 'edit' ? 'แก้ไขข้อมูล: '.htmlspecialchars($data['fname']) : 'ลงทะเบียนผู้ประสบภัย (Smart Form)'; ?>
        </h4>
        <div class="d-flex gap-2">
            <span id="save-status" class="badge bg-light text-muted border py-2 px-3 rounded-pill d-none d-md-block align-self-center">พร้อมทำงาน</span>
            <button type="button" onclick="confirmClearDraft()" class="btn btn-sm btn-light border rounded-pill px-3 text-danger fw-bold"><i class="fas fa-eraser me-1"></i> ล้างค่า</button>
            <a href="evacuee_list.php?shelter_id=<?php echo $data['shelter_id']; ?>" class="btn btn-sm btn-white border rounded-pill px-3 fw-bold">ย้อนกลับ</a>
            <button type="button" id="btn-save-ui" onclick="submitIntelForm()" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
        </div>
    </div>

    <form action="evacuee_save.php" method="POST" id="evacueeForm" novalidate autocomplete="off">
        <div class="card form-card">
            <div class="progress-top"><div id="formProgress"></div></div>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            <input type="hidden" name="shelter_id" value="<?php echo $data['shelter_id']; ?>">
            <input type="hidden" name="birth_date" id="birth_date_db" value="<?php echo htmlspecialchars($data['birth_date']); ?>">
            <input type="hidden" name="photo_base64" id="photo_base64" value="<?php echo htmlspecialchars($data['photo_base64']); ?>">

            <div class="card-body">
                <div class="row g-4">
                    <!-- Left: Personal Data -->
                    <div class="col-lg-7 border-end pe-lg-4">
                        <div class="mb-4">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-id-card me-2"></i>ข้อมูลระบุตัวตน</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">ประเภทบัตร</label>
                                    <select name="id_type" id="id_type" class="form-select" onchange="handleIdTypeChange()">
                                        <option value="thai_id" <?php echo $data['id_type'] == 'thai_id' ? 'selected' : ''; ?>>บัตรประชาชน</option>
                                        <option value="passport" <?php echo $data['id_type'] == 'passport' ? 'selected' : ''; ?>>Passport/ต่างด้าว</option>
                                        <option value="none" <?php echo $data['id_type'] == 'none' ? 'selected' : ''; ?>>ไม่มีเอกสาร</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">เลขประจำตัว <span class="text-danger">*</span></label>
                                    <div class="input-group input-wrapper">
                                        <input type="text" name="id_card" id="id_card_input" class="form-control fw-bold text-primary fs-5" value="<?php echo htmlspecialchars($data['id_card']); ?>" placeholder="x-xxxx-xxxxx-xx-x" maxlength="17">
                                        <i class="fas fa-check-circle valid-feedback-icon" id="id_valid_icon"></i>
                                        <i class="fas fa-times-circle invalid-feedback-icon" id="id_invalid_icon"></i>
                                    </div>
                                    <small id="id_status" class="d-block mt-1"></small>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">สถานะในครอบครัว</label>
                                    <div class="segmented-toggle">
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_yes" value="1" <?php echo $data['is_family_head'] == 1 ? 'checked' : ''; ?>>
                                        <label class="btn btn-sm" for="head_yes">หัวหน้า</label>
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_no" value="0" <?php echo $data['is_family_head'] == 0 ? 'checked' : ''; ?>>
                                        <label class="btn btn-sm" for="head_no">สมาชิก</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small text-muted">รหัสครอบครัว</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="family_code" id="family_code" class="form-control" value="<?php echo htmlspecialchars($data['family_code']); ?>" onblur="checkFamilyHead()">
                                        <button class="btn btn-outline-secondary" type="button" onclick="generateFamilyCode()"><i class="fas fa-magic"></i></button>
                                    </div>
                                    <small id="family_msg" class="text-success fw-bold"></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted">เพศ</label>
                                    <div class="segmented-toggle">
                                        <input type="radio" class="btn-check" name="gender" id="g_male" value="ชาย" <?php echo ($data['gender']=='ชาย') ? 'checked' : ''; ?>>
                                        <label class="btn btn-sm" for="g_male">ชาย</label>
                                        <input type="radio" class="btn-check" name="gender" id="g_female" value="หญิง" <?php echo ($data['gender']=='หญิง') ? 'checked' : ''; ?>>
                                        <label class="btn btn-sm" for="g_female">หญิง</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-user me-2"></i>ข้อมูลส่วนตัว</h6>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">คำนำหน้า</label>
                                    <select name="prefix" class="form-select" onchange="smartSyncByPrefix(this.value)">
                                        <?php foreach(['นาย','นาง','นางสาว','เด็กชาย','เด็กหญิง'] as $p) echo "<option value='$p' ".($data['prefix']==$p ? 'selected':'').">$p</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">ชื่อจริง <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="fname" id="fname" class="form-control" value="<?php echo htmlspecialchars($data['fname']); ?>" required>
                                        <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('fname')"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="lname" id="lname" class="form-control" value="<?php echo htmlspecialchars($data['lname']); ?>" required>
                                        <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('lname')"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-success">วันเกิด (วว/ดด/พศ)</label>
                                    <div class="input-group">
                                        <input type="text" id="birth_date_display" class="form-control" placeholder="เช่น 12/04/2525" maxlength="10">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">อายุ</label>
                                    <input type="number" name="age" id="age_input" class="form-control text-center fw-bold text-primary" placeholder="ระบุ" value="<?php echo htmlspecialchars($data['age']); ?>" oninput="manualAgeSync()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($data['phone']); ?>" placeholder="0xx-xxx-xxxx" maxlength="12">
                                </div>
                            </div>
                        </div>

                        <!-- Health -->
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="text-danger fw-bold mb-3"><i class="fas fa-heartbeat me-2"></i>สุขภาพ (Smart Triage)</h6>
                                <div class="row g-2 mb-3">
                                    <?php 
                                    $v_types = [
                                        'elderly'=>['t'=>'ผู้สูงอายุ (>60)', 'l'=>'yellow'], 
                                        'child'=>['t'=>'เด็กเล็ก (<12)', 'l'=>'green'],
                                        'disabled'=>['t'=>'ผู้พิการ', 'l'=>'yellow'], 
                                        'pregnant'=>['t'=>'ตั้งครรภ์', 'l'=>'yellow'], 
                                        'bedridden'=>['t'=>'ติดเตียง (วิกฤต)', 'l'=>'red'], 
                                        'other'=>['t'=>'อื่นๆ', 'l'=>'yellow']
                                    ];
                                    foreach($v_types as $k => $info): ?>
                                    <div class="col-6 col-md-4">
                                        <label class="v-badge bg-white shadow-sm w-100">
                                            <input class="form-check-input v-checkbox me-2" type="checkbox" name="vulnerable_group[]" value="<?php echo $k; ?>" data-level="<?php echo $info['l']; ?>" onchange="handleIntelligenceHealth()" <?php echo in_array($k, $selected_vulnerable) ? 'checked' : ''; ?>>
                                            <span class="small fw-bold"><?php echo $info['t']; ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small">โรคประจำตัว</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="medical_condition" id="medical_condition" class="form-control" value="<?php echo htmlspecialchars($data['medical_condition']); ?>">
                                            <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('medical_condition')"></i></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-danger">แพ้ยา/อาหาร</label>
                                        <input type="text" name="drug_allergy" class="form-control form-control-sm border-danger" value="<?php echo htmlspecialchars($data['drug_allergy']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Address & Status -->
                    <div class="col-lg-5">
                        
                        <!-- 1. Registered Address -->
                        <div class="mb-4">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-home me-2"></i>ที่อยู่ตามทะเบียนบ้าน</h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">บ้านเลขที่</label>
                                    <input type="text" name="id_card_no" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_no']); ?>" placeholder="ระบุ">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">หมู่ที่</label>
                                    <input type="text" name="id_card_moo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_moo']); ?>" placeholder="ระบุ">
                                </div>
                                <div class="col-md-4">
                                    <!-- Placeholder to keep grid nice, or zip here? -->
                                    <label class="form-label small text-muted">รหัสไปรษณีย์</label>
                                    <input type="text" name="id_card_zipcode" id="id_card_zipcode" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_zipcode']); ?>" maxlength="5" placeholder="xxxxx" autocomplete="postal-code">
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label small text-muted">ตำบล/แขวง</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="id_card_subdistrict" id="id_card_subdistrict" class="form-control" value="<?php echo htmlspecialchars($data['id_card_subdistrict']); ?>" placeholder="ค้นหาตำบล..." autocomplete="address-level3">
                                        <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('id_card_subdistrict')"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">อำเภอ/เขต</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="id_card_district" id="id_card_district" class="form-control" value="<?php echo htmlspecialchars($data['id_card_district']); ?>" placeholder="อำเภอ" autocomplete="address-level2">
                                        <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('id_card_district')"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">จังหวัด</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="id_card_province" id="id_card_province" class="form-control" value="<?php echo htmlspecialchars($data['id_card_province']); ?>" placeholder="จังหวัด" autocomplete="address-level1">
                                        <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('id_card_province')"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Current Address -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white py-2 d-flex justify-content-between align-items-center">
                                <span class="small fw-bold"><i class="fas fa-map-marker-alt me-1"></i> ที่อยู่ปัจจุบัน</span>
                                <button type="button" class="btn btn-sm btn-light py-0 fw-bold text-success" onclick="copyAddress()" style="font-size: 11px;"><i class="fas fa-copy me-1"></i> ใช้ที่อยู่เดิม</button>
                            </div>
                            <div class="card-body bg-light p-3">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">บ้านเลขที่</label>
                                        <input type="text" id="current_no" name="current_no" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['current_no']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">หมู่ที่</label>
                                        <input type="text" id="current_moo" name="current_moo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['current_moo']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">รหัสไปรษณีย์</label>
                                        <input type="text" id="current_zipcode" name="current_zipcode" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['current_zipcode']); ?>" maxlength="5">
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label class="form-label small text-muted">ตำบล/แขวง</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="current_subdistrict" name="current_subdistrict" class="form-control" value="<?php echo htmlspecialchars($data['current_subdistrict']); ?>">
                                            <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('current_subdistrict')"></i></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">อำเภอ/เขต</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="current_district" name="current_district" class="form-control" value="<?php echo htmlspecialchars($data['current_district']); ?>">
                                            <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('current_district')"></i></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">จังหวัด</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="current_province" name="current_province" class="form-control" value="<?php echo htmlspecialchars($data['current_province']); ?>">
                                            <span class="input-group-text bg-white"><i class="fas fa-microphone mic-btn" onclick="startVoiceInput('current_province')"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status & GPS -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">สถานะการพักพิง</label>
                            <div class="segmented-toggle mb-2">
                                <input type="radio" class="btn-check" name="stay_type" id="stay_in" value="shelter" <?php echo $data['stay_type']=='shelter' ? 'checked' : ''; ?> onchange="toggleStayDetail(this.value)">
                                <label class="btn" for="stay_in">พักในศูนย์</label>
                                <input type="radio" class="btn-check" name="stay_type" id="stay_out" value="outside" <?php echo $data['stay_type']=='outside' ? 'checked' : ''; ?> onchange="toggleStayDetail(this.value)">
                                <label class="btn" for="stay_out">พักนอกศูนย์</label>
                            </div>
                            
                            <div id="stay_detail_wrapper" style="display: <?php echo (!empty($data['stay_detail']) || $data['stay_type']=='outside') ? 'block' : 'none'; ?>;">
                                <div class="input-group">
                                    <input type="text" name="stay_detail" id="stay_detail" class="form-control" placeholder="ระบุสถานที่..." value="<?php echo htmlspecialchars($data['stay_detail']); ?>">
                                    <button type="button" class="btn btn-outline-primary" onclick="getLocation()" title="ดึงพิกัด GPS"><i class="fas fa-location-arrow"></i> GPS</button>
                                </div>
                            </div>
                        </div>

                        <!-- Triage -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body p-3">
                                <label class="form-label fw-bold small text-muted mb-2">ระดับความวิกฤต (ประเมินอัตโนมัติ)</label>
                                <div class="d-flex gap-1">
                                    <input type="radio" class="btn-check" name="health_status" id="tr_green" value="ปกติ" <?php echo ($data['health_status']=='ปกติ') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-success flex-fill" for="tr_green">ปกติ (เขียว)</label>
                                    <input type="radio" class="btn-check" name="health_status" id="tr_yellow" value="บาดเจ็บเล็กน้อย" <?php echo ($data['health_status']=='บาดเจ็บเล็กน้อย') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-warning flex-fill" for="tr_yellow">เฝ้าระวัง (เหลือง)</label>
                                    <input type="radio" class="btn-check" name="health_status" id="tr_red" value="วิกฤต" <?php echo ($data['health_status']=='วิกฤต' || $data['health_status']=='ป่วยติดเตียง/บาดเจ็บสาหัส') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-danger flex-fill" for="tr_red">วิกฤต (แดง)</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="pdpa_check" required>
                            <label class="form-check-label small text-muted" for="pdpa_check">ข้าพเจ้ายินยอมให้ข้อมูล (PDPA)</label>
                        </div>
                        <button type="submit" id="btn-submit-main" class="btn btn-primary w-100 py-3 fw-bold shadow-sm"><i class="fas fa-check-circle me-2"></i> ยืนยันข้อมูล</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const IS_EDIT_MODE = <?php echo $mode == 'edit' ? 'true' : 'false'; ?>;
    
    $(document).ready(function() {
        // Datepicker & Events
        $('#birth_date_display').datepicker({
            language:'th-th', format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, enableOnReadonly: false 
        }).on('changeDate', function(e) { updateFromDate(e.date); });

        $('#birth_date_display').on('input blur', function() {
            let val = $(this).val();
            if(val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)) {
                let p = val.split('/');
                let y = parseInt(p[2]); if(y>2400) y-=543;
                let mDate = moment(`${y}-${p[1]}-${p[0]}`);
                if(mDate.isValid()) updateFromDate(mDate);
            }
        });

        $('#id_card_input').on('input', function() {
            let val = $(this).val().replace(/\D/g, '');
            let fmt = '';
            if(val.length > 0) fmt += val.substring(0,1);
            if(val.length > 1) fmt += '-' + val.substring(1,5);
            if(val.length > 5) fmt += '-' + val.substring(5,10);
            if(val.length > 10) fmt += '-' + val.substring(10,12);
            if(val.length > 12) fmt += '-' + val.substring(12,13);
            $(this).val(fmt);

            if(val.length === 13) {
                if(validateThaiID(val)) { $('#id_valid_icon').show(); $('#id_invalid_icon').hide(); checkDuplicateID(val); }
                else { $('#id_valid_icon').hide(); $('#id_invalid_icon').show(); $('#id_status').text('เลขบัตรไม่ถูกต้อง').addClass('text-danger'); }
            } else { $('#id_valid_icon, #id_invalid_icon').hide(); $('#id_status').text(''); }
        });

        // Zipcode: ตัวเลข 5 หลักเท่านั้น
        $('#id_card_zipcode, #current_zipcode').on('input', function() {
            $(this).val($(this).val().replace(/\D/g, '').substring(0,5));
        });

        $('#phone').on('input', function() {
            let v = $(this).val().replace(/\D/g, '');
            if(v.length > 10) v = v.substring(0,10);
            let fmt = '';
            if(v.length > 0) fmt += v.substring(0,3);
            if(v.length > 3) fmt += '-' + v.substring(3,6);
            if(v.length > 6) fmt += '-' + v.substring(6,10);
            $(this).val(fmt);
        });

        let initialDate = $('#birth_date_db').val();
        if(initialDate && initialDate !== '0000-00-00') $('#birth_date_display').datepicker('update', moment(initialDate).toDate());
        toggleStayDetail($('input[name="stay_type"]:checked').val());
        if(!IS_EDIT_MODE) restoreDraft();
        
        $('input, select').on('change input', function() { saveDraft(); updateProgress(); });
        handleIntelligenceHealth();
        updateProgress();
    });

    function startVoiceInput(elementId) {
        if (!('webkitSpeechRecognition' in window)) { Swal.fire('ขออภัย', 'Browser ไม่รองรับ', 'error'); return; }
        let recognition = new webkitSpeechRecognition();
        recognition.lang = 'th-TH'; recognition.interimResults = false;
        let icon = $(`#${elementId}`).next('.input-group-text').find('i');
        icon.addClass('listening');
        recognition.onresult = function(event) { $(`#${elementId}`).val(event.results[0][0].transcript); icon.removeClass('listening'); };
        recognition.onerror = function() { icon.removeClass('listening'); };
        recognition.onend = function() { icon.removeClass('listening'); };
        recognition.start();
    }

    function updateFromDate(dateObj) {
        if(!dateObj) return;
        let mDate = moment(dateObj);
        $('#birth_date_db').val(mDate.format('YYYY-MM-DD'));
        let age = moment().diff(mDate, 'years');
        $('#age_input').val(age);
        smartIntelligenceSync(age);
    }
    
    function smartIntelligenceSync(age) {
        if(age >= 60) $('input[value="elderly"]').prop('checked', true);
        else if(age < 12) $('input[value="child"]').prop('checked', true);
        else {
             if(!$('input[value="elderly"]').is(':disabled')) $('input[value="elderly"]').prop('checked', false);
             if(!$('input[value="child"]').is(':disabled')) $('input[value="child"]').prop('checked', false);
        }
        handleIntelligenceHealth();
    }

    function handleIntelligenceHealth() {
        let l='green'; 
        $('.v-checkbox:checked').each(function(){ let dl=$(this).data('level'); if(dl=='red')l='red'; else if(dl=='yellow'&&l!='red')l='yellow'; });
        if(l=='red') $('#tr_red').prop('checked',true); else if(l=='yellow') $('#tr_yellow').prop('checked',true); else $('#tr_green').prop('checked',true);
    }

    function validateThaiID(id) { if(id.length != 13) return false; let sum=0; for(let i=0;i<12;i++) sum+=parseFloat(id.charAt(i))*(13-i); return (11-sum%11)%10===parseFloat(id.charAt(12)); }
    function checkDuplicateID(id) {
        $.get('check_duplicate_id.php', { id_card: id, current_id: '<?php echo $id; ?>' }).done(function(res){
            if(res.exists) { $('#id_status').html('<i class="fas fa-exclamation-triangle"></i> มีในระบบแล้ว: '+res.shelter_name).addClass('text-danger'); $('#id_invalid_icon').show(); $('#id_valid_icon').hide(); }
            else { $('#id_status').text('✅ ใช้งานได้').removeClass('text-danger').addClass('text-success'); }
        });
    }
    function checkFamilyHead() {
        let code = $('#family_code').val();
        if(code.length > 3) $.get('get_family_head.php', { code: code }).done(function(res){
            if(res.found) Swal.fire({ title: 'พบครอบครัว', text: `ใช้ที่อยู่ของ ${res.head_name} ?`, showCancelButton: true, confirmButtonText: 'ใช้' }).then((r) => { if(r.isConfirmed) { $('input[name="id_card_no"]').val(res.address.no); copyAddress(); }});
        });
    }
    function getLocation() {
        if(navigator.geolocation) {
            let btn = $('#stay_detail').next('button'); btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
            navigator.geolocation.getCurrentPosition(function(pos) {
                let gps = `GPS: ${pos.coords.latitude.toFixed(6)},${pos.coords.longitude.toFixed(6)}`;
                let val = $('#stay_detail').val(); $('#stay_detail').val(val ? val+' '+gps : gps);
                btn.prop('disabled',false).html('<i class="fas fa-location-arrow"></i> GPS');
                Swal.fire({icon:'success',title:'ระบุพิกัดแล้ว',toast:true,position:'top-end',timer:1500,showConfirmButton:false});
            }, function() { btn.prop('disabled',false).html('<i class="fas fa-location-arrow"></i> GPS'); Swal.fire('Error', 'ไม่สามารถดึงพิกัด', 'error'); });
        } else Swal.fire('Error', 'Browser ไม่รองรับ GPS', 'error');
    }
    function toggleStayDetail(val) { if(val==='outside') $('#stay_detail_wrapper').slideDown(); else $('#stay_detail_wrapper').slideUp(); }
    function copyAddress() { 
        ['no','moo','subdistrict','district','province','zipcode'].forEach(f => $(`#current_${f}`).val($(`input[name="id_card_${f}"]`).val())); 
        Swal.fire({icon:'success',title:'คัดลอกที่อยู่แล้ว',toast:true,position:'top-end',timer:1000,showConfirmButton:false}); 
    }
    function smartSyncByPrefix(val) {
        if(['นาย','เด็กชาย'].includes(val)) $('#g_male').prop('checked',true); else $('#g_female').prop('checked',true);
        if(['เด็กชาย','เด็กหญิง'].includes(val)) { $('input[value="child"]').prop('checked',true); if(!$('#age_input').val()) $('#age_input').val(5); }
        handleIntelligenceHealth();
    }
    function manualAgeSync() { smartIntelligenceSync(parseInt($('#age_input').val())); }
    function updateProgress() { let r=['fname','lname','phone','id_card_input']; let f=r.filter(id=>$('#'+id).val()).length; $('#formProgress').css('width',(f/r.length*100)+'%'); }
    function saveDraft() { if(!IS_EDIT_MODE) localStorage.setItem('evacuee_form_draft',$('#evacueeForm').serialize()); }
    function restoreDraft() {
        let d=localStorage.getItem('evacuee_form_draft'); if(d) {
            let p=new URLSearchParams(d); for(const[k,v] of p){ if($(`[name="${k}"]`).is(':radio,:checkbox')) $(`[name="${k}"][value="${v}"]`).prop('checked',true); else $(`[name="${k}"]`).val(v); }
        }
    }
    function confirmClearDraft() { Swal.fire({title:'ล้างข้อมูล?',showCancelButton:true}).then((r)=>{ if(r.isConfirmed){ localStorage.removeItem('evacuee_form_draft'); location.reload(); }}); }
    function submitIntelForm() {
        if(!$('#pdpa_check').is(':checked')) { Swal.fire('ข้อตกลง','กรุณายอมรับเงื่อนไข','warning'); return; }
        if(!$('#fname').val() || !$('#lname').val()) { Swal.fire('ข้อมูลไม่ครบ','กรุณากรอกชื่อ-นามสกุล','warning'); return; }
        $('#btn-submit-main, #btn-save-ui').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> บันทึก...');
        localStorage.removeItem('evacuee_form_draft'); $('#evacueeForm').submit();
    }
</script>

<?php if (isset($_SESSION['error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({icon:'error',title:'ผิดพลาด',text:'<?php echo $_SESSION['error']; unset($_SESSION['error']); ?>'});
    });
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
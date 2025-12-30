<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$shelter_id_url = isset($_GET['shelter_id']) ? intval($_GET['shelter_id']) : 0;
$mode = $id > 0 ? 'edit' : 'add';

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ค่าเริ่มต้น
$data = [
    'id_type' => 'thai_id',
    'prefix' => 'นาย', 'first_name' => '', 'last_name' => '', 'id_card' => '',
    'birth_date' => '', 'birth_date_thai' => '', 'age' => '', 'gender' => 'male', 'phone' => '',
    'shelter_id' => $shelter_id_url, 
    'stay_type' => 'shelter', 'stay_detail' => '',
    'status' => 'active',
    'chronic_disease' => '', 'medication' => '', 'vulnerable_group' => [],
    'addr_no'=>'','addr_moo'=>'','addr_sub'=>'','addr_dis'=>'','addr_prov'=>'',
    'curr_addr_no'=>'','curr_addr_moo'=>'','curr_addr_sub'=>'','curr_addr_dis'=>'','curr_addr_prov'=>'',
    'check_in_date' => date('Y-m-d\TH:i') 
];

function dateToThai($date) {
    if(empty($date) || $date == '0000-00-00') return '';
    $y = date('Y', strtotime($date)) + 543;
    $m = date('m', strtotime($date));
    $d = date('d', strtotime($date));
    return "$d-$m-$y";
}

if ($mode == 'edit') {
    $stmt = $conn->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $data = array_merge($data, $row); 
        $data['birth_date_thai'] = dateToThai($row['birth_date']);
        $data['vulnerable_group'] = !empty($row['vulnerable_group']) ? explode(',', $row['vulnerable_group']) : [];
        if(empty($data['id_type'])) $data['id_type'] = 'thai_id'; 
        
        if(!empty($row['registered_at'])) {
            $data['check_in_date'] = date('Y-m-d\TH:i', strtotime($row['registered_at']));
        }
    }
}

$shelters = $conn->query("SELECT id, name FROM shelters ORDER BY name ASC");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
    .main-content { margin-left: 0 !important; padding: 20px !important; width: 100% !important; }
    .footer { left: 0 !important; width: 100% !important; }
    #vertical-menu-btn { display: none !important; }
    .form-section-title { border-left: 5px solid #0d6efd; padding-left: 15px; margin: 30px 0 20px 0; font-weight: bold; color: #343a40; font-size: 1.1rem; background-color: #f8f9fa; padding-top:10px; padding-bottom:10px; border-radius: 0 5px 5px 0;}
    .required-mark { color: red; }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-user-edit me-2"></i><?php echo $mode == 'edit' ? 'แก้ไขข้อมูล' : 'ลงทะเบียนผู้ประสบภัย'; ?></h4>
                <a href="evacuee_list.php" class="btn btn-light border"><i class="fas fa-arrow-left me-1"></i> ย้อนกลับ</a>
            </div>

            <form action="evacuee_save.php" method="POST" class="needs-validation" novalidate onsubmit="return validateForm()">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-4">
                                
                                <div class="form-section-title mt-0">1. ข้อมูลระบุตัวตน</div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">ประเภทบัตร <span class="required-mark">*</span></label>
                                        <select class="form-select" name="id_type" id="id_type" onchange="toggleIdValidation()">
                                            <option value="thai_id" <?php echo $data['id_type']=='thai_id'?'selected':''; ?>>บัตรประชาชน</option>
                                            <option value="passport" <?php echo $data['id_type']=='passport'?'selected':''; ?>>หนังสือเดินทาง</option>
                                            <option value="no_doc" <?php echo $data['id_type']=='no_doc'?'selected':''; ?>>ไม่มีเอกสาร</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">เลขบัตร/เลขเอกสาร <span class="required-mark">*</span></label>
                                        <input type="text" class="form-control" name="id_card" id="id_card" value="<?php echo htmlspecialchars($data['id_card']); ?>" required <?php echo $mode=='edit'?'readonly':''; ?>>
                                        <div class="invalid-feedback" id="id_feedback">กรุณาระบุเลขที่ถูกต้อง</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">คำนำหน้า <span class="required-mark">*</span></label>
                                        <select class="form-select" name="prefix" required>
                                            <option value="">เลือก</option>
                                            <?php foreach(['นาย','นาง','นางสาว','ด.ช.','ด.ญ.'] as $p) echo "<option value='$p' ".($data['prefix']==$p?'selected':'').">$p</option>"; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ชื่อจริง <span class="required-mark">*</span></label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($data['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">นามสกุล <span class="required-mark">*</span></label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($data['last_name']); ?>" required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">วันเกิด (วว-ดด-ปปปป) พ.ศ.</label>
                                        <input type="text" class="form-control" name="birth_date" id="birth_date_thai" 
                                               value="<?php echo $data['birth_date_thai']; ?>" 
                                               placeholder="เช่น 30-12-2530" maxlength="10" 
                                               onkeyup="autoSlash(this)" onblur="calculateAgeFromThaiDate()">
                                        <small class="text-muted" style="font-size: 11px;">*ระบุปี พ.ศ. ระบบจะคำนวณอายุ</small>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">อายุ (ปี)</label>
                                        <input type="number" class="form-control bg-light" name="age" id="age" value="<?php echo $data['age']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">เพศ</label>
                                        <select class="form-select" name="gender">
                                            <option value="male" <?php echo $data['gender']=='male'?'selected':''; ?>>ชาย</option>
                                            <option value="female" <?php echo $data['gender']=='female'?'selected':''; ?>>หญิง</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">เบอร์โทรศัพท์</label>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($data['phone']); ?>">
                                    </div>
                                </div>

                                <div class="form-section-title">2. ที่อยู่ตามภูมิลำเนา (จำเป็น)</div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">บ้านเลขที่ <span class="required-mark">*</span></label>
                                        <input type="text" class="form-control" name="addr_no" id="addr_no" value="<?php echo htmlspecialchars($data['addr_no']); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">หมู่ที่</label>
                                        <input type="text" class="form-control" name="addr_moo" id="addr_moo" value="<?php echo htmlspecialchars($data['addr_moo']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">ตำบล <span class="required-mark">*</span></label>
                                                <input type="text" class="form-control" name="addr_sub" id="addr_sub" value="<?php echo htmlspecialchars($data['addr_sub']); ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">อำเภอ <span class="required-mark">*</span></label>
                                                <input type="text" class="form-control" name="addr_dis" id="addr_dis" value="<?php echo htmlspecialchars($data['addr_dis']); ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">จังหวัด <span class="required-mark">*</span></label>
                                                <input type="text" class="form-control" name="addr_prov" id="addr_prov" value="<?php echo htmlspecialchars($data['addr_prov']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section-title d-flex justify-content-between align-items-center">
                                    <span>3. ที่อยู่ปัจจุบัน (ติดต่อได้)</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnCopyAddress" onclick="copyAddress()">
                                        <i class="fas fa-copy"></i> คัดลอกตามบัตร
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">บ้านเลขที่</label>
                                        <input type="text" class="form-control" name="curr_addr_no" id="curr_addr_no" value="<?php echo htmlspecialchars($data['curr_addr_no']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">หมู่ที่</label>
                                        <input type="text" class="form-control" name="curr_addr_moo" id="curr_addr_moo" value="<?php echo htmlspecialchars($data['curr_addr_moo']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">ตำบล</label>
                                                <input type="text" class="form-control" name="curr_addr_sub" id="curr_addr_sub" value="<?php echo htmlspecialchars($data['curr_addr_sub']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">อำเภอ</label>
                                                <input type="text" class="form-control" name="curr_addr_dis" id="curr_addr_dis" value="<?php echo htmlspecialchars($data['curr_addr_dis']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">จังหวัด</label>
                                                <input type="text" class="form-control" name="curr_addr_prov" id="curr_addr_prov" value="<?php echo htmlspecialchars($data['curr_addr_prov']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4 bg-soft-light">
                                <div class="form-section-title mt-0 text-danger" style="border-color: #dc3545;">4. การพักพิงและสุขภาพ</div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">ศูนย์พักพิง <span class="required-mark">*</span></label>
                                    <select class="form-select" name="shelter_id" required>
                                        <option value="">-- เลือก --</option>
                                        <?php while($s = $shelters->fetch_assoc()): ?>
                                            <option value="<?php echo $s['id']; ?>" <?php echo $data['shelter_id']==$s['id']?'selected':''; ?>><?php echo $s['name']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-success">วันเวลาที่เข้าพัก (Check-in)</label>
                                    <input type="datetime-local" class="form-control border-success fw-bold" name="check_in_date" value="<?php echo $data['check_in_date']; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">รูปแบบการพักอาศัย</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="stay_type" id="stay_shelter" value="shelter" autocomplete="off" <?php echo $data['stay_type']=='shelter'?'checked':''; ?> onchange="toggleStayDetail()">
                                        <label class="btn btn-outline-primary" for="stay_shelter">พักในศูนย์</label>

                                        <input type="radio" class="btn-check" name="stay_type" id="stay_outside" value="outside" autocomplete="off" <?php echo $data['stay_type']=='outside'?'checked':''; ?> onchange="toggleStayDetail()">
                                        <label class="btn btn-outline-primary" for="stay_outside">พักนอกศูนย์</label>
                                    </div>
                                </div>

                                <div class="mb-3" id="stay_detail_wrapper" style="display: none;">
                                    <label class="form-label text-primary">รายละเอียดที่พัก (นอกศูนย์) <span class="required-mark">*</span></label>
                                    <textarea class="form-control" name="stay_detail" id="stay_detail" rows="2" placeholder="ระบุ บ้านญาติ/วัด/โรงเรียน..."><?php echo htmlspecialchars($data['stay_detail']); ?></textarea>
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">กลุ่มเปราะบาง:</label>
                                    <div class="vstack gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="vulnerable_group[]" value="elderly" id="v_elderly" <?php echo in_array('elderly',$data['vulnerable_group'])?'checked':''; ?>>
                                            <label class="form-check-label" for="v_elderly">👴 ผู้สูงอายุ (60+)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="vulnerable_group[]" value="child" id="v_child" <?php echo in_array('child',$data['vulnerable_group'])?'checked':''; ?>>
                                            <label class="form-check-label" for="v_child">👶 เด็กเล็ก (0-10)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="vulnerable_group[]" value="disabled" <?php echo in_array('disabled',$data['vulnerable_group'])?'checked':''; ?>>
                                            <label class="form-check-label">♿ ผู้พิการ</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="vulnerable_group[]" value="bedridden" <?php echo in_array('bedridden',$data['vulnerable_group'])?'checked':''; ?>>
                                            <label class="form-check-label text-danger fw-bold">🛌 ผู้ป่วยติดเตียง</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="vulnerable_group[]" value="pregnant" <?php echo in_array('pregnant',$data['vulnerable_group'])?'checked':''; ?>>
                                            <label class="form-check-label">🤰 หญิงตั้งครรภ์</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">โรคประจำตัว</label>
                                    <input type="text" class="form-control" name="chronic_disease" value="<?php echo htmlspecialchars($data['chronic_disease']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-danger">ยาที่แพ้ / ต้องการพิเศษ</label>
                                    <input type="text" class="form-control border-danger" name="medication" value="<?php echo htmlspecialchars($data['medication']); ?>">
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm mt-3">
                                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        toggleStayDetail();
        toggleIdValidation();

        // ------------------------------------------------------------------
        // แสดง Popup เมื่อมี Session ส่งกลับมา
        // ------------------------------------------------------------------
        <?php if(isset($_SESSION['swal_icon'])): ?>
            Swal.fire({
                icon: <?php echo json_encode($_SESSION['swal_icon']); ?>,
                title: <?php echo json_encode($_SESSION['swal_title']); ?>,
                text: <?php echo json_encode($_SESSION['swal_text']); ?>,
                timer: 4000,
                showConfirmButton: true
            });
            <?php 
            unset($_SESSION['swal_icon']);
            unset($_SESSION['swal_title']);
            unset($_SESSION['swal_text']);
            ?>
        <?php endif; ?>
    });

    function autoSlash(ele) {
        var val = ele.value.replace(/\D/g, '').substring(0,8);
        var newVal = '';
        if(val.length > 4) {
            newVal += val.substr(0, 2) + '-';
            newVal += val.substr(2, 2) + '-';
            newVal += val.substr(4, 4);
        } else if(val.length > 2) {
            newVal += val.substr(0, 2) + '-';
            newVal += val.substr(2);
        } else {
            newVal = val;
        }
        ele.value = newVal;
    }

    function calculateAgeFromThaiDate() {
        var dateStr = document.getElementById('birth_date_thai').value;
        if(dateStr.length === 10) {
            var parts = dateStr.split('-');
            var day = parseInt(parts[0]);
            var month = parseInt(parts[1]);
            var thaiYear = parseInt(parts[2]);
            var engYear = thaiYear - 543;
            var today = new Date();
            var currentYear = today.getFullYear();
            var currentMonth = today.getMonth() + 1;
            var currentDay = today.getDate();
            var age = currentYear - engYear;
            if (currentMonth < month || (currentMonth == month && currentDay < day)) { age--; }
            if(age >= 0) {
                $('#age').val(age);
                $('#v_elderly').prop('checked', age >= 60);
                $('#v_child').prop('checked', age <= 10);
            }
        }
    }

    function toggleIdValidation() {
        var type = $('#id_type').val();
        if (type === 'thai_id') {
            $('#id_card').attr('maxlength', '13').attr('pattern', '\\d{13}');
        } else {
            $('#id_card').removeAttr('maxlength').removeAttr('pattern');
        }
    }

    function checkThaiID(id) {
        if(id.length != 13) return false;
        var sum = 0;
        for(var i=0; i < 12; i++) sum += parseFloat(id.charAt(i))*(13-i);
        return (11-sum%11)%10 === parseFloat(id.charAt(12));
    }

    function validateForm() {
        var type = $('#id_type').val();
        var id = $('#id_card').val();
        
        if (type === 'thai_id' && !checkThaiID(id)) {
            Swal.fire('ข้อผิดพลาด', 'เลขบัตรประชาชนไม่ถูกต้องตามหลักการคำนวณ', 'error');
            $('#id_card').focus();
            return false;
        }
        
        if ($('#stay_outside').is(':checked') && $('#stay_detail').val().trim() === '') {
            Swal.fire('แจ้งเตือน', 'กรุณาระบุรายละเอียดที่พักภายนอก', 'warning');
            $('#stay_detail').focus();
            return false;
        }
        
        return true;
    }

    function toggleStayDetail() {
        if ($('#stay_outside').is(':checked')) {
            $('#stay_detail_wrapper').slideDown();
            $('#stay_detail').prop('required', true);
        } else {
            $('#stay_detail_wrapper').slideUp();
            $('#stay_detail').prop('required', false);
        }
    }

    function copyAddress() {
        $('#curr_addr_no').val($('#addr_no').val());
        $('#curr_addr_moo').val($('#addr_moo').val());
        $('#curr_addr_sub').val($('#addr_sub').val());
        $('#curr_addr_dis').val($('#addr_dis').val());
        $('#curr_addr_prov').val($('#addr_prov').val());
    }
</script>
<?php include 'includes/footer.php'; ?>
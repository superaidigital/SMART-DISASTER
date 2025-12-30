<?php
session_start(); 
require_once 'config/db.php';
include 'includes/header.php';

$shelter_id = isset($_GET['shelter_id']) ? intval($_GET['shelter_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT e.*, s.name as shelter_name FROM evacuees e LEFT JOIN shelters s ON e.shelter_id = s.id WHERE 1=1";
if ($shelter_id > 0) $sql .= " AND e.shelter_id = $shelter_id";
if (!empty($search)) $sql .= " AND (e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%' OR e.id_card LIKE '%$search%')";
$sql .= " ORDER BY e.registered_at DESC";
$result = $conn->query($sql);

function getStatusBadge($s) {
    if($s=='active') return '<span class="badge bg-success">พักอาศัยอยู่</span>';
    if($s=='hospitalized') return '<span class="badge bg-danger">ส่ง รพ.</span>';
    return '<span class="badge bg-secondary">กลับบ้าน</span>';
}
?>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .main-content { margin-left: 0 !important; padding: 20px !important; width: 100% !important; }
    .footer { left: 0 !important; width: 100% !important; }
    #vertical-menu-btn { display: none !important; }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 text-primary fw-bold">รายชื่อผู้ประสบภัย</h4>
                <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-primary"><i class="fas fa-plus me-2"></i> ลงทะเบียนใหม่</a>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="ค้นหาชื่อ หรือเลขบัตร..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" type="submit">ค้นหา</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ชื่อ-นามสกุล</th>
                                    <th>อายุ/เพศ</th>
                                    <th>กลุ่มเปราะบาง</th>
                                    <th>ที่อยู่</th>
                                    <th>สถานะ</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-primary"><?php echo $row['prefix'].$row['first_name'].' '.$row['last_name']; ?></div>
                                                <small class="text-muted"><?php echo $row['id_card']; ?></small>
                                            </td>
                                            <td><?php echo $row['age']; ?> ปี / <?php echo $row['gender']=='male'?'ช':'ญ'; ?></td>
                                            <td>
                                                <?php 
                                                if($row['vulnerable_group']) {
                                                    $g = explode(',',$row['vulnerable_group']);
                                                    foreach($g as $v) {
                                                        if($v=='elderly') echo '<span class="badge bg-warning text-dark me-1">สูงอายุ</span>';
                                                        if($v=='child') echo '<span class="badge bg-info text-dark me-1">เด็ก</span>';
                                                        if($v=='disabled') echo '<span class="badge bg-primary me-1">พิการ</span>';
                                                        if($v=='bedridden') echo '<span class="badge bg-danger me-1">ติดเตียง</span>';
                                                    }
                                                } else echo '-';
                                                ?>
                                            </td>
                                            <td><small><?php echo $row['address']; ?></small></td>
                                            <td><?php echo getStatusBadge($row['status']); ?></td>
                                            <td class="text-end pe-4">
                                                <a href="evacuee_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                                <a href="evacuee_delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('ลบข้อมูลนี้?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูล</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_SESSION['swal_icon'])): ?>
        Swal.fire({
            icon: <?php echo json_encode($_SESSION['swal_icon']); ?>,
            title: <?php echo json_encode($_SESSION['swal_title']); ?>,
            text: <?php echo json_encode($_SESSION['swal_text']); ?>,
            timer: 4000, // แสดงนานขึ้นเพื่อให้ทันอ่านรายละเอียด
            showConfirmButton: true
        });
        <?php 
        // เคลียร์ค่า Session
        unset($_SESSION['swal_icon']);
        unset($_SESSION['swal_title']);
        unset($_SESSION['swal_text']);
        ?>
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
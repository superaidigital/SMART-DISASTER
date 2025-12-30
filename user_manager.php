<?php
/**
 * user_manager.php
 * หน้าจัดการผู้ใช้งานระบบ (สำหรับ Admin)
 */
session_start();
require_once 'config/db.php';
include 'includes/header.php';

// ตรวจสอบสิทธิ์ (ตัวอย่าง: ต้องเป็น admin เท่านั้น)
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     header("Location: index.php"); // หรือหน้า access denied
//     exit();
// }

// ค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT u.*, s.name as shelter_name 
        FROM users u 
        LEFT JOIN shelters s ON u.shelter_id = s.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (u.username LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}
$sql .= " ORDER BY u.created_at DESC";
$result = $conn->query($sql);

// Helper แปลง Role เป็น Badge
function getRoleBadge($role) {
    switch ($role) {
        case 'admin': return '<span class="badge bg-danger">ผู้ดูแลระบบ</span>';
        case 'staff': return '<span class="badge bg-primary">เจ้าหน้าที่</span>';
        case 'volunteer': return '<span class="badge bg-info">อาสาสมัคร</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($role) . '</span>';
    }
}
?>

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .main-content { margin-left: 0 !important; padding: 20px !important; width: 100% !important; }
    .footer { left: 0 !important; width: 100% !important; }
    #vertical-menu-btn { display: none !important; }
    .avatar-circle {
        width: 40px; height: 40px; background-color: #0d6efd; color: white;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 18px;
    }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-users-cog me-2"></i>จัดการผู้ใช้งานระบบ</h4>
                    <span class="text-muted">ดูแลบัญชีผู้ใช้ กำหนดสิทธิ์ และมอบหมายศูนย์พักพิง</span>
                </div>
                <a href="user_form.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ใช้งานใหม่</a>
            </div>

            <!-- Search Box -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-10">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="ค้นหาชื่อ, Username..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark w-100">ค้นหา</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ผู้ใช้งาน</th>
                                    <th>ตำแหน่ง/สิทธิ์</th>
                                    <th>ดูแลศูนย์พักพิง</th>
                                    <th>สถานะ</th>
                                    <th>วันที่สร้าง</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3">
                                                        <?php echo strtoupper(substr($row['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark">
                                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                        </h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo getRoleBadge($row['role']); ?></td>
                                            <td>
                                                <?php if (!empty($row['shelter_name'])): ?>
                                                    <span class="text-primary"><i class="fas fa-campground me-1"></i> <?php echo $row['shelter_name']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">- ไม่ระบุ -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Check if status key exists, default to 'active' or handle missing key
                                                $userStatus = isset($row['status']) ? $row['status'] : 'active'; 
                                                if ($userStatus == 'active'): 
                                                ?>
                                                    <span class="badge bg-soft-success text-success"><i class="fas fa-check-circle me-1"></i> ใช้งานปกติ</span>
                                                <?php else: ?>
                                                    <span class="badge bg-soft-secondary text-secondary"><i class="fas fa-ban me-1"></i> ระงับการใช้งาน</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></small></td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group">
                                                    <a href="user_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary" title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($row['username'] !== 'admin'): // ป้องกันการลบ admin หลัก ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="ลบ" onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-user-slash fa-3x mb-3"></i><br>
                                            ไม่พบข้อมูลผู้ใช้งาน
                                        </td>
                                    </tr>
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
    // แสดง Popup แจ้งเตือนจาก Session
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(isset($_SESSION['swal_icon'])): ?>
            Swal.fire({
                icon: <?php echo json_encode($_SESSION['swal_icon']); ?>,
                title: <?php echo json_encode($_SESSION['swal_title']); ?>,
                text: <?php echo json_encode($_SESSION['swal_text']); ?>,
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['swal_icon'], $_SESSION['swal_title'], $_SESSION['swal_text']); ?>
        <?php endif; ?>
    });

    // ฟังก์ชันยืนยันการลบ
    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "บัญชีผู้ใช้นี้จะถูกลบถาวร!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'user_save.php?action=delete&id=' + id;
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
<?php
    $pageTitle = 'Danh sách nhân viên';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Xử lý thêm / sửa / xóa nhân viên
    if ($_POST) {
        $action = $_POST['action'] ?? '';

        if ($action == 'add' || $action == 'edit') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = 'staff';

        if (!empty($username) && !empty($full_name)) {
            try {
                if ($action == 'add') {
                    // hash mật khẩu nếu có nhập
                    $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $query = "INSERT INTO users (username, password, full_name, role, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $hashedPassword, $full_name, $role]);
                    $success = "Thêm nhân viên thành công!";
                } else {
                    $id = (int)$_POST['id'];
                    if (!empty($password)) {
                        // nếu có đổi mật khẩu → hash lại
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $query = "UPDATE users SET username=?, password=?, full_name=? WHERE id=? AND role='staff'";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$username, $hashedPassword, $full_name, $id]);
                    } else {
                        $query = "UPDATE users SET username=?, full_name=? WHERE id=? AND role='staff'";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$username, $full_name, $id]);
                    }
                    $success = "Cập nhật nhân viên thành công!";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra: " . $e->getMessage();
            }
        } else {
            $error = "Tên đăng nhập và họ tên không được để trống!";
        }
    }


        if ($action == 'delete') {
            try {
                $id = (int)$_POST['id'];
                $query = "DELETE FROM users WHERE id = ? AND role = 'staff'";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $success = "Xóa nhân viên thành công!";
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra khi xóa: " . $e->getMessage();
            }
        }
    }

    // Lấy danh sách nhân viên
    $query = "SELECT id, username, full_name, role, created_at FROM users WHERE role = 'staff' ORDER BY full_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-success"><i class="fas fa-user me-2"></i>Danh sách nhân viên</h1>
    <button type="button" class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#employeeModal">
        <i class="fas fa-plus"></i> Thêm nhân viên
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success text-center"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger text-center"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card rounded-4 shadow mb-4">
    <div class="card-header bg-light rounded-top-4">
        <h5 class="mb-0"><i class="fas fa-users"></i> Danh sách</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Họ và tên</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Chưa có nhân viên nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo $emp['id']; ?></td>
                            <td><?php echo htmlspecialchars($emp['username']); ?></td>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo $emp['created_at']; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3" onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3" onclick="deleteEmployee(<?php echo $emp['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal thêm/sửa nhân viên -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm nhân viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="empId">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-bold">Tên đăng nhập *</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-bold">Họ và tên *</label>
                        <input type="text" class="form-control" name="full_name" id="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">Mật khẩu</label>
                        <input type="text" class="form-control" name="password" id="password" placeholder="Nhập mật khẩu (để trống nếu không đổi)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary rounded-3">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editEmployee(emp) {
        document.getElementById('modalTitle').textContent = 'Sửa nhân viên';
        document.getElementById('action').value = 'edit';
        document.getElementById('empId').value = emp.id;
        document.getElementById('username').value = emp.username;
        document.getElementById('full_name').value = emp.full_name;
        document.getElementById('password').value = ''; // reset password
        new bootstrap.Modal(document.getElementById('employeeModal')).show();
    }

    function deleteEmployee(id) {
        if (confirm('Bạn có chắc chắn muốn xóa nhân viên này?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Reset form khi đóng modal
    document.getElementById('employeeModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Thêm nhân viên';
        document.getElementById('action').value = 'add';
        document.querySelector('#employeeModal form').reset();
    });
</script>

<?php include '../includes/footer.php'; ?>

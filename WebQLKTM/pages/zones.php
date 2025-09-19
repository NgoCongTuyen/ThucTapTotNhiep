<?php
    $pageTitle = 'Quản lý khu';
    require_once '../config/database.php';
    include '../includes/header.php';
    include 'chat_bot.php';

    $database = new Database();
    $db = $database->getConnection();

    // Xử lý thêm/sửa/xóa khu
    if ($_POST) {
        $action = $_POST['action'] ?? '';

        if ($action == 'add' || $action == 'edit') {
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!empty($code) && !empty($name)) {
                try {
                    if ($action == 'add') {
                        $query = "INSERT INTO zones (code, name, description) VALUES (?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$code, $name, $description]);
                        $success = "Thêm khu thành công!";
                    } else {
                        $id = (int)$_POST['id'];
                        $query = "UPDATE zones SET code=?, name=?, description=? WHERE id=?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$code, $name, $description, $id]);
                        $success = "Cập nhật khu thành công!";
                    }
                } catch (PDOException $e) {
                    $error = "Có lỗi xảy ra: " . $e->getMessage();
                }
            } else {
                $error = "Mã khu và tên khu không được để trống!";
            }
        }

        if ($action == 'delete') {
            try {
                $id = (int)$_POST['id'];

                // Kiểm tra sản phẩm thuộc khu
                $check_query = "SELECT COUNT(*) as count FROM products WHERE zone_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$id]);
                $product_count = $check_stmt->fetch()['count'];

                if ($product_count > 0) {
                    $error = "Không thể xóa khu này vì có $product_count sản phẩm!";
                } else {
                    $query = "DELETE FROM zones WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $success = "Xóa khu thành công!";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra khi xóa: " . $e->getMessage();
            }
        }
    }

    // Lấy danh sách khu
    $query = "SELECT z.*, COUNT(p.id) as product_count
            FROM zones z
            LEFT JOIN products p ON z.id = p.zone_id
            GROUP BY z.id
            ORDER BY z.code";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"  style="color: blue;"><i class="fas fa-warehouse"></i> Quản lý khu</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#zoneModal">
        <i class="fas fa-plus"></i> Thêm khu
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Danh sách khu -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-warehouse"></i> Danh sách khu</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã khu</th>
                        <th>Tên khu</th>   <!-- đổi lại -->
                        <th>Mô tả</th>
                        <th>Số sản phẩm</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($zones)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Chưa có khu nào</td>
                        </tr>
                    <?php else: foreach ($zones as $zone): ?>
                        <tr>
                               <td><?= $zone['id'] ?></td>
                                <td><strong><?= htmlspecialchars($zone['code']) ?></strong></td>
                                <td><?= htmlspecialchars($zone['name']) ?></td> <!-- tên khu -->
                                <td><?= htmlspecialchars($zone['description'] ?? '') ?></td> <!-- mô tả -->
                                <td><span class="badge bg-info"><?= $zone['product_count'] ?></span></td>
                                <td><?= date('d/m/Y', strtotime($zone['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick="editZone(<?= htmlspecialchars(json_encode($zone)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($zone['product_count'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteZone(<?= $zone['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Không thể xóa vì có sản phẩm">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal thêm/sửa khu -->
<div class="modal fade" id="zoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm khu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="zoneId">

                    <div class="mb-3">
                        <label for="code" class="form-label">Mã khu *</label>
                        <input type="text" class="form-control" name="code" id="code" required>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Loại sản phẩm *</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editZone(zone) {
        document.getElementById('modalTitle').textContent = 'Sửa khu';
        document.getElementById('action').value = 'edit';
        document.getElementById('zoneId').value = zone.id;
        document.getElementById('code').value = zone.code;
        document.getElementById('name').value = zone.name;
        document.getElementById('description').value = zone.description || '';
        new bootstrap.Modal(document.getElementById('zoneModal')).show();
    }

    function deleteZone(id) {
        if (confirm('Bạn có chắc chắn muốn xóa khu này?')) {
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

    document.getElementById('zoneModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Thêm khu';
        document.getElementById('action').value = 'add';
        document.querySelector('#zoneModal form').reset();
    });
</script>

<?php include '../includes/footer.php'; ?>

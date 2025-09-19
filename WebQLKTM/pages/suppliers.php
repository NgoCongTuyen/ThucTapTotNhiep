<?php
    $pageTitle = 'Quản lý nhà cung cấp';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Xử lý thêm/sửa/xóa nhà cung cấp
    if ($_POST) {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add' || $action == 'edit') {
            $name = trim($_POST['name'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (!empty($name)) {
                try {
                    if ($action == 'add') {
                        $query = "INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $contact_person, $phone, $email, $address]);
                        $success = "Thêm nhà cung cấp thành công!";
                    } else {
                        $id = (int)$_POST['id'];
                        $query = "UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $contact_person, $phone, $email, $address, $id]);
                        $success = "Cập nhật nhà cung cấp thành công!";
                    }
                } catch (PDOException $e) {
                    $error = "Có lỗi xảy ra: " . $e->getMessage();
                }
            } else {
                $error = "Tên nhà cung cấp không được để trống!";
            }
        }
        
        if ($action == 'delete') {
            try {
                $id = (int)$_POST['id'];
                
                // Kiểm tra xem có sản phẩm nào của nhà cung cấp này không
                $check_query = "SELECT COUNT(*) as count FROM products WHERE supplier_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$id]);
                $product_count = $check_stmt->fetch()['count'];
                
                if ($product_count > 0) {
                    $error = "Không thể xóa nhà cung cấp này vì có $product_count sản phẩm đang sử dụng!";
                } else {
                    $query = "DELETE FROM suppliers WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $success = "Xóa nhà cung cấp thành công!";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra khi xóa: " . $e->getMessage();
            }
        }
    }

    // Lấy danh sách nhà cung cấp
    $query = "SELECT s.*, COUNT(p.id) as product_count 
            FROM suppliers s 
            LEFT JOIN products p ON s.id = p.supplier_id 
            GROUP BY s.id 
            ORDER BY s.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-success"><i class="fas fa-truck me-2"></i>Quản lý nhà cung cấp</h1>
    <button type="button" class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="fas fa-plus"></i> Thêm nhà cung cấp
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded shadow-sm text-center" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded shadow-sm text-center" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Danh sách nhà cung cấp -->
<div class="card rounded-4 shadow mb-4">
    <div class="card-header bg-light rounded-top-4">
        <h5 class="mb-0"><i class="fas fa-truck"></i> Danh sách nhà cung cấp</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Tên công ty</th>
                        <th>Người liên hệ</th>
                        <th>Điện thoại</th>
                        <th>Email</th>
                        <th>Số sản phẩm</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Chưa có nhà cung cấp nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo $supplier['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($supplier['email'] ?? ''); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo number_format($supplier['product_count']); ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($supplier)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($supplier['product_count'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3" onclick="deleteSupplier(<?php echo $supplier['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" disabled title="Không thể xóa vì có sản phẩm">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal thêm/sửa nhà cung cấp -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm nhà cung cấp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="supplierId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">Tên công ty *</label>
                                <input type="text" class="form-control shadow-sm" name="name" id="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_person" class="form-label fw-bold">Người liên hệ</label>
                                <input type="text" class="form-control shadow-sm" name="contact_person" id="contact_person">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-bold">Điện thoại</label>
                                <input type="text" class="form-control shadow-sm" name="phone" id="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Email</label>
                                <input type="email" class="form-control shadow-sm" name="email" id="email">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label fw-bold">Địa chỉ</label>
                        <textarea class="form-control shadow-sm" name="address" id="address" rows="3"></textarea>
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
    function editSupplier(supplier) {
        document.getElementById('modalTitle').textContent = 'Sửa nhà cung cấp';
        document.getElementById('action').value = 'edit';
        document.getElementById('supplierId').value = supplier.id;
        document.getElementById('name').value = supplier.name;
        document.getElementById('contact_person').value = supplier.contact_person || '';
        document.getElementById('phone').value = supplier.phone || '';
        document.getElementById('email').value = supplier.email || '';
        document.getElementById('address').value = supplier.address || '';
        
        new bootstrap.Modal(document.getElementById('supplierModal')).show();
    }

    function deleteSupplier(id) {
        if (confirm('Bạn có chắc chắn muốn xóa nhà cung cấp này?')) {
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
    document.getElementById('supplierModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Thêm nhà cung cấp';
        document.getElementById('action').value = 'add';
        document.querySelector('#supplierModal form').reset();
    });
</script>
<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>

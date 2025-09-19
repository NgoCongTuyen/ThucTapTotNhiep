<?php
    $pageTitle = 'Quản lý danh mục';
    require_once '../config/database.php';
    include '../includes/header.php';
    include 'chat_bot.php';


    $database = new Database();
    $db = $database->getConnection();

    // Xử lý thêm/sửa/xóa danh mục
    if ($_POST) {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add' || $action == 'edit') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($name)) {
                try {
                    if ($action == 'add') {
                        $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $description]);
                        $success = "Thêm danh mục thành công!";
                    } else {
                        $id = (int)$_POST['id'];
                        $query = "UPDATE categories SET name=?, description=? WHERE id=?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $description, $id]);
                        $success = "Cập nhật danh mục thành công!";
                    }
                } catch (PDOException $e) {
                    $error = "Có lỗi xảy ra: " . $e->getMessage();
                }
            } else {
                $error = "Tên danh mục không được để trống!";
            }
        }
        
        if ($action == 'delete') {
            try {
                $id = (int)$_POST['id'];
                
                // Kiểm tra xem có sản phẩm nào thuộc danh mục này không
                $check_query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$id]);
                $product_count = $check_stmt->fetch()['count'];
                
                if ($product_count > 0) {
                    $error = "Không thể xóa danh mục này vì có $product_count sản phẩm đang sử dụng!";
                } else {
                    $query = "DELETE FROM categories WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $success = "Xóa danh mục thành công!";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra khi xóa: " . $e->getMessage();
            }
        }
    }

    // Lấy danh sách danh mục
    $query = "SELECT c.*, COUNT(p.id) as product_count 
            FROM categories c 
            LEFT JOIN products p ON c.id = p.category_id 
            GROUP BY c.id 
            ORDER BY c.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"  style="color: blue;"><i class="fas fa-tags "></i> Quản lý danh mục</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus"></i> Thêm danh mục
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Danh sách danh mục -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-tags"></i> Danh sách danh mục</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên danh mục</th>
                        <th>Mô tả</th>
                        <th>Số sản phẩm</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Chưa có danh mục nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo number_format($category['product_count']); ?></span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($category['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($category['product_count'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Không thể xóa vì có sản phẩm">
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

<!-- Modal thêm/sửa danh mục -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm danh mục</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên danh mục *</label>
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
function editCategory(category) {
    document.getElementById('modalTitle').textContent = 'Sửa danh mục';
    document.getElementById('action').value = 'edit';
    document.getElementById('categoryId').value = category.id;
    document.getElementById('name').value = category.name;
    document.getElementById('description').value = category.description || '';
    
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function deleteCategory(id) {
    if (confirm('Bạn có chắc chắn muốn xóa danh mục này?')) {
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
document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Thêm danh mục';
    document.getElementById('action').value = 'add';
    document.querySelector('#categoryModal form').reset();
});
</script>

<?php include '../includes/footer.php'; ?>

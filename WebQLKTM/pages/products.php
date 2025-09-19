<?php
    $pageTitle = 'Quản lý sản phẩm';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Lấy danh mục, nhà cung cấp, khu vực
    $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $suppliers = $db->query("SELECT * FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $zones = $db->query("SELECT * FROM zones ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

    // Xử lý thêm/sửa sản phẩm
    if ($_POST) {
        $action = $_POST['action'] ?? '';

        if ($action == 'add' || $action == 'edit') {
            if ($action == 'add') {
                $stmt = $db->query("SELECT MAX(id) AS max_id FROM products");
                $max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
                $next_id = $max_id + 1;
                $code = 'SP' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
            } else {
                $code = trim($_POST['code'] ?? '');
            }

            $name = trim($_POST['name'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
            $unit = trim($_POST['unit'] ?? '');
            $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
            $min_stock = !empty($_POST['min_stock']) ? (int)$_POST['min_stock'] : 0;
            $description = trim($_POST['description'] ?? '');
            $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;

            $errors = [];
            if (empty($name)) $errors[] = "Tên sản phẩm không được để trống";
            if (empty($unit)) $errors[] = "Đơn vị không được để trống";
            if ($price < 0) $errors[] = "Giá không được âm";
            if ($min_stock < 0) $errors[] = "Tồn kho tối thiểu không được âm";

            if ($action == 'edit') {
                $id = (int)$_POST['id'];
                $check_query = "SELECT COUNT(*) as count FROM products WHERE code = ? AND id != ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$code, $id]);
                if ($check_stmt->fetch()['count'] > 0) {
                    $errors[] = "Mã sản phẩm đã tồn tại";
                }
            }

            if (empty($errors)) {
                try {
                    if ($action == 'add') {
                        $query = "INSERT INTO products (code, name, category_id, supplier_id, unit, price, min_stock, description, zone_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$code, $name, $category_id, $supplier_id, $unit, $price, $min_stock, $description, $zone_id]);
                        $success = "Thêm sản phẩm thành công! Mã sản phẩm: $code";
                    } else {
                        $query = "UPDATE products SET code=?, name=?, category_id=?, supplier_id=?, unit=?, price=?, min_stock=?, description=?, zone_id=? WHERE id=?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$code, $name, $category_id, $supplier_id, $unit, $price, $min_stock, $description, $zone_id, $id]);
                        $success = "Cập nhật sản phẩm thành công!";
                    }
                } catch (PDOException $e) {
                    $error = "Có lỗi xảy ra: " . $e->getMessage();
                }
            } else {
                $error = implode(", ", $errors);
            }
        }

        if ($action == 'delete') {
            try {
                $id = (int)$_POST['id'];
                $check_query = "SELECT COUNT(*) as count FROM stock_transactions WHERE product_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$id]);
                $transaction_count = $check_stmt->fetch()['count'];

                if ($transaction_count > 0) {
                    $error = "Không thể xóa sản phẩm này vì đã có giao dịch liên quan!";
                } else {
                    $query = "DELETE FROM products WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $success = "Xóa sản phẩm thành công!";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra khi xóa: " . $e->getMessage();
            }
        }
    }

    // Lấy danh sách sản phẩm
    $search = $_GET['search'] ?? '';
    $filter_zone_id = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;

    // Phân trang
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $query = "SELECT p.*, c.name as category_name, s.name as supplier_name, z.code as zone_code, z.name as zone_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN zones z ON p.zone_id = z.id";
    $params = [];
    $where_clauses = [];

    if ($search) {
        $where_clauses[] = "(p.name LIKE ? OR p.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_zone_id) {
        $where_clauses[] = "p.zone_id = ?";
        $params[] = $filter_zone_id;
    }

    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Đếm tổng số bản ghi theo bộ lọc
    $count_sql = "SELECT COUNT(*) as total
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN suppliers s ON p.supplier_id = s.id
                  LEFT JOIN zones z ON p.zone_id = z.id";
    if (!empty($where_clauses)) {
        $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = (int)($count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $total_pages = (int)ceil($total_records / $limit);

    $query .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy tên khu hiện tại
    $current_zone_name = '';
    if ($filter_zone_id) {
        $stmt = $db->prepare("SELECT CONCAT(code, ' - ', name) as full_name FROM zones WHERE id = ?");
        $stmt->execute([$filter_zone_id]);
        $current_zone_name = $stmt->fetchColumn();
    }
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"  style="color: blue;"><i class="fas fa-box"></i> Quản lý sản phẩm <?php echo $current_zone_name ? 'trong khu vực ' . htmlspecialchars($current_zone_name) : ''; ?></h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="fas fa-plus"></i> Thêm sản phẩm
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

  <link rel="stylesheet" href="../css/transactions.css">

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tìm kiếm -->
<div class="row mb-3">
    <div class="col-md-6">
        <form method="GET" class="d-flex">
            <input type="text" class="form-control" name="search" placeholder="Tìm kiếm sản phẩm..." value="<?php echo htmlspecialchars($search); ?>">
            <?php if ($filter_zone_id): ?>
                <input type="hidden" name="zone_id" value="<?php echo htmlspecialchars($filter_zone_id); ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-outline-secondary ms-2">Tìm</button>
        </form>
    </div>
</div>


<link rel="stylesheet" href="../css/main.css">
<!-- Danh sách sản phẩm -->
<div class="table-container table-responsive">
    <table class="table table-striped table-sticky-head">
        <thead>
            <tr>
                <th>Mã SP</th>
                <th>Tên sản phẩm</th>
                <th>Danh mục</th>
                <th>Nhà cung cấp</th>
                <th>Đơn vị</th>
                <th>Giá</th>
                <th>Tồn kho</th>
                <th>Tối thiểu</th>
                <th>Khu vực</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="11" class="text-center text-muted">Không có sản phẩm nào.</td></tr>
            <?php else: foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['code']); ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($product['unit']); ?></td>
                    <td><?php echo number_format($product['price']); ?> VNĐ</td>
                    <td><?php echo number_format($product['current_stock']); ?></td>
                    <td><?php echo number_format($product['min_stock']); ?></td>
                    <td><?php echo htmlspecialchars($product['zone_code'] . ' - ' . $product['zone_name']); ?></td>
                    <td>
                        <?php if ($product['current_stock'] <= $product['min_stock']): ?>
                            <span class="badge bg-danger">Sắp hết</span>
                        <?php elseif ($product['current_stock'] <= $product['min_stock'] * 2): ?>
                            <span class="badge bg-warning">Ít</span>
                        <?php else: ?>
                            <span class="badge bg-success">Đủ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='editProduct(<?php echo json_encode($product); ?>)'><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </table>
</div>

<?php
    // Tạo link phân trang giữ filter
    $query_params = $_GET;
    unset($query_params['page']);
    $base_query_string = http_build_query($query_params);
    $current_page = (int)$page;
    $prev_page = max(1, $current_page - 1);
    $next_page = max(1, min($total_pages, $current_page + 1));
    $from_record = $total_records > 0 ? ($offset + 1) : 0;
    $to_record = min($offset + $limit, (int)$total_records);
    $makeHref = function($p) use ($base_query_string) {
        return 'products.php?' . $base_query_string . ($base_query_string ? '&' : '') . 'page=' . $p;
    };
?>

<?php if ($total_pages > 1): ?>
<div class="mt-3">
    <div class="text-muted small text-center mb-2">
        Hiển thị <?php echo number_format($from_record); ?>–<?php echo number_format($to_record); ?> trong tổng số <?php echo number_format($total_records); ?> sản phẩm
    </div>
    <nav class="d-flex justify-content-center" aria-label="Pagination sản phẩm">
        <ul class="pagination pagination-sm">
            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $makeHref($prev_page); ?>">Trước</a>
            </li>
            <?php
                $window = 2;
                $start = max(1, $current_page - $window);
                $end = min($total_pages, $current_page + $window);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . $makeHref(1) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                for ($p = $start; $p <= $end; $p++) {
                    $active = $p == $current_page ? ' active' : '';
                    echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $makeHref($p) . '">' . $p . '</a></li>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . $makeHref($total_pages) . '">' . $total_pages . '</a></li>';
                }
            ?>
            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $makeHref($next_page); ?>">Sau</a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>

    <!-- Modal thêm/sửa sản phẩm -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm sản phẩm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="productId">

                    <div class="mb-3" id="codeWrapper" style="display:none;">
                        <label for="code" class="form-label">Mã sản phẩm</label>
                        <input type="text" class="form-control" name="code" id="code" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Tên sản phẩm *</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Danh mục</label>
                            <select class="form-select" name="category_id" id="category_id">
                                <option value="">Chọn danh mục</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label">Nhà cung cấp</label>
                            <select class="form-select" name="supplier_id" id="supplier_id">
                                <option value="">Chọn nhà cung cấp</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <label for="unit" class="form-label">Đơn vị *</label>
                            <input type="text" class="form-control" name="unit" id="unit" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price" class="form-label">Giá</label>
                            <input type="number" class="form-control" name="price" id="price" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label for="min_stock" class="form-label">Tồn kho tối thiểu</label>
                            <input type="number" class="form-control" name="min_stock" id="min_stock">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="zone_id" class="form-label">Khu vực</label>
                        <select class="form-select" name="zone_id" id="zone_id">
                            <option value="">Chọn khu vực</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['code'] . ' - ' . $zone['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mt-3">
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
function editProduct(product) {
    document.getElementById('modalTitle').textContent = 'Sửa sản phẩm';
    document.getElementById('action').value = 'edit';
    document.getElementById('productId').value = product.id;
    document.getElementById('code').value = product.code;
    document.getElementById('name').value = product.name;
    document.getElementById('category_id').value = product.category_id || '';
    document.getElementById('supplier_id').value = product.supplier_id || '';
    document.getElementById('unit').value = product.unit;
    document.getElementById('price').value = product.price;
    document.getElementById('min_stock').value = product.min_stock;
    document.getElementById('zone_id').value = product.zone_id || '';
    document.getElementById('description').value = product.description || '';
    document.getElementById('codeWrapper').style.display = 'block';
    new bootstrap.Modal(document.getElementById('productModal')).show();
}

function deleteProduct(id) {
    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
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

document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Thêm sản phẩm';
    document.getElementById('action').value = 'add';
    document.querySelector('#productModal form').reset();
    document.getElementById('codeWrapper').style.display = 'none';
});
</script>

<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>

<?php
$pageTitle = 'Xuất kho';
require_once '../config/database.php';
include '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// ================== Xử lý xuất kho ==================
if ($_POST) {
    $product_id   = $_POST['product_id'] ?? '';
    $quantity     = $_POST['quantity'] ?? 0;
    $unit_price   = $_POST['unit_price'] ?? 0;
    $reference_no = $_POST['reference_no'] ?? '';
    $notes        = $_POST['notes'] ?? '';
    $partner      = $_POST['partner'] ?? '';

    $errors = [];
    if (!$product_id)    $errors[] = "Vui lòng chọn sản phẩm";
    if ($quantity <= 0)  $errors[] = "Số lượng phải lớn hơn 0";
    if (!$reference_no)  $errors[] = "Vui lòng nhập số chứng từ";
    if (!$partner)       $errors[] = "Vui lòng nhập đích đến/mục đích xuất";

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Kiểm tra tồn kho
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) throw new Exception("Sản phẩm không tồn tại");
            if ($product['current_stock'] < $quantity) {
                throw new Exception("Không đủ tồn kho! Hiện tại chỉ còn " . number_format($product['current_stock']) . " " . $product['unit']);
            }

            if ($unit_price <= 0) $unit_price = $product['price'];

            // Thêm giao dịch
            $total_amount = $quantity * $unit_price;
            $stmt = $db->prepare("INSERT INTO stock_transactions 
                (product_id, transaction_type, quantity, unit_price, total_amount, reference_no, partner, notes, user_id) 
                VALUES (?, 'out', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $product_id, $quantity, $unit_price, $total_amount,
                $reference_no, $partner, $notes, $currentUser['id']
            ]);

            // Trừ tồn kho
            $stmt = $db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);

            $db->commit();

            $new_stock = $product['current_stock'] - $quantity;
            $warning = "";
            if ($new_stock <= $product['min_stock']) {
                $warning = " ⚠️ Cảnh báo: tồn kho sau xuất (" . number_format($new_stock) . ") đã dưới mức tối thiểu (" . number_format($product['min_stock']) . ")";
            }

            $success = "✅ Xuất kho thành công! Đã xuất " . number_format($quantity) . " " . $product['unit'] . "." . $warning;

            $_POST = [];
        } catch (Exception $e) {
            $db->rollback();
            $error = "Có lỗi xảy ra: " . $e->getMessage();
        }
    } else {
        $error = implode(", ", $errors);
    }
}

// ================== Lấy dữ liệu ==================
// Danh sách khu
$zones_stmt = $db->prepare("SELECT * FROM zones ORDER BY code ASC");
$zones_stmt->execute();
$zones = $zones_stmt->fetchAll(PDO::FETCH_ASSOC);

// Danh sách sản phẩm (có tồn kho > 0)
$stmt = $db->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name, z.code as zone_code
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    LEFT JOIN zones z ON p.zone_id = z.id
    WHERE p.current_stock > 0
    ORDER BY p.name ASC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-danger"><i class="fas fa-arrow-up me-2"></i>Xuất kho</h1>
    <div>
        <a href="stock_in.php" class="btn btn-outline-primary rounded-3"><i class="fas fa-arrow-down"></i> Nhập kho</a>
        <a href="transactions.php" class="btn btn-outline-info rounded-3"><i class="fas fa-list"></i> Lịch sử</a>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show text-center"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show text-center"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card shadow rounded-4">
            <div class="card-header bg-warning text-dark rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fas fa-arrow-up me-2"></i>Thông tin xuất kho</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <!-- chọn khu -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-warehouse"></i> Chọn khu</label>
                        <select class="form-select shadow-sm" id="zone_filter" onchange="filterProductsByZone()">
                            <option value="">-- Tất cả các khu --</option>
                            <?php foreach ($zones as $z): ?>
                                <option value="<?php echo htmlspecialchars($z['code']); ?>">
                                    <?php echo htmlspecialchars($z['code']); ?> - <?php echo htmlspecialchars($z['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- chọn sản phẩm -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sản phẩm *</label>
                        <select class="form-select shadow-sm" name="product_id" id="product_id" required onchange="updateProductInfo()">
                            <option value="">-- Chọn sản phẩm cần xuất --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"
                                    data-code="<?php echo htmlspecialchars($p['code']); ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-unit="<?php echo htmlspecialchars($p['unit']); ?>"
                                    data-price="<?php echo $p['price']; ?>"
                                    data-stock="<?php echo $p['current_stock']; ?>"
                                    data-min-stock="<?php echo $p['min_stock']; ?>"
                                    data-category="<?php echo htmlspecialchars($p['category_name'] ?? 'Chưa phân loại'); ?>"
                                    data-supplier="<?php echo htmlspecialchars($p['supplier_name'] ?? 'Chưa có NCC'); ?>"
                                    data-zone="<?php echo htmlspecialchars($p['zone_code'] ?? ''); ?>">
                                    [<?php echo htmlspecialchars($p['code']); ?>] 
                                    <?php echo htmlspecialchars($p['name']); ?>
                                    (Tồn: <?php echo number_format($p['current_stock']); ?> <?php echo $p['unit']; ?>, Khu: <?php echo htmlspecialchars($p['zone_code'] ?? ''); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- số lượng + giá -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Số lượng xuất *</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" onchange="calculateTotal(); checkStock();">
                            <div class="form-text" id="stock-warning"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Đơn giá xuất</label>
                            <input type="number" class="form-control" name="unit_price" id="unit_price" step="0.01" onchange="calculateTotal()">
                            <div class="form-text">Để trống sẽ dùng giá hiện tại</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Đối tác/Đích đến *</label>
                        <input type="text" class="form-control" name="partner" required placeholder="VD: Khách hàng A, Bộ phận sản xuất...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Số chứng từ *</label>
                        <input type="text" class="form-control" name="reference_no" required placeholder="VD: XK-2025-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-lg" id="submit-btn" disabled><i class="fas fa-arrow-up me-1"></i> Xuất kho</button>
                </form>
            </div>
        </div>
    </div>
    <!-- info sản phẩm -->
    <div class="col-md-4 mb-4">
        <div class="card shadow rounded-4">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold text-danger"><i class="fas fa-box-open me-2"></i>Thông tin sản phẩm</h5></div>
            <div class="card-body" id="product-info"><p class="text-muted">Chọn sản phẩm để xem chi tiết</p></div>
            <div id="calculation" style="display:none;" class="p-3">
                <hr><h6 class="fw-bold">Tính toán</h6>
                <div class="d-flex justify-content-between"><span>Số lượng:</span><span id="calc-quantity">0</span></div>
                <div class="d-flex justify-content-between"><span>Đơn giá:</span><span id="calc-price">0 VNĐ</span></div>
                <div class="d-flex justify-content-between fw-bold"><span>Tổng:</span><span id="calc-total">0 VNĐ</span></div>
                <div class="d-flex justify-content-between text-info"><span>Tồn kho sau xuất:</span><span id="remaining-stock">0</span></div>
            </div>
        </div>
        <div class="card mt-3 rounded-4" id="warning-card" style="display:none;">
            <div class="card-header bg-danger text-white"><h6><i class="fas fa-exclamation-triangle"></i> Cảnh báo</h6></div>
            <div class="card-body" id="warning-content"></div>
        </div>
    </div>
</div>

<script>
    let currentProduct = null;

    function updateProductInfo() {
        const select = document.getElementById('product_id');
        const selectedOption = select.options[select.selectedIndex];
        const infoDiv = document.getElementById('product-info');
        
        if (selectedOption.value) {
            currentProduct = {
                code: selectedOption.dataset.code,
                name: selectedOption.dataset.name,
                unit: selectedOption.dataset.unit,
                price: parseFloat(selectedOption.dataset.price),
                stock: parseFloat(selectedOption.dataset.stock),
                minStock: parseFloat(selectedOption.dataset.minStock),
                category: selectedOption.dataset.category,
                supplier: selectedOption.dataset.supplier,
                zone: selectedOption.dataset.zone // Thêm zone vào currentProduct
            };
            
            // Cập nhật giá mặc định
            document.getElementById('unit_price').value = currentProduct.price;
            
            // Hiển thị thông tin sản phẩm
            const stockStatus = currentProduct.stock <= currentProduct.minStock ? 
                '<span class="badge bg-warning">Sắp hết</span>' : 
                '<span class="badge bg-success">Đủ</span>';
                
            infoDiv.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">${currentProduct.name}</h6>
                        <p class="card-text">
                            <strong>Mã SP:</strong> ${currentProduct.code}<br>
                            <strong>Danh mục:</strong> ${currentProduct.category}<br>
                            <strong>Nhà cung cấp:</strong> ${currentProduct.supplier}<br>
                            <strong>Đơn vị:</strong> ${currentProduct.unit}<br>
                            <strong>Giá hiện tại:</strong> ${currentProduct.price.toLocaleString('vi-VN')} VNĐ<br>
                            <strong>Tồn kho hiện tại:</strong> <span class="badge bg-primary">${currentProduct.stock.toLocaleString('vi-VN')} ${currentProduct.unit}</span><br>
                            <strong>Tồn kho tối thiểu:</strong> ${currentProduct.minStock.toLocaleString('vi-VN')} ${currentProduct.unit}<br>
                            <strong>Trạng thái:</strong> ${stockStatus}
                        </p>
                    </div>
                </div>
            `;
            
            calculateTotal();
            checkStock();
        } else {
            currentProduct = null;
            infoDiv.innerHTML = '<p class="text-muted"><i class="fas fa-info-circle"></i> Chọn sản phẩm để xem thông tin chi tiết</p>';
            document.getElementById('calculation').style.display = 'none';
            document.getElementById('warning-card').style.display = 'none';
            document.getElementById('submit-btn').disabled = true;
        }
    }

    function calculateTotal() {
        if (!currentProduct) return;
        
        const quantity = parseFloat(document.getElementById('quantity').value) || 0;
        const unitPrice = parseFloat(document.getElementById('unit_price').value) || currentProduct.price;
        const total = quantity * unitPrice;
        const remainingStock = currentProduct.stock - quantity;
        
        if (quantity > 0) {
            document.getElementById('calc-quantity').textContent = quantity.toLocaleString() + ' ' + currentProduct.unit;
            document.getElementById('calc-price').textContent = unitPrice.toLocaleString() + ' VNĐ';
            document.getElementById('calc-total').textContent = total.toLocaleString() + ' VNĐ';
            document.getElementById('remaining-stock').textContent = remainingStock.toLocaleString() + ' ' + currentProduct.unit;
            document.getElementById('calculation').style.display = 'block';
        } else {
            document.getElementById('calculation').style.display = 'none';
        }
    }

    function checkStock() {
        if (!currentProduct) return;
        
        const quantity = parseFloat(document.getElementById('quantity').value) || 0;
        const warningDiv = document.getElementById('stock-warning');
        const warningCard = document.getElementById('warning-card');
        const warningContent = document.getElementById('warning-content');
        const submitBtn = document.getElementById('submit-btn');
        
        if (quantity > currentProduct.stock) {
            warningDiv.innerHTML = '<span class="text-danger">⚠️ Không đủ tồn kho!</span>';
            warningCard.style.display = 'block';
            warningContent.innerHTML = `
                <p class="mb-1">Số lượng yêu cầu: <strong>${quantity.toLocaleString()}</strong></p>
                <p class="mb-1">Tồn kho hiện tại: <strong>${currentProduct.stock.toLocaleString()}</strong></p>
                <p class="mb-0 text-danger">Thiếu: <strong>${(quantity - currentProduct.stock).toLocaleString()}</strong></p>
            `;
            submitBtn.disabled = true;
        } else if (quantity > 0) {
            const remainingStock = currentProduct.stock - quantity;
            
            if (remainingStock <= currentProduct.minStock) {
                warningDiv.innerHTML = '<span class="text-warning">⚠️ Tồn kho sẽ xuống dưới mức tối thiểu</span>';
                warningCard.style.display = 'block';
                warningCard.className = 'card mt-3';
                warningCard.querySelector('.card-header').className = 'card-header bg-warning text-dark';
                warningContent.innerHTML = `
                    <p class="mb-1">Tồn kho sau xuất: <strong>${remainingStock.toLocaleString()}</strong></p>
                    <p class="mb-0">Mức tối thiểu: <strong>${currentProduct.minStock.toLocaleString()}</strong></p>
                    <small class="text-muted">Cần nhập thêm hàng sớm!</small>
                `;
            } else {
                warningDiv.innerHTML = '';
                warningCard.style.display = 'none';
            }
            submitBtn.disabled = false;
        } else {
            warningDiv.innerHTML = '';
            warningCard.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    function filterProductsByZone() {
        const zone = document.getElementById('zone_filter').value;
        const productSelect = document.getElementById('product_id');
        for (let i = 0; i < productSelect.options.length; i++) {
            const opt = productSelect.options[i];
            if (!opt.value) continue; // skip placeholder
            const productZone = opt.getAttribute('data-zone');
            if (!zone || productZone === zone) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        }
        // Reset selection nếu sản phẩm đang chọn không thuộc khu đang chọn
        if (productSelect.selectedIndex > 0) {
            const selectedOpt = productSelect.options[productSelect.selectedIndex];
            if (zone && selectedOpt.getAttribute('data-zone') !== zone) {
                productSelect.selectedIndex = 0;
                updateProductInfo();
            }
        }
    }
</script>
<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>

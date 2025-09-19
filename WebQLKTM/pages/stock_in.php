<?php
    $pageTitle = 'Nhập kho';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // ================== XỬ LÝ SUBMIT ==================
    if ($_POST) {
        $product_id   = $_POST['product_id'] ?? '';
        $quantity     = $_POST['quantity'] ?? 0;
        $unit_price   = $_POST['unit_price'] ?? 0;
        $reference_no = $_POST['reference_no'] ?? '';
        $notes        = $_POST['notes'] ?? '';
        $supplier_id  = $_POST['supplier_id'] ?? null;

        $errors = [];
        if (!$product_id) $errors[] = "Vui lòng chọn sản phẩm";
        if ($quantity <= 0) $errors[] = "Số lượng phải lớn hơn 0";
        if ($unit_price < 0) $errors[] = "Đơn giá không được âm";
        if (!$reference_no) $errors[] = "Vui lòng nhập số chứng từ";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Lấy thông tin sản phẩm
                $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) throw new Exception("Sản phẩm không tồn tại");

                // Lấy tên NCC
                $supplier_name = null;
                if ($supplier_id) {
                    $s_stmt = $db->prepare("SELECT name FROM suppliers WHERE id = ?");
                    $s_stmt->execute([$supplier_id]);
                    if ($row = $s_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $supplier_name = "Nhà cung cấp: " . $row['name'];
                    }
                }

                // Ghi giao dịch nhập
                $total_amount = $quantity * $unit_price;
                $stmt = $db->prepare("INSERT INTO stock_transactions 
                    (product_id, transaction_type, quantity, unit_price, total_amount, reference_no, partner, notes, user_id) 
                    VALUES (?, 'in', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $product_id, $quantity, $unit_price, $total_amount,
                    $reference_no, $supplier_name, $notes, $currentUser['id']
                ]);

                // Cập nhật tồn kho
                $stmt = $db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);

                // Nếu giá thay đổi thì update
                if ($unit_price > 0 && $unit_price != $product['price']) {
                    $stmt = $db->prepare("UPDATE products SET price = ? WHERE id = ?");
                    $stmt->execute([$unit_price, $product_id]);
                }

                $db->commit();
                $success = "✅ Nhập kho thành công! Đã cập nhật " . number_format($quantity) . " " . $product['unit'];

                $_POST = [];
            } catch (Exception $e) {
                $db->rollback();
                $error = "Có lỗi xảy ra: " . $e->getMessage();
            }
        } else {
            $error = implode(", ", $errors);
        }
    }

    // ================== LẤY DỮ LIỆU ==================
    // Danh sách khu
    $zones_stmt = $db->prepare("SELECT * FROM zones ORDER BY code ASC");
    $zones_stmt->execute();
    $zones = $zones_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Danh sách sản phẩm
    $query = "SELECT p.*, c.name as category_name, s.name as supplier_name, z.code as zone_code
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            LEFT JOIN zones z ON p.zone_id = z.id
            ORDER BY p.name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Danh sách NCC
    $suppliers_stmt = $db->prepare("SELECT * FROM suppliers ORDER BY name ASC");
    $suppliers_stmt->execute();
    $suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-primary"><i class="fas fa-arrow-down me-2"></i>Nhập kho</h1>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show rounded shadow-sm text-center">
    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show rounded shadow-sm text-center">
    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- nhập -->
<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card shadow rounded-4">
            <div class="card-header bg-white border-bottom-0 rounded-top-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-info-circle me-2"></i>Thông tin nhập kho</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <!-- chọn khu -->
                    <div class="mb-3">
                        <label for="zone_filter" class="form-label fw-bold"><i class="fas fa-warehouse"></i> Chọn khu</label>
                        <select class="form-select shadow-sm" id="zone_filter" name="zone_filter" onchange="filterProductsByZone()">
                            <option value="">-- Tất cả các khu --</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo htmlspecialchars($zone['code']); ?>">
                                    <?php echo htmlspecialchars($zone['code']); ?> - <?php echo htmlspecialchars($zone['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-primary">Chọn khu để lọc sản phẩm</div>
                    </div>

                    <!-- chọn sản phẩm -->
                    <div class="mb-3">
                        <label for="product_id" class="form-label fw-bold">Chọn sản phẩm *</label>
                        <select class="form-select shadow-sm" name="product_id" id="product_id" required onchange="updateProductInfo()">
                            <option value="">-- Chọn sản phẩm cần nhập --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"
                                    data-code="<?php echo htmlspecialchars($p['code']); ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-unit="<?php echo htmlspecialchars($p['unit']); ?>"
                                    data-price="<?php echo htmlspecialchars($p['price']); ?>"
                                    data-stock="<?php echo htmlspecialchars($p['current_stock']); ?>"
                                    data-category="<?php echo htmlspecialchars($p['category_name'] ?? 'Chưa phân loại'); ?>"
                                    data-supplier="<?php echo htmlspecialchars($p['supplier_name'] ?? 'Chưa có NCC'); ?>"
                                    data-zone="<?php echo htmlspecialchars($p['zone_code'] ?? ''); ?>">
                                    [<?php echo htmlspecialchars($p['code']); ?>] 
                                    <?php echo htmlspecialchars($p['name']); ?>
                                    <?php if ($p['category_name']): ?> - <?php echo htmlspecialchars($p['category_name']); ?><?php endif; ?>
                                    (Khu: <?php echo htmlspecialchars($p['zone_code'] ?? ''); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- nhà cung cấp -->
                    <div class="mb-3">
                        <label for="supplier_id" class="form-label fw-bold">Nhà cung cấp</label>
                        <select class="form-select shadow-sm" name="supplier_id" id="supplier_id">
                            <option value="">Chọn nhà cung cấp</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- số lượng + giá -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Số lượng *</label>
                                <input type="number" class="form-control shadow-sm" name="quantity" id="quantity" min="1" onchange="calculateTotal()" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Đơn giá</label>
                                <input type="number" class="form-control shadow-sm" name="unit_price" id="unit_price" step="0.01" onchange="calculateTotal()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Số chứng từ</label>
                        <input type="text" class="form-control shadow-sm" name="reference_no" required placeholder="VD: PO-2024-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea class="form-control shadow-sm" name="notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg rounded-3 px-4"><i class="fas fa-save me-1"></i> Nhập kho</button>
                </form>
            </div>
        </div>
    </div>

    <!-- info sản phẩm -->
    <div class="col-md-4 mb-4">
        <div class="card shadow rounded-4">
            <div class="card-header bg-white border-bottom-0 rounded-top-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-box-open me-2"></i>Thông tin sản phẩm</h5>
            </div>
            <div class="card-body">
                <div id="product-info"><p class="text-muted">Chọn sản phẩm để xem thông tin</p></div>
                <div id="calculation" style="display: none;">
                    <hr><h6 class="fw-bold">Tính toán</h6>
                    <div class="d-flex justify-content-between"><span>Số lượng:</span><span id="calc-quantity">0</span></div>
                    <div class="d-flex justify-content-between"><span>Đơn giá:</span><span id="calc-price">0 VNĐ</span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>Tổng tiền:</span><span id="calc-total">0 VNĐ</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateProductInfo() {
        const sel = document.getElementById('product_id');
        const opt = sel.options[sel.selectedIndex];
        const info = document.getElementById('product-info');
        if (!opt.value) { info.innerHTML='<p class="text-muted">Chọn sản phẩm để xem</p>'; return; }
        const data = {
            code: opt.dataset.code, name: opt.dataset.name, unit: opt.dataset.unit,
            price: parseFloat(opt.dataset.price), stock: parseFloat(opt.dataset.stock),
            category: opt.dataset.category, supplier: opt.dataset.supplier
        };
        document.getElementById('unit_price').value = data.price;
        info.innerHTML = `
            <div class="card"><div class="card-body">
            <h6>${data.name}</h6>
            <p><strong>Mã SP:</strong> ${data.code}<br>
            <strong>Danh mục:</strong> ${data.category}<br>
            <strong>Nhà cung cấp:</strong> ${data.supplier}<br>
            <strong>Đơn vị:</strong> ${data.unit}<br>
            <strong>Giá:</strong> ${data.price.toLocaleString()} VNĐ<br>
            <strong>Tồn kho:</strong> <span class="badge bg-info">${data.stock.toLocaleString()} ${data.unit}</span></p>
            </div></div>`;
        calculateTotal();
    }
    function calculateTotal() {
        const q = parseFloat(document.getElementById('quantity').value) || 0;
        const p = parseFloat(document.getElementById('unit_price').value) || 0;
        const t = q * p;
        if (q > 0 && p > 0) {
            document.getElementById('calc-quantity').textContent=q.toLocaleString();
            document.getElementById('calc-price').textContent=p.toLocaleString()+' VNĐ';
            document.getElementById('calc-total').textContent=t.toLocaleString()+' VNĐ';
            document.getElementById('calculation').style.display='block';
        } else document.getElementById('calculation').style.display='none';
    }
    function filterProductsByZone() {
        const zone=document.getElementById('zone_filter').value;
        const sel=document.getElementById('product_id');
        for(let i=0;i<sel.options.length;i++){
            const o=sel.options[i]; if(!o.value) continue;
            const z=o.dataset.zone;
            o.style.display = (!zone || z===zone)?'':'none';
        }
        if(sel.selectedIndex>0){
            const opt=sel.options[sel.selectedIndex];
            if(zone && opt.dataset.zone!==zone){ sel.selectedIndex=0; updateProductInfo(); }
        }
    }
</script>

<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>

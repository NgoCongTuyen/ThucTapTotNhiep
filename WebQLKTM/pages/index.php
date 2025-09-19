<?php
    $pageTitle = 'Dashboard - Quản lý kho';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Thống kê tổng quan
    $stats = [];

    // Tổng số sản phẩm
    $query = "SELECT COUNT(*) as total FROM products";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Sản phẩm sắp hết hàng
    $query = "SELECT COUNT(*) as total FROM products WHERE current_stock <= min_stock";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Giao dịch hôm nay
    $query = "SELECT COUNT(*) as total FROM stock_transactions WHERE DATE(transaction_date) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['today_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Tổng giá trị kho
    $query = "SELECT SUM(current_stock * price) as total FROM products";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Lấy danh sách khu vực từ bảng zones
    $query = "SELECT id, code, name FROM zones ORDER BY code";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $zones_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy số sản phẩm theo zone_id
    $products_by_zone = [];
    foreach ($zones_data as $zone) {
        $query = "SELECT COUNT(*) as total FROM products WHERE zone_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$zone['id']]);
        $products_by_zone[$zone['id']] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Sản phẩm sắp hết hàng (thêm tên khu)
    $query = "SELECT p.*, c.name as category_name, z.name as zone_name, z.code as zone_code
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN zones z ON p.zone_id = z.id
            WHERE p.current_stock <= p.min_stock 
            ORDER BY p.current_stock ASC 
            LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Giao dịch gần đây
    $query = "SELECT st.*, p.name as product_name, u.full_name as user_name
            FROM stock_transactions st
            LEFT JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.user_id = u.id
            ORDER BY st.transaction_date DESC
            LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .card {
        border: 2px solid transparent;
        border-radius: 12px;
        transition: all 0.2s ease-in-out;
    }
    .border-left-primary { border-color: #4e73df; }
    .border-left-warning { border-color: #f6c23e; }
    .border-left-info { border-color: #36b9cc; }
    .border-left-success { border-color: #1cc88a; }
    .border-left-secondary { border-color: #858796; }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2" style="color: blue;"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
</div>

<!-- Thống kê tổng quan -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng sản phẩm</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_products']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Sắp hết hàng</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['low_stock']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Giao dịch hôm nay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['today_transactions']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Tổng giá trị kho</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_value']); ?> VNĐ</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Thống kê theo khu vực -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3">Tổng quan theo khu vực</h4>
    </div>
    <?php foreach ($zones_data as $zone): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="products.php?zone_id=<?= $zone['id']; ?>" class="card border-left-secondary shadow h-100 py-2 text-decoration-none">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Khu vực <?= htmlspecialchars($zone['code']); ?> (<?= htmlspecialchars($zone['name']); ?>)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($products_by_zone[$zone['id']] ?? 0); ?> sản phẩm
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <!-- Sản phẩm sắp hết hàng -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sản phẩm sắp hết hàng</h6>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                    <p class="text-muted">Không có sản phẩm nào sắp hết hàng.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Khu vực</th>
                                    <th>Tồn kho</th>
                                    <th>Tối thiểu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']); ?></td>
                                    <td><?= htmlspecialchars($product['zone_code'] . ' - ' . $product['zone_name']); ?></td>
                                    <td><span class="badge bg-danger"><?= $product['current_stock']; ?></span></td>
                                    <td><?= $product['min_stock']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Giao dịch gần đây -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Giao dịch gần đây</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_transactions)): ?>
                    <p class="text-muted">Chưa có giao dịch nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Loại</th>
                                    <th>Số lượng</th>
                                    <th>Ngày</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= htmlspecialchars($transaction['product_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?= $transaction['transaction_type'] == 'in' ? 'success' : 'warning'; ?>">
                                            <?= $transaction['transaction_type'] == 'in' ? 'Nhập' : 'Xuất'; ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($transaction['quantity']); ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>

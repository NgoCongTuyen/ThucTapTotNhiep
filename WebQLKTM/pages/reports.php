<?php
    $pageTitle = 'Báo cáo & Thống kê';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Lấy tham số thời gian
    $date_from = $_GET['date_from'] ?? date('Y-m-01'); // Đầu tháng
    $date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hôm nay
    $report_type = $_GET['report_type'] ?? 'overview';

    // Thống kê tổng quan
    $overview_stats = [];

    // 1. Thống kê sản phẩm
    $product_stats_query = "SELECT 
        COUNT(*) as total_products,
        COUNT(CASE WHEN current_stock > min_stock THEN 1 END) as normal_stock,
        COUNT(CASE WHEN current_stock <= min_stock AND current_stock > 0 THEN 1 END) as low_stock,
        COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock,
        SUM(current_stock * price) as total_inventory_value,
        AVG(current_stock * price) as avg_product_value
    FROM products";
    $stmt = $db->prepare($product_stats_query);
    $stmt->execute();
    $overview_stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Thống kê giao dịch trong khoảng thời gian
    $transaction_stats_query = "SELECT 
        transaction_type,
        COUNT(*) as transaction_count,
        SUM(quantity) as total_quantity,
        SUM(total_amount) as total_amount,
        AVG(total_amount) as avg_amount,
        MIN(transaction_date) as first_transaction,
        MAX(transaction_date) as last_transaction
    FROM stock_transactions 
    WHERE DATE(transaction_date) BETWEEN ? AND ?
    GROUP BY transaction_type";
    $stmt = $db->prepare($transaction_stats_query);
    $stmt->execute([$date_from, $date_to]);
    $transaction_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $overview_stats['transactions'] = [
        'in' => ['transaction_count' => 0, 'total_quantity' => 0, 'total_amount' => 0, 'avg_amount' => 0],
        'out' => ['transaction_count' => 0, 'total_quantity' => 0, 'total_amount' => 0, 'avg_amount' => 0]
    ];

    foreach ($transaction_stats as $stat) {
        if (isset($stat['transaction_type']) && in_array($stat['transaction_type'], ['in', 'out'])) {
            $overview_stats['transactions'][$stat['transaction_type']] = $stat;
        }
    }

    // 3. Báo cáo theo loại
    switch ($report_type) {
        case 'inventory':
            // Báo cáo tồn kho chi tiết
            $inventory_query = "SELECT p.*, c.name as category_name, s.name as supplier_name,
                                    (p.current_stock * p.price) as inventory_value,
                                    CASE 
                                        WHEN p.current_stock = 0 THEN 'Hết hàng'
                                        WHEN p.current_stock <= p.min_stock THEN 'Sắp hết'
                                        WHEN p.current_stock <= p.min_stock * 2 THEN 'Ít'
                                        ELSE 'Đủ'
                                    END as stock_status
                                FROM products p
                                LEFT JOIN categories c ON p.category_id = c.id
                                LEFT JOIN suppliers s ON p.supplier_id = s.id
                                ORDER BY inventory_value DESC";
            $stmt = $db->prepare($inventory_query);
            $stmt->execute();
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'transactions':
            // Báo cáo giao dịch chi tiết với phân trang
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            // Đếm tổng bản ghi trong khoảng thời gian
            $count_sql = "SELECT COUNT(*) AS total
                          FROM stock_transactions st
                          LEFT JOIN products p ON st.product_id = p.id
                          LEFT JOIN users u ON st.user_id = u.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE DATE(st.transaction_date) BETWEEN ? AND ?";
            $count_stmt = $db->prepare($count_sql);
            $count_stmt->execute([$date_from, $date_to]);
            $total_records = (int)($count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            $total_pages = (int)ceil($total_records / $limit);

            // Lấy dữ liệu trang hiện tại
            $transactions_query = "SELECT st.*, p.name as product_name, p.code as product_code, 
                                        p.unit, u.full_name as user_name, c.name as category_name
                                FROM stock_transactions st
                                LEFT JOIN products p ON st.product_id = p.id
                                LEFT JOIN users u ON st.user_id = u.id
                                LEFT JOIN categories c ON p.category_id = c.id
                                WHERE DATE(st.transaction_date) BETWEEN ? AND ?
                                ORDER BY st.transaction_date DESC
                                LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($transactions_query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'categories':
            // Báo cáo theo danh mục
            $categories_query = "SELECT c.name as category_name,
                                        COUNT(p.id) as product_count,
                                        SUM(p.current_stock) as total_stock,
                                        SUM(p.current_stock * p.price) as total_value,
                                        AVG(p.price) as avg_price,
                                        COUNT(CASE WHEN p.current_stock <= p.min_stock THEN 1 END) as low_stock_count
                                FROM categories c
                                LEFT JOIN products p ON c.id = p.category_id
                                GROUP BY c.id, c.name
                                ORDER BY total_value DESC";
            $stmt = $db->prepare($categories_query);
            $stmt->execute();
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'suppliers':
            // Báo cáo theo nhà cung cấp
            $suppliers_query = "SELECT s.name as supplier_name, s.contact_person, s.phone,
                                    COUNT(p.id) as product_count,
                                    SUM(p.current_stock * p.price) as total_value,
                                    COUNT(CASE WHEN p.current_stock <= p.min_stock THEN 1 END) as low_stock_count
                                FROM suppliers s
                                LEFT JOIN products p ON s.id = p.supplier_id
                                GROUP BY s.id, s.name
                                ORDER BY total_value DESC";
            $stmt = $db->prepare($suppliers_query);
            $stmt->execute();
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'movement':
            // Báo cáo xuất nhập theo sản phẩm
            $movement_query = "SELECT p.code, p.name as product_name, p.unit,
                                    SUM(CASE WHEN st.transaction_type = 'in' THEN st.quantity ELSE 0 END) as total_in,
                                    SUM(CASE WHEN st.transaction_type = 'out' THEN st.quantity ELSE 0 END) as total_out,
                                    SUM(CASE WHEN st.transaction_type = 'in' THEN st.total_amount ELSE 0 END) as value_in,
                                    SUM(CASE WHEN st.transaction_type = 'out' THEN st.total_amount ELSE 0 END) as value_out,
                                    p.current_stock,
                                    COUNT(st.id) as transaction_count
                            FROM products p
                            LEFT JOIN stock_transactions st ON p.id = st.product_id 
                                AND DATE(st.transaction_date) BETWEEN ? AND ?
                            GROUP BY p.id
                            HAVING transaction_count > 0
                            ORDER BY transaction_count DESC";
            $stmt = $db->prepare($movement_query);
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            $report_data = [];
    }

    // Dữ liệu cho biểu đồ
    $chart_data_query = "SELECT DATE(transaction_date) as date,
                                transaction_type,
                                SUM(total_amount) as amount,
                                SUM(quantity) as quantity
                        FROM stock_transactions 
                        WHERE DATE(transaction_date) BETWEEN ? AND ?
                        GROUP BY DATE(transaction_date), transaction_type
                        ORDER BY date";
    $stmt = $db->prepare($chart_data_query);
    $stmt->execute([$date_from, $date_to]);
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn bị dữ liệu biểu đồ
    $dates = [];
    $in_amounts = [];
    $out_amounts = [];
    $in_quantities = [];
    $out_quantities = [];

    $current_date = new DateTime($date_from);
    $end_date = new DateTime($date_to);

    // Nếu chỉ có 1 ngày, thêm 1 ngày trước và 1 ngày sau để biểu đồ rõ hơn
    if ($date_from == $date_to) {
        $current_date->modify('-1 day');
        $end_date->modify('+1 day');
    }

    while ($current_date <= $end_date) {
        $date_str = $current_date->format('Y-m-d');
        $dates[] = $current_date->format('d/m');
        
        $in_amount = 0;
        $out_amount = 0;
        $in_quantity = 0;
        $out_quantity = 0;
        
        foreach ($chart_data as $data) {
            if ($data['date'] == $date_str) {
                if ($data['transaction_type'] == 'in') {
                    $in_amount = (float)($data['amount'] ?? 0);
                    $in_quantity = (int)($data['quantity'] ?? 0);
                } elseif ($data['transaction_type'] == 'out') {
                    $out_amount = (float)($data['amount'] ?? 0);
                    $out_quantity = (int)($data['quantity'] ?? 0);
                }
            }
        }
        
        $in_amounts[] = $in_amount;
        $out_amounts[] = $out_amount;
        $in_quantities[] = $in_quantity;
        $out_quantities[] = $out_quantity;
        
        $current_date->add(new DateInterval('P1D'));
    }
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-primary"><i class="fas fa-chart-line me-2"></i>Báo cáo & Thống kê</h1>
</div>

<!-- Bộ lọc và điều khiển -->
<div class="card mb-4">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Bộ lọc báo cáo</h5>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate('today')">Hôm nay</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate('week')">Tuần này</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate('month')">Tháng này</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate('quarter')">Quý này</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="report_type" class="form-label">Loại báo cáo</label>
                <select class="form-select" name="report_type" id="report_type">
                    <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Tổng quan</option>
                    <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Báo cáo tồn kho</option>
                    <option value="transactions" <?php echo $report_type == 'transactions' ? 'selected' : ''; ?>>Lịch sử giao dịch</option>
                    <option value="categories" <?php echo $report_type == 'categories' ? 'selected' : ''; ?>>Theo danh mục</option>
                    <option value="suppliers" <?php echo $report_type == 'suppliers' ? 'selected' : ''; ?>>Theo nhà cung cấp</option>
                    <option value="movement" <?php echo $report_type == 'movement' ? 'selected' : ''; ?>>Xuất nhập theo SP</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Xem báo cáo
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Thống kê tổng quan -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng sản phẩm</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overview_stats['products']['total_products']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overview_stats['products']['total_inventory_value'], 0); ?> VNĐ
                        </div>
                        <div class="text-xs text-muted">
                            Tồn kho bình thường: <?php echo $overview_stats['products']['normal_stock']; ?> SP
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Cảnh báo tồn kho</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overview_stats['products']['low_stock']); ?>
                        </div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Giao dịch (<?php echo date('d/m', strtotime($date_from)); ?> - <?php echo date('d/m', strtotime($date_to)); ?>)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overview_stats['transactions']['in']['transaction_count'] + $overview_stats['transactions']['out']['transaction_count']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            <span class="text-primary">Nhập: <?php echo $overview_stats['transactions']['in']['transaction_count']; ?></span> | 
                            <span class="text-danger">Xuất: <?php echo $overview_stats['transactions']['out']['transaction_count']; ?></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Biểu đồ -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Biểu đồ xuất nhập kho theo ngày</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow">
                        <a class="dropdown-item" href="#" onclick="toggleChart('amount')">Theo giá trị</a>
                        <a class="dropdown-item" href="#" onclick="toggleChart('quantity')">Theo số lượng</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area" style="position: relative; height: 40vh; width: 100%;">
                    <canvas id="transactionChart" style="height: 100%; width: 100%;"></canvas>
                    <div id="noTransactionData" style="display: none; text-align: center; color: #666; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">Không có dữ liệu để hiển thị</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Phân tích giao dịch</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2" style="position: relative; height: 40vh; width: 100%;">
                    <canvas id="pieChart" style="height: 100%; width: 100%;"></canvas>
                    <div id="noPieData" style="display: none; text-align: center; color: #666; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">Không có dữ liệu để hiển thị</div>
                </div>
                <div class="mt-4 text-center small">
                    <span class="mr-2">
                        <i class="fas fa-circle text-primary"></i> Nhập kho
                    </span>
                    <span class="mr-2">
                        <i class="fas fa-circle text-danger"></i> Xuất kho
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nội dung báo cáo chi tiết -->
<div class="card shadow mb-4" id="report-content">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <?php
            $report_titles = [
                'overview' => 'Báo cáo tổng quan',
                'inventory' => 'Báo cáo tồn kho chi tiết',
                'transactions' => 'Lịch sử giao dịch chi tiết',
                'categories' => 'Báo cáo theo danh mục',
                'suppliers' => 'Báo cáo theo nhà cung cấp',
                'movement' => 'Báo cáo xuất nhập theo sản phẩm'
            ];
            echo $report_titles[$report_type] ?? 'Báo cáo';
            ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($report_type == 'overview'): ?>
            <!-- Báo cáo tổng quan -->
            <div class="row">
                <div class="col-md-6">
                    <h6>Thống kê sản phẩm</h6>
                    <table class="table table-sm">
                        <tr><td>Tổng số sản phẩm:</td><td><strong><?php echo number_format($overview_stats['products']['total_products']); ?></strong></td></tr>
                        <tr><td>Tồn kho bình thường:</td><td><span class="text-success"><?php echo number_format($overview_stats['products']['normal_stock']); ?></span></td></tr>
                        <tr><td>Sắp hết hàng:</td><td><span class="text-warning"><?php echo number_format($overview_stats['products']['low_stock']); ?></span></td></tr>
                        <tr><td>Hết hàng:</td><td><span class="text-danger"><?php echo number_format($overview_stats['products']['out_of_stock']); ?></span></td></tr>
                        <tr><td>Tổng giá trị kho:</td><td><strong><?php echo number_format($overview_stats['products']['total_inventory_value'], 0); ?> VNĐ</strong></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Thống kê giao dịch (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Tổng lần nhập kho:</td>
                            <td><span class="text-primary"><?php echo number_format($overview_stats['transactions']['in']['transaction_count']); ?></span></td>
                        </tr>
                        <tr>
                            <td>Tổng lần xuất kho:</td>
                            <td><span class="text-danger"><?php echo number_format($overview_stats['transactions']['out']['transaction_count']); ?></span></td>
                        </tr>
                        <tr>
                            <td>Giá trị nhập:</td>
                            <td><span class="text-primary"><?php echo number_format($overview_stats['transactions']['in']['total_amount'], 0); ?> VNĐ</span></td>
                        </tr>
                        <tr>
                            <td>Giá trị xuất:</td>
                            <td><span class="text-danger"><?php echo number_format($overview_stats['transactions']['out']['total_amount'], 0); ?> VNĐ</span></td>
                        </tr>
                        <tr>
                    <td>Lợi nhuận: </td>
                        <?php 
                            $in_amount = $overview_stats['transactions']['in']['total_amount'] ?? 0;
                            $out_amount = $overview_stats['transactions']['out']['total_amount'] ?? 0;
                            $profit_loss = $out_amount - $in_amount;
                            $label = $profit_loss >= 0 ? 'Lãi' : 'Lỗ';
                            $color_class = $profit_loss >= 0 ? 'text-success' : 'text-danger';
                            $abs_value = abs($profit_loss);
                        ?>
                        <td><strong class="<?php echo $color_class; ?>"><?php echo $label; ?>: <?php echo number_format($abs_value, 0); ?> VNĐ</strong></td>
                    </tr>
                    </table>
                </div>
            </div>
            
        <?php elseif ($report_type == 'inventory'): ?>
            <!-- Báo cáo tồn kho -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Nhà cung cấp</th>
                            <th>Tồn kho</th>
                            <th>Tối thiểu</th>
                            <th>Đơn giá</th>
                            <th>Giá trị</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $item): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($item['code']); ?></code></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?></td>
                            <td><?php echo number_format($item['min_stock']); ?></td>
                            <td><?php echo number_format($item['price'], 0); ?> VNĐ</td>
                            <td><strong><?php echo number_format($item['inventory_value'], 0); ?> VNĐ</strong></td>
                            <td>
                                <?php
                                $status_colors = [
                                    'Hết hàng' => 'danger',
                                    'Sắp hết' => 'warning', 
                                    'Ít' => 'info',
                                    'Đủ' => 'success'
                                ];
                                $color = $status_colors[$item['stock_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo $item['stock_status']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type == 'transactions'): ?>
            <!-- Lịch sử giao dịch -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Thời gian</th>
                            <th>Loại</th>
                            <th>Sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Số lượng</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Chứng từ</th>
                            <th>Người thực hiện</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $transaction): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $transaction['transaction_type'] == 'in' ? 'primary' : 'danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo $transaction['transaction_type'] == 'in' ? 'down' : 'up'; ?>"></i>
                                    <?php echo $transaction['transaction_type'] == 'in' ? 'Nhập' : 'Xuất'; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($transaction['product_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($transaction['product_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($transaction['quantity']); ?> <?php echo $transaction['unit']; ?></td>
                            <td><?php echo number_format($transaction['unit_price'], 0); ?> VNĐ</td>
                            <td><strong><?php echo number_format($transaction['total_amount'], 0); ?> VNĐ</strong></td>
                            <td><code><?php echo htmlspecialchars($transaction['reference_no']); ?></code></td>
                            <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($total_pages) && $total_pages > 1): ?>
            <?php
                // Tạo query giữ nguyên bộ lọc (trừ page)
                $query_params = $_GET;
                unset($query_params['page']);
                $base_query_string = http_build_query($query_params);
                $current_page = (int)$page;
                $prev_page = max(1, $current_page - 1);
                $next_page = min($total_pages, $current_page + 1);
                $makeHref = function($p) use ($base_query_string) {
                    return 'reports.php?' . $base_query_string . ($base_query_string ? '&' : '') . 'page=' . $p;
                };
                $from_record = $total_records > 0 ? ($offset + 1) : 0;
                $to_record = min($offset + $limit, (int)$total_records);
                $window = 2;
                $start = max(1, $current_page - $window);
                $end = min($total_pages, $current_page + $window);
            ?>
            <div class="mt-3">
                <div class="text-muted small text-center mb-2">
                    Hiển thị <?php echo number_format($from_record); ?>–<?php echo number_format($to_record); ?> trong tổng số <?php echo number_format($total_records); ?> bản ghi
                </div>
                <nav aria-label="Pagination báo cáo giao dịch" class="d-flex justify-content-center">
                    <ul class="pagination pagination-sm">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $makeHref($prev_page); ?>">Trước</a>
                        </li>
                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $makeHref(1); ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <li class="page-item <?php echo $p == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $makeHref($p); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $makeHref($total_pages); ?>"><?php echo $total_pages; ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $makeHref($next_page); ?>">Sau</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
        <?php elseif ($report_type == 'categories'): ?>
            <!-- Báo cáo theo danh mục -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Danh mục</th>
                            <th>Số sản phẩm</th>
                            <th>Tổng tồn kho</th>
                            <th>Giá trị</th>
                            <th>Giá TB</th>
                            <th>Sắp hết</th>
                            <th>Tỷ lệ cảnh báo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $category): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($category['category_name'] ?? 'Chưa phân loại'); ?></strong></td>
                            <td><?php echo number_format($category['product_count']); ?></td>
                            <td><?php echo number_format($category['total_stock']); ?></td>
                            <td><strong><?php echo number_format($category['total_value'], 0); ?> VNĐ</strong></td>
                            <td><?php echo number_format($category['avg_price'], 0); ?> VNĐ</td>
                            <td>
                                <?php if ($category['low_stock_count'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $category['low_stock_count']; ?></span>
                                <?php else: ?>
                                    <span class="text-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $warning_rate = $category['product_count'] > 0 ? ($category['low_stock_count'] / $category['product_count']) * 100 : 0;
                                $color = $warning_rate > 50 ? 'danger' : ($warning_rate > 20 ? 'warning' : 'success');
                                ?>
                                <span class="text-<?php echo $color; ?>"><?php echo number_format($warning_rate, 1); ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type == 'suppliers'): ?>
            <!-- Báo cáo theo nhà cung cấp -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nhà cung cấp</th>
                            <th>Người liên hệ</th>
                            <th>Điện thoại</th>
                            <th>Số sản phẩm</th>
                            <th>Tổng giá trị</th>
                            <th>Sắp hết hàng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $supplier): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($supplier['supplier_name'] ?? 'Chưa có NCC'); ?></strong></td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($supplier['product_count']); ?></td>
                            <td><strong><?php echo number_format($supplier['total_value'], 0); ?> VNĐ</strong></td>
                            <td>
                                <?php if ($supplier['low_stock_count'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $supplier['low_stock_count']; ?></span>
                                <?php else: ?>
                                    <span class="text-success">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type == 'movement'): ?>
            <!-- Báo cáo xuất nhập theo sản phẩm -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th>Nhập</th>
                            <th>Xuất</th>
                            <th>Chênh lệch</th>
                            <th>Giá trị nhập</th>
                            <th>Giá trị xuất</th>
                            <th>Tồn hiện tại</th>
                            <th>Số GD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $movement): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($movement['code']); ?></code></td>
                            <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                            <td><span class="text-primary"><?php echo number_format($movement['total_in']); ?> <?php echo $movement['unit']; ?></span></td>
                            <td><span class="text-danger"><?php echo number_format($movement['total_out']); ?> <?php echo $movement['unit']; ?></span></td>
                            <td>
                                <?php 
                                $diff = $movement['total_in'] - $movement['total_out'];
                                $color = $diff >= 0 ? 'primary' : 'danger';
                                ?>
                                <span class="text-<?php echo $color; ?>"><?php echo ($diff >= 0 ? '+' : '') . number_format($diff); ?></span>
                            </td>
                            <td><span class="text-primary"><?php echo number_format($movement['value_in'], 0); ?> VNĐ</span></td>
                            <td><span class="text-danger"><?php echo number_format($movement['value_out'], 0); ?> VNĐ</span></td>
                            <td><strong><?php echo number_format($movement['current_stock']); ?> <?php echo $movement['unit']; ?></strong></td>
                            <td><span class="badge bg-info"><?php echo $movement['transaction_count']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if (empty($report_data) && $report_type != 'overview'): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Không có dữ liệu</h5>
                <p class="text-muted">Không tìm thấy dữ liệu cho báo cáo này trong khoảng thời gian đã chọn.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dữ liệu cho biểu đồ
    const chartData = {
        dates: <?php echo json_encode($dates); ?>,
        inAmounts: <?php echo json_encode($in_amounts); ?>,
        outAmounts: <?php echo json_encode($out_amounts); ?>,
        inQuantities: <?php echo json_encode($in_quantities); ?>,
        outQuantities: <?php echo json_encode($out_quantities); ?>
    };

    console.log('Chart Data:', chartData);

    let currentChartType = 'amount';
    let lineChart;
    let pieChart;

    document.addEventListener('DOMContentLoaded', function() {
        const transactionChartCanvas = document.getElementById('transactionChart');
        const noTransactionDataDiv = document.getElementById('noTransactionData');
        const pieChartCanvas = document.getElementById('pieChart');
        const noPieDataDiv = document.getElementById('noPieData');

        // Check if there's any data for the line chart
        const hasLineChartData = chartData.dates.length > 0 && 
                                (chartData.inAmounts.some(val => val > 0) || chartData.outAmounts.some(val => val > 0));

        if (hasLineChartData) {
            transactionChartCanvas.style.display = 'block';
            noTransactionDataDiv.style.display = 'none';
            try {
                const ctx = transactionChartCanvas.getContext('2d');
                if (ctx) {
                    lineChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.dates,
                            datasets: [{
                                label: 'Nhập kho',
                                data: chartData.inAmounts,
                                borderColor: 'rgba(54, 162, 235, 1)', // Màu primary
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Xuất kho',
                                data: chartData.outAmounts,
                                borderColor: 'rgba(220, 53, 69, 1)', // Màu danger
                                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString('vi-VN') + ' VNĐ';
                                        }
                                    }
                                },
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 45
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' VNĐ';
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    console.error("Could not get 2D context for transactionChart canvas.");
                    transactionChartCanvas.style.display = 'none';
                    noTransactionDataDiv.style.display = 'block';
                }
            } catch (e) {
                console.error("Error initializing transactionChart:", e);
                transactionChartCanvas.style.display = 'none';
                noTransactionDataDiv.style.display = 'block';
            }
        } else {
            transactionChartCanvas.style.display = 'none';
            noTransactionDataDiv.style.display = 'block';
        }

        // Check if there's any data for the pie chart
        const totalIn = <?php echo $overview_stats['transactions']['in']['total_amount'] ?? 0; ?>;
        const totalOut = <?php echo $overview_stats['transactions']['out']['total_amount'] ?? 0; ?>;
        const hasPieChartData = (totalIn > 0 || totalOut > 0);

        if (hasPieChartData) {
            pieChartCanvas.style.display = 'block';
            noPieDataDiv.style.display = 'none';
            try {
                const pieCtx = pieChartCanvas.getContext('2d');
                if (pieCtx) {
                    pieChart = new Chart(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Nhập kho', 'Xuất kho'],
                            datasets: [{
                                data: [totalIn, totalOut],
                                backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(220, 53, 69, 0.8)'], // Màu primary và danger
                                borderColor: ['rgba(54, 162, 235, 1)', 'rgba(220, 53, 69, 1)'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed.toLocaleString('vi-VN') + ' VNĐ';
                                        }
                                    }
                                }
                            },
                            cutout: '70%'
                        }
                    });
                } else {
                    console.error("Could not get 2D context for pieChart canvas.");
                    pieChartCanvas.style.display = 'none';
                    noPieDataDiv.style.display = 'block';
                }
            } catch (e) {
                console.error("Error initializing pieChart:", e);
                pieChartCanvas.style.display = 'none';
                noPieDataDiv.style.display = 'block';
            }
        } else {
            pieChartCanvas.style.display = 'none';
            noPieDataDiv.style.display = 'block';
        }
    });

    // Functions
    function toggleChart(type) {
        if (!lineChart) {
            console.warn("Line chart not initialized.");
            return;
        }
        currentChartType = type;
        if (type === 'amount') {
            lineChart.data.datasets[0].data = chartData.inAmounts;
            lineChart.data.datasets[1].data = chartData.outAmounts;
            lineChart.data.datasets[0].label = 'Nhập kho (VNĐ)';
            lineChart.data.datasets[1].label = 'Xuất kho (VNĐ)';
            lineChart.options.scales.y.ticks.callback = function(value) {
                return value.toLocaleString('vi-VN') + ' VNĐ';
            };
            lineChart.options.plugins.tooltip.callbacks.label = function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' VNĐ';
            };
        } else {
            lineChart.data.datasets[0].data = chartData.inQuantities;
            lineChart.data.datasets[1].data = chartData.outQuantities;
            lineChart.data.datasets[0].label = 'Nhập kho (SP)';
            lineChart.data.datasets[1].label = 'Xuất kho (SP)';
            lineChart.options.scales.y.ticks.callback = function(value) {
                return value.toLocaleString('vi-VN');
            };
            lineChart.options.plugins.tooltip.callbacks.label = function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' sản phẩm';
            };
        }
        lineChart.update();
    }

    function setQuickDate(type) {
        const today = new Date();
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        switch(type) {
            case 'today':
                const todayStr = today.toISOString().split('T')[0];
                dateFrom.value = todayStr;
                dateTo.value = todayStr;
                break;
            case 'week':
                const weekStart = new Date(today); // Create a new Date object to avoid modifying 'today'
                weekStart.setDate(today.getDate() - today.getDay());
                dateFrom.value = weekStart.toISOString().split('T')[0];
                dateTo.value = new Date().toISOString().split('T')[0];
                break;
            case 'month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                dateFrom.value = monthStart.toISOString().split('T')[0];
                dateTo.value = new Date().toISOString().split('T')[0];
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                const quarterStart = new Date(today.getFullYear(), quarter * 3, 1);
                dateFrom.value = quarterStart.toISOString().split('T')[0];
                dateTo.value = new Date().toISOString().split('T')[0];
                break;
        }
        document.forms[0].submit();
    }

    function resetFilters() {
        document.getElementById('report_type').value = 'overview';
        document.getElementById('date_from').value = '<?php echo date('Y-m-01'); ?>';
        document.getElementById('date_to').value = '<?php echo date('Y-m-d'); ?>';
        document.forms[0].submit();
    }

    function exportReport() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.open('export_reports.php?' + params.toString(), '_blank');
    }

    function exportPDF() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'pdf');
        window.open('export_reports.php?' + params.toString(), '_blank');
    }


</script>
<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>
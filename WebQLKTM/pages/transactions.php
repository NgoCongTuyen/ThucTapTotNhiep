<?php
    $pageTitle = 'Lịch sử giao dịch';
    require_once '../config/database.php';
    include '../includes/header.php';

    $database = new Database();
    $db = $database->getConnection();

    // Lọc dữ liệu
    $type_filter = $_GET['type'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $product_filter = $_GET['product'] ?? '';
    $user_filter = $_GET['user'] ?? '';

    // Xây dựng where chung cho danh sách giao dịch
    $where_conditions = [];
    $params = [];

    if ($type_filter) {
        $where_conditions[] = "st.transaction_type = ?";
        $params[] = $type_filter;
    }
    if ($date_from) {
        $where_conditions[] = "DATE(st.transaction_date) >= ?";
        $params[] = $date_from;
    }
    if ($date_to) {
        $where_conditions[] = "DATE(st.transaction_date) <= ?";
        $params[] = $date_to;
    }
    if ($product_filter) {
        $where_conditions[] = "(p.name LIKE ? OR p.code LIKE ?)";
        $params[] = "%$product_filter%";
        $params[] = "%$product_filter%";
    }
    if ($user_filter) {
        $where_conditions[] = "st.user_id = ?";
        $params[] = $user_filter;
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Phân trang
    $page = $_GET['page'] ?? 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Đếm tổng số bản ghi
    $count_query = "SELECT COUNT(*) as total 
                    FROM stock_transactions st
                    LEFT JOIN products p ON st.product_id = p.id
                    LEFT JOIN users u ON st.user_id = u.id
                    $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Lấy dữ liệu giao dịch
    $query = "SELECT st.*, 
                    p.name AS product_name, 
                    p.code AS product_code, 
                    p.unit, 
                    u.full_name AS user_name,
                    st.partner
            FROM stock_transactions st
            LEFT JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.user_id = u.id
            $where_clause
            ORDER BY st.transaction_date DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách users cho filter
    $users_query = "SELECT id, full_name FROM users ORDER BY full_name";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================== THỐNG KÊ ===================
    // Chuẩn bị điều kiện riêng cho thống kê doanh thu/nhập kho
    $stats_conditions = [];
    $stats_params = [];

    if ($date_from) {
        $stats_conditions[] = "DATE(st.transaction_date) >= ?";
        $stats_params[] = $date_from;
    }
    if ($date_to) {
        $stats_conditions[] = "DATE(st.transaction_date) <= ?";
        $stats_params[] = $date_to;
    }
    if ($user_filter) {
        $stats_conditions[] = "st.user_id = ?";
        $stats_params[] = $user_filter;
    }
    if ($product_filter) {
        $stats_conditions[] = "(p.name LIKE ? OR p.code LIKE ?)";
        $stats_params[] = "%$product_filter%";
        $stats_params[] = "%$product_filter%";
    }

    $stats_where = !empty($stats_conditions) ? "WHERE " . implode(" AND ", $stats_conditions) : "";

    // Thống kê tổng quan
    $stats_query = "SELECT 
                        st.transaction_type,
                        COUNT(*) as count,
                        SUM(st.total_amount) as total_amount,
                        SUM(st.quantity) as total_quantity
                    FROM stock_transactions st
                    JOIN products p ON st.product_id = p.id
                    $stats_where
                    GROUP BY st.transaction_type";

    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute($stats_params);
    $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_summary = [
        'in' => ['count' => 0, 'amount' => 0, 'quantity' => 0],
        'out' => ['count' => 0, 'amount' => 0, 'quantity' => 0]
    ];
    foreach ($stats as $stat) {
        if (in_array($stat['transaction_type'], ['in', 'out'])) {
            $stats_summary[$stat['transaction_type']] = [
                'count' => (int)($stat['count'] ?? 0),
                'amount' => (float)($stat['total_amount'] ?? 0),
                'quantity' => (int)($stat['total_quantity'] ?? 0)
            ];
        }
    }

    // Tổng chi phí nhập kho
    $import_value_query = "SELECT SUM(st.quantity * p.price) AS total_import_value
                           FROM stock_transactions st
                           JOIN products p ON st.product_id = p.id
                           $stats_where " . 
                           (empty($stats_where) ? "WHERE" : " AND") . " st.transaction_type = 'in'";
    $import_value_stmt = $db->prepare($import_value_query);
    $import_value_stmt->execute($stats_params);
    $total_import_value = $import_value_stmt->fetch(PDO::FETCH_ASSOC)['total_import_value'] ?? 0;

    // Tổng doanh thu
    $revenue_query = "SELECT SUM(st.quantity * p.price) AS total_revenue
                      FROM stock_transactions st
                      JOIN products p ON st.product_id = p.id
                      $stats_where " . 
                      (empty($stats_where) ? "WHERE" : " AND") . " st.transaction_type = 'out'";
    $revenue_stmt = $db->prepare($revenue_query);
    $revenue_stmt->execute($stats_params);
    $total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
?>

<link rel="stylesheet" href="../css/main.css">

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-info"><i class="fas fa-list me-2"></i>Lịch sử giao dịch</h1>
    <div>
        <a href="stock_in.php" class="btn btn-warning btn-sm rounded-3">
            <i class="fas fa-arrow-down"></i> Nhập kho
        </a>
        <a href="stock_out.php" class="btn btn-success btn-sm rounded-3">
            <i class="fas fa-arrow-up"></i> Xuất kho
        </a>
        <button class="btn btn-info btn-sm rounded-3" onclick="exportTransactions()">
            <i class="fas fa-download"></i> Xuất Excel
        </button>
    </div>
</div>
<!-- tổng quan  -->

<div class="row mb-4">
    <!-- Nhập kho -->
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="h5">
                            <?php echo ($type_filter == 'out') ? 0 : number_format($stats_summary['in']['count']); ?>
                        </div>
                        <div>
                            Nhập kho (<?php echo ($type_filter == 'out') ? 0 : number_format($stats_summary['in']['amount']); ?> VNĐ)
                        </div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-down fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Xuất kho -->
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="h5">
                            <?php echo ($type_filter == 'in') ? 0 : number_format($stats_summary['out']['count']); ?>
                        </div>
                        <div>
                            Xuất kho (<?php echo ($type_filter == 'in') ? 0 : number_format($stats_summary['out']['amount']); ?> VNĐ)
                        </div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-up fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh thu / Chi phí -->
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if ($type_filter == 'in'): ?>
                            <div>Chi phí nhập kho</div>
                            <div class="h5"><?php echo number_format(abs($total_import_value)); ?> VNĐ</div>
                        <?php elseif ($type_filter == 'out'): ?>
                            <div>Doanh thu</div>
                            <div class="h5"><?php echo number_format(abs($total_revenue)); ?> VNĐ</div>
                        <?php else: ?>
                            <div>Doanh thu</div>
                            <div class="h5"><?php echo number_format(abs($total_revenue)); ?> VNĐ</div>
                        <?php endif; ?>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-balance-scale fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tổng giao dịch -->
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="h5"><?php echo $total_records; ?></div>
                        <div>Tổng giao dịch</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Bộ lọc -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-filter"></i> Bộ lọc</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="type" class="form-label">Loại giao dịch</label>
                <select class="form-select" name="type" id="type">
                    <option value="">Tất cả</option>
                    <option value="in" <?php echo $type_filter == 'in' ? 'selected' : ''; ?>>Nhập kho</option>
                    <option value="out" <?php echo $type_filter == 'out' ? 'selected' : ''; ?>>Xuất kho</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-2">
                <label for="product" class="form-label">Sản phẩm</label>
                <input type="text" class="form-control" name="product" id="product" placeholder="Tên/Mã SP" value="<?php echo htmlspecialchars($product_filter); ?>">
            </div>
            <div class="col-md-2">
                <label for="user" class="form-label">Người thực hiện</label>
                <select class="form-select" name="user" id="user">
                    <option value="">Tất cả</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Lọc
                    </button>
                    <a href="transactions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách giao dịch -->
<div class="card rounded-4 shadow">
    <div class="card-header bg-light rounded-top-4">
        <h5 class="mb-0"><i class="fas fa-list"></i> Danh sách giao dịch (<?php echo number_format($total_records); ?> bản ghi)</h5>
    </div>
    <div class="card-body">
        <div class="table-container table-responsive">
            <table class="table table-striped table-hover align-middle table-sticky-head">
                <thead class="table-dark">
                    <tr>
                        <th>Thời gian</th>
                        <th>Loại</th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                        <th>Chứng từ</th>
                        <th>Người thực hiện</th>
                        <th>Ghi chú</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Không có giao dịch nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?><br>
                                    <?php echo date('H:i:s', strtotime($transaction['transaction_date'])); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $transaction['transaction_type'] == 'in' ? 'warning' : 'success'; ?>">
                                    <i class="fas fa-arrow-<?php echo $transaction['transaction_type'] == 'in' ? 'down' : 'up'; ?>"></i>
                                    <?php echo $transaction['transaction_type'] == 'in' ? 'Nhập' : 'Xuất'; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($transaction['product_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($transaction['product_name']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo number_format($transaction['quantity']); ?></strong>
                                <?php echo htmlspecialchars($transaction['unit']); ?>
                            </td>
                            <td><?php echo number_format($transaction['unit_price'], 0, ',', '.'); ?> VNĐ</td>
                            <td>
                                <strong class="text-<?php echo $transaction['transaction_type'] == 'in' ? 'warning' : 'success'; ?>">
                                    <?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?> VNĐ
                                </strong>
                            </td>
                            <td><code><?php echo htmlspecialchars($transaction['reference_no']); ?></code></td>
                            <td><small><?php echo htmlspecialchars($transaction['user_name']); ?></small></td>
                            <td><small><?php echo htmlspecialchars($transaction['notes']); ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#detailModal"
                                        onclick='showTransactionDetail(<?php echo json_encode($transaction, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
            // Tạo query string giữ nguyên bộ lọc (trừ page)
            $query_params = $_GET;
            unset($query_params['page']);
            $base_query_string = http_build_query($query_params);

            // Tính toán phạm vi hiển thị hiện tại
            $from_record = $total_records > 0 ? ($offset + 1) : 0;
            $to_record = min($offset + $limit, (int)$total_records);
        ?>

        <div class="mt-3">
          
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination giao dịch" class="d-flex justify-content-center">
                <ul class="pagination pagination-sm">
                    <?php
                        $current_page = (int)$page;
                        $prev_page = max(1, $current_page - 1);
                        $next_page = min($total_pages, $current_page + 1);
                        $makeHref = function($p) use ($base_query_string) {
                            return 'transactions.php?' . $base_query_string . ($base_query_string ? '&' : '') . 'page=' . $p;
                        };

                        // Số lượng trang hiển thị xung quanh trang hiện tại
                        $window = 2;
                        $start = max(1, $current_page - $window);
                        $end = min($total_pages, $current_page + $window);
                    ?>

                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $makeHref($prev_page); ?>" tabindex="-1" aria-disabled="<?php echo $current_page <= 1 ? 'true' : 'false'; ?>">Trước</a>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Chi tiết giao dịch -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-info-circle"></i> Chi tiết giao dịch</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered">
          <tbody>
            <tr><th>Thời gian</th><td id="detail-time"></td></tr>
            <tr><th>Loại giao dịch</th><td id="detail-type"></td></tr>
            <tr><th>Mã SP</th><td id="detail-code"></td></tr>
            <tr><th>Tên SP</th><td id="detail-name"></td></tr>
            <tr><th>Số lượng</th><td id="detail-qty"></td></tr>
            <tr><th>Đơn giá</th><td id="detail-price"></td></tr>
            <tr><th>Thành tiền</th><td id="detail-total"></td></tr>
            <tr><th>Người thực hiện</th><td id="detail-user"></td></tr>
            <tr><th>Chứng từ</th><td id="detail-ref"></td></tr>
            <tr><th>Đích đến / Nhà cung cấp</th><td id="detail-partner"></td></tr>
            <tr><th>Ghi chú</th><td id="detail-notes"></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
    function exportTransactions() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.open('export_transactions.php?' + params.toString(), '_blank');
    }

    function showTransactionDetail(data) {
        document.getElementById('detail-time').innerText = data.transaction_date;
        document.getElementById('detail-type').innerText = data.transaction_type === 'in' ? 'Nhập kho' : 'Xuất kho';
        document.getElementById('detail-code').innerText = data.product_code;
        document.getElementById('detail-name').innerText = data.product_name;
        document.getElementById('detail-qty').innerText = data.quantity + ' ' + (data.unit ?? '');
        document.getElementById('detail-price').innerText = Number(data.unit_price).toLocaleString() + ' VNĐ';
        document.getElementById('detail-total').innerText = Number(data.total_amount).toLocaleString() + ' VNĐ';
        document.getElementById('detail-user').innerText = data.user_name;
        document.getElementById('detail-partner').innerText = data.partner || 'Không có';
        document.getElementById('detail-ref').innerText = data.reference_no;
        document.getElementById('detail-notes').innerText = data.notes || 'Không có';
    }
</script>

<?php include 'chat_bot.php'; ?>
<?php include '../includes/footer.php'; ?>
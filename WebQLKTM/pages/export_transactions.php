<?php
    require_once '../config/database.php';
    require_once '../includes/auth.php';

    requireLogin(); // Yêu cầu đăng nhập

    $database = new Database();
    $db = $database->getConnection();

    // Lấy tham số lọc từ URL
    $type_filter = $_GET['type'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $product_filter = $_GET['product'] ?? '';
    $user_filter = $_GET['user'] ?? '';

    // Xây dựng điều kiện WHERE
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

    // Lấy dữ liệu giao dịch
    $query = "SELECT st.transaction_date, st.transaction_type, p.code, p.name as product_name,
                    st.quantity, p.unit, st.unit_price, st.total_amount, st.reference_no,
                    st.notes, u.full_name as user_name, c.name as category_name
            FROM stock_transactions st
            LEFT JOIN products p ON st.product_id = p.id
            LEFT JOIN users u ON st.user_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            $where_clause
            ORDER BY st.transaction_date DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Thiết lập header cho file Excel
    $filename = 'lich_su_giao_dich_' . date('Y-m-d_H-i-s') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><title>Lịch sử giao dịch</title></head>';
    echo '<body>';

    echo '<h2>HỆ THỐNG QUẢN LÝ KHO</h2>';
    echo '<h3>Lịch sử giao dịch từ ' . date('d/m/Y', strtotime($date_from)) . ' đến ' . date('d/m/Y', strtotime($date_to)) . '</h3>';
    echo '<p>Xuất lúc: ' . date('d/m/Y H:i:s') . '</p>';

    if (empty($transactions)) {
        echo '<p>Không có dữ liệu giao dịch nào trong khoảng thời gian và bộ lọc đã chọn.</p>';
    } else {
        echo '<table border="1">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Thời gian</th>';
        echo '<th>Loại</th>';
        echo '<th>Mã SP</th>';
        echo '<th>Tên sản phẩm</th>';
        echo '<th>Danh mục</th>';
        echo '<th>Số lượng</th>';
        echo '<th>Đơn vị</th>';
        echo '<th>Đơn giá</th>';
        echo '<th>Thành tiền</th>';
        echo '<th>Chứng từ</th>';
        echo '<th>Người thực hiện</th>';
        echo '<th>Ghi chú</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($transactions as $transaction) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($transaction['transaction_date'])) . '</td>';
            echo '<td>' . ($transaction['transaction_type'] == 'in' ? 'Nhập' : 'Xuất') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['code']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['product_name']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['category_name'] ?? 'N/A') . '</td>';
            // Chỉnh sửa định dạng số lượng: không có dấu thập phân, không có dấu phân cách hàng nghìn trong giá trị, dùng mso-number-format để Excel tự định dạng
            echo '<td style="mso-number-format:\#\#\#0">' . number_format((int)$transaction['quantity'], 0, '', '') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['unit']) . '</td>';
            // Chỉnh sửa định dạng đơn giá: 2 chữ số thập phân, không có dấu phân cách hàng nghìn trong giá trị, dùng mso-number-format để Excel tự định dạng
            echo '<td style="mso-number-format:\#\. \#\#0\,00">' . number_format((float)$transaction['unit_price'], 2, ',', '') . '</td>';
            // Chỉnh sửa định dạng thành tiền: 2 chữ số thập phân, không có dấu phân cách hàng nghìn trong giá trị, dùng mso-number-format để Excel tự định dạng
            echo '<td style="mso-number-format:\#\. \#\#0\,00">' . number_format((float)$transaction['total_amount'], 2, ',', '') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['reference_no']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['user_name']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['notes']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    echo '</body></html>';
    exit();
?>

<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();

if (isset($_GET['download'])) {
    $backup_content = $database->backupDatabase();
    $filename = 'warehouse_db_backup_' . date('Ymd_His') . '.sql';
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup_content));
    
    echo $backup_content;
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card rounded-4 shadow">
                    <div class="card-header bg-primary text-white rounded-top-4">
                        <h4><i class="fas fa-download"></i> Backup Database</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info rounded-3">
                            <h6><i class="fas fa-info-circle"></i> Thông tin Backup</h6>
                            <ul class="mb-0">
                                <li>File backup sẽ chứa toàn bộ cấu trúc và dữ liệu database</li>
                                <li>Định dạng: SQL script có thể import lại</li>
                                <li>Tên file: warehouse_db_backup_YYYYMMDD_HHMMSS.sql</li>
                            </ul>
                        </div>
                        
                        <?php
                        $info = $database->getDatabaseInfo();
                        if (!isset($info['error'])):
                        ?>
                        <h6>Thông tin Database hiện tại:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered rounded-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>Bảng</th>
                                        <th>Số bản ghi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($info['table_counts'] as $table => $count): ?>
                                    <tr>
                                        <td><code><?php echo $table; ?></code></td>
                                        <td><?php echo number_format($count); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="?download=1" class="btn btn-success btn-lg rounded-3">
                                <i class="fas fa-download"></i> Tải xuống Backup
                            </a>
                            <a href="index.php" class="btn btn-secondary rounded-3">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

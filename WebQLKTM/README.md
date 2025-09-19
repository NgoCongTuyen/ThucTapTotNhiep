# 🏪 Hệ thống Quản lý Kho - WebQLKTM

## 📁 Cấu trúc thư mục

```
WebQLKTM/
├── config/                 # Cấu hình hệ thống
│   └── database.php       # Kết nối database
├── includes/               # Thành phần chung
│   ├── auth.php           # Xác thực và phân quyền
│   ├── header.php         # Header chung
│   └── footer.php         # Footer chung
├── pages/                  # Giao diện người dùng
│   ├── index.php          # Dashboard chính
│   ├── login.php          # Trang đăng nhập
│   ├── products.php       # Quản lý sản phẩm
│   ├── stock_in.php       # Nhập kho
│   ├── stock_out.php      # Xuất kho
│   ├── transactions.php   # Lịch sử giao dịch
│   ├── suppliers.php      # Quản lý nhà cung cấp
│   ├── reports.php        # Báo cáo & thống kê
│   ├── chat_bot.php       # Giao diện chatbot
│   ├── chat_bot_api.php   # API chatbot
│   ├── backup_database.php # Sao lưu database
│   ├── export_reports.php  # Xuất báo cáo
│   ├── export_transactions.php # Xuất giao dịch
│   ├── debug_products.php # Debug sản phẩm
│   ├── demo.php           # Demo chatbot
│   ├── setup_check.php    # Kiểm tra cài đặt
│   └── logout.php         # Đăng xuất
├── api/                    # API endpoints
│   └── check_low_stock.php # API kiểm tra tồn kho thấp
├── scripts/                # Scripts và database
│   └── warehouse_db.sql   # Cấu trúc database
├── index.php               # Trang chính (redirect)
├── .htaccess              # Cấu hình Apache
└── README.md              # Hướng dẫn này
```

## 🚀 Cách sử dụng

### **1. Truy cập hệ thống:**
- **Trang chủ**: `http://localhost/WebQLKTM/`
- **Dashboard**: `http://localhost/WebQLKTM/pages/`
- **Đăng nhập**: `http://localhost/WebQLKTM/pages/login.php`

### **2. Các tính năng chính:**
- 📊 **Dashboard**: Tổng quan kho hàng
- 📦 **Quản lý sản phẩm**: Thêm, sửa, xóa sản phẩm
- 📥 **Nhập kho**: Quản lý hàng nhập
- 📤 **Xuất kho**: Quản lý hàng xuất
- 📋 **Giao dịch**: Lịch sử nhập/xuất kho
- 🏢 **Nhà cung cấp**: Quản lý NCC
- 📈 **Báo cáo**: Thống kê và xuất báo cáo
- 🤖 **Chatbot AI**: Hỗ trợ truy vấn kho

### **3. Chatbot AI:**
- **Giao diện**: `chat_bot.php`
- **API**: `chat_bot_api.php`
- **Cách dùng**: Click vào nút 💬 ở góc phải dưới
- **Câu hỏi mẫu**:
  - "Tổng quan kho"
  - "Khu A có bao nhiêu sản phẩm?"
  - "Sản phẩm nào sắp hết hàng?"

## ⚙️ Cài đặt

### **1. Yêu cầu hệ thống:**
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx
- mod_rewrite (cho .htaccess)

### **2. Cài đặt database:**
```sql
-- Import file scripts/warehouse_db.sql
mysql -u root -p warehouse_db < scripts/warehouse_db.sql
```

### **3. Cấu hình database:**
Chỉnh sửa `config/database.php`:
```php
private $host = "localhost";
private $db_name = "warehouse_db";
private $username = "your_username";
private $password = "your_password";
```

## 🔧 Bảo trì

### **1. Sao lưu database:**
```bash
php pages/backup_database.php
```

### **2. Xuất báo cáo:**
- Báo cáo tồn kho: `pages/export_reports.php`
- Báo cáo giao dịch: `pages/export_transactions.php`

### **3. Debug:**
- Debug sản phẩm: `pages/debug_products.php`
- Kiểm tra cài đặt: `pages/setup_check.php`

## 📱 API Endpoints

### **1. Chatbot API:**
- **URL**: `pages/chat_bot_api.php`
- **Method**: POST
- **Data**: `question=your_question`

### **2. Low Stock API:**
- **URL**: `api/check_low_stock.php`
- **Method**: GET
- **Auth**: Required
- **Response**: `{"count": 5}`

## 🛡️ Bảo mật

- **Authentication**: Yêu cầu đăng nhập cho hầu hết trang
- **Session management**: Sử dụng PHP sessions
- **SQL Injection**: Sử dụng PDO prepared statements
- **File access**: Bảo vệ thư mục config và includes

## 📞 Hỗ trợ

Nếu gặp vấn đề, hãy kiểm tra:
1. **Database connection**: `pages/setup_check.php`
2. **PHP errors**: Kiểm tra error log
3. **File permissions**: Đảm bảo web server có quyền đọc/ghi
4. **mod_rewrite**: Kiểm tra Apache mod_rewrite

## 🔄 Cập nhật

Để cập nhật hệ thống:
1. Sao lưu database
2. Sao lưu file cấu hình
3. Cập nhật code
4. Kiểm tra hoạt động
5. Khôi phục nếu có lỗi

---

**Phiên bản**: 1.0  
**Cập nhật**: 2024  
**Tác giả**: WebQLKTM Team

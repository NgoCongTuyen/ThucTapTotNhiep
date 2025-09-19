<?php
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ================== HELPER ================== */
function getZoneByCode(PDO $pdo, string $code) {
    $stmt = $pdo->prepare("SELECT * FROM zones WHERE code = ?");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ensureZone(PDO $pdo, string $code, ?string $name = null, ?string $desc = null) {
    $code = strtoupper($code);
    $zone = getZoneByCode($pdo, $code);
    if ($zone) return $zone;
    if (!$name) $name = "Khu $code";
    if (!$desc) $desc = "";
    $stmt = $pdo->prepare("INSERT INTO zones (code, name, description, created_at) VALUES (?, ?, ?, NOW())"); 
    $stmt->execute([$code, $name, $desc]);
    return getZoneByCode($pdo, $code);
}

function getCategoryIdByName(PDO $pdo, ?string $name) {
    if (!$name) return null;
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$name, $name]);
    return (int)$pdo->lastInsertId();
}

function nextProductCode(PDO $pdo) {
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM products");
    $max_id = (int)($stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0);
    return 'SP' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);
}

function findProduct(PDO $pdo, string $key, ?int $zoneId = null) {
    $sql = "SELECT p.*, z.code AS zone_code FROM products p 
            LEFT JOIN zones z ON p.zone_id = z.id WHERE p.code = ?";
    $params = [$key];
    if ($zoneId) { $sql .= " AND p.zone_id = ?"; $params[] = $zoneId; }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return $row;

    $sql = "SELECT p.*, z.code AS zone_code FROM products p 
            LEFT JOIN zones z ON p.zone_id = z.id WHERE p.name LIKE ?";
    $params = ["%$key%"];
    if ($zoneId) { $sql .= " AND p.zone_id = ?"; $params[] = $zoneId; }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fmtMoney($v) { return number_format((float)$v, 0, ',', '.') . ' VNĐ'; }

/* ---- NLP helpers ---- */
function vn_to_number(?string $s) {
    if ($s === null) return null;
    // nhận các dạng: "12.000", "12,000", "12000", "12.5", "12,5"
    $s = trim($s);
    // nếu có cả . và , thì giả định . là phân tách nghìn, , là thập phân (vi-VN)
    if (strpos($s, '.') !== false && strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        // nếu có , nhưng không có . → coi , là thập phân
        if (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        } else {
            // chỉ có . → có thể là nghìn
            // ta bỏ dấu .
            $s = str_replace('.', '', $s);
        }
    }
    if (!is_numeric($s)) return null;
    return (float)$s;
}

function parse_details(string $text): array {
    $out = [
        'qty' => null,
        'price' => null,
        'partner' => null,
        'reference' => null,
        'note' => null,
    ];
    // số lượng
    if (preg_match('/số\s*lượng\s*([0-9\.\,]+)/iu', $text, $m)) {
        $out['qty'] = (int) vn_to_number($m[1]);
    } elseif (preg_match('/\b(\d{1,9})\b\s*(?:cái|thùng|kg|bao|sp|đv|đơn vị)?/iu', $text, $m)) {
        // fallback: số đầu tiên xuất hiện
        $out['qty'] = (int) vn_to_number($m[1]);
    }
    // giá/đơn giá
    if (preg_match('/(?:đơn\s*giá|giá)\s*([0-9\.\,]+)/iu', $text, $m)) {
        $out['price'] = vn_to_number($m[1]);
    }
    // chứng từ / ref
    if (preg_match('/(?:chứng\s*từ|ref(?:erence)?|mã)\s*([A-Z0-9\-\_\/]+)/iu', $text, $m)) {
        $out['reference'] = trim($m[1]);
    }
    // đối tác (từ / đến / cho)
    if (preg_match('/(?:từ|đến|cho)\s+([^\|\n\,]+?)(?:\s+(?:giá|đơn\s*giá|ref|chứng\s*từ|ghi\s*chú)\b|$)/iu', $text, $m)) {
        $out['partner'] = trim($m[1]);
    }
    // ghi chú
    if (preg_match('/(?:ghi\s*chú|note)\s*([^\n]+)$/iu', $text, $m)) {
        $out['note'] = trim($m[1]);
    }
    return $out;
}

/**
 * Ghi giao dịch kho.
 * - Nếu $unitPriceOverride != null sẽ dùng làm đơn giá (với xuất: cho phép; với nhập: cập nhật price sản phẩm).
 * - Lưu: unit_price, total_amount, reference_no, partner, notes, user_id, transaction_date.
 */
function stockTransaction(PDO $pdo, int $productId, string $type, int $qty, ?string $partner, ?int $userId = null, string $note = '', ?string $referenceNo = null, ?float $unitPriceOverride = null) {
    if (!in_array($type, ['in','out'], true)) {
        throw new Exception("Loại giao dịch không hợp lệ.");
    }

    // Lấy thông tin sản phẩm
    $stmt = $pdo->prepare("SELECT id, current_stock, price, unit, min_stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new Exception("Không tìm thấy sản phẩm ID {$productId}.");

    $currentStock = (int)$product['current_stock'];
    $unitPrice = $unitPriceOverride !== null ? (float)$unitPriceOverride : (float)$product['price'];

    if ($qty <= 0) throw new Exception("Số lượng phải > 0.");

    // Kiểm tra tồn kho khi xuất
    if ($type === 'out' && $qty > $currentStock) {
        throw new Exception("Tồn kho không đủ (hiện có {$currentStock} {$product['unit']}, yêu cầu {$qty}).");
    }

    $totalAmount = $unitPrice * $qty;

    // Thêm giao dịch
    $stmt = $pdo->prepare("INSERT INTO stock_transactions 
        (product_id, transaction_type, quantity, unit_price, total_amount, reference_no, partner, notes, user_id, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $productId,
        $type,
        $qty,
        $unitPrice,
        $totalAmount,
        $referenceNo,
        $partner,
        $note,
        $userId
    ]);

    // Cập nhật tồn kho
    $delta = ($type === 'in') ? $qty : -$qty;
    $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
    $stmt->execute([$delta, $productId]);

    // Nếu nhập & có đơn giá override → cập nhật giá sản phẩm
    if ($type === 'in' && $unitPriceOverride !== null && $unitPriceOverride >= 0) {
        $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
        $stmt->execute([$unitPriceOverride, $productId]);
    }

    // Cảnh báo tồn kho thấp (sau xuất)
    if ($type === 'out') {
        $newStock = $currentStock - $qty;
        if ($newStock <= (int)$product['min_stock']) {
            return [
                'new_stock' => $newStock,
                'warning' => "⚠️ Tồn kho sau xuất ({$newStock}) <= mức tối thiểu (".$product['min_stock'].")"
            ];
        }
        return ['new_stock' => $newStock];
    } else {
        $newStock = $currentStock + $qty;
        return ['new_stock' => $newStock];
    }
}

/* ================== MAIN ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
    header('Content-Type: application/json; charset=utf-8');
    $question = trim($_POST['question']);
    if (!isset($_SESSION['chat_history'])) {
        $_SESSION['chat_history'] = [];
        $_SESSION['chat_history'][] = [
            "role"=>"system",
            "content"=>"Bạn là trợ lý AI quản lý kho. Hãy ưu tiên dùng dữ liệu DB để trả lời. Khi người dùng yêu cầu nhập hoặc xuất, hãy lưu đủ quantity, unit_price (nếu có), total_amount, reference_no, partner, notes vào bảng stock_transactions và cập nhật tồn kho."
        ];
    }
    $pdo = (new Database())->getConnection();
    $productData = '';

    try {
        /* ========== NGHIỆP VỤ ========= */
        // tạo khu kho
       if (preg_match('/tạo\s+khu\s*([A-Z])(?:\s+tên\s+([^,]+))?(?:\s+mô\s+tả\s+(.+))?/iu', $question, $m)) {
            $code = strtoupper(trim($m[1]));
            $name = "Khu {$code}";
            $desc = "";

            if (!empty($m[2]) || !empty($m[3])) {
                // Trường hợp có từ khóa rõ ràng: tên ... mô tả ...
                $name = !empty($m[2]) ? trim($m[2]) : $name;
                $desc = !empty($m[3]) ? trim($m[3]) : '';
            } else {
                // Không có từ khóa -> lấy phần còn lại sau "tạo khu A ..."
                if (preg_match('/tạo\s+khu\s*[A-Z]\s+(.+)/iu', $question, $mm)) {
                    $rest = trim($mm[1]);

                    // Tìm dấu hiệu phân tách mô tả
                    if (preg_match('/(.+?)\s+(chứa|để|lưu|mô\s*tả)\s+(.+)/iu', $rest, $mm2)) {
                        $name = trim($mm2[1]);
                        $desc = trim($mm2[2] . " " . $mm2[3]); // gộp lại thành mô tả đầy đủ
                    } else {
                        // Nếu không có từ khóa mô tả, toàn bộ coi là tên
                        $name = $rest;
                    }
                }
            }

            // Kiểm tra khu đã tồn tại chưa
            $stmt = $pdo->prepare("SELECT * FROM zones WHERE code = ?");
            $stmt->execute([$code]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($zone) {
                $productData = "⚠️ Khu {$code} đã tồn tại: {$zone['name']}"
                            . ($zone['description'] ? " ({$zone['description']})" : "");
            } else {
                $stmt = $pdo->prepare("INSERT INTO zones (code, name, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$code, $name, $desc]);

                $zoneId = $pdo->lastInsertId();
                $productData = "✅ Đã tạo khu mới:\n"
                            . "- Mã: {$code}\n"
                            . "- Tên: {$name}\n"
                            . "- Mô tả: " . ($desc ?: "Không có") . "\n"
                            . "- ID: {$zoneId}";
            }
        }


        // Thêm sản phẩm (tên, danh mục, đơn vị, giá, khu)
       elseif (preg_match('/thêm\s+sản\s+phẩm\s+(.+?)\s+danh\s+mục\s+(.+?)\s+đơn\s+vị\s+(.+?)\s+giá\s+([0-9\.\,]+)(?:\s*vnđ)?\s+vào\s+khu\s*([A-Z])/iu', $question, $m)) {
            $name = trim($m[1]); 
            $catName = trim($m[2]); 
            $unit = trim($m[3]);
            $price = (int) vn_to_number($m[4]);   // dùng hàm chuẩn hóa số tiền
            $zoneCode = strtoupper($m[5]);

            $zone = ensureZone($pdo, $zoneCode);
            $catId = getCategoryIdByName($pdo, $catName);
            $code = nextProductCode($pdo);

            $stmt = $pdo->prepare("INSERT INTO products 
                (code, name, category_id, unit, price, min_stock, current_stock, created_at, zone_id)
                VALUES (?, ?, ?, ?, ?, 1, 0, NOW(), ?)");
            $stmt->execute([$code, $name, $catId, $unit, $price, $zone['id']]);

            $productData = "✅ Đã thêm sản phẩm mới:\n"
                        . "- Mã: {$code}\n"
                        . "- Tên: {$name}\n"
                        . "- Danh mục: {$catName}\n"
                        . "- Đơn vị: {$unit}\n"
                        . "- Giá: " . fmtMoney($price) . "\n"
                        . "- Khu: {$zoneCode}";
        }
                // Thêm / tạo danh mục
        elseif (preg_match('/(?:tạo|thêm)\s+danh\s+mục\s+(.+?)(?:\s+mô\s+tả\s+(.+))?$/iu', $question, $m)) {
            $name = trim($m[1]);
            $desc = isset($m[2]) ? trim($m[2]) : '';

            if (!$name) {
                $productData = "❌ Tên danh mục không được để trống.";
            } else {
                // kiểm tra tồn tại
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $productData = "⚠️ Danh mục '{$name}' đã tồn tại (ID: {$row['id']}).";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$name, $desc]);
                    $newId = $pdo->lastInsertId();
                    $productData = "✅ Đã tạo danh mục '{$name}' (ID: {$newId})"
                                 . ($desc ? " | Mô tả: {$desc}" : " | Không có mô tả.");
                }
            }
        }


        // Nhập kho nói tự nhiên
        elseif (preg_match('/\bnhập\b/iu', $question)) {
            // tìm sản phẩm
            // cú pháp hỗ trợ: "nhập <tên hoặc mã> số lượng X từ <NCC> chứng từ <REF> ghi chú <NOTE>"
            if (preg_match('/nhập\s+(.+?)(?:\s+số\s+lượng|\s+sl|\s+gia|$)/iu', $question, $pm)) {
                $key = trim($pm[1]);
                $p = findProduct($pdo, $key);
                if (!$p) {
                    $productData = "❌ Không tìm thấy sản phẩm '{$key}'.";
                } else {
                    $d = parse_details($question);
                    if (!$d['qty'] || $d['qty'] <= 0) throw new Exception("Vui lòng nêu số lượng cần nhập.");
                    $unitPriceOverride = $d['price']; // có thể null
                    $ref = $d['reference'];
                    $partner = $d['partner'];
                    $note = $d['note'] ?? 'Nhập qua chatbot';

                    $res = stockTransaction(
                        $pdo,
                        (int)$p['id'],
                        'in',
                        (int)$d['qty'],
                        $partner,
                        $_SESSION['user_id'] ?? null,
                        $note,
                        $ref,
                        $unitPriceOverride
                    );
                    $newStock = $pdo->query("SELECT current_stock FROM products WHERE id={$p['id']}")->fetchColumn();
                    $usedPrice = ($unitPriceOverride !== null) ? $unitPriceOverride : $p['price'];
                    $productData = "📥 Nhập {$p['name']} ({$p['code']}) số lượng {$d['qty']}"
                                 . " với đơn giá ".fmtMoney($usedPrice)
                                 . ($partner ? " từ {$partner}" : "")
                                 . ($ref ? " | CT: {$ref}" : "")
                                 . ". Tồn mới: {$newStock}.";
                }
            } else {
                $productData = "❓ Bạn muốn nhập sản phẩm nào? Ví dụ: 'nhập SP01 số lượng 50 giá 12.000 từ NCC ABC ref PO-01 ghi chú hàng gấp'.";
            }
        }
        // Xuất kho nói tự nhiên
        elseif (preg_match('/\bxuất\b/iu', $question)) {
            // cú pháp hỗ trợ: "xuất <tên hoặc mã> số lượng X (giá Y) (đến|cho <đối tác>) (ref <mã>) (ghi chú <...>)"
            if (preg_match('/xuất\s+(.+?)(?:\s+số\s+lượng|\s+sl|\s+gia|$)/iu', $question, $pm)) {
                $key = trim($pm[1]);
                $p = findProduct($pdo, $key);
                if (!$p) {
                    $productData = "❌ Không tìm thấy sản phẩm '{$key}'.";
                } else {
                    $d = parse_details($question);
                    if (!$d['qty'] || $d['qty'] <= 0) throw new Exception("Vui lòng nêu số lượng cần xuất.");
                    $unitPriceOverride = $d['price']; // nếu null → dùng giá hiện tại
                    $ref = $d['reference'];
                    $partner = $d['partner'];
                    $note = $d['note'] ?? 'Xuất qua chatbot';

                    $res = stockTransaction(
                        $pdo,
                        (int)$p['id'],
                        'out',
                        (int)$d['qty'],
                        $partner,
                        $_SESSION['user_id'] ?? null,
                        $note,
                        $ref,
                        $unitPriceOverride
                    );
                    $newStock = $res['new_stock'] ?? ($p['current_stock'] - (int)$d['qty']);
                    $warnTxt = isset($res['warning']) ? " ".$res['warning'] : "";
                    $usedPrice = ($unitPriceOverride !== null) ? $unitPriceOverride : $p['price'];
                    $productData = "📤 Xuất {$p['name']} ({$p['code']}) số lượng {$d['qty']}"
                                 . " với đơn giá ".fmtMoney($usedPrice)
                                 . ($partner ? " đến {$partner}" : "")
                                 . ($ref ? " | CT: {$ref}" : "")
                                 . ". Tồn mới: {$newStock}.{$warnTxt}";
                }
            } else {
                $productData = "❓ Bạn muốn xuất sản phẩm nào? Ví dụ: 'xuất SP01 số lượng 5 đến Xưởng A ref XK-12 ghi chú test'.";
            }
        }
        // Lịch sử
        elseif (preg_match('/(lịch\s+sử|giao\s+dịch)(?!\s+ngày)/iu',$question)) {
            $rows=$pdo->query("SELECT st.*,p.code,p.name,z.code AS zone_code FROM stock_transactions st
                                JOIN products p ON st.product_id=p.id LEFT JOIN zones z ON p.zone_id=z.id
                                ORDER BY st.transaction_date DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
            if($rows){ $productData="📅 Lịch sử giao dịch:"; foreach($rows as $r){ $sign=$r['transaction_type']=='in'?'+':'-';
                $productData.="\n{$r['transaction_date']} | {$r['code']} - {$r['name']} ({$r['zone_code']}) | {$sign}{$r['quantity']}"
                             . " | ĐG: ".fmtMoney($r['unit_price'])." | TT: ".fmtMoney($r['total_amount'])
                             . ($r['reference_no']?" | CT: {$r['reference_no']}":"")
                             . ($r['partner']?" | Đối tác: {$r['partner']}":"")
                             . ($r['notes']?" | Ghi chú: {$r['notes']}":""); } }
            else $productData="Không có giao dịch.";
        }
        elseif (preg_match('/tổng\s+quan/iu',$question)) {
            $row=$pdo->query("SELECT COUNT(*) sp,SUM(current_stock) sl,SUM(current_stock*price) gt FROM products")->fetch(PDO::FETCH_ASSOC);
            $productData="📊 Tổng quan kho:\n- Sản phẩm: {$row['sp']}\n- Tổng tồn: {$row['sl']}\n- Giá trị: ".fmtMoney($row['gt']);
        }
        elseif (preg_match('/giao\s+dịch\s+ngày\s+(\d{4}-\d{2}-\d{2})/iu',$question,$m)) {
            $date=$m[1];
            $rows=$pdo->prepare("SELECT st.*,p.code,p.name,z.code AS zone_code FROM stock_transactions st
                                JOIN products p ON st.product_id=p.id LEFT JOIN zones z ON p.zone_id=z.id
                                WHERE DATE(st.transaction_date)=? ORDER BY st.transaction_date DESC");
            $rows->execute([$date]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC);
            if($rows){ $productData="📅 Giao dịch ngày {$date}:"; foreach($rows as $r){ $sign=$r['transaction_type']=='in'?'+':'-';
                $productData.="\n{$r['transaction_date']} | {$r['code']} - {$r['name']} ({$r['zone_code']}) | {$sign}{$r['quantity']}"
                             . " | ĐG: ".fmtMoney($r['unit_price'])." | TT: ".fmtMoney($r['total_amount'])
                             . ($r['reference_no']?" | CT: {$r['reference_no']}":"")
                             . ($r['partner']?" | Đối tác: {$r['partner']}":"")
                             . ($r['notes']?" | Ghi chú: {$r['notes']}":""); } }
            else $productData="❌ Không có giao dịch ngày {$date}.";
        }

        // Danh sách sản phẩm
        elseif (preg_match('/(danh\s*sách|list)\s+sản\s+phẩm(\s+khu\s*([A-Z]))?/iu',$question,$m)) {
            if (isset($m[3])) {
                $zone = strtoupper($m[3]);
                $stmt = $pdo->prepare("
                    SELECT p.code, p.name, p.current_stock, p.price, p.unit, c.name AS category_name
                    FROM products p 
                    JOIN zones z ON p.zone_id = z.id 
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE z.code = ? 
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([$zone]); 
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($rows) {
                    $productData = "📦 Danh sách sản phẩm khu {$zone}:";
                    foreach ($rows as $r) {
                        $cat = $r['category_name'] ?: "Chưa có danh mục";
                        $unit = $r['unit'] ?: "đv";
                        $productData .= "\n\n{$r['code']} - {$r['name']}"
                                    . "\nDM: {$cat}"
                                    . "\nSL: {$r['current_stock']} {$unit}"
                                    . "\nGiá: " . fmtMoney($r['price']);
                    }
                } else {
                    $productData = "❌ Không có sản phẩm trong khu {$zone}.";
                }
            } else {
                $rows = $pdo->query("
                    SELECT p.code, p.name, p.current_stock, p.price, p.unit, z.code AS zone_code, c.name AS category_name
                    FROM products p 
                    LEFT JOIN zones z ON p.zone_id = z.id 
                    LEFT JOIN categories c ON p.category_id = c.id
                    ORDER BY p.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);

                if ($rows) {
                    $productData = "📦 Danh sách sản phẩm trong kho:";
                    foreach ($rows as $r) {
                        $zone = $r['zone_code'] ?: "Chưa phân khu";
                        $cat = $r['category_name'] ?: "Chưa có danh mục";
                        $unit = $r['unit'] ?: "đv";
                        $productData .= "\n\n{$r['code']} - {$r['name']} ({$zone})"
                                    . "\nDM: {$cat}"
                                    . "\nSL: {$r['current_stock']} {$unit}"
                                    . "\nGiá: " . fmtMoney($r['price']);
                    }
                } else {
                    $productData = "❌ Kho chưa có sản phẩm.";
                }
            }
        }
  

        // Tìm kiếm theo tên
       elseif (preg_match('/(tìm|search)\s+sản\s+phẩm\s+(.+)/iu',$question,$m)) {
            $kw = trim($m[2]);
            $stmt = $pdo->prepare("
                SELECT p.code, p.name, p.current_stock, p.price, p.unit, z.code AS zone_code, c.name AS category_name
                FROM products p 
                LEFT JOIN zones z ON p.zone_id = z.id 
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.name LIKE ? OR p.code LIKE ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute(["%$kw%", "%$kw%"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                $productData = "🔎 Kết quả tìm kiếm '{$kw}':";
                foreach ($rows as $r) {
                    $zone = $r['zone_code'] ?: "Chưa phân khu";
                    $cat = $r['category_name'] ?: "Chưa có danh mục";
                    $unit = $r['unit'] ?: "đv";
                    $productData .= "\n\n{$r['code']} - {$r['name']} ({$zone})"
                                . "\nDM: {$cat}"
                                . "\nSL: {$r['current_stock']} {$unit}"
                                . "\nGiá: " . fmtMoney($r['price']);
                }
            } else {
                $productData = "❌ Không tìm thấy sản phẩm '{$kw}'.";
            }
        }


    } catch(Exception $e){ $productData="❌ ".$e->getMessage(); }

    /* ========== LƯU LỊCH SỬ & GỌI GROQ API ========== */
    $_SESSION['chat_history'][]=["role"=>"user","content"=>$question];
    if($productData) $_SESSION['chat_history'][]=["role"=>"system","content"=>$productData];

    $api_key="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";
    $data=["model"=>"llama-3.3-70b-versatile","messages"=>$_SESSION['chat_history'],"temperature"=>0.7,"max_tokens"=>1024];
    $ch=curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>["Content-Type: application/json","Authorization: Bearer {$api_key}"],
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($data, JSON_UNESCAPED_UNICODE)
    ]);
    $response=curl_exec($ch); curl_close($ch);
    $result=json_decode($response,true);
    if(isset($result['error'])){
        echo json_encode(["answer"=>"❌ API Error: ".$result['error']['message']],JSON_UNESCAPED_UNICODE); exit;
    }

    $answer=$result['choices'][0]['message']['content'] ?? ($productData ?: "Xin lỗi, tôi chưa có câu trả lời.");
    $_SESSION['chat_history'][]=["role"=>"assistant","content"=>$answer];
    echo json_encode(["answer"=>$answer],JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405);
echo json_encode(["error"=>"Method not allowed"]);

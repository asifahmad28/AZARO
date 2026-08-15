<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path): never {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) redirect('login.php');
}

function require_role(string $role): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== $role) redirect('index.php');
}

function is_staff(): bool {
    return is_logged_in() && in_array($_SESSION['user']['role'] ?? '', ['admin','moderator'], true);
}

function require_staff(): void {
    require_login();
    if (!is_staff()) redirect('index.php');
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

function discount_percent($price, $comparePrice): int {
    $price=(float)$price; $comparePrice=(float)$comparePrice;
    if($comparePrice <= $price || $comparePrice <= 0) return 0;
    return (int)round((($comparePrice-$price)/$comparePrice)*100);
}

function product_price_data(array $product): array {
    $price=(float)($product['price'] ?? 0);
    $compare=(float)($product['compare_price'] ?? 0);
    return ['price'=>$price,'compare_price'=>$compare,'discount'=>discount_percent($price,$compare)];
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array {
    $x = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $x;
}

function money($n): string {
    return '৳' . number_format((float)$n, 2);
}

function product_image(?string $path): string {
    return $path
        ? BASE_URL . '/' . ltrim($path, '/')
        : BASE_URL . '/assets/product-placeholder.svg';
}

function upload_profile_photo(string $field = 'profile_photo'): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field];
    if ((int)$file['size'] > 3 * 1024 * 1024) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return null;
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    $folder = __DIR__ . '/uploads/profiles/';
    if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        return null;
    }

    return 'uploads/profiles/' . $filename;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}

function upload_product_image(string $field = 'image'): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['size'] > 5 * 1024 * 1024) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    $folder = __DIR__ . '/uploads/products/';

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];

    if (move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        return 'uploads/products/' . $filename;
    }

    return null;
}


/* =========================
   CART
========================= */

function cart(): array {
    return isset($_SESSION['cart']) && is_array($_SESSION['cart'])
        ? $_SESSION['cart']
        : [];
}

function cart_count(): int {
    $count = 0;

    foreach (cart() as $qty) {
        $count += max(0, (int)$qty);
    }

    return $count;
}

function cart_add(int $productId, int $quantity = 1): void {
    if ($productId <= 0) return;

    $quantity = max(1, $quantity);

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$productId] =
        (int)($_SESSION['cart'][$productId] ?? 0) + $quantity;
}

function cart_update($quantities, ?int $quantity = null): void {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Supports both cart_update([id => qty]) and the older cart_update(id, qty) form.
    if (is_int($quantities) || ctype_digit((string)$quantities)) {
        $productId=(int)$quantities;
        $qty=(int)($quantity ?? 0);
        if($productId <= 0) return;
        if($qty <= 0) unset($_SESSION['cart'][$productId]);
        else $_SESSION['cart'][$productId]=$qty;
        return;
    }

    if (!is_array($quantities)) return;
    foreach ($quantities as $productId => $qty) {
        $productId=(int)$productId; $qty=(int)$qty;
        if($productId <= 0) continue;
        if($qty <= 0) unset($_SESSION['cart'][$productId]);
        else $_SESSION['cart'][$productId]=$qty;
    }
}

function cart_remove(int $productId): void {
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(): void {
    unset($_SESSION['cart']);
}


/* =========================
   PDF
========================= */

function pdf_escape(string $s): string {
    $converted = @iconv(
        'UTF-8',
        'windows-1252//TRANSLIT//IGNORE',
        $s
    );

    if ($converted !== false) {
        $s = $converted;
    }

    return str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', '', ' '],
        $s
    );
}

function make_invoice_pdf(int $orderId): string {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT o.*, b.name AS buyer_name, b.email AS buyer_email
        FROM orders o
        JOIN users b ON b.id=o.client_id
        WHERE o.id=? LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order=$stmt->fetch();
    if(!$order) throw new RuntimeException('Order not found.');

    $stmt=$pdo->prepare("
        SELECT od.quantity, od.unit_price, p.name
        FROM order_details od
        JOIN products p ON p.id=od.product_id
        WHERE od.order_id=? ORDER BY od.id
    ");
    $stmt->execute([$orderId]);
    $items=$stmt->fetchAll();

    $content='';
    // White page
    $content.="1 1 1 rg\n0 0 595 842 re f\n";
    // AZARO teal header
    $content.="0 0.592 0.698 rg\n0 710 595 132 re f\n";
    $content.="0.04 0.13 0.16 rg\n395 710 200 132 re f\n";

    $content.="1 1 1 rg\nBT\n/F1 25 Tf\n1 0 0 1 42 790 Tm\n(".pdf_escape('AZARO').") Tj\nET\n";
    $content.="BT\n/F1 9 Tf\n1 0 0 1 44 772 Tm\n(".pdf_escape('OWN YOUR STYLE').") Tj\nET\n";
    $content.="BT\n/F1 25 Tf\n1 0 0 1 414 790 Tm\n(".pdf_escape('INVOICE').") Tj\nET\n";
    $content.="BT\n/F1 9 Tf\n1 0 0 1 430 772 Tm\n(".pdf_escape('ORDER #'.$orderId).") Tj\nET\n";

    $content.="0.04 0.13 0.16 rg\n";
    $info=[
      ['INVOICE DATE',date('d M Y',strtotime((string)$order['date']))],
      ['CUSTOMER',$order['buyer_name']??'Customer'],
      ['EMAIL',$order['buyer_email']??''],
      ['DELIVERY ADDRESS',$order['address']??'N/A']
    ];
    $x=45;$y=665;
    foreach($info as $pair){
      $content.="BT\n/F1 8 Tf\n1 0 0 1 {$x} {$y} Tm\n(".pdf_escape($pair[0]).") Tj\nET\n";
      $content.="BT\n/F1 10 Tf\n1 0 0 1 {$x} ".($y-17)." Tm\n(".pdf_escape($pair[1]).") Tj\nET\n";
      $x += 270;
      if($x>300){$x=45;$y-=58;}
    }

    $tableY=520;
    $content.="0 0.592 0.698 rg\n45 {$tableY} 505 30 re f\n1 1 1 rg\n";
    foreach([[55,'ITEM'],[355,'QTY'],[420,'UNIT'],[490,'TOTAL']] as $h){
      $content.="BT\n/F1 8 Tf\n1 0 0 1 {$h[0]} ".($tableY+10)." Tm\n(".pdf_escape($h[1]).") Tj\nET\n";
    }

    $y=$tableY-25;$subtotal=0;$idx=0;
    foreach($items as $item){
      $idx++;
      $qty=(float)$item['quantity'];$unit=(float)$item['unit_price'];$line=$qty*$unit;$subtotal+=$line;
      if($idx%2===0){$content.="0.96 0.98 0.98 rg\n45 {$y} 505 25 re f\n";}
      $content.="0.08 0.12 0.14 rg\n";
      $name=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$item['name']); if($name===false)$name=(string)$item['name']; $name=substr($name,0,42);
      $row=[ [55,$name],[355,number_format($qty,0)],[420,number_format($unit,2)],[490,number_format($line,2)] ];
      foreach($row as $r){
        $content.="BT\n/F1 8 Tf\n1 0 0 1 {$r[0]} ".($y+8)." Tm\n(".pdf_escape($r[1]).") Tj\nET\n";
      }
      $y-=25;if($y<310)break;
    }

    $grand=(float)$order['price'];
    $totalY=$y-20;
    $content.="0.04 0.13 0.16 rg\n365 {$totalY} 185 78 re f\n1 1 1 rg\n";
    $content.="BT\n/F1 9 Tf\n1 0 0 1 380 ".($totalY+52)." Tm\n(".pdf_escape('SUBTOTAL').") Tj\nET\n";
    $content.="BT\n/F1 10 Tf\n1 0 0 1 490 ".($totalY+52)." Tm\n(".pdf_escape(number_format($subtotal,2)).") Tj\nET\n";
    $content.="BT\n/F1 13 Tf\n1 0 0 1 380 ".($totalY+25)." Tm\n(".pdf_escape('TOTAL').") Tj\nET\n";
    $content.="BT\n/F1 13 Tf\n1 0 0 1 462 ".($totalY+25)." Tm\n(".pdf_escape('BDT '.number_format($grand,2)).") Tj\nET\n";

    $content.="0 0.592 0.698 rg\n45 ".($totalY-35)." 280 30 re f\n1 1 1 rg\n";
    $status=strtoupper((string)($order['status']??'pending'));
    $content.="BT\n/F1 9 Tf\n1 0 0 1 60 ".($totalY-24)." Tm\n(".pdf_escape('ORDER STATUS: '.$status).") Tj\nET\n";

    $content.="0.25 0.29 0.30 rg\nBT\n/F1 8 Tf\n1 0 0 1 45 130 Tm\n(".pdf_escape('Thank you for choosing AZARO.').") Tj\nET\n";
    $content.="BT\n/F1 8 Tf\n1 0 0 1 45 115 Tm\n(".pdf_escape('Please keep this invoice for your records.').") Tj\nET\n";
    $content.="0 0.592 0.698 rg\n0 42 595 36 re f\n1 1 1 rg\nBT\n/F1 9 Tf\n1 0 0 1 225 56 Tm\n(".pdf_escape('AZARO • OWN YOUR STYLE').") Tj\nET\n";

    $objects=[
      "<< /Type /Catalog /Pages 2 0 R >>",
      "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
      "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
      "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
      "<< /Length ".strlen($content)." >>\nstream\n".$content."endstream"
    ];
    $pdf="%PDF-1.4\n";$offsets=[0];
    foreach($objects as $i=>$object){$n=$i+1;$offsets[$n]=strlen($pdf);$pdf.=$n." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf);
    $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
    $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    return $pdf;
}


/* =========================
   SMTP HELPERS
   No PHPMailer required.
========================= */

function smtp_read($socket): string {
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes): void {
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException(
            'SMTP error ' . $code . ': ' . trim($response)
        );
    }
}

function smtp_command($socket, string $command, array $codes): void {
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $codes);
}

function smtp_send_message(
    string $to,
    string $subject,
    string $body,
    ?string $pdf = null,
    ?string $filename = null
): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (
        SMTP_USER === '' ||
        SMTP_PASS === '' ||
        SMTP_PASS === 'PASTE_YOUR_16_CHARACTER_APP_PASSWORD_HERE'
    ) {
        error_log('AZARO SMTP: SMTP_USER or SMTP_PASS is not configured.');
        return false;
    }

    $fromEmail = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL !== ''
        ? SMTP_FROM_EMAIL
        : SMTP_USER;

    $socket = @fsockopen(
        SMTP_HOST,
        SMTP_PORT,
        $errno,
        $errstr,
        20
    );

    if (!$socket) {
        error_log(
            'AZARO SMTP connection failed: ' .
            $errno . ' - ' . $errstr
        );
        return false;
    }

    try {
        smtp_expect($socket, [220]);

        smtp_command($socket, 'EHLO localhost', [250]);

        smtp_command($socket, 'STARTTLS', [220]);

        if (!stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        )) {
            throw new RuntimeException('Could not start TLS.');
        }

        smtp_command($socket, 'EHLO localhost', [250]);

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(SMTP_USER), [334]);
        smtp_command($socket, base64_encode(SMTP_PASS), [235]);

        smtp_command(
            $socket,
            'MAIL FROM:<' . $fromEmail . '>',
            [250]
        );

        smtp_command(
            $socket,
            'RCPT TO:<' . $to . '>',
            [250, 251]
        );

        fwrite($socket, "DATA\r\n");
        smtp_expect($socket, [354]);

        $boundary = '=_AZARO_' . bin2hex(random_bytes(12));

        $headers  = "From: " . SMTP_FROM_NAME .
                    " <" . $fromEmail . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($pdf !== null) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"" .
                        $boundary . "\"\r\n";

            $message  = $headers . "\r\n";
            $message .= "--" . $boundary . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";

            $safeFilename = $filename ?: 'AZARO-Invoice.pdf';

            $message .= "--" . $boundary . "\r\n";
            $message .= "Content-Type: application/pdf; name=\"" .
                        $safeFilename . "\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"" .
                        $safeFilename . "\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($pdf));
            $message .= "--" . $boundary . "--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";

            $message = $headers . "\r\n" . $body . "\r\n";
        }

        // SMTP DATA must end with CRLF.CRLF.
        $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);

        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250]);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;

    } catch (Throwable $e) {
        fclose($socket);
        error_log('AZARO SMTP error: ' . $e->getMessage());
        return false;
    }
}


/* =========================
   REGISTRATION / WELCOME EMAIL
========================= */

function send_registration_email(string $name,string $email,string $role): bool {
    $safeName=trim($name)!==''?trim($name):'Customer';
    $subject='Welcome to AZARO — Own Your Style';
    $body=
        "Hello ".$safeName.",\r\n\r\n".
        "WELCOME TO AZARO.\r\n".
        "Own Your Style.\r\n\r\n".
        "Your AZARO account has been created successfully. We are excited to have you with us.\r\n\r\n".
        "Registered email: ".$email."\r\n".
        "Account type: Buyer\r\n\r\n".
        "Explore our shirts, pants, trousers and ready-to-wear combos whenever you are ready.\r\n".
        "Your order history, profile and account settings are available from your AZARO profile.\r\n\r\n".
        "Thank you for choosing AZARO — where everyday style feels effortless.\r\n\r\n".
        "Warm regards,\r\n".
        "AZARO Team\r\n".
        "Own Your Style.";
    return smtp_send_message($email,$subject,$body);
}

/* =========================
   ORDER CONFIRMATION EMAIL
========================= */

function send_order_confirmation_email(int $orderId): bool {
    $pdo=db();
    $stmt=$pdo->prepare("SELECT o.*,b.name AS buyer_name,b.email AS buyer_email FROM orders o JOIN users b ON b.id=o.client_id WHERE o.id=? LIMIT 1");
    $stmt->execute([$orderId]);$order=$stmt->fetch();
    if(!$order || !filter_var($order['buyer_email'],FILTER_VALIDATE_EMAIL))return false;
    try{$pdf=make_invoice_pdf($orderId);}catch(Throwable $e){error_log('AZARO PDF error: '.$e->getMessage());return false;}
    $subject='AZARO — Order #'.$orderId.' Confirmed';
    $body=
      "Hello ".($order['buyer_name']??'Customer').",\r\n\r\n".
      "Your AZARO order #".$orderId." has been confirmed.\r\n\r\n".
      "Order total: BDT ".number_format((float)$order['price'],2)."\r\n".
      "Delivery address: ".($order['address']??'N/A')."\r\n\r\n".
      "Your AZARO invoice is attached as a PDF for your records.\r\n\r\n".
      "Thank you for shopping with AZARO.\r\n".
      "Own Your Style.\r\n\r\n".
      "AZARO Team";
    return smtp_send_message($order['buyer_email'],$subject,$body,$pdf,'AZARO-Order-'.$orderId.'.pdf');
}


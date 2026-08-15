<?php
require_once 'functions.php';
require_login();

$orderId=(int)($_GET['id']??0);
$pdo=db();
$s=$pdo->prepare("SELECT * FROM orders WHERE id=? AND (client_id=? OR seller_id=?)");
$s->execute([$orderId,user()['id'],user()['id']]);
if(!$s->fetch() && !is_staff()){http_response_code(403);exit('Access denied.');}

$pdf=make_invoice_pdf($orderId);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="AZARO-Order-'.$orderId.'.pdf"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
exit;

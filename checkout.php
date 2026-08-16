<?php
require_once 'functions.php';
require_role('buyer');
$pdo=db();
$ids=array_keys($_SESSION['cart'] ?? []);
if(!$ids)redirect('cart.php');

$in=implode(',',array_fill(0,count($ids),'?'));
$st=$pdo->prepare("SELECT p.* FROM products p WHERE p.id IN ($in) AND p.status='active'");
$st->execute($ids);
$items=$st->fetchAll();

$total=0;
foreach($items as $p)$total += $p['price'] * (cart()[$p['id']]??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $address=trim($_POST['address']);
    if(!$address){flash('error','Delivery address is required.');redirect('checkout.php');}

    $pdo->beginTransaction();
    try{
        $bySeller=[];
        foreach($items as $p){
            $q=cart()[$p['id']]??0;
            if($q<1||$q>$p['stock'])throw new Exception('Stock changed for '.$p['name']);
            $bySeller[$p['seller_id']][]=['p'=>$p,'q'=>$q];
        }

        foreach($bySeller as $sellerId=>$rows){
            $sum=0;$qtyTotal=0;
            foreach($rows as $r){$sum += $r['p']['price']*$r['q'];$qtyTotal += $r['q'];}

            // The exact address entered by the buyer is saved to the order.
            $pdo->prepare("INSERT INTO orders(date,price,quantity,client_id,seller_id,status,address,courier_status) VALUES(NOW(),?,?,?,?,?,?,'Not sent')")
                ->execute([$sum,$qtyTotal,user()['id'],$sellerId,'pending',$address]);

            $oid=$pdo->lastInsertId();
            foreach($rows as $r){
                $p=$r['p'];$q=$r['q'];
                $pdo->prepare("INSERT INTO order_details(product_id,order_id,quantity,unit_price) VALUES(?,?,?,?)")
                    ->execute([$p['id'],$oid,$q,$p['price']]);
                $pdo->prepare("UPDATE products SET stock=stock-? WHERE id=?")->execute([$q,$p['id']]);
            }
        }

        $pdo->commit();
        cart_clear();
        flash('success','Order placed successfully.');
        redirect('orders.php');
    }catch(Throwable $e){
        $pdo->rollBack();
        flash('error',$e->getMessage());
        redirect('checkout.php');
    }
}

$title='Checkout';
include 'partials/header.php';
?>
<section class="page">
<div class="container two-col">
  <div class="panel">
    <h1>Checkout</h1>
    <p class="muted">The address entered below will be saved directly to the seller's Incoming Orders section.</p>
    <form method="post">
      <?=csrf_field()?>
      <div class="field">
        <label>Delivery Address</label>
        <textarea name="address" rows="7" required placeholder="House/Road, Area, City..."></textarea>
      </div>
      <button class="btn btn-primary" style="margin-top:15px">Place Order</button>
    </form>
  </div>
  <div class="panel">
    <h2>Order Summary</h2>
    <?php foreach($items as $p):?>
      <p><?=e($p['name'])?> × <?=cart()[$p['id']]??0?> <b style="float:right"><?=money($p['price']*(cart()[$p['id']]??0))?></b></p>
    <?php endforeach;?>
    <hr><h2>Total <span style="float:right"><?=money($total)?></span></h2>
  </div>
</div>
</section>
<?php include 'partials/footer.php'; ?>

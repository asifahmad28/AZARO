<?php
require_once __DIR__ . '/functions.php';
require_staff();
$pdo=db();
$section=$_GET['section'] ?? 'dashboard';
$allowedSections=['dashboard','orders','products','customers','finance','analytics','categories','reports','management'];
if(!in_array($section,$allowedSections,true)) $section='dashboard';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='confirm_order'){
            $oid=(int)$_POST['order_id'];
            $s=$pdo->prepare("SELECT id,status FROM orders WHERE id=? LIMIT 1");$s->execute([$oid]);$o=$s->fetch();
            if(!$o) throw new RuntimeException('Order not found.');
            if($o['status']!=='confirmed'){
                $pdo->prepare("UPDATE orders SET status='confirmed',confirmed_at=NOW() WHERE id=?")->execute([$oid]);
                if(send_order_confirmation_email($oid)){
                    $pdo->prepare("UPDATE orders SET voucher_sent_at=NOW() WHERE id=?")->execute([$oid]);
                    flash('success','Order confirmed. Buyer email and PDF invoice sent.');
                }else flash('warning','Order confirmed. Email was not sent; check SMTP settings in config.php.');
            }else flash('success','Order is already confirmed.');
            redirect('admin.php?section=orders');
        }
        if($action==='order_status'){
            $status=$_POST['status']??'pending';
            $allowed=['pending','confirmed','cancelled','delivered'];
            if(!in_array($status,$allowed,true)) $status='pending';
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,(int)$_POST['order_id']]);
            flash('success','Order status updated.'); redirect('admin.php?section=orders');
        }
        if($action==='courier_status'){
            $allowed=['Not sent','Sent to courier','Delivered','Returned'];$v=$_POST['courier_status']??'Not sent';
            if(!in_array($v,$allowed,true)) $v='Not sent';
            $pdo->prepare("UPDATE orders SET courier_status=? WHERE id=?")->execute([$v,(int)$_POST['order_id']]);
            flash('success','Courier status updated.'); redirect('admin.php?section=orders');
        }
        if($action==='save_product'){
            $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$content=trim($_POST['content']??'');
            $price=(float)($_POST['price']??0);$compare=(float)($_POST['compare_price']??0);$stock=max(0,(int)($_POST['stock']??0));
            $cat=(int)($_POST['category_id']??0);$delivery=trim($_POST['delivery_kind']??'')?:'Home Delivery';$status=$_POST['status']??'active';
            if($name===''||$price<=0||$cat<=0) throw new RuntimeException('Product name, category and valid price are required.');
            if($compare>0 && $compare<$price) throw new RuntimeException('Old price must be higher than current price.');
            $img=upload_product_image();
            if($id){
                $sql="UPDATE products SET name=?,content=?,price=?,compare_price=?,stock=?,category_id=?,delivery_kind=?,status=?".($img?',image=?':'')." WHERE id=?";
                $args=[$name,$content,$price,$compare>0?$compare:null,$stock,$cat,$delivery,$status]; if($img)$args[]=$img; $args[]=$id;
                $pdo->prepare($sql)->execute($args); flash('success','Product updated.');
            }else{
                $pdo->prepare("INSERT INTO products(name,content,price,compare_price,stock,delivery_kind,category_id,seller_id,image,status) VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$content,$price,$compare>0?$compare:null,$stock,$delivery,$cat,(int)user()['id'],$img,'active']);
                flash('success','Product added.');
            }
            redirect('admin.php?section=products');
        }
        if($action==='toggle_product'){
            $id=(int)$_POST['id'];$new=$_POST['new_status']==='active'?'active':'blocked';
            $pdo->prepare("UPDATE products SET status=? WHERE id=?")->execute([$new,$id]);flash('success','Product visibility updated.');redirect('admin.php?section=products');
        }
        if($action==='delete_product'){
            $pdo->prepare("UPDATE products SET status='deleted' WHERE id=?")->execute([(int)$_POST['id']]);flash('success','Product removed from the store.');redirect('admin.php?section=products');
        }
        if($action==='save_category'){
            $title=trim($_POST['title']??'');$id=(int)($_POST['id']??0);if($title==='') throw new RuntimeException('Category name is required.');
            if($id)$pdo->prepare("UPDATE categories SET title=? WHERE id=?")->execute([$title,$id]);else $pdo->prepare("INSERT INTO categories(title,parent_id) VALUES(?,NULL)")->execute([$title]);
            flash('success','Category saved.');redirect('admin.php?section=categories');
        }
        if($action==='delete_category'){
            $id=(int)$_POST['id'];$count=$pdo->prepare("SELECT COUNT(*) c FROM products WHERE category_id=? AND status!='deleted'");$count->execute([$id]);
            if((int)$count->fetch()['c']>0) throw new RuntimeException('This category has products. Move those products first.');
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);flash('success','Category deleted.');redirect('admin.php?section=categories');
        }
        if($action==='user_role' && is_admin()){
            $role=$_POST['role']??'buyer';$allowed=['buyer','moderator','admin'];if(!in_array($role,$allowed,true))$role='buyer';
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,(int)$_POST['id']]);flash('success','User role updated.');redirect('admin.php?section=customers');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());redirect('admin.php?section='.$section);}
}

$stats=[
 'users'=>(int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='buyer'")->fetch()['c'],
 'staff'=>(int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role IN('admin','moderator')")->fetch()['c'],
 'products'=>(int)$pdo->query("SELECT COUNT(*) c FROM products WHERE status='active'")->fetch()['c'],
 'orders'=>(int)$pdo->query("SELECT COUNT(*) c FROM orders")->fetch()['c'],
 'pending'=>(int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status='pending'")->fetch()['c'],
 'sales'=>(float)$pdo->query("SELECT COALESCE(SUM(price),0) c FROM orders WHERE status IN('confirmed','delivered')")->fetch()['c'],
];
$recentOrders=$pdo->query("SELECT o.*,u.name buyer_name,u.email buyer_email FROM orders o JOIN users u ON u.id=o.client_id ORDER BY o.date DESC LIMIT 10")->fetchAll();
$products=$pdo->query("SELECT p.*,c.title category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status!='deleted' ORDER BY p.id DESC")->fetchAll();
$cats=$pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status!='deleted') product_count FROM categories c ORDER BY c.title")->fetchAll();
$buyers=$pdo->query("SELECT id,name,email,phone,role,created_at FROM users ORDER BY id DESC")->fetchAll();
$allOrders=$pdo->query("SELECT o.*,u.name buyer_name,u.email buyer_email FROM orders o JOIN users u ON u.id=o.client_id ORDER BY o.date DESC")->fetchAll();
$editProduct=null;if(isset($_GET['edit'])){ $s=$pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");$s->execute([(int)$_GET['edit']]);$editProduct=$s->fetch(); }
$editCat=null;if(isset($_GET['edit_category'])){$s=$pdo->prepare("SELECT * FROM categories WHERE id=?");$s->execute([(int)$_GET['edit_category']]);$editCat=$s->fetch();}
$title='Admin Dashboard';include 'partials/header.php';
?>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <?php $staffUser = user(); $staffPhoto = !empty($staffUser['photo']) ? BASE_URL . '/' . ltrim((string)$staffUser['photo'], '/') : ''; ?>
    <div class="admin-user">
      <div class="avatar">
        <?php if ($staffPhoto): ?>
          <img src="<?=e($staffPhoto)?>" alt="<?=e($staffUser['name'] ?? 'Profile')?>">
        <?php else: ?>
          <?=e(strtoupper(substr((string)($staffUser['name'] ?? 'A'),0,1)))?>
        <?php endif; ?>
      </div>
      <div><b><?=e($staffUser['name'] ?? '')?></b><small><?=is_admin()?'Administrator':'Moderator'?></small></div>
    </div>
    <nav class="admin-nav">
      <a class="<?= $section==='dashboard'?'active':'' ?>" href="admin.php?section=dashboard"><span>⌂</span>Dashboard</a>
      <a class="<?= $section==='orders'?'active':'' ?>" href="admin.php?section=orders"><span>▣</span>Orders <?php if($stats['pending']):?><em><?=$stats['pending']?></em><?php endif;?></a>
      <a class="<?= $section==='products'?'active':'' ?>" href="admin.php?section=products"><span>▤</span>Products</a>
      <a class="<?= $section==='customers'?'active':'' ?>" href="admin.php?section=customers"><span>♙</span>Customers</a>
      <a class="<?= $section==='finance'?'active':'' ?>" href="admin.php?section=finance"><span>৳</span>Finance</a>
      <a class="<?= $section==='analytics'?'active':'' ?>" href="admin.php?section=analytics"><span>◒</span>Analytics</a>
      <div class="nav-label">COLLECTION SETUP</div>
      <a class="<?= $section==='categories'?'active':'' ?>" href="admin.php?section=categories"><span>◇</span>Categories</a>
      <a href="products.php"><span>↗</span>AZARO Store</a>
      <div class="nav-label">REPORTING</div>
      <a class="<?= $section==='reports'?'active':'' ?>" href="admin.php?section=reports"><span>▥</span>Reports</a>
      <?php if(is_admin()):?><a class="<?= $section==='management'?'active':'' ?>" href="admin.php?section=management"><span>⚙</span>Management</a><?php endif;?>
    </nav>
    <div class="admin-side-bottom"><a href="<?=BASE_URL?>/profile.php">My Profile</a><a href="<?=BASE_URL?>/logout.php">Logout</a></div>
  </aside>
  <main class="admin-main">
    <div class="admin-top"><div><span class="admin-kicker">CONTROL CENTER</span><h1><?=ucwords(str_replace('_',' ',$section))?></h1></div><div class="admin-top-actions"><a class="admin-search" href="admin.php?section=products">⌕ <span>Manage AZARO...</span></a><a class="btn btn-light" href="products.php">View Store</a></div></div>

    <?php if($section==='dashboard'): ?>
      <div class="admin-stats"><div class="a-stat"><span>Customers</span><b><?=$stats['users']?></b><small>Registered buyers</small></div><div class="a-stat"><span>Products</span><b><?=$stats['products']?></b><small>Live in store</small></div><div class="a-stat"><span>Orders</span><b><?=$stats['orders']?></b><small><?=$stats['pending']?> pending</small></div><div class="a-stat"><span>Sales</span><b><?=money($stats['sales'])?></b><small>Confirmed + delivered</small></div></div>
      <div class="admin-grid-2"><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">RECENT ACTIVITY</span><h2>Incoming Orders</h2></div><a href="admin.php?section=orders">View all</a></div><div class="order-list"><?php foreach($recentOrders as $o):?><div class="order-row"><div class="order-icon">#<?=e($o['id'])?></div><div class="order-main"><b><?=e($o['buyer_name'])?></b><span><?=e($o['buyer_email'])?></span></div><div class="order-money"><?=money($o['price'])?></div><span class="status-pill status-<?=e($o['status'])?>"><?=e(ucfirst($o['status']))?></span></div><?php endforeach;?><?php if(!$recentOrders):?><p class="muted">No orders yet.</p><?php endif;?></div></section><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">STORE HEALTH</span><h2>Quick Actions</h2></div></div><div class="quick-grid"><a href="admin.php?section=products"><b>＋</b><span>Add product</span></a><a href="admin.php?section=categories"><b>◇</b><span>Manage categories</span></a><a href="admin.php?section=customers"><b>♙</b><span>Customers</span></a><a href="admin.php?section=reports"><b>▥</b><span>Sales reports</span></a></div></section></div>
    <?php elseif($section==='orders'): ?>
      <section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">ORDER MANAGEMENT</span><h2>Incoming Orders</h2></div><span class="count-badge"><?=$stats['orders']?> total</span></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Order</th><th>Buyer</th><th>Address</th><th>Amount</th><th>Status</th><th>Courier</th><th>Action</th></tr></thead><tbody><?php foreach($allOrders as $o):?><tr><td><b>#<?=e($o['id'])?></b><small><?=e($o['date'])?></small></td><td><b><?=e($o['buyer_name'])?></b><small><?=e($o['buyer_email'])?></small></td><td><?=e($o['address']??'N/A')?></td><td><b><?=money($o['price'])?></b></td><td><?php if($o['status']==='pending'):?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="confirm_order"><input type="hidden" name="order_id" value="<?=$o['id']?>"><button class="status-button pending-button">Confirm</button></form><?php else:?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="order_status"><input type="hidden" name="order_id" value="<?=$o['id']?>"><select name="status" onchange="this.form.submit()" class="status-select"><option value="pending" <?=$o['status']==='pending'?'selected':''?>>Pending</option><option value="confirmed" <?=$o['status']==='confirmed'?'selected':''?>>Confirmed</option><option value="delivered" <?=$o['status']==='delivered'?'selected':''?>>Delivered</option><option value="cancelled" <?=$o['status']==='cancelled'?'selected':''?>>Cancelled</option></select></form><?php endif;?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="courier_status"><input type="hidden" name="order_id" value="<?=$o['id']?>"><select name="courier_status" onchange="this.form.submit()" class="status-select"><option>Not sent</option><option <?=$o['courier_status']==='Sent to courier'?'selected':''?>>Sent to courier</option><option <?=$o['courier_status']==='Delivered'?'selected':''?>>Delivered</option><option <?=$o['courier_status']==='Returned'?'selected':''?>>Returned</option></select></form></td><td><a class="table-action" href="<?=BASE_URL?>/invoice.php?id=<?=$o['id']?>">Invoice</a></td></tr><?php endforeach;?></tbody></table></div></section>
    <?php elseif($section==='products'): ?>
      <div class="admin-grid-2 product-admin-grid"><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">CATALOG</span><h2><?= $editProduct?'Edit Product':'Add Product'?></h2></div></div><form method="post" enctype="multipart/form-data" class="admin-form"><?=csrf_field()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=e($editProduct['id']??0)?>"><label>Product name<input name="name" required value="<?=e($editProduct['name']??'')?>"></label><div class="form-two"><label>Category<select name="category_id" required><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($editProduct&&$editProduct['category_id']==$c['id'])?'selected':''?>><?=e($c['title'])?></option><?php endforeach;?></select></label><label>Stock<input name="stock" type="number" min="0" required value="<?=e($editProduct['stock']??0)?>"></label></div><div class="form-two"><label>Current price<input name="price" type="number" min="0.01" step="0.01" required value="<?=e($editProduct['price']??'')?>"></label><label>Old price / MRP<input name="compare_price" type="number" min="0" step="0.01" value="<?=e($editProduct['compare_price']??'')?>"></label></div><label>Delivery method<input name="delivery_kind" value="<?=e($editProduct['delivery_kind']??'Home Delivery')?>"></label><label>Description<textarea name="content" rows="7"><?=e($editProduct['content']??'')?></textarea></label><label>Product image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><div class="form-actions"><button class="btn btn-primary">Save Product</button><?php if($editProduct):?><a class="btn btn-light" href="admin.php?section=products">Cancel</a><?php endif;?></div></form></section><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">LIVE CATALOG</span><h2>Products</h2></div><span class="count-badge"><?=count($products)?></span></div><div class="mini-product-list"><?php foreach($products as $p):$pd=product_price_data($p);?><div class="mini-product"><img src="<?=product_image($p['image'])?>"><div><b><?=e($p['name'])?></b><small><?=e($p['category'])?> · Stock <?=$p['stock']?></small><strong><?=money($p['price'])?> <?php if($pd['discount']):?><del><?=money($pd['compare_price'])?></del><i><?=$pd['discount']?>% OFF</i><?php endif;?></strong></div><div class="mini-actions"><a href="admin.php?section=products&edit=<?=$p['id']?>">Edit</a><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=$p['id']?>"><input type="hidden" name="new_status" value="<?=$p['status']==='active'?'blocked':'active'?>"><button><?=$p['status']==='active'?'Block':'Activate'?></button></form></div></div><?php endforeach;?></div></section></div>
    <?php elseif($section==='customers'): ?>
      <section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">CUSTOMER MANAGEMENT</span><h2>Customers & Staff</h2></div></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>User</th><th>Phone</th><th>Role</th><th>Joined</th><th>Action</th></tr></thead><tbody><?php foreach($buyers as $u):?><tr><td><b><?=e($u['name'])?></b><small><?=e($u['email'])?></small></td><td><?=e($u['phone']??'—')?></td><td><span class="role-pill role-<?=e($u['role'])?>"><?=e(ucfirst($u['role']))?></span></td><td><?=e($u['created_at'])?></td><td><?php if(is_admin()):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="user_role"><input type="hidden" name="id" value="<?=$u['id']?>"><select name="role" onchange="this.form.submit()" class="status-select"><option value="buyer" <?=$u['role']==='buyer'?'selected':''?>>Buyer</option><option value="moderator" <?=$u['role']==='moderator'?'selected':''?>>Moderator</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Admin</option></select></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
    <?php elseif($section==='finance'): ?>
      <div class="admin-stats"><div class="a-stat"><span>Confirmed sales</span><b><?=money($stats['sales'])?></b><small>Current database total</small></div><div class="a-stat"><span>Average order</span><b><?=money($stats['orders']?$stats['sales']/$stats['orders']:0)?></b><small>Across all orders</small></div></div><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">FINANCE</span><h2>Sales Ledger</h2></div></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Order</th><th>Date</th><th>Buyer</th><th>Status</th><th>Amount</th></tr></thead><tbody><?php foreach($allOrders as $o):?><tr><td>#<?=$o['id']?></td><td><?=e($o['date'])?></td><td><?=e($o['buyer_name'])?></td><td><span class="status-pill status-<?=e($o['status'])?>"><?=e(ucfirst($o['status']))?></span></td><td><b><?=money($o['price'])?></b></td></tr><?php endforeach;?></tbody></table></div></section>
    <?php elseif($section==='analytics'): $confirmed=(int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status IN('confirmed','delivered')")->fetch()['c'];$delivered=(int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status='delivered'")->fetch()['c'];$conversion=$stats['orders']?round(($confirmed/$stats['orders'])*100):0;$delivery=$confirmed?round(($delivered/$confirmed)*100):0;?>
      <div class="admin-stats"><div class="a-stat"><span>Confirmation rate</span><b><?=$conversion?>%</b><small>Orders confirmed or delivered</small></div><div class="a-stat"><span>Delivery rate</span><b><?=$delivery?>%</b><small>Of confirmed orders</small></div><div class="a-stat"><span>Active catalog</span><b><?=$stats['products']?></b><small>Visible products</small></div></div><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">ANALYTICS</span><h2>Store performance</h2></div></div><div class="metric-bars"><div><span>Orders confirmed</span><b><?=$conversion?>%</b><div><i style="width:<?=$conversion?>%"></i></div></div><div><span>Orders delivered</span><b><?=$delivery?>%</b><div><i style="width:<?=$delivery?>%"></i></div></div></div></section>
    <?php elseif($section==='categories'): ?>
      <div class="admin-grid-2"><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">COLLECTION SETUP</span><h2><?= $editCat?'Edit Category':'Add Category'?></h2></div></div><form method="post" class="admin-form"><?=csrf_field()?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?=e($editCat['id']??0)?>"><label>Category name<input name="title" required value="<?=e($editCat['title']??'')?>"></label><button class="btn btn-primary">Save Category</button></form></section><section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">CATEGORIES</span><h2>Store Categories</h2></div></div><?php foreach($cats as $c):?><div class="category-row"><div><b><?=e($c['title'])?></b><small><?=$c['product_count']?> products</small></div><div><a href="admin.php?section=categories&edit_category=<?=$c['id']?>">Edit</a><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?=$c['id']?>"><button onclick="return confirm('Delete this category?')">Delete</button></form></div></div><?php endforeach;?></section></div>
    <?php elseif($section==='reports'): ?>
      <section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">REPORTING</span><h2>Sales Report</h2></div></div><div class="report-cards"><div><span>Today</span><b><?=money((float)$pdo->query("SELECT COALESCE(SUM(price),0) c FROM orders WHERE status IN('confirmed','delivered') AND DATE(date)=CURDATE()")->fetch()['c'])?></b></div><div><span>Last 7 days</span><b><?=money((float)$pdo->query("SELECT COALESCE(SUM(price),0) c FROM orders WHERE status IN('confirmed','delivered') AND date>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch()['c'])?></b></div><div><span>Last 30 days</span><b><?=money((float)$pdo->query("SELECT COALESCE(SUM(price),0) c FROM orders WHERE status IN('confirmed','delivered') AND date>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetch()['c'])?></b></div></div></section>
    <?php elseif($section==='management'): ?>
      <section class="admin-card"><div class="admin-card-head"><div><span class="admin-kicker">MANAGEMENT</span><h2>System Management</h2></div></div><div class="management-grid"><a href="setup.php"><b>Database Setup</b><span>Run safe migrations and seed missing defaults.</span></a><a href="profile.php"><b>Administrator Profile</b><span>Update your own account information.</span></a><a href="products.php"><b>AZARO Store</b><span>Preview the customer-facing storefront.</span></a><a href="README.md"><b>Project Guide</b><span>Local setup and SMTP notes.</span></a></div></section>
    <?php endif; ?>
  </main>
</div>
</main>
</body>
</html>

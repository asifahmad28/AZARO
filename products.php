<?php
require_once __DIR__ . '/functions.php';
$pdo=db();$title='Shop';
$cats=$pdo->query("SELECT * FROM categories ORDER BY title")->fetchAll();
$where=["p.status='active'"];$params=[];
if(!empty($_GET['q'])){$where[]="(p.name LIKE ? OR p.content LIKE ?)";$q='%'.trim($_GET['q']).'%';$params=[$q,$q];}
if(!empty($_GET['category'])){$where[]='p.category_id=?';$params[]=(int)$_GET['category'];}
$sort=$_GET['sort']??'';$order=$sort==='price_low'?'p.price ASC':($sort==='price_high'?'p.price DESC':'p.created_at DESC');
$stmt=$pdo->prepare("SELECT p.*,c.title category FROM products p JOIN categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where)." ORDER BY $order");$stmt->execute($params);$products=$stmt->fetchAll();
include __DIR__.'/partials/header.php';
function render_product_card(array $p): void { $pd=product_price_data($p); ?>
<article class="card product-card">
  <a class="product-media" href="<?=BASE_URL?>/product.php?id=<?=$p['id']?>">
    <?php if($pd['discount']):?><span class="discount-badge">-<?=$pd['discount']?>%</span><?php endif;?><img class="product-img" src="<?=product_image($p['image'])?>" alt="<?=e($p['name'])?>">
  </a>
  <div class="card-body"><span class="badge"><?=e($p['category'])?></span><h3><?=e($p['name'])?></h3>
    <div class="price-row"><strong class="price"><?=money($p['price'])?></strong><?php if($pd['discount']):?><del><?=money($pd['compare_price'])?></del><?php endif;?></div>
    <?php if($pd['discount']):?><span class="save-line">Save <?=money($pd['compare_price']-$pd['price'])?></span><?php endif;?>
    <div class="card-actions"><a class="btn btn-light" href="<?=BASE_URL?>/product.php?id=<?=$p['id']?>">View Details</a><form method="post" action="<?=BASE_URL?>/cart.php" style="flex:1"><input type="hidden" name="action" value="add"><?=csrf_field()?><input type="hidden" name="product_id" value="<?=$p['id']?>"><input type="hidden" name="quantity" value="1"><button class="btn btn-primary" style="width:100%" <?=$p['stock']<=0?'disabled':''?>><?=($p['stock']>0?'Add to Cart':'Out of Stock')?></button></form></div>
  </div>
</article>
<?php }
?>
<section class="page"><div class="container">
  <div class="shop-hero"><div><span class="eyebrow">AZARO</span><h1>Wear it well. Own your style.</h1><p>Explore shirts, pants, trousers and curated combos from the official AZARO fashion store.</p></div><div class="shop-hero-stat"><b><?=count($products)?></b><span>Products available</span></div></div>
  <div class="section-head"><div><span class="eyebrow">BROWSE</span><h2>All Products</h2></div><span class="muted"><?=count($products)?> results</span></div>
  <form class="filters" method="get"><input name="q" value="<?=e($_GET['q']??'')?>" placeholder="Search products..."><select name="category"><option value="">All categories</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($_GET['category']??'')==$c['id']?'selected':''?>><?=e($c['title'])?></option><?php endforeach;?></select><select name="sort"><option value="">Latest</option><option value="price_low" <?=$sort==='price_low'?'selected':''?>>Price: Low to High</option><option value="price_high" <?=$sort==='price_high'?'selected':''?>>Price: High to Low</option></select><button class="btn btn-primary">Filter</button></form>
  <div class="grid"><?php foreach($products as $p)render_product_card($p);?></div>
  <?php if(!$products):?><div class="panel empty-state"><h2>No products found</h2><p class="muted">Try another search or category.</p></div><?php endif;?>
</div></section>
<?php include __DIR__.'/partials/footer.php'; ?>

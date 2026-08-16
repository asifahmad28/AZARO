<?php
require_once __DIR__.'/functions.php';
$pdo=db(); $title='Home';

$categories=$pdo->query("SELECT * FROM categories ORDER BY id LIMIT 8")->fetchAll();
$products=$pdo->query("SELECT p.*,c.title category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
$offerStmt=$pdo->query("SELECT p.*,c.title category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.compare_price IS NOT NULL AND p.compare_price>p.price ORDER BY p.created_at DESC LIMIT 1");
$offer=$offerStmt->fetch();
include __DIR__.'/partials/header.php';
?>
<section class="hero fashion-hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="eyebrow">AZARO • NEW SEASON</span>
      <h1>Dress well.<br><em>Own your style.</em></h1>
      <p>Timeless everyday pieces, refined fits and effortless combinations — curated for the way you live.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?=BASE_URL?>/products.php">Shop the collection</a>
        <a class="btn btn-outline" href="<?=BASE_URL?>/products.php?category=1">Explore shirts</a>
      </div>
      <div class="hero-note"><span>✦</span> Designed for everyday confidence</div>
    </div>
    <div class="hero-visual">
      <div class="hero-logo-card"><img src="<?=BASE_URL?>/assets/azaro-logo.jpg" alt="AZARO logo"><b>Own Your Style</b></div>
      <div class="hero-vertical">AZARO / 2026 COLLECTION</div>
    </div>
  </div>
</section>

<section class="section category-section">
  <div class="container">
    <div class="section-head"><div><span class="eyebrow">THE EDIT</span><h2>Shop by category</h2></div><a href="<?=BASE_URL?>/products.php" class="text-link">View all →</a></div>
    <div class="category-grid">
      <?php foreach($categories as $i=>$c):?>
      <a class="category-tile" href="<?=BASE_URL?>/products.php?category=<?=$c['id']?>">
        <span class="category-no"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span>
        <div><h3><?=e($c['title'])?></h3><p>Explore collection</p></div><span class="arrow">↗</span>
      </a>
      <?php endforeach;?>
    </div>
  </div>
</section>

<section class="section featured-section">
  <div class="container">
    <div class="section-head"><div><span class="eyebrow">CURATED FOR YOU</span><h2>Latest arrivals</h2></div><a href="<?=BASE_URL?>/products.php" class="text-link">See all pieces →</a></div>
    <div class="grid">
      <?php foreach($products as $p): $pd=product_price_data($p);?>
      <article class="card product-card">
        <a class="product-media" href="<?=BASE_URL?>/product.php?id=<?=$p['id']?>">
          <?php if($pd['discount']):?><span class="discount-badge">-<?=$pd['discount']?>%</span><?php endif;?>
          <img class="product-img" src="<?=product_image($p['image'])?>" alt="<?=e($p['name'])?>">
        </a>
        <div class="card-body"><span class="badge"><?=e($p['category'])?></span><h3><?=e($p['name'])?></h3>
          <div class="price-row"><strong class="price"><?=money($p['price'])?></strong><?php if($pd['discount']):?><del><?=money($pd['compare_price'])?></del><?php endif;?></div>
          <?php if($pd['discount']):?><span class="save-line">Save <?=money($pd['compare_price']-$pd['price'])?></span><?php endif;?>
          <div class="card-actions"><a class="btn btn-light" href="<?=BASE_URL?>/product.php?id=<?=$p['id']?>">View</a><form method="post" action="<?=BASE_URL?>/cart.php" style="flex:1"><?=csrf_field()?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?=$p['id']?>"><button class="btn btn-primary" style="width:100%">Add to Bag</button></form></div>
        </div>
      </article>
      <?php endforeach;?>
    </div>
  </div>
</section>

<section class="editorial">
  <div class="container editorial-grid">
    <div><span class="eyebrow">THE AZARO STANDARD</span><h2>Simple pieces.<br><em>Strong presence.</em></h2><p>We keep the collection focused: shirts, pants, trousers and thoughtfully paired combos that make getting dressed easier.</p><a class="text-link light-link" href="<?=BASE_URL?>/products.php">Discover AZARO →</a></div>
    <div class="editorial-stat"><strong>01</strong><span>Clean cuts</span><strong>02</strong><span>Everyday comfort</span><strong>03</strong><span>Easy styling</span></div>
  </div>
</section>

<section class="trust-section">
  <div class="container trust-grid"><div><b>✓</b><span><strong>Curated quality</strong><small>Pieces selected for everyday wear</small></span></div><div><b>✓</b><span><strong>Clear pricing</strong><small>Offers shown before checkout</small></span></div><div><b>✓</b><span><strong>Easy ordering</strong><small>Simple shopping from start to finish</small></span></div><div><b>✓</b><span><strong>Order history</strong><small>Track every purchase from your profile</small></span></div></div>
</section>

<?php if($offer): $opd=product_price_data($offer); ?>
<div class="offer-backdrop" id="offerPopup" aria-hidden="true">
  <div class="offer-modal">
    <button class="offer-close" type="button" onclick="closeAzaroOffer()">×</button>
    <div class="offer-image"><img src="<?=product_image($offer['image'])?>" alt="<?=e($offer['name'])?>"></div>
    <div class="offer-copy"><span class="eyebrow">LIMITED-TIME OFFER</span><h2>Made for your next look.</h2><p><?=e($offer['name'])?></p><div class="offer-price"><strong><?=money($offer['price'])?></strong><del><?=money($offer['compare_price'])?></del><span>-<?=$opd['discount']?>%</span></div><a class="btn btn-primary" href="<?=BASE_URL?>/product.php?id=<?=$offer['id']?>">View offer</a></div>
  </div>
</div>
<script>
(function(){
  if(sessionStorage.getItem('azaro_offer_seen')) return;
  setTimeout(function(){var p=document.getElementById('offerPopup');if(p){p.classList.add('show');p.setAttribute('aria-hidden','false');}},900);
})();
function closeAzaroOffer(){var p=document.getElementById('offerPopup');if(p){p.classList.remove('show');p.setAttribute('aria-hidden','true');sessionStorage.setItem('azaro_offer_seen','1');}}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAzaroOffer();});
document.getElementById('offerPopup')?.addEventListener('click',function(e){if(e.target===this)closeAzaroOffer();});
</script>
<?php endif;?>
<?php include __DIR__.'/partials/footer.php';?>

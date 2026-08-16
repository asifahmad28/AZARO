<?php require_once __DIR__.'/../functions.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0097b2">
<title><?=e($title??SITE_NAME)?> | <?=SITE_NAME?> — <?=SITE_TAGLINE?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=BASE_URL?>/assets/style.css">
</head>
<body>
<div class="announcement">AZARO — Own Your Style <span>•</span> New season essentials are here</div>
<header class="topbar">
  <div class="container nav">
    <a class="brand" href="<?=BASE_URL?>/index.php">
      <img src="<?=BASE_URL?>/assets/azaro-logo.jpg" alt="AZARO" class="logo">
      <span><strong>AZARO</strong><small><?=SITE_TAGLINE?></small></span>
    </a>
    <form class="search" action="<?=BASE_URL?>/products.php" method="get">
      <span>⌕</span><input name="q" value="<?=e($_GET['q']??'')?>" placeholder="Search shirts, pants, trousers, combos..."><button>Search</button>
    </form>
    <nav class="links">
      <a href="<?=BASE_URL?>/products.php">Shop</a>
      <?php if(is_logged_in()):?>
        <?php if(is_staff()):?><a href="<?=BASE_URL?>/admin.php">Dashboard</a>
        <?php elseif(user()['role']==='buyer'):?><a href="<?=BASE_URL?>/orders.php">Orders</a><?php endif;?>
        <a class="header-profile-link" href="<?=BASE_URL?>/profile.php">
          <?php if(!empty(user()['photo'])): ?><img src="<?=e(BASE_URL.'/'.ltrim(user()['photo'],'/'))?>" alt="Profile" class="header-avatar">
          <?php else: ?><span class="header-avatar header-avatar-fallback"><?=e(strtoupper(substr((string)(user()['name']??'U'),0,1)))?></span><?php endif;?>
          <span class="desktop-only">Profile</span>
        </a>
        <a href="<?=BASE_URL?>/logout.php">Logout</a>
      <?php else:?>
        <a href="<?=BASE_URL?>/login.php">Login</a><a class="nav-cta" href="<?=BASE_URL?>/register.php">Join AZARO</a>
      <?php endif;?>
      <a class="cart" href="<?=BASE_URL?>/cart.php">Bag <span><?=cart_count()?></span></a>
    </nav>
  </div>
</header>
<?php foreach(flashes() as [$type,$msg]):?><div class="flash <?=e($type)?>"><?=e($msg)?></div><?php endforeach;?>
<main>

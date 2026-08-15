<?php
require_once __DIR__ . '/config.php';
$messages=[];$ok=false;
try{
  $pdo=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("USE `".DB_NAME."`");

  $sql=[
    "CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,phone VARCHAR(45),password VARCHAR(255) NOT NULL,photo VARCHAR(200),role ENUM('buyer','moderator','admin','seller') NOT NULL DEFAULT 'buyer',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS sellers(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,take_home DECIMAL(12,2) DEFAULT 0,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS clients(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,address VARCHAR(255),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS categories(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(100) NOT NULL,parent_id INT NULL,FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE SET NULL) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS products(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255) NOT NULL,content TEXT,price DECIMAL(12,2) NOT NULL,compare_price DECIMAL(12,2) DEFAULT NULL,stock INT NOT NULL DEFAULT 0,delivery_kind VARCHAR(100) DEFAULT 'Home Delivery',category_id INT NOT NULL,seller_id INT NOT NULL,image VARCHAR(255),status ENUM('active','blocked','deleted') DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(category_id) REFERENCES categories(id),FOREIGN KEY(seller_id) REFERENCES users(id)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS orders(id INT AUTO_INCREMENT PRIMARY KEY,date DATETIME NOT NULL,price DECIMAL(12,2) NOT NULL,quantity INT NOT NULL,client_id INT NOT NULL,seller_id INT NOT NULL,status ENUM('pending','confirmed','cancelled','delivered') DEFAULT 'pending',address VARCHAR(255),courier_status VARCHAR(40) DEFAULT 'Not sent',courier_name VARCHAR(100) DEFAULT NULL,confirmed_at DATETIME NULL,voucher_sent_at DATETIME NULL,FOREIGN KEY(client_id) REFERENCES users(id),FOREIGN KEY(seller_id) REFERENCES users(id)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS order_details(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,order_id INT NOT NULL,quantity INT NOT NULL,unit_price DECIMAL(12,2) NOT NULL,FOREIGN KEY(product_id) REFERENCES products(id),FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS messages(id INT AUTO_INCREMENT PRIMARY KEY,sender_id INT NOT NULL,receiver_id INT NOT NULL,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL,rating TINYINT NOT NULL,comment TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY one_review(product_id,user_id),FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS reports(id INT AUTO_INCREMENT PRIMARY KEY,reporter_id INT NOT NULL,target_type VARCHAR(30),target_id INT,reason TEXT,status VARCHAR(30) DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB"
  ];
  foreach($sql as $q)$pdo->exec($q);

  $roleType=$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch()['Type'] ?? '';
  if(stripos($roleType,"'moderator'")===false) $pdo->exec("ALTER TABLE users MODIFY role ENUM('buyer','moderator','admin','seller') NOT NULL DEFAULT 'buyer'");

  $productCols=$pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN,0);
  if(!in_array('compare_price',$productCols,true)) $pdo->exec("ALTER TABLE products ADD compare_price DECIMAL(12,2) DEFAULT NULL AFTER price");

  $orderCols=$pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN,0);
  foreach([
    'courier_status'=>"ALTER TABLE orders ADD courier_status VARCHAR(40) DEFAULT 'Not sent'",
    'courier_name'=>"ALTER TABLE orders ADD courier_name VARCHAR(100) NULL",
    'confirmed_at'=>"ALTER TABLE orders ADD confirmed_at DATETIME NULL",
    'voucher_sent_at'=>"ALTER TABLE orders ADD voucher_sent_at DATETIME NULL"
  ] as $col=>$q) if(!in_array($col,$orderCols,true)) $pdo->exec($q);

  // Legacy seller accounts are internal moderators. Buyers never see a seller identity.
  $pdo->exec("UPDATE users SET role='moderator' WHERE role='seller'");

  $hash=password_hash('admin123',PASSWORD_DEFAULT);
  $s=$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES('AZARO Admin','admin@azaro.local',?,'admin') ON DUPLICATE KEY UPDATE name=VALUES(name),role='admin'");
  $s->execute([$hash]);

  $moderator=$pdo->query("SELECT id FROM users WHERE role='moderator' LIMIT 1")->fetch();
  if(!$moderator){
    $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES('AZARO Moderator','moderator@azaro.local',?,'moderator')")->execute([password_hash('moderator123',PASSWORD_DEFAULT)]);
    $mid=(int)$pdo->lastInsertId();
  }else $mid=(int)$moderator['id'];

  $pdo->prepare("INSERT INTO sellers(user_id) VALUES(?) ON DUPLICATE KEY UPDATE user_id=user_id")->execute([$mid]);
  $pdo->prepare("UPDATE products SET seller_id=? WHERE seller_id NOT IN (SELECT id FROM users)")->execute([$mid]);

  $cats=['Shirts','Pants','Trousers','Combo','New Arrivals','Essentials'];
  $ins=$pdo->prepare("INSERT INTO categories(title) SELECT ? WHERE NOT EXISTS(SELECT 1 FROM categories WHERE title=?)");
  foreach($cats as $cat)$ins->execute([$cat,$cat]);

  $count=$pdo->query("SELECT COUNT(*) c FROM products")->fetch()['c'];
  if(!$count){
    $ids=[];
    foreach($cats as $cat){$q=$pdo->prepare("SELECT id FROM categories WHERE title=? LIMIT 1");$q->execute([$cat]);$ids[$cat]=(int)$q->fetch()['id'];}
    $p=$pdo->prepare("INSERT INTO products(name,content,price,compare_price,stock,delivery_kind,category_id,seller_id,image,status) VALUES(?,?,?,?,?,?,?,?,?,'active')");
    $p->execute(['Classic Oxford Shirt','A clean everyday shirt with a refined silhouette, soft hand-feel and easy styling for work or weekends.',1890,2290,30,'Home Delivery',$ids['Shirts'],$mid,'assets/product-placeholder.svg']);
    $p->execute(['Relaxed Fit Cotton Pant','Comfort-first cotton trousers with a versatile straight fit for everyday movement.',2190,2590,25,'Home Delivery',$ids['Pants'],$mid,'assets/product-placeholder.svg']);
    $p->execute(['Tailored Essential Trouser','A polished trouser with a clean line and understated finish for a smarter wardrobe.',2490,2990,20,'Home Delivery',$ids['Trousers'],$mid,'assets/product-placeholder.svg']);
    $p->execute(['Everyday Style Combo','A coordinated shirt and trouser pairing designed to make getting dressed effortless.',3990,4690,15,'Home Delivery',$ids['Combo'],$mid,'assets/product-placeholder.svg']);
  }
  $ok=true;$messages[]='AZARO database is ready. Fashion categories, admin/moderator roles and discount support are configured.';
}catch(Throwable $e){$messages[]='Setup error: '.$e->getMessage();}
?><!doctype html><html><head><meta charset="utf-8"><title>AZARO Setup</title><link rel="stylesheet" href="assets/style.css"></head><body><section class="page"><div class="panel auth"><div class="eyebrow">AZARO • OWN YOUR STYLE</div><h1>System Setup</h1><?php foreach($messages as $m):?><div class="notice"><?=htmlspecialchars($m)?></div><?php endforeach;?><?php if($ok):?><p><b>Admin:</b> admin@azaro.local / admin123</p><p><b>Moderator:</b> moderator@azaro.local / moderator123</p><a class="btn btn-primary" href="index.php">Open AZARO</a><?php endif;?></div></section></body></html>

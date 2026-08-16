<?php
require_once __DIR__.'/functions.php';$pdo=db();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$s=$pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");$s->execute([trim($_POST['email']??'')]);$u=$s->fetch();
 if($u&&password_verify($_POST['password']??'',$u['password'])){unset($u['password']);$_SESSION['user']=$u;flash('success','Welcome back, '.$u['name'].'!');if(in_array($u['role'],['admin','moderator'],true))redirect('admin.php');redirect('index.php');}else $error='Invalid email or password.';
}
$title='Login';include __DIR__.'/partials/header.php';?>
<section class="page"><div class="panel auth"><div class="eyebrow">AZARO — OWN YOUR STYLE</div><h1>Welcome back</h1><p class="muted">Sign in to manage your account, orders and shopping cart.</p><?php if($error):?><div class="flash error" style="position:static;margin:10px 0"><?=e($error)?></div><?php endif;?><form method="post" class="form-grid"><?=csrf_field()?><div class="field full"><label>Email</label><input name="email" type="email" required></div><div class="field full"><label>Password</label><input name="password" type="password" required></div><div class="field full"><button class="btn btn-primary">Login</button></div></form><p>New to AZARO? <a href="<?=BASE_URL?>/register.php" style="color:var(--blue)">Create an account</a></p></div></section><?php include __DIR__.'/partials/footer.php';?>

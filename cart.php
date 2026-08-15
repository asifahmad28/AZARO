<?php
require_once __DIR__ . '/functions.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    // ADD TO CART
    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if ($productId > 0) {
            $stmt = $pdo->prepare(
                "SELECT id, stock FROM products WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                flash('error', 'Product not found.');
            } elseif ((int)$product['stock'] <= 0) {
                flash('error', 'This product is out of stock.');
            } else {
                $currentQty = (int)(cart()[$productId] ?? 0);
                $newQty = min(
                    $currentQty + $quantity,
                    (int)$product['stock']
                );

                // cart_update() expects an array.
                cart_update([
                    $productId => $newQty
                ]);

                flash('success', 'Product added to cart.');
            }
        }

        redirect('cart.php');
    }

    // BUY NOW: add one product and go straight to checkout.
    if ($action === 'buy_now') {
        require_role('buyer');
        $productId=(int)($_POST['product_id']??0);
        $quantity=max(1,(int)($_POST['quantity']??1));
        $stmt=$pdo->prepare("SELECT id,stock FROM products WHERE id=? AND status='active' LIMIT 1");
        $stmt->execute([$productId]);$product=$stmt->fetch();
        if(!$product || (int)$product['stock']<=0){flash('error','This product is out of stock.');redirect('product.php?id='.$productId);}
        cart_update([$productId=>min($quantity,(int)$product['stock'])]);
        redirect('checkout.php');
    }

    // UPDATE CART
    if ($action === 'update') {
        $quantities = $_POST['qty'] ?? [];

        if (is_array($quantities)) {
            foreach ($quantities as $productId => $quantity) {
                $productId = (int)$productId;
                $quantity = (int)$quantity;

                if ($productId <= 0) {
                    continue;
                }

                $stmt = $pdo->prepare(
                    "SELECT stock FROM products WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if (!$product || $quantity <= 0) {
                    cart_remove($productId);
                    continue;
                }

                $quantity = min($quantity, (int)$product['stock']);

                cart_update([
                    $productId => $quantity
                ]);
            }
        }

        flash('success', 'Cart updated successfully.');
        redirect('cart.php');
    }

    // REMOVE ONE PRODUCT
    if ($action === 'remove') {
        $productId = (int)($_POST['product_id'] ?? 0);

        if ($productId > 0) {
            cart_remove($productId);
        }

        flash('success', 'Product removed from cart.');
        redirect('cart.php');
    }

    // CLEAR CART
    if ($action === 'clear') {
        cart_clear();

        flash('success', 'Cart cleared.');
        redirect('cart.php');
    }
}


// LOAD CART ITEMS
$cartItems = cart();
$ids = array_keys($cartItems);

$items = [];
$total = 0;

if ($ids) {
    $ids = array_map('intval', $ids);
    $in = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT * FROM products
         WHERE id IN ($in)
         ORDER BY id DESC"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $product) {
        $id = (int)$product['id'];
        $qty = (int)($cartItems[$id] ?? 0);
        $stock = (int)$product['stock'];

        if ($qty <= 0 || $stock <= 0) {
            cart_remove($id);
            continue;
        }

        // Never allow cart quantity above current stock.
        if ($qty > $stock) {
            $qty = $stock;

            cart_update([
                $id => $qty
            ]);
        }

        $product['cart_qty'] = $qty;
        $product['line_total'] = $qty * (float)$product['price'];

        $total += $product['line_total'];
        $items[] = $product;
    }
}

$title = 'Cart';
include __DIR__ . '/partials/header.php';
?>

<section class="page">
    <div class="container">

        <div class="section-head">
            <div>
                <h1>Your Cart</h1>
                <p class="muted">
                    <?= count($items) ?> product(s) in your cart
                </p>
            </div>

            <a href="<?= BASE_URL ?>/products.php"
               class="btn btn-light">
                Continue Shopping
            </a>
        </div>

        <div class="panel">

            <?php if (!$items): ?>

                <div style="text-align:center;padding:50px 20px">
                    <h2>Your cart is empty.</h2>

                    <p class="muted">
                        Add something you like from the marketplace.
                    </p>

                    <a href="<?= BASE_URL ?>/products.php"
                       class="btn btn-primary">
                        Browse Products
                    </a>
                </div>

            <?php else: ?>

                <form method="post">
                    <?= csrf_field() ?>

                    <div class="table-wrap">
                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($items as $p): ?>

                                <tr>

                                    <td>
                                        <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id'] ?>">
                                            <b><?= e($p['name']) ?></b>
                                        </a>
                                    </td>

                                    <td>
                                        <?= money($p['price']) ?>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            name="qty[<?= (int)$p['id'] ?>]"
                                            min="0"
                                            max="<?= (int)$p['stock'] ?>"
                                            value="<?= (int)$p['cart_qty'] ?>"
                                            style="width:80px;padding:8px"
                                        >
                                    </td>

                                    <td>
                                        <b><?= money($p['line_total']) ?></b>
                                    </td>

                                    <td>
                                        <button
                                            type="submit"
                                            name="action"
                                            value="remove"
                                            class="btn btn-light"
                                            onclick="this.form.product_id.value='<?= (int)$p['id'] ?>';"
                                        >
                                            Remove
                                        </button>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- Hidden product id used by Remove button -->
                    <input
                        type="hidden"
                        name="product_id"
                        id="cart_product_id"
                        value=""
                    >

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-top:20px;
                            gap:20px;
                            flex-wrap:wrap
                        "
                    >

                        <div>
                            <strong>Grand Total:</strong>
                            <strong><?= money($total) ?></strong>
                        </div>

                        <div
                            style="
                                display:flex;
                                gap:10px;
                                flex-wrap:wrap
                            "
                        >

                            <button
                                type="submit"
                                name="action"
                                value="update"
                                class="btn btn-light"
                            >
                                Update Cart
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="clear"
                                class="btn btn-light"
                                onclick="
                                    return confirm(
                                        'Are you sure you want to clear the cart?'
                                    );
                                "
                            >
                                Clear Cart
                            </button>

                            <a
                                class="btn btn-primary"
                                href="<?= BASE_URL ?>/checkout.php"
                            >
                                Checkout
                            </a>

                        </div>
                    </div>

                </form>

            <?php endif; ?>

        </div>
    </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Placed</title>
  <link rel="stylesheet" href="theme.css">
  <link rel="stylesheet" href="orderplaced.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
</head>
<body>
  <div class="order-success">
    <div id="orderSuccess" style="width:200px;height:200px;margin:auto;"></div>
    <h2>Thank you! Your order has been placed.</h2>
    <p>You can track your order in the <a href="track_orders.php">My Orders</a> section.</p>
    <a href="products.php" class="btn">Continue Shopping</a>
  </div>
  <script>
  lottie.loadAnimation({
    container: document.getElementById('orderSuccess'),
    renderer: 'svg',
    loop: false, // play once
    autoplay: true,
    path: 'https://assets10.lottiefiles.com/packages/lf20_jbrw3hcz.json' // Success animation JSON link
  });
</script>
</body>
</html>

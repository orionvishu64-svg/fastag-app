<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Placed Successfully</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

  <style>
    body.orderplaced-page {
      min-height: 100vh;
      background: linear-gradient(180deg, #f5f7fb, #eef2ff);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .success-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 40px 30px;
      max-width: 460px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,0.08);
      animation: fadeUp .4s ease;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .success-title {
      font-weight: 800;
      margin-top: 12px;
    }

    .success-text {
      color: #6c757d;
      font-size: 15px;
      margin-top: 6px;
    }

    .success-actions .btn {
      border-radius: 10px;
      padding: 12px 16px;
      font-weight: 600;
    }

    .btn-primary {
      box-shadow: 0 10px 24px rgba(13,110,253,0.25);
    }

    .btn-outline-secondary:hover {
      background: #f1f3f5;
    }
  </style>
</head>

<body class="orderplaced-page">

  <audio id="order-sound" src="/public/sounds/order-success.mp3" preload="auto"></audio>

  <div class="success-card">

    <div id="orderSuccess" style="width:200px;height:200px;margin:auto;"></div>

    <h2 class="success-title text-success">
      Order Placed Successfully!
    </h2>

    <p class="success-text">
      Thank you for your order. You can track your shipment and order status anytime from your account.
    </p>

    <div class="success-actions d-grid gap-3 mt-4">
      <a href="track_orders.php" class="btn btn-primary">
        <i class="fa-solid fa-box me-2"></i>
        My Orders
      </a>

      <a href="products.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-cart-shopping me-2"></i>
        Continue Shopping
      </a>
    </div>
  </div>
  <script>
    const sound = document.getElementById("order-sound");
    function playSuccessSound() {
      if (!sound) return;
      const p = sound.play();
      if (p) {
        p.catch(() => {
          document.body.addEventListener('click', () => sound.play(), { once: true });
        });
      }
    }
    const animation = lottie.loadAnimation({
      container: document.getElementById('orderSuccess'),
      renderer: 'svg',
      loop: false,
      autoplay: true,
      path: 'https://assets10.lottiefiles.com/packages/lf20_jbrw3hcz.json'
    });

    animation.addEventListener("DOMLoaded", playSuccessSound);
  </script>
</body>
</html>
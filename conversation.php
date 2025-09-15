<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Conversations</title>
  <link rel="stylesheet" href="conversation.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
  <header>
    <div class="header-left">
      <a href="contact.php" class="back-link">⬅ Back</a>
      <h2>🗨️ Your Conversations</h2>
    </div>
  </header>

  <main id="conversationArea">
    <div id="closedTicketsContainer"></div>
    <div id="openTicketContainer"></div>
  </main>

  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script src="script.js"></script>
  <script src="conversation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

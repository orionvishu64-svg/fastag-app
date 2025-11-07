<?php
require_once __DIR__ . '/config/db.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    echo "Post not found.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = :slug AND is_published = 1 LIMIT 1");
$stmt->execute([':slug' => $slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo "Post not found.";
    exit;
}

$title = htmlspecialchars($post['title']);
$img = $post['image_url'] ? htmlspecialchars($post['image_url']) : 'assets/img/default-thumb.jpg';
$created = date('F j, Y', strtotime($post['created_at']));
$author = htmlspecialchars($post['author'] ?? '');
$tag = htmlspecialchars($post['tag'] ?? '');
$read_time = htmlspecialchars($post['read_time'] ?? '1 min');
$content = $post['content']; // assume admin inserts safe HTML

// Fetch related posts (by exact tag match) — this may return empty array
$related = [];
if (!empty($post['tag'])) {
    try {
        $rStmt = $pdo->prepare("
            SELECT id, title, slug, excerpt, image_url, author, read_time, tag
            FROM blog_posts
            WHERE is_published = 1
              AND tag = :tag
              AND id != :id
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $rStmt->execute([':tag' => $post['tag'], ':id' => $post['id']]);
        $related = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // optional: log the error instead of exposing it
        error_log("Related posts query failed: " . $e->getMessage());
        $related = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $title; ?> - FASTag Blog</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/blog.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<a href="blog.php" class="btn btn-back">Go Back</a>

<main class="single-post">
  <div class="container">
    <article class="single-article">
      <header>
        <h1><?php echo $title; ?></h1>
        <div class="meta">
          <span><?php echo $created; ?></span>
          <?php if ($author): ?><span>By <?php echo $author; ?></span><?php endif; ?>
          <?php if ($tag): ?><span class="category"><?php echo $tag; ?></span><?php endif; ?>
          <span class="read-time"><?php echo $read_time; ?></span>
        </div>
        <div class="single-thumb">
          <img loading="lazy" src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
        </div>
      </header>

      <div class="single-content">
        <?php
        // OUTPUT POST BODY: if content is trusted HTML this is fine.
        // If you cannot guarantee it, sanitize with HTMLPurifier before echoing.
        echo $content;
        ?>
      </div>

      <div class="post-actions">
  <a class="share-btn" data-share="twitter" title="Share on Twitter">
    <i class="fab fa-twitter"></i>
  </a>
  <a class="share-btn" data-share="fb" title="Share on Facebook">
    <i class="fab fa-facebook"></i>
  </a>
  <a class="share-btn" data-share="wa" title="Share on WhatsApp">
    <i class="fab fa-whatsapp"></i>
  </a>
</div>

    </article>
  </div>
</main>

<?php if (!empty($related)): ?>
<section class="related-wrap">
  <div class="container">
    <h3 class="related-title">Related posts</h3>
    <div class="related-grid">
      <?php foreach ($related as $rp):
          $rtitle = htmlspecialchars($rp['title']);
          $rexcerpt = htmlspecialchars($rp['excerpt']);
          $rslug = htmlspecialchars($rp['slug']);
          $rimg = $rp['image_url'] ? htmlspecialchars($rp['image_url']) : 'assets/img/default-thumb.jpg';
          $rauthor = htmlspecialchars($rp['author'] ?? '');
          $rtime = htmlspecialchars($rp['read_time'] ?? '1 min');
      ?>
        <article class="post-card">
          <a class="post-link" href="post.php?slug=<?php echo urlencode($rslug); ?>">
            <div class="post-image"><img loading="lazy" src="<?php echo $rimg; ?>" alt="<?php echo $rtitle; ?>"></div>
            <div class="post-body">
              <div class="post-meta">
                <?php if ($rp['tag']): ?><span class="category"><?php echo htmlspecialchars($rp['tag']); ?></span><?php endif; ?>
                <span class="read-time"><?php echo $rtime; ?></span>
              </div>
              <h4 class="post-title"><?php echo $rtitle; ?></h4>
              <p class="post-excerpt"><?php echo $rexcerpt; ?></p>
              <?php if ($rauthor): ?><div class="author">By <?php echo $rauthor; ?></div><?php endif; ?>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('click', function(e){
  if(e.target.closest && e.target.closest('.share-btn')){
    const btn = e.target.closest('.share-btn');
    const type = btn.dataset.share;
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.querySelector('h1').innerText || document.title);
    let shareUrl = '';
    if(type === 'twitter') shareUrl = `https://twitter.com/share?url=${url}&text=${title}`;
    if(type === 'fb') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
    if(type === 'wa') shareUrl = `https://wa.me/?text=${title}%20${url}`;
    if(shareUrl) window.open(shareUrl, '_blank', 'noopener');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="/public/js/script.js"></script>
</body>
</html>

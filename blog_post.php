<?php
// fastag_website/blog_post.php
require_once __DIR__ . '/config/db.php';

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('blog_image_url_public')) {
    function blog_image_url_public(?string $url): string {
        $url = trim((string)$url);

        $base = '/superadmin/assets/blog';
        $default = $base . '/default_blog.png';

        if ($url === '') {
            return $default;
        }

        if (preg_match('#^https?://#i', $url) || substr($url, 0, 2) === '//') {
            return $url;
        }

        if (strpos($url, '/uploads/blog/') === 0 || strpos($url, '/assets/blog/') === 0) {
            $file = basename($url);
            return $base . '/' . $file;
        }

        if (strpos($url, '/') === false) {
            return $base . '/' . $url;
        }

        if ($url[0] === '/') {
            return $url;
        }

        return $default;
    }
}

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    echo "Post not found.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM blog_posts
    WHERE slug = :slug
      AND is_published = 1
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo "Post not found.";
    exit;
}

$title     = h($post['title'] ?? 'Untitled');
$img       = blog_image_url_public($post['image_url'] ?? '');
$created   = !empty($post['created_at']) ? date('F j, Y', strtotime($post['created_at'])) : '';
$author    = h($post['author'] ?? '');
$tag       = h($post['tag'] ?? '');
$read_time = h($post['read_time'] ?? '1 min');
$content   = $post['content'] ?? '';
$related = [];
if (!empty($post['tag'])) {
    try {
        $rStmt = $pdo->prepare("
            SELECT id, title, slug, excerpt, image_url, author, read_time, tag, created_at
            FROM blog_posts
            WHERE is_published = 1
              AND tag = :tag
              AND id != :id
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $rStmt->execute([
            ':tag' => $post['tag'],
            ':id'  => $post['id'],
        ]);
        $related = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Related posts query failed: ' . $e->getMessage());
        $related = [];
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="py-5 bg-light">

  <div class="container">

    <!-- Back button -->
    <div class="mb-4">
      <a href="blog.php" class="btn btn-outline-secondary btn-sm">
        ← Back to Blog
      </a>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- ARTICLE -->
        <article class="card border-0 shadow-sm mb-5">

          <!-- Featured image -->
          <img
            src="<?php echo h($img); ?>"
            class="card-img-top"
            alt="<?php echo $title; ?>"
            loading="lazy"
            style="max-height:420px;object-fit:cover;"
          >

          <div class="card-body p-4 p-lg-5">

            <!-- Meta -->
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-muted">
              <?php if ($created): ?>
                <span><i class="far fa-calendar-alt me-1"></i><?php echo h($created); ?></span>
              <?php endif; ?>

              <?php if ($author): ?>
                <span><i class="far fa-user me-1"></i><?php echo $author; ?></span>
              <?php endif; ?>

              <?php if ($tag): ?>
                <span class="badge bg-warning text-dark"><?php echo $tag; ?></span>
              <?php endif; ?>

              <span><i class="far fa-clock me-1"></i><?php echo $read_time; ?></span>
            </div>

            <!-- Title -->
            <h1 class="fw-bold mb-4"><?php echo $title; ?></h1>

            <!-- Content -->
            <div class="fs-5 lh-lg text-dark">
              <?php echo $content; ?>
            </div>

            <!-- Share -->
            <div class="border-top pt-4 mt-5">
              <div class="d-flex align-items-center gap-3">
                <span class="fw-semibold">Share:</span>

                <a class="btn btn-outline-primary btn-sm share-btn" data-share="twitter">
                  <i class="fab fa-twitter"></i>
                </a>

                <a class="btn btn-outline-primary btn-sm share-btn" data-share="fb">
                  <i class="fab fa-facebook"></i>
                </a>

                <a class="btn btn-outline-success btn-sm share-btn" data-share="wa">
                  <i class="fab fa-whatsapp"></i>
                </a>
              </div>
            </div>

          </div>
        </article>

      </div>
    </div>

  </div>
</main>

<!-- RELATED POSTS -->
<?php if (!empty($related)): ?>
<section class="py-5">
  <div class="container">

    <h3 class="fw-bold mb-4 text-center">Related Posts</h3>

    <div class="row g-4 justify-content-center">

      <?php foreach ($related as $rp):
        $rtitle   = h($rp['title'] ?? '');
        $rexcerpt = h($rp['excerpt'] ?? '');
        $rslug    = h($rp['slug'] ?? ('post-' . (int)$rp['id']));
        $rimg     = blog_image_url_public($rp['image_url'] ?? '');
        $rauthor  = h($rp['author'] ?? '');
        $rtime    = h($rp['read_time'] ?? '1 min');
        $rtag     = h($rp['tag'] ?? '');
      ?>

      <div class="col-lg-4 col-md-6">
        <article class="card h-100 border-0 shadow-sm">

          <a href="blog_post.php?slug=<?php echo urlencode($rslug); ?>"
             class="text-decoration-none text-dark">

            <img
              src="<?php echo h($rimg); ?>"
              class="card-img-top"
              alt="<?php echo $rtitle; ?>"
              loading="lazy"
              style="height:200px;object-fit:cover;"
            >

            <div class="card-body d-flex flex-column">

              <div class="d-flex justify-content-between align-items-center mb-2">
                <?php if ($rtag): ?>
                  <span class="badge bg-warning text-dark"><?php echo $rtag; ?></span>
                <?php endif; ?>
                <small class="text-muted"><?php echo $rtime; ?></small>
              </div>

              <h5 class="fw-bold"><?php echo $rtitle; ?></h5>

              <p class="text-muted small mb-3"><?php echo $rexcerpt; ?></p>

              <?php if ($rauthor): ?>
                <small class="mt-auto text-secondary">
                  By <?php echo $rauthor; ?>
                </small>
              <?php endif; ?>

            </div>
          </a>

        </article>
      </div>

      <?php endforeach; ?>

    </div>
  </div>
</section>
<?php endif; ?>

<!-- SHARE SCRIPT (UNCHANGED LOGIC) -->
<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest && e.target.closest('.share-btn');
  if (!btn) return;

  const type  = btn.dataset.share;
  const url   = encodeURIComponent(window.location.href);
  const title = encodeURIComponent((document.querySelector('h1') || {}).innerText || document.title);
  let shareUrl = '';

  if (type === 'twitter') {
    shareUrl = `https://twitter.com/share?url=${url}&text=${title}`;
  } else if (type === 'fb') {
    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
  } else if (type === 'wa') {
    shareUrl = `https://wa.me/?text=${title}%20${url}`;
  }

  if (shareUrl) {
    window.open(shareUrl, '_blank', 'noopener');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

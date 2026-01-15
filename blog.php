<?php
// fastag_website/blog.php
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

$limit  = 6;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Fetch published posts
$stmt = $pdo->prepare("
    SELECT id, title, slug, excerpt, image_url, author, read_time, tag, created_at
    FROM blog_posts
    WHERE is_published = 1
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count for pagination
$countStmt  = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE is_published = 1");
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<style>
.blog-hero {
  min-height: 340px;
  display: flex;
  align-items: center;
}

.blog-hero-bg {
  position: absolute;
  inset: 0;
  background: url('/uploads/images/blog.jpg') center center / cover no-repeat;
}

.blog-hero-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.55);
}

@media (max-width: 768px) {
  .blog-hero {
    min-height: 240px;
  }
}

.post-card,
.card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  overflow: hidden;
}

.post-card:hover,
.card:hover {
  transform: translateY(-6px);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.12);
}

.post-image img,
.card img {
  transition: transform 0.4s ease;
}

.post-card:hover img,
.card:hover img {
  transform: scale(1.06);
}
</style>
<main>
  <!-- HERO -->
  <section class="blog-hero position-relative overflow-hidden">
    <div class="blog-hero-bg"></div>
    <div class="blog-hero-overlay"></div>

    <div class="container position-relative text-center">
      <h1 class="fw-bold mb-2 text-dark">FASTag Blog</h1>
      <p class="fs-5 text-dark mb-0">
        Latest updates, tutorials, and news about FASTag services
      </p>
    </div>
  </section>

  <!-- BLOG LIST -->
  <section class="py-5">
    <div class="container">

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Latest Articles</h2>
        <span class="text-muted small">
          Showing <?php echo count($posts); ?> of <?php echo $total; ?> posts
        </span>
      </div>

      <?php if (empty($posts)): ?>
        <div class="alert alert-info">
          No posts yet. Please check back soon.
        </div>
      <?php else: ?>

        <div class="row g-4">
          <?php foreach ($posts as $post): ?>
            <?php
              $title     = h($post['title'] ?? '');
              $excerpt   = h($post['excerpt'] ?? '');
              $slug      = h($post['slug'] ?? ('post-' . (int)$post['id']));
              $img       = blog_image_url_public($post['image_url'] ?? '');
              $author    = h($post['author'] ?? '');
              $tag       = h($post['tag'] ?? '');
              $read_time = h($post['read_time'] ?? '1 min');
              $created   = !empty($post['created_at'])
                           ? date('F j, Y', strtotime($post['created_at']))
                           : '';
            ?>

            <div class="col-lg-4 col-md-6">
              <article class="card h-100 border-0 shadow-sm">

                <a href="blog_post.php?slug=<?php echo urlencode($slug); ?>"
                   class="text-decoration-none text-dark">

                  <img
                    src="<?php echo h($img); ?>"
                    class="card-img-top"
                    alt="<?php echo $title; ?>"
                    loading="lazy"
                    style="object-fit: cover; height: 200px;"
                  >

                  <div class="card-body d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <?php if ($tag): ?>
                        <span class="badge bg-warning text-dark">
                          <?php echo $tag; ?>
                        </span>
                      <?php endif; ?>
                      <small class="text-muted"><?php echo $read_time; ?></small>
                    </div>

                    <h5 class="fw-bold mb-2">
                      <?php echo $title; ?>
                    </h5>

                    <?php if ($created): ?>
                      <small class="text-muted mb-2 d-block">
                        <?php echo h($created); ?>
                      </small>
                    <?php endif; ?>

                    <p class="text-muted mb-3">
                      <?php echo $excerpt; ?>
                    </p>

                    <?php if ($author): ?>
                      <small class="mt-auto text-secondary">
                        By <?php echo $author; ?>
                      </small>
                    <?php endif; ?>

                  </div>
                </a>

              </article>
            </div>

          <?php endforeach; ?>
        </div>

      <?php endif; ?>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
          <ul class="pagination justify-content-center">

            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
              <a class="page-link"
                 href="?page=<?php echo max(1, $page - 1); ?>">
                &laquo; Previous
              </a>
            </li>

            <li class="page-item disabled">
              <span class="page-link">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
              </span>
            </li>

            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
              <a class="page-link"
                 href="?page=<?php echo min($totalPages, $page + 1); ?>">
                Next &raquo;
              </a>
            </li>

          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
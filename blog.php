<?php
// fastag_website/blog.php
require_once __DIR__ . '/config/db.php'; // must define $pdo

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

$limit  = 9;
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASTag Blog</title>
    <link rel="stylesheet" href="/public/css/styles.css">
    <link rel="stylesheet" href="/public/css/blog.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
  <section class="hero">
    <div class="container">
      <h1>FASTag Blog</h1>
      <p>Latest updates, tutorials and news</p>
    </div>
  </section>

  <section class="featured-posts">
    <div class="container">
      <h2>Latest Articles</h2>
      <div class="posts-grid">
        <?php if (empty($posts)): ?>
          <p>No posts yet. Check back soon.</p>
        <?php else: ?>
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
            <article class="post-card">
              <a class="post-link" href="blog_post.php?slug=<?php echo urlencode($slug); ?>">
                <div class="post-image">
                  <img loading="lazy" src="<?php echo h($img); ?>" alt="<?php echo $title; ?>">
                </div>
                <div class="post-body">
                  <div class="post-meta">
                    <?php if ($tag): ?>
                      <span class="category"><?php echo $tag; ?></span>
                    <?php endif; ?>
                    <span class="read-time"><?php echo $read_time; ?></span>
                  </div>

                  <h3 class="post-title"><?php echo $title; ?></h3>

                  <?php if ($created): ?>
                    <p class="post-date"><?php echo h($created); ?></p>
                  <?php endif; ?>

                  <p class="post-excerpt"><?php echo $excerpt; ?></p>

                  <?php if ($author): ?>
                    <p class="author">By <?php echo $author; ?></p>
                  <?php endif; ?>
                </div>
              </a>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
        <?php endif; ?>

        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

        <?php if ($page < $totalPages): ?>
          <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="/public/js/script.js"></script>
</body>
</html>
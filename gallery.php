<?php
require_once __DIR__ . '/config/database.php';

$gallery_images = [];
if ($conn) {
    $res = mysqli_query($conn, "SELECT * FROM gallery_images WHERE is_published = 1 ORDER BY display_order ASC, id DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $gallery_images[] = $r;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gallery — Brightway School</title>
  <meta name="description" content="Photos of our campus, classrooms, activities and events.">
  <meta name="theme-color" content="#0c1a26">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body data-page="gallery">
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main">
    <section class="page-hero">
      <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
      <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
      <div class="decor decor--book" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-bookopen"/></svg></div>
      <div class="decor decor--triangle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-triangle"/></svg></div>
      <div class="container page-hero__inner">
        <h1>School gallery</h1>
        <p>A look at campus, classrooms, activities and celebrations.</p>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="filters" role="group" aria-label="Filter gallery by category" data-reveal>
          <button class="filter" type="button" data-filter="all" aria-pressed="true">All</button>
          <button class="filter" type="button" data-filter="campus" aria-pressed="false">Campus</button>
          <button class="filter" type="button" data-filter="activities" aria-pressed="false">Activities</button>
          <button class="filter" type="button" data-filter="events" aria-pressed="false">Events</button>
          <button class="filter" type="button" data-filter="classrooms" aria-pressed="false">Classrooms</button>
        </div>
        <div class="gallery" id="gallery">
          <?php if (!empty($gallery_images)): ?>
            <?php foreach ($gallery_images as $img): 
              $path = htmlspecialchars($img['image_path']);
              $title = htmlspecialchars($img['title']);
              $alt = htmlspecialchars($img['alt_text'] ?: $img['title']);
              $cat = htmlspecialchars($img['category']);
            ?>
              <button class="gallery__item" type="button" data-category="<?php echo $cat; ?>">
                <img src="<?php echo $path; ?>" alt="<?php echo $alt; ?>" width="800" height="600" loading="lazy">
                <figcaption><?php echo $title; ?></figcaption>
              </button>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; padding: 48px; color: var(--lead, #7b8a99);">No gallery photos available yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <section class="section section--paper">
      <div class="container">
        <div class="cta-band" data-reveal>
          <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
          <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
          <h2>Want to see it in person?</h2>
          <p>Book a school visit and meet the people behind the pictures.</p>
          <div class="btn-row">
            <a class="btn btn--mint" href="admissions.php">Apply for admission</a>
            <a class="btn btn--line" href="contact.php">Contact us</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="assets/js/main.js" defer></script>
  <script src="assets/js/gallery.js" defer></script>
</body>
</html>

<?php
require_once __DIR__ . '/config/database.php';

$web_images = [];
if ($conn) {
    $res = mysqli_query($conn, "SELECT image_key, image_path, fallback_path, alt_text FROM website_images");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $has_file = !empty($r['image_path']) && file_exists(__DIR__ . '/' . $r['image_path']);
            $web_images[$r['image_key']] = [
                'src' => $has_file ? $r['image_path'] : $r['fallback_path'],
                'alt' => $r['alt_text']
            ];
        }
    }
}
function getWebImg($key, $default_src, $default_alt = '') {
    global $web_images;
    if (isset($web_images[$key])) {
        return $web_images[$key];
    }
    return ['src' => $default_src, 'alt' => $default_alt];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Academics — Brightway School</title>
  <meta name="description" content="Academic levels, subjects and our teaching approach.">
  <meta name="theme-color" content="#0c1a26">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body data-page="academics">
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main">
    <section class="page-hero">
      <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
      <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
      <div class="decor decor--book" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-bookopen"/></svg></div>
      <div class="decor decor--triangle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-triangle"/></svg></div>
      <div class="container page-hero__inner">
        <h1>Academic programs</h1>
        <p>A clear path from the first day of school to graduation, with room to explore.</p>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">Academic levels</h2>
          <p class="lead">Replace the grade ranges and descriptions with your own.</p>
        </div>
        <div class="levels" data-reveal>
          <?php 
            $p_ey = getWebImg('program_early_years', 'assets/images/gallery/classroom-1.svg', 'Early Years');
            $p_pr = getWebImg('program_primary', 'assets/images/gallery/activity-1.svg', 'Primary');
            $p_md = getWebImg('program_middle', 'assets/images/gallery/classroom-2.svg', 'Middle School');
            $p_sc = getWebImg('program_secondary', 'assets/images/gallery/campus-2.svg', 'Secondary');
          ?>
          <article class="level">
            <div class="level__arch" style="--bg:#ffe3ec"><img src="<?php echo htmlspecialchars($p_ey['src']); ?>" alt="<?php echo htmlspecialchars($p_ey['alt']); ?>" loading="lazy"></div>
            <h3>Early Years</h3>
            <p><span class="ph">[Add age or grade range]</span><br>Play-based learning that builds confidence and curiosity.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#e4fbe4"><img src="<?php echo htmlspecialchars($p_pr['src']); ?>" alt="<?php echo htmlspecialchars($p_pr['alt']); ?>" loading="lazy"></div>
            <h3>Primary</h3>
            <p><span class="ph">[Add grade range]</span><br>Core literacy and numeracy with a love of discovery.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#eee6ff"><img src="<?php echo htmlspecialchars($p_md['src']); ?>" alt="<?php echo htmlspecialchars($p_md['alt']); ?>" loading="lazy"></div>
            <h3>Middle School</h3>
            <p><span class="ph">[Add grade range]</span><br>Wider subjects and growing responsibility.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#fff1c9"><img src="<?php echo htmlspecialchars($p_sc['src']); ?>" alt="<?php echo htmlspecialchars($p_sc['alt']); ?>" loading="lazy"></div>
            <h3>Secondary</h3>
            <p><span class="ph">[Add grade range]</span><br>Focused preparation for exams and future studies.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section section--paper" id="subjects">
      <div class="container">
        <div class="section-head" data-reveal>
          <h2 class="title">Subjects and learning areas</h2>
          <p class="lead">List the subjects you actually offer at each level.</p>
        </div>
        <ul class="subjects" data-reveal><li class="subject"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-math"/></svg></i><div>Mathematics<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-book"/></svg></i><div>English<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></i><div>Science<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-code"/></svg></i><div>Computer Studies<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-globe"/></svg></i><div>Social Studies<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-palette"/></svg></i><div>Art & Design<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-ball"/></svg></i><div>Physical Education<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-music"/></svg></i><div>Music<small class="ph">[Add grades]</small></div></li><li class="subject"><i style="--c:#fff1c9"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-leaf"/></svg></i><div>Moral & Life Skills<small class="ph">[Add grades]</small></div></li></ul>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">How we teach</h2>
          <p class="lead">Describe your teaching approach in a few clear principles.</p>
        </div>
        <div class="cards-3" data-reveal>
          <article class="card bg-lilac"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-pencil"/></svg></span><h3>Learn by doing</h3><p>Projects, experiments and discussion keep lessons active.</p></article>
          <article class="card bg-mint"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-check"/></svg></span><h3>Clear, regular feedback</h3><p>Students know where they are and what to work on next.</p></article>
          <article class="card bg-pink"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-bulb"/></svg></span><h3>Think and reflect</h3><p>Questions and reflection build deeper understanding.</p></article>
        </div>
      </div>
    </section>

    <section class="section section--paper">
      <div class="container">
        <div class="facilities" style="margin-top:0" data-reveal>
          <div>
            <h2 class="title" style="font-size:1.9rem">Facilities</h2>
            <p class="lead" style="margin:0">Learning spaces that support every subject.</p>
          </div>
          <ul class="chips"><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-book"/></svg></i>Library</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></i>Science lab</li><li class="chip"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-laptop"/></svg></i>Computer lab</li><li class="chip"><i style="--c:#fff1c9"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-ball"/></svg></i>Sports ground</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-palette"/></svg></i>Art room</li><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-building"/></svg></i>Assembly hall</li></ul>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">Beyond the classroom</h2>
          <p class="lead">Extracurricular activities. Edit to match what you offer.</p>
        </div>
        <div class="cards-2" data-reveal>
          <article class="card bg-sun"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-trophy"/></svg></span><h3>Sports and fitness</h3><p>Team games, athletics and everyday movement.</p></article>
          <article class="card bg-pink"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-music"/></svg></span><h3>Arts and music</h3><p>Drawing, performance and creative expression.</p></article>
          <article class="card bg-mint"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></span><h3>Clubs and competitions</h3><p>Science, debate, reading and more.</p></article>
          <article class="card bg-lilac"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-heart"/></svg></span><h3>Community service</h3><p>Opportunities to give back and lead.</p></article>
        </div>
      </div>
    </section>
    <section class="section section--paper">
      <div class="container">
        <div class="cta-band" data-reveal>
          <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
          <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
          <h2>Admissions are open. Take the first step.</h2>
          <p>Tell us a little about your child and we will get back to you with the next steps.</p>
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
</body>
</html>

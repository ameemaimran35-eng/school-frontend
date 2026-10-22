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
  <title>About Brightway School</title>
  <meta name="description" content="Learn about our mission, values, facilities and staff.">
  <meta name="theme-color" content="#0c1a26">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body data-page="about">
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main">
    <section class="page-hero">
      <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
      <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
      <div class="decor decor--book" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-bookopen"/></svg></div>
      <div class="decor decor--triangle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-triangle"/></svg></div>
      <div class="container page-hero__inner">
        <h1>About our school</h1>
        <p>Our story, our values and the people who make school feel like a community.</p>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <?php $abt_m = getWebImg('about_page_main', 'assets/images/about-teacher.svg', 'Teacher'); ?>
        <div class="photo-arch" style="--bg:#1ea7ec" data-reveal><img src="<?php echo htmlspecialchars($abt_m['src']); ?>" alt="<?php echo htmlspecialchars($abt_m['alt']); ?>" loading="lazy"></div>
        <div data-reveal style="--d:.1s">
          <span class="eyebrow">Who we are</span>
          <h2 class="title">A community built around curious learners.</h2>
          <p class="lead">Introduce your school here: when and why it was founded, who it serves, and what a typical day feels like. This paragraph is placeholder text.</p>
          <p class="lead">Add a second paragraph about your teaching philosophy or your community.</p>
          <a class="btn btn--dark" href="admissions.php">Start admission</a>
        </div>
      </div>
    </section>

    <section class="section section--paper">
      <div class="container">
        <div class="cards-2" data-reveal>
          <article class="card bg-lilac"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-bulb"/></svg></span><h3>Our mission</h3><p>Write your mission statement here in one or two clear sentences. It should describe what the school does and for whom.</p></article>
          <article class="card bg-mint"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-globe"/></svg></span><h3>Our vision</h3><p>Write your vision here: the future you hope your students will build and the kind of people they will become.</p></article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">Values and educational approach</h2>
          <p class="lead">Edit these to match your school’s real principles.</p>
        </div>
        <div class="cards-3" data-reveal>
          <article class="card bg-pink"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-heart"/></svg></span><h3>Respect and care</h3><p>Every student is valued, listened to and supported.</p></article>
          <article class="card bg-sun"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-pencil"/></svg></span><h3>Curiosity and effort</h3><p>Asking questions and working steadily are celebrated.</p></article>
          <article class="card bg-lilac"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-users"/></svg></span><h3>Teamwork and service</h3><p>Students learn to collaborate and contribute to their community.</p></article>
        </div>
      </div>
    </section>

    <section class="section section--paper">
      <div class="container split split--flip">
        <?php $abt_c = getWebImg('about_page_campus', 'assets/images/gallery/campus-2.svg', 'School campus'); ?>
        <div class="photo-rect" data-reveal><img src="<?php echo htmlspecialchars($abt_c['src']); ?>" alt="<?php echo htmlspecialchars($abt_c['alt']); ?>" loading="lazy"></div>
        <div data-reveal style="--d:.1s">
          <h2 class="title">Facilities and school environment</h2>
          <p class="lead">Describe your campus: classrooms, outdoor spaces, safety measures and anything that makes it welcoming.</p>
          <ul class="chips" style="margin-top:1.4rem"><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-book"/></svg></i>Library</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></i>Science lab</li><li class="chip"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-laptop"/></svg></i>Computer lab</li><li class="chip"><i style="--c:#fff1c9"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-ball"/></svg></i>Sports ground</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-palette"/></svg></i>Art room</li><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-building"/></svg></i>Assembly hall</li></ul>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">Meet our staff</h2>
          <p class="lead">Placeholder profiles. Replace the names, roles and photos.</p>
        </div>
        <div class="staff" data-reveal>
          <div class="person"><div class="person__photo" style="--bg:var(--lilac-soft)"><img src="assets/images/teacher.svg" alt="Staff portrait placeholder"></div><h3 class="ph">[Principal name]</h3><p>Principal</p></div>
          <div class="person"><div class="person__photo" style="--bg:var(--mint-soft)"><img src="assets/images/student-2.svg" alt="Staff portrait placeholder"></div><h3 class="ph">[Name]</h3><p>Head of Academics</p></div>
          <div class="person"><div class="person__photo" style="--bg:var(--pink-soft)"><img src="assets/images/student-5.svg" alt="Staff portrait placeholder"></div><h3 class="ph">[Name]</h3><p>Head of Student Life</p></div>
          <div class="person"><div class="person__photo" style="--bg:var(--sun-soft)"><img src="assets/images/student-3.svg" alt="Staff portrait placeholder"></div><h3 class="ph">[Name]</h3><p>Admissions Officer</p></div>
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

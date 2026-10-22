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
  <title>Brightway School — Where curious minds grow</title>
  <meta name="description" content="Brightway School: a caring, forward-looking school. Replace this description with your school's own.">
  <meta name="theme-color" content="#0c1a26">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body data-page="home">
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main">

    <!-- 2. HERO -->
    <section class="hero">
      <div class="hero__bg" aria-hidden="true">
        <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
        <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
        <div class="decor decor--book" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-bookopen"/></svg></div>
        <div class="decor decor--circle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-circle"/></svg></div>
        <div class="decor decor--triangle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-triangle"/></svg></div>
        <div class="decor decor--pencil" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-pencil"/></svg></div>
      </div>
      <div class="container hero__inner">
        <h1>Where curious minds grow into confident futures.</h1>
        <p class="hero__sub">A caring, forward-looking school for <strong>Early Years to Secondary</strong>. Where every student grows into their best self.</p>
        <div class="hero__actions">
          <a class="btn btn--mint" href="admissions.php">Apply for admission</a>
          <a class="btn btn--mint js-login-modal-trigger" href="admin/login.php" onclick="if(window.openLoginPortalModal){return window.openLoginPortalModal(event);}">Login</a>
          <a class="play-link" href="gallery.php"><span class="play-link__disc"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-play"/></svg></span>Take a campus tour</a>
        </div>
        <div class="hero__badge">
          <span>Admissions open for <strong>2026 – 2027</strong></span>
          <div class="avatars" aria-hidden="true">
            <span style="background-image:url(assets/images/student-1.svg);background-color:#bba8e3"></span>
            <span style="background-image:url(assets/images/student-2.svg);background-color:#e4fbe4"></span>
            <span style="background-image:url(assets/images/student-3.svg);background-color:#ffc6dc"></span>
            <span style="background-image:url(assets/images/student-4.svg);background-color:#fff1c9"></span>
            <span style="background-image:url(assets/images/student-5.svg);background-color:#a9f7a3"></span>
          </div>
        </div>
      </div>
      <div class="hero__arches">
        <?php 
          $h1 = getWebImg('hero_arch_1', 'assets/images/student-1.svg', 'Student holding a folder');
          $h2 = getWebImg('hero_arch_2', 'assets/images/student-2.svg', 'Student giving a thumbs up');
          $h3 = getWebImg('hero_arch_3', 'assets/images/student-3.svg', 'Student with headphones and a notebook');
        ?>
        <div class="arch arch--ring" style="--bg:#bba8e3"><div class="arch__frame"><img src="<?php echo htmlspecialchars($h1['src']); ?>" alt="<?php echo htmlspecialchars($h1['alt']); ?>"></div></div>
        <div class="arch arch--mid" style="--bg:#e4fbe4"><div class="arch__frame"><img src="<?php echo htmlspecialchars($h2['src']); ?>" alt="<?php echo htmlspecialchars($h2['alt']); ?>"></div></div>
        <div class="arch arch--ring" style="--bg:#ffc6dc"><div class="arch__frame"><img src="<?php echo htmlspecialchars($h3['src']); ?>" alt="<?php echo htmlspecialchars($h3['alt']); ?>"></div></div>
      </div>
    </section>

    <!-- 3. HIGHLIGHTS -->
    <section class="highlights" aria-label="Key school information">
      <div class="container">
        <div class="highlights__grid" data-reveal>
          <div class="highlight"><span class="highlight__icon" style="background:var(--lilac-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-users"/></svg></span><div><small>Grades offered</small><strong>Early Years â€“ Grade 10</strong></div></div>
          <div class="highlight"><span class="highlight__icon" style="background:var(--mint-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-calendar"/></svg></span><div><small>Admissions</small><strong>Open for 2026â€“27</strong></div></div>
          <div class="highlight"><span class="highlight__icon" style="background:var(--pink-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-clock"/></svg></span><div><small>School hours</small><strong>8:00 AM â€“ 2:30 PM</strong></div></div>
          <div class="highlight"><span class="highlight__icon" style="background:var(--sun-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-pin"/></svg></span><div><small>Campus</small><strong>Lahore, Pakistan</strong></div></div>
        </div>
      </div>
    </section>

    <!-- 4. EXPLORE PROGRAMS -->
    <section class="section explore" style="padding-top:clamp(24px,4vw,48px)">
      <div class="container explore__layout">
        <div data-reveal>
          <h2 class="title">Explore<br>Our Programs</h2>
          <p class="lead">Browse the learning areas that shape each school day, from first words to final exams.</p>
          <a class="btn btn--dark" href="academics.php">View all programs</a>
        </div>
        <ul class="cat-grid" data-reveal style="--d:.1s">
          <li><a class="cat tile--lilac" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-math"/></svg></div><span>Mathematics</span></a></li>
          <li><a class="cat tile--mint" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-book"/></svg></div><span>English</span></a></li>
          <li><a class="cat tile--pink" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></div><span>Science</span></a></li>
          <li><a class="cat tile--lilac" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-code"/></svg></div><span>Computer Studies</span></a></li>
          <li><a class="cat tile--mint" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-globe"/></svg></div><span>Social Studies</span></a></li>
          <li><a class="cat tile--pink" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-palette"/></svg></div><span>Art &amp; Design</span></a></li>
          <li><a class="cat tile--lilac" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-ball"/></svg></div><span>Physical Education</span></a></li>
          <li><a class="cat tile--mint" href="academics.php#subjects"><div class="cat__tile"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-music"/></svg></div><span>Music</span></a></li>
        </ul>
      </div>
    </section>

    <!-- 5. ABOUT -->
    <section class="section">
      <div class="container about__layout">
        <div class="about__media" data-reveal>
          <?php 
            $abt_tea = getWebImg('about_teacher_home', 'assets/images/about-teacher.svg', 'Teacher');
            $abt_ins = getWebImg('about_inset_home', 'assets/images/gallery/classroom-1.svg', 'School preview');
          ?>
          <div class="about__photo"><img src="<?php echo htmlspecialchars($abt_tea['src']); ?>" alt="<?php echo htmlspecialchars($abt_tea['alt']); ?>" loading="lazy"></div>
          <a class="about__inset" href="gallery.php" aria-label="See the school gallery">
            <img src="<?php echo htmlspecialchars($abt_ins['src']); ?>" alt="<?php echo htmlspecialchars($abt_ins['alt']); ?>" loading="lazy">
            <span class="play"><span><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-play"/></svg></span></span>
          </a>
        </div>
        <div data-reveal style="--d:.1s">
          <span class="eyebrow">About us</span>
          <h2 class="title">A school where learning grows with every student.</h2>
          <p class="lead">Brightway School has been nurturing young minds for over a decade. We believe in building strong foundations, caring communities, and a love of learning that lasts a lifetime.</p>
          <ul class="checks">
            <li><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-check"/></svg>Caring teachers</li>
            <li><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-check"/></svg>Balanced curriculum</li>
            <li><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-check"/></svg>Safe, supportive campus</li>
            <li><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-check"/></svg>Partnership with parents</li>
          </ul>
          <a class="btn btn--dark" href="about.php">Explore more <svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-plus"/></svg></a>
        </div>
      </div>
    </section>

    <!-- 6. WHY CHOOSE -->
    <section class="section section--paper">
      <div class="container why__layout">
        <div data-reveal>
          <h2 class="title">Why families choose Brightway</h2>
          <p class="lead">These are just a few of the things that make Brightway School a community parents and students love.</p>
          <a class="btn btn--outline" href="about.php">Read our approach</a>
        </div>
        <div class="why__list" data-reveal style="--d:.1s">
          <div class="why-item" style="--bg:var(--lilac-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-bulb"/></svg></span><div><h3>Understanding first</h3><p>Lessons built around real understanding, not just memorising.</p></div></div>
          <div class="why-item" style="--bg:var(--pink-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-heart"/></svg></span><div><h3>Caring teachers</h3><p>Educators who know each student and nurture their strengths.</p></div></div>
          <div class="why-item" style="--bg:var(--mint-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-shield"/></svg></span><div><h3>A safe campus</h3><p>A secure, welcoming place where children feel they belong.</p></div></div>
          <div class="why-item" style="--bg:var(--sun-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-trophy"/></svg></span><div><h3>Balanced growth</h3><p>Academics alongside sports, arts and character building.</p></div></div>
          <div class="why-item" style="--bg:var(--mint-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-users"/></svg></span><div><h3>Open communication</h3><p>Regular, clear updates so parents are always in the loop.</p></div></div>
          <div class="why-item" style="--bg:var(--lilac-soft)"><span class="icon-chip"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-laptop"/></svg></span><div><h3>Modern learning spaces</h3><p>Classrooms and labs that support hands-on learning.</p></div></div>
        </div>
      </div>
    </section>

    <!-- 7. ACADEMICS + FACILITIES -->
    <section class="section">
      <div class="container">
        <div class="section-head section-head--split" data-reveal>
          <div>
            <h2 class="title">Programs for every stage</h2>
            <p class="lead" style="margin:0">From the first day of school to graduation.</p>
          </div>
          <a class="btn btn--outline" href="academics.php">Academic programs</a>
        </div>
        <div class="levels" data-reveal>
          <article class="level">
            <div class="level__arch" style="--bg:#ffe3ec"><img src="assets/images/gallery/classroom-1.svg" alt="Early Years learning environment" loading="lazy"></div>
            <h3>Early Years</h3>
            <p><strong>Ages 3&#8211;5 (Pre-School &amp; KG)</strong><br>A gentle start built on play and curiosity.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#e4fbe4"><img src="assets/images/gallery/activity-1.svg" alt="Primary learning environment" loading="lazy"></div>
            <h3>Primary</h3>
            <p><strong>Grades 1&#8211;5 (Ages 6&#8211;11)</strong><br>Strong foundations in reading, writing and number.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#eee6ff"><img src="assets/images/gallery/classroom-2.svg" alt="Middle School learning environment" loading="lazy"></div>
            <h3>Middle School</h3>
            <p><strong>Grades 6&#8211;8 (Ages 12&#8211;14)</strong><br>Growing independence and wider subject choice.</p>
          </article>
          <article class="level">
            <div class="level__arch" style="--bg:#fff1c9"><img src="assets/images/gallery/campus-2.svg" alt="Secondary learning environment" loading="lazy"></div>
            <h3>Secondary</h3>
            <p><strong>Grades 9&#8211;10 (Ages 15&#8211;17)</strong><br>Preparing students for board exams and higher education.</p>
          </article>
        </div>
        <div class="facilities" data-reveal>
          <div>
            <h3 class="title" style="font-size:1.6rem">Facilities</h3>
            <p class="lead" style="margin:0">Spaces designed to support learning, play and creativity.</p>
          </div>
          <ul class="chips"><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-book"/></svg></i>Library</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-flask"/></svg></i>Science lab</li><li class="chip"><i style="--c:#e4fbe4"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-laptop"/></svg></i>Computer lab</li><li class="chip"><i style="--c:#fff1c9"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-ball"/></svg></i>Sports ground</li><li class="chip"><i style="--c:#ffe3ec"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-palette"/></svg></i>Art room</li><li class="chip"><i style="--c:#eee6ff"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-building"/></svg></i>Assembly hall</li></ul>
        </div>
      </div>
    </section>

    <!-- 8. ADMISSIONS CTA -->
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

    <!-- 9. EVENTS -->
    <section class="section">
      <div class="container">
        <div class="section-head section-head--split" data-reveal>
          <div>
            <h2 class="title">Events &amp; activities</h2>
            <p class="lead" style="margin:0">Life at school goes well beyond the classroom.</p>
          </div>
        </div>
        <div class="events" data-reveal>
          <article class="event">
            <div class="event__img"><img src="assets/images/gallery/event-1.svg" alt="School stage event (placeholder illustration)" loading="lazy"><             <div class="event__body"><span class="event__date"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-calendar"/></svg><strong>December 2026</strong></span><h3>Annual Day</h3><p>A grand celebration of talent â€” performances, awards, and proud moments for every family.</p></div>
          </article>
          <article class="event">
            <div class="event__img"><img src="assets/images/gallery/activity-2.svg" alt="Students playing outdoors (placeholder illustration)" loading="lazy"></div>
            <div class="event__body"><span class="event__date"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-calendar"/></svg><strong>November 2026</strong></span><h3>Sports Day</h3><p>A full day of friendly competition â€” track, field, and team events for all age groups.</p></div>
          </article>
          <article class="event">
            <div class="event__img"><img src="assets/images/gallery/classroom-3.svg" alt="Students in a classroom (placeholder illustration)" loading="lazy"></div>
            <div class="event__body"><span class="event__date"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-calendar"/></svg><strong>October 2026</strong></span><h3>Science Fair</h3><p>Students showcase innovative projects to parents, teachers, and local community guests.</p></div></div>
          </article>
        </div>
      </div>
    </section>

    <!-- 10. GALLERY PREVIEW -->
    <section class="section section--paper">
      <div class="container">
        <div class="section-head section-head--split" data-reveal>
          <div>
            <h2 class="title">A glimpse of school life</h2>
            <p class="lead" style="margin:0">Campus, classrooms, activities and celebrations.</p>
          </div>
          <a class="btn btn--dark" href="gallery.php">Open the gallery</a>
        </div>
        <div class="mosaic" data-reveal>
          <a href="gallery.php"><img src="assets/images/gallery/campus-1.svg" alt="Campus building (placeholder illustration)" width="800" height="600"></a>
          <a href="gallery.php"><img src="assets/images/gallery/activity-1.svg" alt="Outdoor activity (placeholder illustration)" width="800" height="600"></a>
          <a href="gallery.php"><img src="assets/images/gallery/classroom-2.svg" alt="Classroom (placeholder illustration)" width="800" height="600"></a>
          <a href="gallery.php"><img src="assets/images/gallery/event-2.svg" alt="School event (placeholder illustration)" width="800" height="600"></a>
        </div>
      </div>
    </section>

    <!-- 11. TESTIMONIALS -->
    <section class="section">
      <div class="container">
        <div class="section-head section-head--center" data-reveal>
          <h2 class="title">What families say</h2>
          <p class="lead">Hear from the parents and students who are part of the Brightway community.</p>
        </div>
        <div class="quotes" data-reveal>
          <figure class="quote" style="margin:0"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-quote"/></svg><p>Brightway has given my daughter the confidence to speak up and the curiosity to keep learning. The teachers genuinely care about every child.</p><footer><span class="dot" style="--c:var(--lilac-soft)"></span><div><strong>Fatima Tariq</strong>Parent of a Grade 5 student</div></footer></figure>
          <figure class="quote" style="margin:0"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-quote"/></svg><p>I used to find school boring, but here every subject feels connected to real life. My favourite part is the science lab â€” we do experiments every week!</p><footer><span class="dot" style="--c:var(--mint-soft)"></span><div><strong>Zain Ahmed</strong>Student, Grade 7</div></footer></figure>
          <figure class="quote" style="margin:0"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-quote"/></svg><p>As a parent, the regular updates and open-door policy of the administration give us full confidence in the school. Truly a second home for our children.</p><footer><span class="dot" style="--c:var(--pink-soft)"></span><div><strong>Hassan Mirza</strong>Parent &amp; Community Member</div></footer></figure>
        </div>
      </div>
    </section>

    <!-- 12. CONTACT -->
    <section class="section section--paper">
      <div class="container contact__layout">
        <div data-reveal>
          <h2 class="title">Get in touch</h2>
          <p class="lead">Questions about admissions, visits or school life? Send us a message and we will reply.</p>
        <ul class="info-list">
          <li><span class="icon-chip" style="--c:var(--lilac-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-pin"/></svg></span><div><small>Address</small><strong>Model Town, Lahore, Pakistan</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--mint-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-phone"/></svg></span><div><small>Phone</small><strong>+92 42 3590 0000</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--pink-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-mail"/></svg></span><div><small>Email</small><strong>info@brightwayschool.edu.pk</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--sun-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-clock"/></svg></span><div><small>Office hours</small><strong>Monâ€“Sat, 8:00 AM â€“ 3:00 PM</strong></div></li>
        </ul>
        </div>
        <div data-reveal style="--d:.1s">
        <form class="form" id="home-form" data-validate data-endpoint="php/contact-submit.php" action="#" method="post">
          <div class="form__grid">
          <div class="field">
            <label for="hf-name">Your name<span class="req" aria-hidden="true">*</span></label>
            <input id="hf-name" name="hf-name" type="text" required autocomplete="name" aria-describedby="hf-name-err">
            <span class="field__error" id="hf-name-err" role="alert"></span>
          </div>
          <div class="field">
            <label for="hf-email">Email<span class="req" aria-hidden="true">*</span></label>
            <input id="hf-email" name="hf-email" type="email" required autocomplete="email" aria-describedby="hf-email-err">
            <span class="field__error" id="hf-email-err" role="alert"></span>
          </div>
          <div class="field field--full">
            <label for="hf-phone">Phone</label>
            <input id="hf-phone" name="hf-phone" type="tel" autocomplete="tel" aria-describedby="hf-phone-err">
            <span class="field__error" id="hf-phone-err" role="alert"></span>
          </div>
          <div class="field field--full">
            <label for="hf-msg">Message<span class="req" aria-hidden="true">*</span></label>
            <textarea id="hf-msg" name="hf-msg" req
            uired minlength="10" placeholder="How can we help?" aria-describedby="hf-msg-err"></textarea>
            <span class="field__error" id="hf-msg-err" role="alert"></span>
          </div>
          </div>
          <div class="form__foot">
            <button class="btn btn--dark" type="submit">Send message</button>
          </div>
          <div class="form__status" role="status" aria-live="polite"></div>
          <p class="form__note">Fields marked * are required. </p>
        </form>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="assets/js/main.js" defer></script>
</body>
</html>


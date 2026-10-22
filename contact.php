<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact — Brightway School</title>
  <meta name="description" content="Contact the school office or send an inquiry.">
  <meta name="theme-color" content="#0c1a26">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body data-page="contact">
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main">
    <section class="page-hero">
      <div class="decor decor--rocket" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-rocket"/></svg></div>
      <div class="decor decor--balloon" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-balloon"/></svg></div>
      <div class="decor decor--book" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-bookopen"/></svg></div>
      <div class="decor decor--triangle" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><use href="assets/images/icons.svg#d-triangle"/></svg></div>
      <div class="container page-hero__inner">
        <h1>Contact us</h1>
        <p>We would love to hear from you. Send a message or visit the school office.</p>
      </div>
    </section>

    <section class="section">
      <div class="container contact__layout">
        <div data-reveal>
          <h2 class="title">School office</h2>
          <p class="lead">Reach us using the details below, or fill in the form and we will get back to you.</p>
        <ul class="info-list">
          <li><span class="icon-chip" style="--c:var(--lilac-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-pin"/></svg></span><div><small>Address</small><strong class="ph">[School address, city]</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--mint-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-phone"/></svg></span><div><small>Phone</small><strong class="ph">[Phone number]</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--pink-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-mail"/></svg></span><div><small>Email</small><strong class="ph">[school@example.com]</strong></div></li>
          <li><span class="icon-chip" style="--c:var(--sun-soft)"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-clock"/></svg></span><div><small>Office hours</small><strong class="ph">[Days and hours]</strong></div></li>
        </ul>
        </div>
        <div data-reveal style="--d:.1s">
        <form class="form" id="contact-form" data-validate data-endpoint="php/contact-submit.php" action="#" method="post">
          <div class="form__grid">
          <div class="field">
            <label for="ct-name">Your name<span class="req" aria-hidden="true">*</span></label>
            <input id="ct-name" name="ct-name" type="text" required autocomplete="name" aria-describedby="ct-name-err">
            <span class="field__error" id="ct-name-err" role="alert"></span>
          </div>
          <div class="field">
            <label for="ct-email">Email<span class="req" aria-hidden="true">*</span></label>
            <input id="ct-email" name="ct-email" type="email" required autocomplete="email" aria-describedby="ct-email-err">
            <span class="field__error" id="ct-email-err" role="alert"></span>
          </div>
          <div class="field">
            <label for="ct-phone">Phone</label>
            <input id="ct-phone" name="ct-phone" type="tel" autocomplete="tel" aria-describedby="ct-phone-err">
            <span class="field__error" id="ct-phone-err" role="alert"></span>
          </div>
          <div class="field">
            <label for="ct-topic">Topic<span class="req" aria-hidden="true">*</span></label>
            <select id="ct-topic" name="ct-topic" required aria-describedby="ct-topic-err"><option value="">Select…</option><option>Admissions</option><option>Academics</option><option>Visit the school</option><option>Other</option></select>
            <span class="field__error" id="ct-topic-err" role="alert"></span>
          </div>
          <div class="field field--full">
            <label for="ct-msg">Message<span class="req" aria-hidden="true">*</span></label>
            <textarea id="ct-msg" name="ct-msg" required minlength="10" placeholder="How can we help?" aria-describedby="ct-msg-err"></textarea>
            <span class="field__error" id="ct-msg-err" role="alert"></span>
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

        <!-- MAP SECTION -->
    <section class="section section--paper" style="padding-top:0">
      <div class="container">
        <!-- TODO: Apne school ki exact Google Maps embed link yahan lagayen -->
        <!-- Steps: Google Maps -> Search school -> Share -> Embed a map -> Copy HTML -> paste src URL here -->
        <div style="border-radius:1rem; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); border:1px solid var(--border);" data-reveal>
          <iframe
            id="school-map"
            title="School Location on Google Maps"
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3402.0!2d74.3294!3d31.5204!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x391904b5e3a7c3e1%3A0x1234abcd!2sModel+Town%2C+Lahore%2C+Pakistan!5e0!3m2!1sen!2spk!4v1234567890"
            width="100%"
            height="450"
            style="border:0; display:block;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade">
          </iframe>
        </div>
        <p style="margin-top:.75rem; font-size:.85rem; color:var(--muted); text-align:center;">
          📍 Model Town, Lahore, Pakistan &nbsp;|&nbsp;
          <a href="https://maps.google.com/?q=Model+Town,Lahore,Pakistan" target="_blank" rel="noopener" style="color:var(--accent)">Open in Google Maps ↗</a>
        </p>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="assets/js/main.js" defer></script>
</body>
</html>

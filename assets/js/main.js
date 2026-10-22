/* ==========================================================================
   Brightway School — main.js
   1. Load shared components (navbar / footer)
   2. Navbar behaviour (scroll state, mobile menu, active link)
   3. Reveal-on-scroll
   4. Form validation (frontend only)
   ========================================================================== */
(function () {
  "use strict";

  document.documentElement.classList.add("js");
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- 1. components ---------- */
  function loadIncludes() {
    var slots = document.querySelectorAll("[data-include]");
    return Promise.all(Array.prototype.map.call(slots, function (slot) {
      return fetch(slot.getAttribute("data-include"))
        .then(function (res) {
          if (!res.ok) throw new Error(res.status + " " + res.statusText);
          return res.text();
        })
        .then(function (html) { slot.outerHTML = html; })
        .catch(function (err) {
          console.error("Could not load " + slot.getAttribute("data-include") + ":", err);
          slot.innerHTML = '<p style="margin:0;padding:12px 16px;background:#fff3cd;color:#664d03;font:600 14px system-ui">' +
            "Shared components could not load. Run the site through a local server (see README) instead of opening the file directly.</p>";
        });
    }));
  }

  /* ---------- 2. navbar ---------- */
  function initNav() {
    var wrap = document.querySelector(".nav-wrap");
    if (!wrap) return;
    var toggle = wrap.querySelector(".nav__toggle");
    var links = wrap.querySelector(".nav__links");
    var page = document.body.getAttribute("data-page");

    // mark current page
    wrap.querySelectorAll("[data-nav-link]").forEach(function (a) {
      if (a.getAttribute("data-nav-link") === page) a.setAttribute("aria-current", "page");
    });

    // scroll state
    function onScroll() { wrap.classList.toggle("is-scrolled", window.scrollY > 24); }
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    // mobile menu
    function setOpen(open) {
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      links.classList.toggle("is-open", open);
    }
    toggle.addEventListener("click", function () {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });
    links.addEventListener("click", function (e) { if (e.target.closest("a")) setOpen(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setOpen(false); });
    document.addEventListener("click", function (e) { if (!wrap.contains(e.target)) setOpen(false); });
    window.matchMedia("(min-width: 901px)").addEventListener("change", function () { setOpen(false); });
  }

  /* ---------- footer year ---------- */
  function initYear() {
    document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });
  }

  /* ---------- 3. reveal on scroll ---------- */
  function initReveal() {
    var items = document.querySelectorAll("[data-reveal]");
    if (!items.length) return;
    if (reduceMotion || !("IntersectionObserver" in window)) {
      items.forEach(function (el) { el.classList.add("is-in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add("is-in"); io.unobserve(entry.target); }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    items.forEach(function (el) { io.observe(el); });
  }

  /* ---------- 4. forms ---------- */
  var MESSAGES = {
    required: "This field is required.",
    email: "Enter a valid email address, like name@example.com.",
    tel: "Enter a valid phone number (digits, spaces, + - ( ) allowed).",
    minlength: function (n) { return "Please enter at least " + n + " characters."; },
    checkbox: "Please tick this box to continue."
  };

  function errorFor(field) {
    var v = field.value.trim();
    if (field.type === "checkbox") return field.required && !field.checked ? MESSAGES.checkbox : "";
    if (field.required && !v) return MESSAGES.required;
    if (!v) return "";
    if (field.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return MESSAGES.email;
    if (field.type === "tel" && !/^[+()\d][\d\s\-()]{6,17}$/.test(v)) return MESSAGES.tel;
    var min = parseInt(field.getAttribute("minlength"), 10);
    if (min && v.length < min) return MESSAGES.minlength(min);
    return "";
  }

  function showError(field, msg) {
    var box = field.closest(".field");
    var out = box.querySelector(".field__error");
    box.classList.toggle("has-error", !!msg);
    field.setAttribute("aria-invalid", msg ? "true" : "false");
    if (out) out.textContent = msg;
  }

  function initForms() {
    document.querySelectorAll("form[data-validate]").forEach(function (form) {
      form.setAttribute("novalidate", "");
      var fields = form.querySelectorAll("input, select, textarea");
      var status = form.querySelector(".form__status");

      fields.forEach(function (f) {
        f.addEventListener("blur", function () { showError(f, errorFor(f)); });
        f.addEventListener("input", function () {
          if (f.closest(".field").classList.contains("has-error")) showError(f, errorFor(f));
        });
      });

      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var firstBad = null;
        fields.forEach(function (f) {
          var msg = errorFor(f);
          showError(f, msg);
          if (msg && !firstBad) firstBad = f;
        });
        status.className = "form__status";
        if (firstBad) {
          status.textContent = "Some details need your attention. Check the highlighted fields and try again.";
          status.classList.add("is-error");
          firstBad.focus();
          return;
        }
        // FRONTEND ONLY: nothing is sent yet.
        // Later, post FormData(form) to the URL in form.dataset.endpoint (e.g. a PHP/MySQLi handler).
        status.textContent = "Thank you. Your message is ready to send. (Demo only: connect this form to the backend to receive submissions.)";
        status.classList.add("is-success");
        form.reset();
        fields.forEach(function (f) { showError(f, ""); });
      });
    });
  }

  /* ---------- 5. login portal modal ---------- */
  function initLoginModal() {
    var modal = document.getElementById("loginModal");
    if (!modal) return;

    function openModal(e) {
      if (e) e.preventDefault();
      modal.removeAttribute("hidden");
      // Force layout repaint so transition works
      void modal.offsetWidth;
      modal.classList.add("is-open");
      document.body.style.overflow = "hidden";
      var firstCard = modal.querySelector(".login-role-card");
      if (firstCard) firstCard.focus();
    }

    function closeModal() {
      modal.classList.remove("is-open");
      document.body.style.overflow = "";
      setTimeout(function () {
        if (!modal.classList.contains("is-open")) {
          modal.setAttribute("hidden", "");
        }
      }, 260);
    }

    // Intercept clicks on any login buttons or links pointing to login.php
    document.addEventListener("click", function (e) {
      var trigger = e.target.closest('a[href*="admin/login.php"], .js-login-modal-trigger, [data-login-modal]');
      if (trigger && !modal.contains(trigger)) {
        openModal(e);
      } else if (e.target.closest("[data-close-modal]")) {
        closeModal();
      }
    });

    // Close on Escape key
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        closeModal();
      }
    });
  }

  /* ---------- boot ---------- */
  document.addEventListener("DOMContentLoaded", function () {
    loadIncludes().then(function () {
      initNav();
      initYear();
      initReveal();
      initForms();
      initLoginModal();
    });
  });
})();

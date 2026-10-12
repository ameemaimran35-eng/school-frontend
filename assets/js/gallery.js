/* ==========================================================================
   Gallery page — category filter + keyboard-friendly lightbox
   ========================================================================== */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var items = Array.prototype.slice.call(document.querySelectorAll(".gallery__item"));
    var filters = document.querySelectorAll(".filter");
    if (!items.length) return;

    /* ----- filter ----- */
    filters.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var cat = btn.getAttribute("data-filter");
        filters.forEach(function (b) { b.setAttribute("aria-pressed", String(b === btn)); });
        items.forEach(function (item) {
          var show = cat === "all" || item.getAttribute("data-category") === cat;
          item.classList.toggle("is-hidden", !show);
          item.classList.remove("is-entering");
          if (show) { void item.offsetWidth; item.classList.add("is-entering"); }
        });
      });
    });

    /* ----- lightbox ----- */
    var box = document.createElement("div");
    box.className = "lightbox";
    box.setAttribute("role", "dialog");
    box.setAttribute("aria-modal", "true");
    box.setAttribute("aria-label", "Image viewer");
    box.innerHTML =
      '<button class="lb-close" aria-label="Close image viewer"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-close"/></svg></button>' +
      '<button class="lb-prev" aria-label="Previous image"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-chev"/></svg></button>' +
      '<button class="lb-next" aria-label="Next image"><svg class="icon" aria-hidden="true"><use href="assets/images/icons.svg#i-chev"/></svg></button>' +
      '<figure><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="><figcaption></figcaption></figure>';
    document.body.appendChild(box);

    var img = box.querySelector("img");
    var cap = box.querySelector("figcaption");
    var current = 0, opener = null;

    function visible() { return items.filter(function (i) { return !i.classList.contains("is-hidden"); }); }

    function show(i) {
      var list = visible();
      current = (i + list.length) % list.length;
      var src = list[current].querySelector("img");
      img.src = src.src;
      img.alt = src.alt;
      cap.textContent = src.alt;
    }
    function open(item) {
      opener = item;
      show(visible().indexOf(item));
      box.classList.add("is-open");
      document.body.style.overflow = "hidden";
      box.querySelector(".lb-close").focus();
    }
    function close() {
      box.classList.remove("is-open");
      document.body.style.overflow = "";
      if (opener) opener.focus();
    }

    items.forEach(function (item) { item.addEventListener("click", function () { open(item); }); });
    box.querySelector(".lb-close").addEventListener("click", close);
    box.querySelector(".lb-prev").addEventListener("click", function () { show(current - 1); });
    box.querySelector(".lb-next").addEventListener("click", function () { show(current + 1); });
    box.addEventListener("click", function (e) { if (e.target === box) close(); });
    document.addEventListener("keydown", function (e) {
      if (!box.classList.contains("is-open")) return;
      if (e.key === "Escape") close();
      if (e.key === "ArrowLeft") show(current - 1);
      if (e.key === "ArrowRight") show(current + 1);
    });
  });
})();

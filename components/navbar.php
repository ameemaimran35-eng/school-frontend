<?php
// navbar.php - Converted from navbar.html
?>
<header class="nav-wrap">
  <nav class="nav" aria-label="Main navigation">
    <a class="brand" href="index.php" aria-label="Brightway School — home">
      <span class="brand__mark" aria-hidden="true"></span>
      <span>Brightway</span>
    </a>

    <ul class="nav__links" id="nav-links">
      <li><a href="index.php" data-nav-link="home">Home</a></li>
      <li><a href="about.php" data-nav-link="about">About</a></li>
      <li><a href="academics.php" data-nav-link="academics">Academics</a></li>
      <li><a href="gallery.php" data-nav-link="gallery">Gallery</a></li>
      <li><a href="contact.php" data-nav-link="contact">Contact</a></li>
      <li class="nav__cta-item"><a class="btn btn--mint" href="admissions.php">Admissions</a></li>
      <li class="nav__cta-item"><a class="btn btn--mint js-login-modal-trigger" href="admin/login.php" onclick="if(window.openLoginPortalModal){return window.openLoginPortalModal(event);}">Login</a></li>
    </ul>

    <a class="btn btn--mint nav__cta" href="admissions.php">Admissions</a>

    <button class="nav__toggle" type="button" aria-expanded="false" aria-controls="nav-links" aria-label="Open menu">
      <span></span><span></span>
    </button>
  </nav>
</header>

<style>
/* Login Portal Modal Styles */
.login-modal-overlay {
  position: fixed !important;
  inset: 0 !important;
  z-index: 999999 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 16px !important;
  opacity: 0;
  visibility: hidden;
  transition: opacity .25s ease, visibility .25s ease;
  pointer-events: none;
}
.login-modal-overlay.is-open {
  opacity: 1 !important;
  visibility: visible !important;
  pointer-events: auto !important;
}
.login-modal-backdrop {
  position: absolute !important;
  inset: 0 !important;
  background: rgba(8, 16, 24, 0.8) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  backdrop-filter: blur(8px) !important;
  cursor: pointer;
}
.login-modal-dialog {
  position: relative !important;
  width: 100% !important;
  max-width: 480px !important;
  background: #0f1d2b !important;
  background: linear-gradient(180deg, #142436 0%, #0c1a27 100%) !important;
  border: 1px solid rgba(255, 255, 255, 0.14) !important;
  border-radius: 20px !important;
  box-shadow: 0 25px 65px -15px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.06) !important;
  padding: 26px 24px 20px !important;
  color: #fff !important;
  z-index: 2 !important;
  transform: scale(0.92) translateY(12px);
  transition: transform .26s cubic-bezier(0.16, 1, 0.3, 1), opacity .26s ease;
  overflow: hidden !important;
  box-sizing: border-box !important;
}
.login-modal-overlay.is-open .login-modal-dialog {
  transform: scale(1) translateY(0) !important;
}
.login-modal-header {
  display: flex !important;
  align-items: center !important;
  gap: 14px !important;
  margin-bottom: 20px !important;
  position: relative !important;
  padding-right: 36px !important;
}
.login-modal-badge {
  width: 44px !important;
  height: 44px !important;
  border-radius: 12px !important;
  background: rgba(52, 211, 153, 0.14) !important;
  color: #34d399 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  flex-shrink: 0 !important;
  border: 1px solid rgba(52, 211, 153, 0.28) !important;
}
.login-modal-title {
  font-size: 1.25rem !important;
  font-weight: 800 !important;
  color: #ffffff !important;
  margin: 0 !important;
  line-height: 1.2 !important;
  letter-spacing: -0.01em !important;
}
.login-modal-subtitle {
  font-size: 0.85rem !important;
  color: rgba(255, 255, 255, 0.68) !important;
  margin: 4px 0 0 !important;
}
.login-modal-close {
  position: absolute !important;
  top: 0 !important;
  right: 0 !important;
  width: 34px !important;
  height: 34px !important;
  border-radius: 50% !important;
  border: 1px solid rgba(255, 255, 255, 0.16) !important;
  background: rgba(255, 255, 255, 0.08) !important;
  color: #fff !important;
  font-size: 1.3rem !important;
  line-height: 1 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  cursor: pointer !important;
  transition: all .2s ease !important;
  padding: 0 !important;
}
.login-modal-close:hover {
  background: rgba(255, 255, 255, 0.2) !important;
  border-color: rgba(255, 255, 255, 0.4) !important;
  transform: scale(1.08) !important;
}
.login-modal-body {
  display: flex !important;
  flex-direction: column !important;
  gap: 11px !important;
}
.login-role-card {
  display: flex !important;
  align-items: center !important;
  gap: 14px !important;
  padding: 13px 16px !important;
  border-radius: 14px !important;
  background: rgba(255, 255, 255, 0.04) !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  text-decoration: none !important;
  color: #fff !important;
  transition: all .22s cubic-bezier(0.16, 1, 0.3, 1) !important;
  position: relative !important;
  box-sizing: border-box !important;
}
.login-role-card:hover {
  background: rgba(255, 255, 255, 0.09) !important;
  transform: translateY(-2px) !important;
  box-shadow: 0 10px 24px -6px rgba(0, 0, 0, 0.4) !important;
}
.login-role-card--admin:hover {
  border-color: rgba(99, 102, 241, 0.55) !important;
}
.login-role-card--accountant:hover {
  border-color: rgba(16, 185, 129, 0.55) !important;
}
.login-role-card--receptionist:hover {
  border-color: rgba(245, 158, 11, 0.55) !important;
}
.login-role-icon {
  width: 42px !important;
  height: 42px !important;
  border-radius: 11px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  flex-shrink: 0 !important;
  transition: transform .2s ease !important;
}
.login-role-card:hover .login-role-icon {
  transform: scale(1.08) !important;
}
.login-role-card--admin .login-role-icon {
  background: rgba(99, 102, 241, 0.16) !important;
  color: #818cf8 !important;
  border: 1px solid rgba(99, 102, 241, 0.3) !important;
}
.login-role-card--accountant .login-role-icon {
  background: rgba(16, 185, 129, 0.16) !important;
  color: #34d399 !important;
  border: 1px solid rgba(16, 185, 129, 0.3) !important;
}
.login-role-card--receptionist .login-role-icon {
  background: rgba(245, 158, 11, 0.16) !important;
  color: #fbbf24 !important;
  border: 1px solid rgba(245, 158, 11, 0.3) !important;
}
.login-role-content {
  flex: 1 !important;
  min-width: 0 !important;
}
.login-role-top {
  display: flex !important;
  align-items: center !important;
  gap: 8px !important;
  margin-bottom: 2px !important;
}
.login-role-title {
  font-size: 0.98rem !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  margin: 0 !important;
  line-height: 1.25 !important;
}
.login-role-tag {
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  padding: 2px 7px !important;
  border-radius: 6px !important;
  line-height: 1 !important;
}
.login-role-card--admin .login-role-tag {
  background: rgba(99, 102, 241, 0.22) !important;
  color: #a5b4fc !important;
}
.login-role-card--accountant .login-role-tag {
  background: rgba(16, 185, 129, 0.22) !important;
  color: #6ee7b7 !important;
}
.login-role-card--receptionist .login-role-tag {
  background: rgba(245, 158, 11, 0.22) !important;
  color: #fde68a !important;
}
.login-role-desc {
  font-size: 0.8rem !important;
  color: rgba(255, 255, 255, 0.62) !important;
  margin: 0 !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
}
.login-role-arrow {
  color: rgba(255, 255, 255, 0.35) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: transform .2s ease, color .2s ease !important;
  flex-shrink: 0 !important;
}
.login-role-card:hover .login-role-arrow {
  transform: translateX(4px) !important;
  color: #34d399 !important;
}
.login-role-card--admin:hover .login-role-arrow {
  color: #818cf8 !important;
}
.login-role-card--receptionist:hover .login-role-arrow {
  color: #fbbf24 !important;
}
.login-modal-footer {
  margin-top: 18px !important;
  padding-top: 14px !important;
  border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
  text-align: center !important;
  font-size: 0.76rem !important;
  color: rgba(255, 255, 255, 0.45) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 6px !important;
}

@media (max-width: 480px) {
  .login-modal-overlay {
    padding: 12px !important;
  }
  .login-modal-dialog {
    padding: 20px 16px 16px !important;
    border-radius: 18px !important;
  }
  .login-modal-header {
    margin-bottom: 16px !important;
  }
  .login-modal-badge {
    width: 38px !important;
    height: 38px !important;
  }
  .login-modal-title {
    font-size: 1.1rem !important;
  }
  .login-role-card {
    padding: 11px 12px !important;
    gap: 10px !important;
  }
  .login-role-icon {
    width: 36px !important;
    height: 36px !important;
    border-radius: 9px !important;
  }
  .login-role-title {
    font-size: 0.9rem !important;
  }
  .login-role-desc {
    font-size: 0.74rem !important;
  }
}
</style>

<!-- Modern Role Selection Popup Modal -->
<div class="login-modal-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle" hidden>
  <div class="login-modal-backdrop" data-close-modal></div>
  <div class="login-modal-dialog">
    <div class="login-modal-header">
      <div class="login-modal-badge" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      <div>
        <h2 id="loginModalTitle" class="login-modal-title">Select Portal</h2>
        <p class="login-modal-subtitle">Choose your portal to proceed to login</p>
      </div>
      <button type="button" class="login-modal-close" data-close-modal aria-label="Close popup">&times;</button>
    </div>

    <div class="login-modal-body">
      <!-- 1. School Admin -->
      <a href="admin/login.php?role=admin" class="login-role-card login-role-card--admin">
        <div class="login-role-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l7 4v6c0 5.25-3.5 10-7 12-3.5-2-7-6.75-7-12V6l7-4z"/>
            <path d="M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
            <path d="M6 19c0-3 3-5 6-5s6 2 6 5"/>
          </svg>
        </div>
        <div class="login-role-content">
          <div class="login-role-top">
            <h3 class="login-role-title">School Admin</h3>
            <span class="login-role-tag">Full Access</span>
          </div>
          <p class="login-role-desc">Principal, Management &amp; Settings</p>
        </div>
        <div class="login-role-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>

      <!-- 2. Accountant / Fee Manager -->
      <a href="admin/login.php?role=accountant" class="login-role-card login-role-card--accountant">
        <div class="login-role-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
            <path d="M6 15h2m4 0h6"/>
          </svg>
        </div>
        <div class="login-role-content">
          <div class="login-role-top">
            <h3 class="login-role-title">Accountant / Fee Manager</h3>
            <span class="login-role-tag">Finance</span>
          </div>
          <p class="login-role-desc">Fee Collection, Dues, Salaries &amp; Accounts</p>
        </div>
        <div class="login-role-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>

      <!-- 3. Receptionist / Admission Officer -->
      <a href="admin/login.php?role=receptionist" class="login-role-card login-role-card--receptionist">
        <div class="login-role-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="login-role-content">
          <div class="login-role-top">
            <h3 class="login-role-title">Receptionist / Admission Officer</h3>
            <span class="login-role-tag">Front Desk</span>
          </div>
          <p class="login-role-desc">Inquiries, Admissions &amp; Visitor Desk</p>
        </div>
        <div class="login-role-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>
    </div>

    <div class="login-modal-footer">
      <span>Protected school portal &bull; Authorized personnel only</span>
    </div>
  </div>
</div>

<script>
(function() {
  function setupLoginModal() {
    var modal = document.getElementById("loginModal");
    if (!modal) return;

    window.openLoginPortalModal = function(e) {
      if (e) {
        if (typeof e.preventDefault === "function") e.preventDefault();
        if (typeof e.stopPropagation === "function") e.stopPropagation();
      }
      modal.removeAttribute("hidden");
      void modal.offsetWidth;
      modal.classList.add("is-open");
      document.body.style.overflow = "hidden";
      var firstCard = modal.querySelector(".login-role-card");
      if (firstCard) firstCard.focus();
      return false;
    };

    window.closeLoginPortalModal = function(e) {
      if (e && typeof e.preventDefault === "function") e.preventDefault();
      modal.classList.remove("is-open");
      document.body.style.overflow = "";
      setTimeout(function() {
        if (!modal.classList.contains("is-open")) {
          modal.setAttribute("hidden", "");
        }
      }, 260);
      return false;
    };

    // Global listener for click on any login link
    document.addEventListener("click", function(e) {
      var trigger = e.target.closest('a[href*="admin/login.php"], .js-login-modal-trigger, [data-login-modal]');
      if (trigger && !modal.contains(trigger)) {
        window.openLoginPortalModal(e);
      } else if (e.target.closest("[data-close-modal]")) {
        window.closeLoginPortalModal(e);
      }
    }, true);

    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        window.closeLoginPortalModal(e);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupLoginModal);
  } else {
    setupLoginModal();
  }
})();
</script>

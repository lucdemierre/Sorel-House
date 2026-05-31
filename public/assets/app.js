(() => {
  // ─── THEME ───────────────────────────────────────────────
  const root = document.documentElement;
  const savedTheme = localStorage.getItem("sorel-theme");
  root.dataset.theme = savedTheme || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

  document.querySelector("[data-theme-toggle]")?.addEventListener("click", () => {
    root.classList.add("theme-changing");
    root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
    localStorage.setItem("sorel-theme", root.dataset.theme);
    setTimeout(() => root.classList.remove("theme-changing"), 380);
  });

  // ─── DIALOGS ─────────────────────────────────────────────
  document.querySelectorAll("[data-open]").forEach((button) => {
    button.addEventListener("click", () => document.getElementById(button.dataset.open)?.showModal());
  });
  document.querySelectorAll("[data-edit-property]").forEach((button) => {
    button.addEventListener("click", () => {
      const dialog = document.getElementById("edit-property-dialog");
      if (!dialog) return;
      const fields = ["id", "address", "addressLine2", "townCity", "county", "postcode", "propertyType", "bedrooms", "bathrooms", "localAuthority", "councilTaxBand", "ownershipReference", "accessNotes", "emergencyNotes"];
      fields.forEach((key) => {
        const names = { id: "property_id", addressLine2: "address_line_2", townCity: "town_city", propertyType: "property_type", localAuthority: "local_authority", councilTaxBand: "council_tax_band", ownershipReference: "ownership_reference", accessNotes: "access_notes", emergencyNotes: "emergency_notes" };
        dialog.querySelector(`[name="${names[key] || key}"]`).value = button.dataset[key] || "";
      });
      dialog.showModal();
    });
  });
  document.querySelectorAll("[data-edit-tenant]").forEach((button) => {
    button.addEventListener("click", () => {
      const dialog = document.getElementById("edit-tenant-dialog");
      if (!dialog) return;
      ["id", "name", "email", "rent", "day", "status"].forEach((key) => {
        const names = { id: "tenant_id", rent: "monthly_rent", day: "rent_due_day" };
        dialog.querySelector(`[name="${names[key] || key}"]`).value = button.dataset[key];
      });
      dialog.showModal();
    });
  });
  document.querySelectorAll("[data-confirm]").forEach((button) => {
    button.addEventListener("click", (event) => {
      if (!confirm(button.dataset.confirm)) event.preventDefault();
    });
  });

  // ─── PROPERTY SEARCH ─────────────────────────────────────
  const propertySearch = document.querySelector("[data-property-search]");
  if (propertySearch) {
    const cards = [...document.querySelectorAll("[data-property-card]")];
    const empty = document.querySelector("[data-property-search-empty]");
    propertySearch.addEventListener("input", () => {
      const term = propertySearch.value.trim().toLowerCase();
      let visible = 0;
      cards.forEach((card, index) => {
        const matches = card.dataset.search.includes(term);
        if (matches) {
          card.hidden = false;
          card.style.setProperty("--motion-order", index % 6);
          card.style.animationName = "none";
          requestAnimationFrame(() => { card.style.animationName = "" });
        } else {
          card.hidden = true;
        }
        if (matches) visible++;
      });
      if (empty) empty.hidden = visible !== 0;
    });
  }

  // ─── DIALOG CLOSE ────────────────────────────────────────
  const closeDialog = (dialog) => {
    if (!dialog?.open || dialog.classList.contains("is-closing")) return;
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) {
      dialog.close();
      return;
    }
    dialog.classList.add("is-closing");
    setTimeout(() => {
      dialog.close();
      dialog.classList.remove("is-closing");
    }, 220);
  };
  document.querySelectorAll("[data-close]").forEach((button) => {
    button.addEventListener("click", () => closeDialog(button.closest("dialog")));
  });
  document.querySelectorAll("dialog").forEach((dialog) => {
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) closeDialog(dialog);
    });
    dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      closeDialog(dialog);
    });
  });

  // ─── WORKSPACE MOTION ────────────────────────────────────
  document.querySelectorAll(".app-shell .nav a").forEach((item, index) => {
    item.style.setProperty("--motion-order", index);
  });
  document.querySelectorAll(".metrics .metric, .feature-grid .feature-card, .property-grid .property-card, .tenant-grid .tenant-card, .reminder-grid .reminder-card, .portal-repairs .repair-card, .agreement-grid .agreement-card, .conversation-list .message-card, .table-wrap").forEach((item, index) => {
    item.classList.add("workspace-motion");
    item.style.setProperty("--motion-order", index % 10);
  });
  document.querySelectorAll(".table-wrap tbody tr").forEach((item, index) => {
    item.style.setProperty("--motion-order", index % 12);
  });
  // Portal messages stagger
  document.querySelectorAll(".portal-thread .portal-message").forEach((item, index) => {
    item.style.setProperty("--motion-order", index);
  });

  // ─── METRIC NUMBER COUNT-UP ───────────────────────────────
  document.querySelectorAll(".metric strong").forEach((el) => {
    const raw = el.textContent.trim();
    // Only animate pure integers or simple £ amounts
    const isInt = /^\d+$/.test(raw);
    const isPound = /^£[\d,]+\.\d{2}$/.test(raw);
    if (!isInt && !isPound) return;
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const numericStr = raw.replace(/[£,]/g, "");
    const target = parseFloat(numericStr);
    const prefix = isPound ? "£" : "";
    const decimals = isPound ? 2 : 0;

    el.textContent = prefix + (0).toLocaleString("en-GB", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    const start = performance.now();
    const duration = 820;
    const easeOut = (t) => 1 - Math.pow(1 - t, 3);

    const tick = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const current = target * easeOut(progress);
      el.textContent = prefix + current.toLocaleString("en-GB", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
      if (progress < 1) requestAnimationFrame(tick);
    };
    // Delay slightly so the card entrance animation completes first
    const delay = parseInt(el.closest(".metric")?.style.getPropertyValue("--motion-order") || 0) * 52 + 180;
    setTimeout(() => requestAnimationFrame(tick), delay);
  });

  // ─── PUBLIC SCROLL / PARALLAX ────────────────────────────
  const publicHeader = document.querySelector(".public-header");
  if (publicHeader) {
    const progressBar = publicHeader.querySelector(".scroll-progress");
    const editorialImages = document.querySelectorAll(".public-editorial img");
    const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;
    let currentScroll = scrollY;
    let targetScroll = scrollY;
    let animationFrame;

    const renderScroll = () => {
      currentScroll += (targetScroll - currentScroll) * 0.105;
      if (Math.abs(targetScroll - currentScroll) < 0.08) currentScroll = targetScroll;

      const progress = Math.min(currentScroll / Math.max(innerHeight, 1), 1);
      const visualProgress = reducedMotion ? 0 : progress;
      document.documentElement.style.setProperty("--hero-copy-shift", `${visualProgress * 28}px`);
      document.documentElement.style.setProperty("--hero-copy-opacity", `${1 - visualProgress * 0.42}`);
      document.documentElement.style.setProperty("--hero-image-shift", `${visualProgress * 24}px`);

      editorialImages.forEach((image) => {
        const rect = image.getBoundingClientRect();
        const viewportProgress = (rect.top + rect.height / 2 - innerHeight / 2) / Math.max(innerHeight, 1);
        image.style.setProperty("--editorial-image-shift", `${reducedMotion ? 0 : viewportProgress * -18}px`);
      });

      if (progressBar) {
        const pageProgress = currentScroll / Math.max(document.documentElement.scrollHeight - innerHeight, 1);
        progressBar.style.transform = `scaleX(${Math.min(Math.max(pageProgress, 0), 1)})`;
      }

      if (currentScroll !== targetScroll) animationFrame = requestAnimationFrame(renderScroll);
      else animationFrame = null;
    };

    const updateScroll = () => {
      targetScroll = scrollY;
      publicHeader.classList.toggle("scrolled", targetScroll > 18);
      if (reducedMotion) currentScroll = targetScroll;
      if (!animationFrame) animationFrame = requestAnimationFrame(renderScroll);
    };

    addEventListener("scroll", updateScroll, { passive: true });
    addEventListener("resize", updateScroll, { passive: true });
    updateScroll();
  }

  const publicBody = document.querySelector(".public-body");
  if (publicBody) {
    const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;
    requestAnimationFrame(() => publicBody.classList.add("motion-ready"));

    if (!reducedMotion) {
      let pointerFrame;
      let pointerX = 0;
      const renderPointer = () => {
        document.documentElement.style.setProperty("--hero-image-x", `${pointerX * -8}px`);
        document.documentElement.style.setProperty("--hero-copy-x", `${pointerX * 5}px`);
        pointerFrame = null;
      };
      addEventListener("pointermove", (event) => {
        pointerX = (event.clientX / Math.max(innerWidth, 1) - .5) * 2;
        if (!pointerFrame) pointerFrame = requestAnimationFrame(renderPointer);
      }, { passive: true });
      document.querySelector(".public-hero-image")?.addEventListener("pointerleave", () => {
        pointerX = 0;
        if (!pointerFrame) pointerFrame = requestAnimationFrame(renderPointer);
      });
    }

    if (!reducedMotion) {
      document.querySelectorAll("a[href]").forEach((link) => {
        link.addEventListener("click", (event) => {
          const url = new URL(link.href, location.href);
          const samePage = url.pathname === location.pathname && url.search === location.search;
          if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || url.origin !== location.origin || samePage) return;
          event.preventDefault();
          publicBody.classList.add("is-leaving");
          setTimeout(() => { location.href = url.href }, 360);
        });
      });
    }
  }

  // ─── INTERSECTION REVEAL ──────────────────────────────────
  const revealItems = document.querySelectorAll("[data-reveal]");
  if (revealItems.length) {
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) {
      revealItems.forEach((item) => item.classList.add("revealed"));
    } else if ("IntersectionObserver" in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("revealed");
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.10, rootMargin: "0px 0px -6% 0px" });
      revealItems.forEach((item) => observer.observe(item));
      // Immediately reveal items already in view
      setTimeout(() => {
        revealItems.forEach((item) => {
          if (item.getBoundingClientRect().top < innerHeight * 0.96) item.classList.add("revealed");
        });
      }, 160);
    } else {
      revealItems.forEach((item) => item.classList.add("revealed"));
    }
  }

  // ─── NAV LINK HOVER RIPPLE (app sidebar) ──────────────────
  document.querySelectorAll(".nav a").forEach((link) => {
    link.addEventListener("mouseenter", () => {
      link.style.transition = "background .2s ease, color .2s ease, transform .22s cubic-bezier(.22,1,.36,1), box-shadow .22s ease";
    });
  });

  // ─── BADGE PULSE for expired items ────────────────────────
  document.querySelectorAll(".badge.expired").forEach((badge) => {
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    badge.style.animation = "badge-pulse 2.4s ease-in-out infinite";
  });

  // ─── STAGGER TABLE ROWS on page load ──────────────────────
  document.querySelectorAll(".panel tbody tr").forEach((tr, i) => {
    tr.style.setProperty("--motion-order", i % 14);
    tr.classList.add("workspace-motion");
  });

  // ─── SMOOTH DIALOG BACKDROP ───────────────────────────────
})();

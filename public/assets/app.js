(() => {
  const root = document.documentElement;
  const savedTheme = localStorage.getItem("sorel-theme");
  root.dataset.theme = savedTheme || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

  document.querySelector("[data-theme-toggle]")?.addEventListener("click", () => {
    root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
    localStorage.setItem("sorel-theme", root.dataset.theme);
  });

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
  const propertySearch = document.querySelector("[data-property-search]");
  if (propertySearch) {
    const cards = [...document.querySelectorAll("[data-property-card]")];
    const empty = document.querySelector("[data-property-search-empty]");
    propertySearch.addEventListener("input", () => {
      const term = propertySearch.value.trim().toLowerCase();
      let visible = 0;
      cards.forEach((card) => {
        const matches = card.dataset.search.includes(term);
        card.hidden = !matches;
        if (matches) visible += 1;
      });
      if (empty) empty.hidden = visible !== 0;
    });
  }
  document.querySelectorAll("[data-close]").forEach((button) => {
    button.addEventListener("click", () => button.closest("dialog")?.close());
  });
  document.querySelectorAll("dialog").forEach((dialog) => {
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) dialog.close();
    });
  });

  const publicHeader = document.querySelector(".public-header");
  if (publicHeader) {
    const progressBar = publicHeader.querySelector(".scroll-progress");
    const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;
    let currentScroll = scrollY;
    let targetScroll = scrollY;
    let animationFrame;
    const renderScroll = () => {
      currentScroll += (targetScroll - currentScroll) * 0.105;
      if (Math.abs(targetScroll - currentScroll) < 0.08) currentScroll = targetScroll;
      const progress = Math.min(currentScroll / Math.max(innerHeight, 1), 1);
      document.documentElement.style.setProperty("--hero-copy-shift", `${progress * 28}px`);
      document.documentElement.style.setProperty("--hero-copy-opacity", `${1 - progress * 0.42}`);
      document.documentElement.style.setProperty("--hero-image-shift", `${progress * 24}px`);
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
    addEventListener("scroll", () => {
      updateScroll();
    }, { passive: true });
    addEventListener("resize", updateScroll, { passive: true });
    updateScroll();
  }

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
      }, { threshold: 0.12, rootMargin: "0px 0px -8% 0px" });
      revealItems.forEach((item) => observer.observe(item));
      setTimeout(() => {
        revealItems.forEach((item) => {
          if (item.getBoundingClientRect().top < innerHeight * 0.96) item.classList.add("revealed");
        });
      }, 180);
    } else {
      revealItems.forEach((item) => item.classList.add("revealed"));
    }
  }

})();

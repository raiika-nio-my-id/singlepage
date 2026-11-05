/* JadiDaring marketing — vanilla JS: i18n (ID/EN), nav, reveals, contact → WhatsApp */
(function () {
  "use strict";
  var WA = "6285117177883";
  var root = document.documentElement;
  root.classList.add("js");

  var EN = {
    "skip": "Skip to content",
    "nav.expertise": "Expertise", "nav.showcase": "Showcase", "nav.pricing": "Pricing", "nav.contact": "Contact",
    "cta.showcase": "Showcase", "cta.wa": "Chat on WhatsApp", "cta.seeShowcase": "See the Showcase",
    "cta.playGamehub": "Play on GameHub", "cta.openHub": "Open GameHub", "cta.open": "Open",
    "hero.kicker": "Digital studio · Indonesia · Booking new projects",
    "hero.l1": "We build", "hero.l2": "digital products",
    "hero.l3": "that <mark>actually</mark> ship.",
    "hero.sub": "From one-page wedding sites to AI-powered multi-vendor marketplaces — we design, build, and launch fast, reliable web products for businesses that refuse to be ordinary.",
    "stat.years": "Years in Laravel", "stat.projects": "Projects shipped", "stat.special": "Core specialties",
    "exp.eyebrow": "What we do",
    "exp.title": "Expertise, <em>proven in the field.</em>",
    "exp.sub": "Five specialties, one standard: clean code, fast launches, and products you can maintain for years.",
    "exp.laravel.t": "Laravel Development",
    "exp.laravel.d": "Robust, scalable backends with clean architecture — Eloquent, Queues, Livewire, all the way to Filament admin panels.",
    "exp.ai.t": "AI Agents & Automation",
    "exp.ai.d": "Intelligent agents that automate complex workflows, integrate with LLMs, and give your product a brain.",
    "exp.pos.t": "POS Systems",
    "exp.pos.d": "Point-of-sale for restaurants, retail, and services — fast checkout, inventory, and real-time reporting built for peak hours.",
    "exp.mkt.t": "Marketplace & E-commerce",
    "exp.mkt.d": "Full multi-vendor marketplaces with payment gateways, seller dashboards, order tracking, and escrow logic — built to scale from day one.",
    "exp.sp.t": "Single Page Website",
    "exp.sp.d": "One page that looks premium and converts — wedding invitations, events, ceremonies, company profiles, and personal sites. Fast to launch, easy to share.",
    "sc.title": "Don't just read. <em>Try it live.</em>",
    "sc.sub": "Products we build and run ourselves — plus client projects already in production.",
    "sc.live": "Live games & products", "sc.liveSub": "Real-time, multiplayer, right in the browser. Open one and play now.",
    "sc.hub.d": "Home of every JadiDaring mini-game. Pick a game, invite friends, play together from your phone.",
    "sc.dq.d": "Draw your own fish, then watch it swim in a shared aquarium alongside everyone else's.",
    "sc.lb.d": "A shared night sky — everyone who joins lights up the same sky, in real time.",
    "sc.client": "Client work", "sc.clientSub": "Real products for real businesses. Full case studies on request.",
    "sc.bum.c": "Company Profile",
    "sc.bum.d": "Hajj & Umrah website — company profile, tour packages, and a clear booking flow so prospective pilgrims trust fast and reach out easily.",
    "sc.ius.c": "Listing Platform",
    "sc.ius.d": "Listing platform for a premium used-iPhone store — catalog, trade-in, service, and conversion pages designed to close sales faster.",
    "sc.inv.d": "POS, stock, and accounting for an iPhone store — invoices, IMEI tracking, supplier debt, and a daily ops dashboard in one Laravel panel.",
    "sc.private": "Internal system · demo on request",
    "pr.eyebrow": "Transparent pricing", "pr.title": "Pick your <em>starting point.</em>",
    "pr.sub": "No hidden fees. Prices scale with your needs, and every engagement starts with a free discovery call.",
    "pr.early": "Early client", "pr.popular": "Most popular", "pr.per": "/ project",
    "pr.s.n": "Single Page Website", "pr.s.note": "Early-client discount — normally Rp200rb",
    "pr.s.d": "One clean, share-ready page — for weddings, events, ceremonies, company profiles, and personal sites.",
    "pr.s.f1": "Wedding, event & ceremony", "pr.s.f2": "Company profile & personal site", "pr.s.f3": "Responsive design",
    "pr.s.f4": "Basic SEO + WhatsApp contact", "pr.s.f5": "7-day delivery",
    "pr.p.n": "Listing Platform",
    "pr.p.d": "A dynamic Laravel-powered site with listing pages, filters, search, and an admin backend — ideal for directories, portals, and service platforms.",
    "pr.p.f1": "Dynamic listing pages with filters", "pr.p.f2": "Laravel + Filament admin panel", "pr.p.f3": "User authentication & roles",
    "pr.p.f4": "Database-driven content", "pr.p.f5": "SEO-optimized routes", "pr.p.f6": "40-day delivery",
    "pr.e.d": "A fully-featured multi-vendor marketplace — listings, transactions, escrow, seller dashboard, and AI-powered recommendations.",
    "pr.e.f1": "Everything in Professional", "pr.e.f2": "Multi-vendor seller system", "pr.e.f3": "Payment gateway (Midtrans / Xendit)",
    "pr.e.f4": "Order tracking & escrow logic", "pr.e.f5": "Review & rating system", "pr.e.f6": "AI-powered search / recommendations",
    "pr.e.f7": "60-day delivery + 30-day support",
    "ct.eyebrow": "Get in touch", "ct.title": "Let's build <em>something real.</em>",
    "ct.sub": "Full brief or just a rough idea? Tell us — we'll figure out the scope and the right plan together.",
    "ct.name": "Your name", "ct.namePh": "e.g. Andika", "ct.budget": "Budget range", "ct.select": "Select a plan…",
    "ct.o1": "Starter — Rp50rb early client (Single Page)", "ct.o2": "Professional — Rp5M (Listing Platform)",
    "ct.o3": "Enterprise — Rp30M (Marketplace)", "ct.o4": "Custom budget",
    "ct.msg": "Tell us about your project", "ct.msgPh": "What you want to build, target launch date, references you like…",
    "ct.err": "Please fill in your name and project details.", "ct.send": "Send via WhatsApp",
    "ct.hint": "Opens WhatsApp with your message pre-filled.",
    "ft.rights": "All rights reserved."
  };

  /* capture Indonesian defaults straight from the markup */
  var ID = {};
  var textEls = document.querySelectorAll("[data-i18n]");
  var htmlEls = document.querySelectorAll("[data-i18n-html]");
  var phEls = document.querySelectorAll("[data-i18n-ph]");
  textEls.forEach(function (el) { ID[el.dataset.i18n] = el.textContent; });
  htmlEls.forEach(function (el) { ID[el.dataset.i18nHtml] = el.innerHTML; });
  phEls.forEach(function (el) { ID["ph:" + el.dataset.i18nPh] = el.getAttribute("placeholder"); });
  var META = {
    id: { title: document.title },
    en: { title: "JadiDaring — Digital Studio: Websites, Laravel, POS, Marketplaces & AI Agents | From Rp50rb" }
  };

  var current = "id";
  function setLang(lang) {
    if (lang !== "en") lang = "id";
    current = lang;
    var dict = lang === "en" ? EN : ID;
    textEls.forEach(function (el) { var v = dict[el.dataset.i18n]; if (v != null) el.textContent = v; });
    htmlEls.forEach(function (el) { var v = dict[el.dataset.i18nHtml]; if (v != null) el.innerHTML = v; });
    phEls.forEach(function (el) {
      var k = el.dataset.i18nPh;
      var v = lang === "en" ? EN[k] : ID["ph:" + k];
      if (v != null) el.setAttribute("placeholder", v);
    });
    root.lang = lang;
    document.title = META[lang].title;
    document.querySelectorAll("[data-lang]").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.dataset.lang === lang));
    });
    try { localStorage.setItem("jd-lang", lang); } catch (e) {}
  }

  var qs = new URLSearchParams(location.search).get("lang");
  var stored = null;
  try { stored = localStorage.getItem("jd-lang"); } catch (e) {}
  var initial = qs || stored || "id"; /* Indonesian is the default; EN only on explicit choice */
  if (initial !== "id") setLang(initial);
  document.querySelectorAll("[data-lang]").forEach(function (b) {
    b.addEventListener("click", function () { setLang(b.dataset.lang); });
  });

  /* mobile menu */
  var burger = document.querySelector(".nav__burger");
  var menu = document.getElementById("mobile-menu");
  function closeMenu() { burger.setAttribute("aria-expanded", "false"); menu.hidden = true; }
  burger.addEventListener("click", function () {
    var open = burger.getAttribute("aria-expanded") === "true";
    burger.setAttribute("aria-expanded", String(!open));
    menu.hidden = open;
  });
  menu.addEventListener("click", function (e) { if (e.target.closest("a")) closeMenu(); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeMenu(); });
  window.matchMedia("(min-width: 1081px)").addEventListener("change", function (m) { if (m.matches) closeMenu(); });

  /* scroll reveals, staggered within each parent */
  var revealEls = document.querySelectorAll("[data-reveal]");
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var sibs = Array.prototype.slice.call(en.target.parentNode.querySelectorAll(":scope > [data-reveal]"));
        en.target.style.setProperty("--rd", Math.max(0, sibs.indexOf(en.target)) * 90 + "ms");
        en.target.classList.add("is-in");
        io.unobserve(en.target);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* active section in nav */
  var links = document.querySelectorAll(".nav__links a");
  if ("IntersectionObserver" in window) {
    var so = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.setAttribute("aria-current", String(a.getAttribute("href") === "#" + en.target.id)); });
      });
    }, { rootMargin: "-45% 0px -50% 0px" });
    ["expertise", "showcase", "pricing", "contact"].forEach(function (id) {
      var s = document.getElementById(id); if (s) so.observe(s);
    });
  }

  /* contact form → wa.me with a pre-filled message */
  var form = document.getElementById("contact-form");
  var err = document.getElementById("form-err");
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var fName = document.getElementById("f-name");
    var fMsg = document.getElementById("f-msg");
    var name = fName.value.trim();
    var budget = document.getElementById("f-budget").value;
    var msg = fMsg.value.trim();
    fName.setAttribute("aria-invalid", String(!name));
    fMsg.setAttribute("aria-invalid", String(!msg));
    if (!name || !msg) { err.hidden = false; (name ? fMsg : fName).focus(); return; }
    err.hidden = true;
    var en = current === "en";
    var text = (en ? "Hi JadiDaring!\n\n" : "Halo JadiDaring!\n\n") +
      (en ? "*Name:* " : "*Nama:* ") + name + "\n" +
      "*Budget:* " + (budget || (en ? "Not decided yet" : "Belum ditentukan")) + "\n\n" +
      (en ? "*Project:*\n" : "*Project:*\n") + msg;
    window.open("https://wa.me/" + WA + "?text=" + encodeURIComponent(text), "_blank", "noopener");
  });

  var y = document.getElementById("year");
  if (y) y.textContent = String(new Date().getFullYear());
})();

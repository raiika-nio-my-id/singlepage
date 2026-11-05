/* JadiDaring marketing — i18n (ID default / EN), nav, contact → WhatsApp. No scroll theater. */
(function () {
  "use strict";
  var WA = "6285117177883";
  var root = document.documentElement;

  var EN = {
    "skip": "Skip to content",
    "nav.showcase": "Showcase",
    "nav.expertise": "Stack",
    "nav.pricing": "Pricing",
    "nav.contact": "Contact",
    "cta.wa": "Chat on WhatsApp",
    "cta.showcase": "See the showcase",
    "cta.gamehub": "Open GameHub",
    "cta.open": "Open",
    "cta.openSite": "Open site",
    "hero.kicker": "Web studio in Indonesia",
    "hero.title": "Live links.<br>Fixed prices.",
    "hero.sub": "Single page Rp50rb (usually Rp200rb). Laravel listings Rp5jt. Marketplaces Rp30jt. GameHub, Drawquarium, and Langit Bersama are playable now.",
    "sc.title": "Already live.",
    "sc.sub": "Client sites, then games you can open in the browser.",
    "sc.hub.d": "Home of every JadiDaring mini-game. Pick one, invite friends, play from your phone.",
    "sc.dq.d": "Draw your fish, then watch it swim with everyone else's.",
    "sc.lb.d": "A shared night sky, filled in real time, in the browser.",
    "sc.bum.c": "Company profile",
    "sc.bum.d": "Hajj and Umrah: tour packages and a booking path. Prospective pilgrims can reach the team directly.",
    "sc.ius.c": "Listing",
    "sc.ius.d": "Used-iPhone catalog, trade-in, and pages that lead to a chat.",
    "sc.inv.d": "Invoices, stock, IMEI, and supplier debt in one Laravel panel.",
    "sc.private": "Internal system. Demo over WhatsApp.",
    "exp.title": "What we use.",
    "exp.sub": "Five capabilities already running on shipped work.",
    "exp.ai": "LLM, RAG, workflow automation, OpenAI, Claude",
    "exp.pos": "Checkout, stock, multi-outlet, reports",
    "pr.title": "Pricing.",
    "pr.sub": "Per project. The first conversation is free.",
    "pr.early": "Early client",
    "pr.asked": "Most requested",
    "pr.was": "Usually",
    "pr.s.n": "Single page",
    "pr.s.d": "Wedding, event, ceremony, company profile, personal. Basic SEO and WhatsApp.",
    "pr.s.meta": "7-day delivery",
    "pr.p.n": "Listing platform",
    "pr.p.d": "Listing pages, filters, auth, and a Filament panel.",
    "pr.p.meta": "40-day delivery",
    "pr.e.n": "Marketplace",
    "pr.e.d": "Multi-vendor, Midtrans or Xendit, escrow, reviews, and recommendations.",
    "pr.e.meta": "60 days + 30 days of support",
    "ct.title": "Write it short.",
    "ct.sub": "This opens WhatsApp 0851-1717-7883 with the text you wrote.",
    "ct.name": "Name",
    "ct.budget": "Budget",
    "ct.select": "Pick a plan…",
    "ct.o1": "Starter - Rp50rb (usually Rp200rb)",
    "ct.o2": "Professional - Rp5jt",
    "ct.o3": "Enterprise - Rp30jt",
    "ct.o4": "Custom budget",
    "ct.msg": "Project",
    "ct.err": "Add your name and the project first.",
    "ct.errName": "Add your name.",
    "ct.errMsg": "Tell us about the project.",
    "ct.send": "Send to WhatsApp",
    "ct.hint": "Opens WhatsApp with your message filled in.",
    "ft.rights": "All rights reserved."
  };

  var ID = {};
  var textEls = document.querySelectorAll("[data-i18n]");
  var htmlEls = document.querySelectorAll("[data-i18n-html]");
  var phEls = document.querySelectorAll("[data-i18n-ph]");
  textEls.forEach(function (el) { ID[el.dataset.i18n] = el.textContent; });
  htmlEls.forEach(function (el) { ID[el.dataset.i18nHtml] = el.innerHTML; });
  phEls.forEach(function (el) { ID["ph:" + el.dataset.i18nPh] = el.getAttribute("placeholder"); });

  var descEl = document.querySelector('meta[name="description"]');
  var META = {
    id: {
      title: document.title,
      desc: descEl ? descEl.getAttribute("content") : ""
    },
    en: {
      title: "JadiDaring (JaRing) — Live links, fixed prices",
      desc: "Indonesian web studio. Single page from Rp50rb, Laravel listings at Rp5jt, marketplaces at Rp30jt. Live games: GameHub, Drawquarium, Langit Bersama."
    }
  };

  var current = "id";
  function setLang(lang) {
    if (lang !== "en") lang = "id";
    current = lang;
    var dict = lang === "en" ? EN : ID;
    textEls.forEach(function (el) {
      var v = dict[el.dataset.i18n];
      if (v != null) el.textContent = v;
    });
    htmlEls.forEach(function (el) {
      var v = dict[el.dataset.i18nHtml];
      if (v != null) el.innerHTML = v;
    });
    phEls.forEach(function (el) {
      var k = el.dataset.i18nPh;
      var v = lang === "en" ? EN[k] : ID["ph:" + k];
      if (v != null) el.setAttribute("placeholder", v);
    });
    root.lang = lang;
    document.title = META[lang].title;
    if (descEl && META[lang].desc) descEl.setAttribute("content", META[lang].desc);
    document.querySelectorAll("[data-lang]").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.dataset.lang === lang));
    });
    if (burger) {
      var open = burger.getAttribute("aria-expanded") === "true";
      burger.setAttribute("aria-label", open ? (lang === "en" ? "Close" : "Tutup") : "Menu");
    }
    try { localStorage.setItem("jd-lang", lang); } catch (e) {}
  }

  var burger = document.querySelector(".nav__burger");
  var menu = document.getElementById("mobile-menu");

  var qs = new URLSearchParams(location.search).get("lang");
  var stored = null;
  try { stored = localStorage.getItem("jd-lang"); } catch (e) {}
  var initial = qs || stored || "id";
  if (initial !== "id") setLang(initial);
  document.querySelectorAll("[data-lang]").forEach(function (b) {
    b.addEventListener("click", function () { setLang(b.dataset.lang); });
  });

  function setMenu(open) {
    if (!burger || !menu) return;
    burger.setAttribute("aria-expanded", String(open));
    burger.setAttribute("aria-label", open ? (current === "en" ? "Close" : "Tutup") : "Menu");
    menu.hidden = !open;
  }
  if (burger && menu) {
    burger.addEventListener("click", function () {
      setMenu(burger.getAttribute("aria-expanded") !== "true");
    });
    menu.addEventListener("click", function (e) {
      if (e.target.closest("a")) setMenu(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setMenu(false);
    });
    window.matchMedia("(min-width: 1041px)").addEventListener("change", function (m) {
      if (m.matches) setMenu(false);
    });
  }

  var links = document.querySelectorAll('a[href="#expertise"], a[href="#pricing"], a[href="#contact"]');
  if ("IntersectionObserver" in window) {
    var so = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var hash = "#" + en.target.id;
        links.forEach(function (a) {
          a.setAttribute("aria-current", String(a.getAttribute("href") === hash));
        });
      });
    }, { rootMargin: "-45% 0px -50% 0px" });
    ["expertise", "pricing", "contact"].forEach(function (id) {
      var s = document.getElementById(id);
      if (s) so.observe(s);
    });
  }

  var form = document.getElementById("contact-form");
  var err = document.getElementById("form-err");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var fName = document.getElementById("f-name");
      var fMsg = document.getElementById("f-msg");
      var fBudget = document.getElementById("f-budget");
      var name = fName.value.trim();
      var msg = fMsg.value.trim();
      var selected = fBudget.options[fBudget.selectedIndex];
      var budget = fBudget.value ? selected.text : "";
      var errName = document.getElementById("err-name");
      var errMsg = document.getElementById("err-msg");
      fName.setAttribute("aria-invalid", String(!name));
      fMsg.setAttribute("aria-invalid", String(!msg));
      if (errName) errName.hidden = !!name;
      if (errMsg) errMsg.hidden = !!msg;
      if (!name) fName.setAttribute("aria-describedby", "err-name");
      else fName.removeAttribute("aria-describedby");
      if (!msg) fMsg.setAttribute("aria-describedby", "err-msg");
      else fMsg.removeAttribute("aria-describedby");
      if (!name || !msg) {
        err.hidden = false;
        err.focus();
        return;
      }
      err.hidden = true;
      var en = current === "en";
      var text = (en ? "Hi JadiDaring!\n\n" : "Halo JadiDaring!\n\n") +
        (en ? "*Name:* " : "*Nama:* ") + name + "\n" +
        "*Budget:* " + (budget || (en ? "Not decided yet" : "Belum ditentukan")) + "\n\n" +
        (en ? "*Project:*\n" : "*Project:*\n") + msg;
      window.open("https://wa.me/" + WA + "?text=" + encodeURIComponent(text), "_blank", "noopener,noreferrer");
    });
  }

  var y = document.getElementById("year");
  if (y) y.textContent = String(new Date().getFullYear());
})();

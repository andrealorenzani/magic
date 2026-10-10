// Help terms: the dotted-underline word opens a small popup beside it. Without JavaScript the
// term stays plain text and the popup stays hidden, so the page is unchanged.
(() => {
  const terms = Array.from(document.querySelectorAll(".help__term"));
  if (terms.length === 0) return;

  const GAP = 8;
  const WIDE = window.matchMedia("(min-width: 721px)");
  const popOf = (term) => document.getElementById(term.getAttribute("aria-controls"));

  const close = (term) => {
    const p = popOf(term);
    if (p) p.hidden = true;
    term.setAttribute("aria-expanded", "false");
  };
  const closeAll = (except) => {
    terms.forEach((t) => {
      if (t !== except) close(t);
    });
  };
  const openTerm = () => terms.find((t) => t.getAttribute("aria-expanded") === "true");

  const place = (term, p) => {
    if (!WIDE.matches) return;
    const box = p.offsetParent || document.documentElement;
    const origin = box.getBoundingClientRect();
    const t = term.getBoundingClientRect();
    const w = p.offsetWidth;
    const h = p.offsetHeight;
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;
    let left;
    let top;
    if (t.right + GAP + w <= vw) {
      left = t.right + GAP;
      top = t.top;
    } else if (t.left - GAP - w >= 0) {
      left = t.left - GAP - w;
      top = t.top;
    } else {
      left = t.left;
      top = t.bottom + GAP;
    }
    left = Math.max(GAP, Math.min(left, vw - w - GAP));
    top = Math.max(GAP, Math.min(top, vh - h - GAP));
    p.style.left = left - origin.left - (box.clientLeft || 0) + (box.scrollLeft || 0) + "px";
    p.style.top = top - origin.top - (box.clientTop || 0) + (box.scrollTop || 0) + "px";
  };

  const toggle = (term) => {
    const p = popOf(term);
    if (!p) return;
    const willOpen = p.hidden;
    closeAll(term);
    p.hidden = !willOpen;
    term.setAttribute("aria-expanded", String(willOpen));
    if (willOpen) place(term, p);
  };

  terms.forEach((term) => {
    const wrap = term.closest(".help");
    const id = term.getAttribute("data-help-for");
    const pop = id ? document.getElementById(id) : null;
    if (!wrap || !pop) return;
    const heading = wrap.closest("h1, h2, h3, h4, h5, h6");
    if (heading) heading.after(pop);
    term.setAttribute("role", "button");
    term.setAttribute("tabindex", "0");
    term.setAttribute("aria-expanded", "false");
    term.setAttribute("aria-controls", id);
    wrap.classList.add("help--on");
    term.addEventListener("click", (e) => {
      e.preventDefault();
      toggle(term);
    });
    term.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggle(term);
      }
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    const open = openTerm();
    if (!open) return;
    close(open);
    open.focus();
  });

  document.addEventListener("click", (e) => {
    if (e.target instanceof Element && e.target.closest(".help, .help__pop")) return;
    closeAll(null);
  });

  window.addEventListener(
    "scroll",
    (e) => {
      if (!WIDE.matches) return;
      if (e.target instanceof Element && e.target.closest(".help__pop")) return;
      const open = openTerm();
      const p = open ? popOf(open) : null;
      if (open && p) place(open, p);
    },
    true
  );
  window.addEventListener("resize", () => closeAll(null));
})();

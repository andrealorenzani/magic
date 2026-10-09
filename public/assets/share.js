// Progressive enhancement for sharing: copy a link (button, or by activating its QR code), "Share…" where the
// browser supports it, and the menu's hidden-details link. Without JavaScript the link stays selectable in its text field.
// This file never reads or writes browser storage; the remembered details arrive in a page event.
(() => {
  const field = (id) => document.getElementById(id);
  let resetTimer = null;
  const STATUS = "[data-copy-status], [data-hidden-status]";

  const showStatus = (scope, message) => {
    const status = scope.querySelector(STATUS);
    if (!status) return;
    status.textContent = message;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
      status.textContent = "";
    }, 6000);
  };

  // Copies a text (or a promise of one). Returns true when the browser accepted it.
  const copyText = async (source, input) => {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        if (typeof ClipboardItem !== "undefined" && navigator.clipboard.write) {
          const blob = Promise.resolve(source).then((t) => new Blob([t], { type: "text/plain" }));
          await navigator.clipboard.write([new ClipboardItem({ "text/plain": blob })]);
          return true;
        }
      } catch (e) {
        /* try the next way */
      }
      try {
        await navigator.clipboard.writeText(await source);
        return true;
      } catch (e) {
        /* try the next way */
      }
    }
    const text = await source;
    if (input.value !== text) input.value = text;
    const more = input.closest("details");
    if (more) more.open = true;
    input.focus();
    input.select();
    try {
      if (document.execCommand("copy")) return true;
    } catch (e) {
      /* fall through */
    }
    return false;
  };

  const copyWithFeedback = async (input, scope) => {
    let ok = false;
    try {
      ok = await copyText(input.value, input);
    } catch (e) {
      ok = false;
    }
    showStatus(scope, ok ? "Link copied" : "Press Ctrl+C (or long-press) to copy.");
    return ok;
  };

  const makeCopyable = (box, input, scope) => {
    box.setAttribute("role", "button");
    box.setAttribute("tabindex", "0");
    box.setAttribute("aria-label", "Copy link (QR code)");
    box.addEventListener("click", () => {
      if (input.value !== "") copyWithFeedback(input, scope);
    });
    box.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " " || e.key === "Spacebar") {
        e.preventDefault();
        if (input.value !== "") copyWithFeedback(input, scope);
      }
    });
  };

  document.querySelectorAll("[data-copy]").forEach((btn) => {
    const input = field(btn.dataset.copy);
    if (!input) return;
    const scope = btn.closest("section") || document;
    const label = btn.textContent;
    btn.hidden = false;
    btn.addEventListener("click", async () => {
      const ok = await copyWithFeedback(input, scope);
      if (ok) {
        btn.textContent = "Link copied";
        setTimeout(() => {
          btn.textContent = label;
        }, 4000);
      }
    });
  });

  document.querySelectorAll("[data-qr-copy]").forEach((box) => {
    const input = field(box.dataset.qrCopy);
    if (!input) return;
    const scope = box.closest("[data-hidden-panel]") || box.closest("section") || document;
    makeCopyable(box, input, scope);
    const hint = scope.querySelector("[data-qr-hint]");
    if (hint) hint.hidden = false;
  });

  // ---- the menu's hidden-details link ----
  const SVG = "http://www.w3.org/2000/svg";
  const drawQr = (holder, size, path) => {
    while (holder.firstChild) holder.removeChild(holder.firstChild);
    const svg = document.createElementNS(SVG, "svg");
    svg.setAttribute("class", "qr");
    svg.setAttribute("viewBox", "0 0 " + size + " " + size);
    svg.setAttribute("role", "img");
    svg.setAttribute("aria-label", "QR code of the link");
    svg.setAttribute("shape-rendering", "crispEdges");
    const rect = document.createElementNS(SVG, "rect");
    rect.setAttribute("width", String(size));
    rect.setAttribute("height", String(size));
    rect.setAttribute("fill", "#fff");
    const p = document.createElementNS(SVG, "path");
    p.setAttribute("d", path);
    p.setAttribute("fill", "#000");
    svg.appendChild(rect);
    svg.appendChild(p);
    holder.appendChild(svg);
  };

  const panel = document.querySelector("[data-hidden-panel]");
  if (panel) {
    const input = panel.querySelector("[data-hidden-link]");
    const qrBox = panel.querySelector("[data-hidden-qrbox]");
    const qrHolder = panel.querySelector("[data-hidden-qr]");
    const copyBtn = panel.querySelector("[data-hidden-copy]");
    const MESSAGES = {
      terms: "Please accept the Terms again, then try once more.",
      data: "Open Self discovery and submit once, then try again.",
    };
    if (copyBtn && input) {
      copyBtn.addEventListener("click", () => {
        if (input.value !== "") copyWithFeedback(input, panel);
      });
    }

    document.addEventListener("magic:share-hidden", (event) => {
      if (!input || !qrBox || !qrHolder) return;
      const d = event.detail || {};
      const pos = d.pos || {};
      const body = new URLSearchParams();
      ["name", "date", "time", "city", "lat", "lon", "tz"].forEach((f) => body.set(f, String(d[f] || "")));
      ["city", "lat", "lon", "tz"].forEach((f) => body.set("pos_" + f, String(pos[f] || "")));
      panel.hidden = false;
      qrBox.hidden = true;
      input.value = "";
      showStatus(panel, "Creating your link…");

      const request = fetch("hidden.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
        credentials: "same-origin",
        cache: "no-store",
      })
        .then((r) => r.json().catch(() => ({ ok: false, error: "server" })))
        .catch(() => ({ ok: false, error: "server" }));
      const link = request.then((j) => {
        if (!j || j.ok !== true || typeof j.link !== "string") {
          const err = new Error(j && typeof j.error === "string" ? j.error : "server");
          throw err;
        }
        return j.link;
      });

      Promise.all([request, copyText(link, input).catch(() => null)])
        .then(([j, ok]) => {
          if (!j || j.ok !== true || typeof j.link !== "string") {
            throw new Error(j && typeof j.error === "string" ? j.error : "server");
          }
          input.value = j.link;
          const lines = [];
          if (j.qr && typeof j.qr.path === "string" && Number.isInteger(j.qr.size)) {
            drawQr(qrHolder, j.qr.size, j.qr.path);
            qrBox.hidden = false;
          } else {
            lines.push("It is too long for a QR code.");
          }
          lines.unshift(ok ? "Link copied" : "Press Ctrl+C (or long-press) to copy.");
          showStatus(panel, lines.join(" "));
        })
        .catch((err) => {
          showStatus(panel, MESSAGES[err && err.message] || "Could not create the link. Please try again.");
        });
    });
  }

  if (!navigator.share) return;
  document.querySelectorAll("[data-share]").forEach((btn) => {
    const input = field(btn.dataset.share);
    if (!input) return;
    btn.hidden = false;
    btn.addEventListener("click", () => {
      navigator.share({ title: document.title, url: input.value }).catch(() => {});
    });
  });
})();

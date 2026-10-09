// "?" help popovers: each button toggles the text block that follows it. The block stays in the
// normal page flow. Without JavaScript the buttons stay hidden and the page is unchanged.
(() => {
  const buttons = Array.from(document.querySelectorAll(".help__btn"));
  if (buttons.length === 0) return;

  const pop = (btn) => document.getElementById(btn.getAttribute("aria-controls"));
  const close = (btn) => {
    const p = pop(btn);
    if (p) p.hidden = true;
    btn.setAttribute("aria-expanded", "false");
  };
  const closeAll = (except) => {
    buttons.forEach((b) => {
      if (b !== except) close(b);
    });
  };

  buttons.forEach((btn) => {
    btn.hidden = false;
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const p = pop(btn);
      if (!p) return;
      const open = p.hidden;
      closeAll(btn);
      p.hidden = !open;
      btn.setAttribute("aria-expanded", String(open));
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    const open = buttons.find((b) => b.getAttribute("aria-expanded") === "true");
    if (!open) return;
    close(open);
    open.focus();
    closeAll(null);
  });

  document.addEventListener("click", (e) => {
    if (e.target instanceof Element && e.target.closest(".help")) return;
    closeAll(null);
  });
})();

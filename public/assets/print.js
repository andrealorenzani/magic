// Reveals the Print button (hidden in the markup so that without JavaScript nothing is broken;
// the browser's own Print still works).
(() => {
  document.querySelectorAll("[data-print]").forEach((btn) => {
    btn.hidden = false;
    btn.addEventListener("click", () => window.print());
  });
})();

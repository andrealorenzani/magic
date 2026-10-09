// Progressive enhancement for the Share section: un-hides "Copy link" and, where the browser
// supports it, "Share…". Without JavaScript the link stays selectable in its text field.
(() => {
  const field = (id) => document.getElementById(id);
  document.querySelectorAll("[data-copy]").forEach((btn) => {
    const input = field(btn.dataset.copy);
    if (!input) return;
    btn.hidden = false;
    btn.addEventListener("click", async () => {
      input.select();
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(input.value);
        } else {
          document.execCommand("copy");
        }
        btn.textContent = "Copied";
      } catch (e) {
        input.select();
      }
    });
  });
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

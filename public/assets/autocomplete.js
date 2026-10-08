// Progressive enhancement: city suggestions that also fill hidden lat/lon/tz fields.
// One behaviour per [data-place] container; without JavaScript the server resolves the city text.
(() => {
  const setup = (box) => {
    const field = (name) => box.querySelector(`[data-field="${name}"]`);
    const city = field("city");
    const list = box.querySelector('[data-role="list"]');
    const status = box.querySelector('[data-role="status"]');
    const fields = { lat: field("lat"), lon: field("lon"), tz: field("tz") };
    if (!city || !list || !status) return;
    let timer = null;
    let controller = null;
    let active = -1;
    let items = [];
    const options = () => Array.from(list.children);

    function setActive(i) {
      const opts = options();
      active = opts.length === 0 ? -1 : (i + opts.length) % opts.length;
      opts.forEach((o, n) => o.setAttribute("aria-selected", String(n === active)));
      if (active >= 0) {
        city.setAttribute("aria-activedescendant", opts[active].id);
        opts[active].scrollIntoView?.({ block: "nearest" });
      } else {
        city.removeAttribute("aria-activedescendant");
      }
    }

    const clearPlace = () => Object.values(fields).forEach((f) => (f.value = ""));

    function choose(c) {
      city.value = c.label;
      fields.lat.value = c.lat;
      fields.lon.value = c.lon;
      fields.tz.value = c.timeZone;
      status.textContent = `Time zone: ${c.timeZone}`;
      list.hidden = true;
      city.setAttribute("aria-expanded", "false");
      city.removeAttribute("aria-activedescendant");
      active = -1;
    }

    function show(cities) {
      items = cities;
      active = -1;
      city.removeAttribute("aria-activedescendant");
      list.replaceChildren(
        ...cities.map((c, n) => {
          const li = document.createElement("li");
          li.id = `${list.id}-opt-${n}`;
          li.setAttribute("role", "option");
          li.setAttribute("aria-selected", "false");
          li.textContent = c.label;
          li.addEventListener("mousedown", (e) => { e.preventDefault(); choose(c); });
          return li;
        }),
      );
      list.hidden = cities.length === 0;
      city.setAttribute("aria-expanded", String(cities.length > 0));
    }

    city.addEventListener("input", () => {
      clearPlace();
      status.textContent = "";
      clearTimeout(timer);
      if (city.value.trim().length < 2) return show([]);
      timer = setTimeout(async () => {
        controller?.abort();
        controller = new AbortController();
        try {
          const res = await fetch(`api/cities.php?q=${encodeURIComponent(city.value.trim())}`, { signal: controller.signal });
          show(await res.json());
        } catch { /* aborted or offline: the server will resolve the text on submit */ }
      }, 250);
    });
    city.addEventListener("keydown", (e) => {
      const open = !list.hidden && items.length > 0;
      if (e.key === "ArrowDown" && open) { e.preventDefault(); setActive(active + 1); }
      else if (e.key === "ArrowUp" && open) { e.preventDefault(); setActive(active < 0 ? -1 : active - 1); }
      else if (e.key === "Enter" && open && active >= 0) { e.preventDefault(); choose(items[active]); }
      else if (e.key === "Escape" && open) { e.preventDefault(); show([]); }
    });
    city.addEventListener("blur", () => setTimeout(() => show([]), 120));
  };
  document.querySelectorAll("[data-place]").forEach(setup);
})();

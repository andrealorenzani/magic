// Progressive enhancement: city suggestions that also fill hidden lat/lon/tz fields.
// Without JavaScript the server resolves the city text itself.
(() => {
  const city = document.getElementById("city");
  const list = document.getElementById("city-list");
  const status = document.getElementById("status");
  const fields = { lat: document.getElementById("lat"), lon: document.getElementById("lon"), tz: document.getElementById("tz") };
  let timer = null;
  let controller = null;

  const clearPlace = () => Object.values(fields).forEach((f) => (f.value = ""));

  function choose(c) {
    city.value = c.label;
    fields.lat.value = c.lat;
    fields.lon.value = c.lon;
    fields.tz.value = c.timeZone;
    status.textContent = `Time zone: ${c.timeZone}`;
    list.hidden = true;
    city.setAttribute("aria-expanded", "false");
  }

  function show(cities) {
    list.replaceChildren(
      ...cities.map((c) => {
        const li = document.createElement("li");
        li.setAttribute("role", "option");
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
  city.addEventListener("blur", () => setTimeout(() => show([]), 120));
})();

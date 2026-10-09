// Remembers, in this browser only, the details the visitor typed for themselves and a short
// list of people they looked up. Nothing leaves the browser through this file (the menu hands the
// remembered details to share.js only when the visitor asks for a hidden link). Without
// JavaScript or storage the page works exactly the same.
(() => {
  const KEY_ME = "magic.me.v1";
  const KEY_LOVED = "magic.loved.v1";
  const PREFIX = "magic.";
  const MAX_PEOPLE = 20;
  const MAX_LOVED_BYTES = 16384;
  const MAX_ME_BYTES = 2048;
  const FLAG = "magic.justSaved";
  const MAX_RAW = 32768;
  const MAX_NAME = 40;
  const MAX_CITY = 80;
  const MAX_TZ = 64;
  const MAX_COORD = 12;
  const FIELDS = ["name", "date", "time", "city", "lat", "lon", "tz"];
  const POS_FIELDS = ["city", "lat", "lon", "tz"];

  // ---- storage access (every touch is guarded) ----
  let store = null;
  try {
    const s = window.localStorage;
    s.setItem(PREFIX + "probe", "1");
    s.removeItem(PREFIX + "probe");
    store = s;
  } catch (e) {
    store = null;
  }
  const rawGet = (key) => {
    try {
      return store ? store.getItem(key) : null;
    } catch (e) {
      return null;
    }
  };
  const rawSet = (key, value) => {
    try {
      if (store) store.setItem(key, value);
    } catch (e) {
      /* quota or blocked: ignore */
    }
  };
  const forgetAll = () => {
    try {
      window.sessionStorage.removeItem(FLAG);
    } catch (e) {
      /* ignore */
    }
    try {
      if (!store) return;
      const keys = [];
      for (let i = 0; i < store.length; i++) {
        const k = store.key(i);
        if (k && k.indexOf(PREFIX) === 0) keys.push(k);
      }
      keys.forEach((k) => store.removeItem(k));
    } catch (e) {
      /* ignore */
    }
  };

  // ---- validation ----
  const noControl = (s) => !/[\u0000-\u001f\u007f-\u009f]/.test(s);
  const text = (v, max) => (typeof v === "string" && v.length <= max && noControl(v) ? v : "");
  const clean = {
    name: (v) => text(v, MAX_NAME),
    date: (v) => {
      const m = typeof v === "string" ? /^(\d{4})-(\d{2})-(\d{2})$/.exec(v) : null;
      if (!m) return "";
      const y = +m[1];
      const mo = +m[2];
      const d = +m[3];
      return y >= 1000 && y <= 2100 && mo >= 1 && mo <= 12 && d >= 1 && d <= 31 ? v : "";
    },
    time: (v) => (typeof v === "string" && /^([01]\d|2[0-3]):[0-5]\d$/.test(v) ? v : ""),
    city: (v) => text(v, MAX_CITY),
    lat: (v) => coord(v, 90),
    lon: (v) => coord(v, 180),
    tz: (v) => (typeof v === "string" && v.length <= MAX_TZ && /^[A-Za-z0-9_+\/-]+$/.test(v) ? v : ""),
  };
  function coord(v, limit) {
    if (typeof v !== "string" || v.length > MAX_COORD || !/^-?\d{1,3}(\.\d{1,8})?$/.test(v)) return "";
    return Math.abs(parseFloat(v)) <= limit ? v : "";
  }
  const cleanEntry = (o) => {
    const src = o && typeof o === "object" ? o : {};
    const out = {};
    FIELDS.forEach((f) => {
      out[f] = clean[f](src[f]);
    });
    const p = src.pos && typeof src.pos === "object" ? src.pos : {};
    out.pos = {};
    POS_FIELDS.forEach((f) => {
      out.pos[f] = clean[f](p[f]);
    });
    return out;
  };

  const parse = (raw) => {
    if (typeof raw !== "string" || raw.length > MAX_RAW) return null;
    try {
      const o = JSON.parse(raw);
      return o && typeof o === "object" && !Array.isArray(o) && o.v === 1 ? o : null;
    } catch (e) {
      return null;
    }
  };
  const readMe = () => {
    const o = parse(rawGet(KEY_ME));
    return o ? cleanEntry(o) : null;
  };
  const readLoved = () => {
    const o = parse(rawGet(KEY_LOVED));
    if (!o || !Array.isArray(o.list)) return [];
    const out = [];
    o.list.slice(0, MAX_PEOPLE).forEach((item) => {
      const e = cleanEntry(item);
      if (e.name === "") return;
      e.id = e.name.toLowerCase();
      e.t = item && typeof item.t === "number" && isFinite(item.t) ? item.t : 0;
      out.push(e);
    });
    return out;
  };
  const writeMe = (entry) => {
    const o = Object.assign({ v: 1, t: Date.now() }, entry);
    const s = JSON.stringify(o);
    if (s.length <= MAX_ME_BYTES) rawSet(KEY_ME, s);
  };
  const writeLoved = (list) => {
    const items = list.slice().sort((a, b) => b.t - a.t);
    const size = () => JSON.stringify({ v: 1, list: items }).length;
    while (items.length > MAX_PEOPLE || (items.length > 0 && size() > MAX_LOVED_BYTES)) items.pop();
    rawSet(KEY_LOVED, JSON.stringify({ v: 1, list: items }));
  };

  // ---- form access ----
  const fieldMap = (fs) => {
    const prefix = fs.dataset.prefix || "";
    const map = {};
    fs.querySelectorAll("input[name]").forEach((el) => {
      if (el.name.indexOf(prefix) !== 0) return;
      map[el.name.slice(prefix.length)] = el;
    });
    return map;
  };
  const readFields = (fs) => {
    const map = fieldMap(fs);
    const raw = { pos: {} };
    FIELDS.forEach((f) => {
      if (map[f]) raw[f] = map[f].value.trim();
    });
    POS_FIELDS.forEach((f) => {
      if (map["pos_" + f]) raw.pos[f] = map["pos_" + f].value.trim();
    });
    return raw;
  };
  const writeFields = (fs, entry) => {
    const map = fieldMap(fs);
    FIELDS.forEach((f) => {
      if (map[f]) map[f].value = entry[f] || "";
    });
    POS_FIELDS.forEach((f) => {
      if (map["pos_" + f]) map["pos_" + f].value = (entry.pos && entry.pos[f]) || "";
    });
  };
  const isBlank = (fs) => {
    const map = fieldMap(fs);
    return Object.keys(map).every((k) => {
      const v = map[k].value.trim();
      return v === "" || (k === "time" && v === "12:00");
    });
  };
  const sameNumber = (a, b) => a === b || (a !== "" && b !== "" && Math.abs(parseFloat(a) - parseFloat(b)) < 0.00002);
  const same = (fs, entry) => {
    if (!entry) return false;
    const cur = cleanEntry(readFields(fs));
    const map = fieldMap(fs);
    const eq = (a, b, f) => (f === "lat" || f === "lon" ? sameNumber(a, b) : a === b);
    return (
      FIELDS.every((f) => !map[f] || eq(cur[f], entry[f], f)) &&
      POS_FIELDS.every((f) => !map["pos_" + f] || eq(cur.pos[f], entry.pos[f], f))
    );
  };
  const hasContent = (e) => e.date !== "" || e.city !== "" || e.name !== "";

  // ---- menu: clean browser data, share hidden data ----
  const initMenu = () => {
    const cleanBtn = document.querySelector("[data-menu-clean]");
    const row = document.querySelector("[data-menu-clean-row]");
    const panel = document.querySelector("[data-menu-clean-panel]");
    const yes = document.querySelector("[data-menu-clean-yes]");
    const no = document.querySelector("[data-menu-clean-no]");
    const form = document.querySelector("[data-menu-clean-form]");
    if (cleanBtn && panel && yes && no && form) {
      if (row) row.hidden = false;
      const heading = panel.querySelector("h3");
      const closePanel = () => {
        panel.hidden = true;
        cleanBtn.focus();
      };
      cleanBtn.addEventListener("click", () => {
        panel.hidden = false;
        if (heading) heading.focus();
      });
      no.addEventListener("click", closePanel);
      yes.addEventListener("click", () => {
        forgetAll();
        form.submit();
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && !panel.hidden) closePanel();
      });
    }
    const shareLink = document.querySelector("[data-menu-share-hidden]");
    if (shareLink) {
      const entry = store ? readMe() : null;
      const ready = !!entry && ["date", "time", "city", "lat", "lon", "tz"].every((f) => entry[f] !== "");
      if (!ready) {
        shareLink.setAttribute("aria-disabled", "true");
        shareLink.classList.add("is-empty");
      } else {
        shareLink.addEventListener("click", (e) => {
          e.preventDefault();
          const fresh = readMe();
          if (fresh) document.dispatchEvent(new CustomEvent("magic:share-hidden", { detail: fresh }));
        });
      }
    }
  };
  initMenu();

  if (document.querySelector("[data-forget-memory]")) {
    forgetAll();
    return;
  }
  document.querySelectorAll("[data-memory-forget-on-submit]").forEach((form) => {
    form.addEventListener("submit", forgetAll);
  });
  if (!store) return;

  // ---- per form ----
  const setText = (el, msg) => {
    if (el) el.textContent = msg;
  };

  document.querySelectorAll("form[data-memory]").forEach((form) => {
    const bar = form.querySelector("[data-memory-bar]");
    const saveBtn = form.querySelector("[data-memory-save]");
    const forgetBtn = form.querySelector("[data-memory-forget]");
    const status = form.querySelector("[data-memory-status]");
    const savedBox = form.querySelector("[data-saved]");
    const select = form.querySelector("[data-saved-select]");
    const removeBtn = form.querySelector("[data-saved-remove]");
    const hiddenMode = !!form.querySelector("[data-hidden-person]");
    const people = Array.from(form.querySelectorAll("fieldset[data-person]")).filter((fs) => !(hiddenMode && fs.dataset.person === "loved")).map((fs) => ({
      fs,
      kind: fs.dataset.person,
      auto: true,
    }));
    const lovedFs = people.find((p) => p.kind === "loved");

    const saveMe = (p) => {
      const entry = cleanEntry(readFields(p.fs));
      if (hasContent(entry)) writeMe(entry);
    };
    const saveLoved = (p) => {
      const entry = cleanEntry(readFields(p.fs));
      if (entry.name === "") return;
      entry.id = entry.name.toLowerCase();
      entry.t = Date.now();
      const list = readLoved().filter((x) => x.id !== entry.id);
      list.push(entry);
      writeLoved(list);
    };
    const saveOne = (p) => (p.kind === "me" ? saveMe(p) : saveLoved(p));

    const refreshSaved = () => {
      if (!select || !savedBox) return;
      const list = readLoved().sort((a, b) => a.name.localeCompare(b.name));
      while (select.options.length > 1) select.remove(1);
      list.forEach((e) => {
        const opt = document.createElement("option");
        opt.value = e.id;
        opt.textContent = e.name;
        select.appendChild(opt);
      });
      savedBox.hidden = list.length === 0;
    };
    const refreshSave = () => {
      if (!saveBtn) return;
      saveBtn.hidden = !people.some((p) => !p.auto && hasContent(cleanEntry(readFields(p.fs))));
    };

    // Prefill fresh forms; decide whether filled forms may be saved automatically.
    const me = readMe();
    const loved = readLoved();
    people.forEach((p) => {
      if (isBlank(p.fs)) {
        if (p.kind === "me" && me) writeFields(p.fs, me);
        return;
      }
      const stored = p.kind === "me" ? me : loved.find((x) => x.id === cleanEntry(readFields(p.fs)).name.toLowerCase());
      p.auto = same(p.fs, stored);
    });

    form.addEventListener("submit", () => {
      try {
        let saved = false;
        people.forEach((p) => {
          if (p.auto) {
            saveOne(p);
            saved = true;
          }
        });
        if (saved) window.sessionStorage.setItem(FLAG, "1");
      } catch (e) {
        /* never block the submit */
      }
    });

    if (select && lovedFs) {
      select.addEventListener("change", () => {
        const e = readLoved().find((x) => x.id === select.value);
        if (!e) return;
        writeFields(lovedFs.fs, e);
        lovedFs.auto = true;
        refreshSave();
      });
    }
    if (removeBtn && select) {
      removeBtn.addEventListener("click", () => {
        const id = select.value;
        if (!id) return;
        writeLoved(readLoved().filter((x) => x.id !== id));
        refreshSaved();
        setText(status, "Removed from this browser.");
      });
    }
    if (saveBtn) {
      saveBtn.addEventListener("click", () => {
        people.forEach((p) => {
          if (!p.auto) {
            saveOne(p);
            p.auto = true;
          }
        });
        refreshSaved();
        refreshSave();
        setText(status, "Saved in this browser.");
      });
    }
    if (forgetBtn) {
      forgetBtn.addEventListener("click", () => {
        forgetAll();
        refreshSaved();
        setText(status, "Removed from this browser.");
      });
    }

    try {
      if (window.sessionStorage.getItem(FLAG)) {
        window.sessionStorage.removeItem(FLAG);
        setText(status, "Saved in this browser.");
      }
    } catch (e) {
      /* ignore */
    }
    refreshSaved();
    refreshSave();
    if (bar) bar.hidden = false;
  });
})();

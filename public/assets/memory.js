// Remembers, in this browser only, the details the visitor typed for themselves, a short list of
// people they looked up and the hidden codes friends shared with them. Nothing leaves the browser
// through this file (the remembered details go to share.js only when the visitor asks for a hidden
// code). Without JavaScript or storage the page works the same, except for the parts that need storage.
(() => {
  const KEY_ME = "magic.me.v1";
  const KEY_LOVED = "magic.loved.v1";
  const KEY_FRIENDS = "magic.friends.v1";
  const PREFIX = "magic.";
  const MAX_PEOPLE = 20;
  const MAX_LOVED_BYTES = 16384;
  const MAX_ME_BYTES = 2048;
  const FLAG = "magic.justSaved";
  const FRIEND_FLAG = "magic.friendHandled";
  const NO_STORE_TEXT = "This browser does not allow storage, so Friends and Soul Affinity cannot remember anything here.";
  const MAX_RAW = 32768;
  const REQUIRED = ["date", "time", "city", "lat", "lon", "tz"];
  const MAX_FRIENDS = 60;
  const MAX_FRIENDS_BYTES = 40000;
  const MAX_FRIENDS_RAW = 49152;
  const MAX_CODE = 400;
  const MAX_NICK = 40;
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
      window.sessionStorage.removeItem(FRIEND_FLAG);
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

  const parse = (raw, limit) => {
    if (typeof raw !== "string" || raw.length > (limit || MAX_RAW)) return null;
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

  // ---- friends: hidden codes with a nickname ----
  const validCode = (v) => (typeof v === "string" && v.length >= 1 && v.length <= MAX_CODE && /^[A-Za-z0-9_-]+$/.test(v) ? v : "");
  const nickText = (v) => text(typeof v === "string" ? v.trim() : "", MAX_NICK);
  const readFriends = () => {
    const o = parse(rawGet(KEY_FRIENDS), MAX_FRIENDS_RAW);
    if (!o || !Array.isArray(o.list)) return [];
    const seen = {};
    const out = [];
    o.list.slice(0, MAX_FRIENDS).forEach((item) => {
      if (!item || typeof item !== "object") return;
      const code = validCode(item.code);
      if (code === "" || seen[code]) return;
      seen[code] = true;
      out.push({ code, nick: nickText(item.nick), t: typeof item.t === "number" && isFinite(item.t) ? item.t : 0 });
    });
    return out;
  };
  const writeFriends = (list) => {
    const items = list.slice().sort((a, b) => b.t - a.t);
    const size = () => JSON.stringify({ v: 1, list: items }).length;
    while (items.length > MAX_FRIENDS || (items.length > 0 && size() > MAX_FRIENDS_BYTES)) items.pop();
    rawSet(KEY_FRIENDS, JSON.stringify({ v: 1, list: items }));
  };
  const addFriend = (code, nick) => {
    const c = validCode(code);
    if (c === "" || !store) return;
    const n = nickText(nick);
    const list = readFriends();
    const known = list.find((x) => x.code === c);
    if (known) {
      if (n !== "") known.nick = n;
    } else {
      list.push({ code: c, nick: n, t: Date.now() });
    }
    writeFriends(list);
  };
  // The code of a pasted link or a bare code; the browser never decodes it, the server decides.
  const extractCode = (raw) => {
    const t = typeof raw === "string" ? raw.trim() : "";
    const m = /(?:^|[?&#])h=([A-Za-z0-9_-]{1,400})(?![A-Za-z0-9_-])/.exec(t);
    if (m) return /^M[A-P][A-Za-z0-9_-]{6,398}$/.test(m[1]) ? m[1] : "";
    return /^M[A-P][A-Za-z0-9_-]{6,398}$/.test(t) ? t : "";
  };
  const hasFriend = (code) => readFriends().some((x) => x.code === code);
  const fold = (s) => {
    let out = String(s).toLowerCase();
    try {
      out = out.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch (e) {
      /* keep the lower-cased text */
    }
    return out;
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

  // ---- shared confirmation dialog ----
  const confirmBox = (title, message) =>
    new Promise((resolve) => {
      const dlg = document.querySelector("[data-confirm]");
      const yes = dlg ? dlg.querySelector("[data-confirm-yes]") : null;
      const no = dlg ? dlg.querySelector("[data-confirm-no]") : null;
      if (!dlg || !yes || !no) {
        resolve(false);
        return;
      }
      const opener = document.activeElement;
      const t = dlg.querySelector("[data-confirm-title]");
      const p = dlg.querySelector("[data-confirm-text]");
      if (t) t.textContent = title;
      if (p) p.textContent = message;
      let done = false;
      const finish = (value) => {
        if (done) return;
        done = true;
        yes.removeEventListener("click", onYes);
        no.removeEventListener("click", onNo);
        dlg.removeEventListener("cancel", onCancel);
        dlg.removeEventListener("close", onNo);
        document.removeEventListener("keydown", onKey);
        try {
          if (typeof dlg.close === "function") dlg.close();
        } catch (e) {
          /* already closed */
        }
        dlg.removeAttribute("open");
        if (opener && typeof opener.focus === "function") opener.focus();
        resolve(value);
      };
      const onYes = () => finish(true);
      const onNo = () => finish(false);
      const onCancel = (e) => {
        e.preventDefault();
        finish(false);
      };
      const onKey = (e) => {
        if (e.key === "Escape") finish(false);
      };
      yes.addEventListener("click", onYes);
      no.addEventListener("click", onNo);
      dlg.addEventListener("cancel", onCancel);
      dlg.addEventListener("close", onNo);
      document.addEventListener("keydown", onKey);
      try {
        if (typeof dlg.showModal === "function") dlg.showModal();
        else dlg.setAttribute("open", "");
      } catch (e) {
        dlg.setAttribute("open", "");
      }
      no.focus();
    });

  const setText = (el, msg) => {
    if (el) el.textContent = msg;
  };
  const complete = (e) => !!e && REQUIRED.every((f) => e[f] !== "");
  const hasAnyStored = () => {
    try {
      if (!store) return false;
      for (let i = 0; i < store.length; i++) {
        const k = store.key(i);
        if (k && k.indexOf(PREFIX) === 0 && k !== PREFIX + "probe") return true;
      }
    } catch (e) {
      /* ignore */
    }
    return false;
  };
  const setEnabled = (btn, on) => {
    if (!btn) return;
    btn.classList.toggle("is-empty", !on);
    if (on) btn.removeAttribute("aria-disabled");
    else btn.setAttribute("aria-disabled", "true");
  };
  const aParams = (me) => {
    const parts = ["a_name=" + encodeURIComponent(me.name || "Me")];
    ["date", "time", "city", "lat", "lon", "tz"].forEach((f) => parts.push("a_" + f + "=" + encodeURIComponent(me[f])));
    POS_FIELDS.forEach((f) => {
      if (me.pos && me.pos[f]) parts.push("a_pos_" + f + "=" + encodeURIComponent(me.pos[f]));
    });
    return parts.join("&");
  };

  // ---- locks: Soul Affinity and Friends need a complete Self entry ----
  const unlockSections = () => {
    document.querySelectorAll("[data-needs-self]").forEach((card) => {
      card.classList.remove("is-locked");
      card.removeAttribute("aria-disabled");
      if (card.dataset.unlockHref) card.setAttribute("href", card.dataset.unlockHref);
      const lock = card.querySelector("[data-lock-text]");
      const open = card.querySelector("[data-unlock-text]");
      if (lock) lock.hidden = true;
      if (open) open.hidden = false;
    });
    ["[data-love-locked]", "[data-friends-locked]"].forEach((sel) => {
      const el = document.querySelector(sel);
      if (el) el.hidden = true;
    });
    ["[data-love-body]", "[data-friends-body]"].forEach((sel) => {
      const el = document.querySelector(sel);
      if (el) el.hidden = false;
    });
  };

  // ---- Self Discovery: actions ----
  const initSelf = (form) => {
    const fs = form.querySelector("fieldset[data-person='me']");
    if (!fs) return;
    const actions = form.querySelector("[data-self-actions]");
    const saveBtn = form.querySelector("[data-self-save]");
    const clearBtn = form.querySelector("[data-self-clear]");
    const codeBtn = form.querySelector("[data-self-hidden-code]");
    const status = form.querySelector("[data-self-status]");
    const bar = form.querySelector("[data-memory-bar]");
    const entryNow = () => cleanEntry(readFields(fs));
    const placeChanged = (a, b) => (b.lat !== "" && b.lon !== "" ? !(sameNumber(a.lat, b.lat) && sameNumber(a.lon, b.lon) && a.tz === b.tz) : a.city !== b.city);
    const changed = (stored, cur) => stored.name !== cur.name || stored.date !== cur.date || stored.time !== cur.time || placeChanged(stored, cur);
    const needsConfirm = (cur) => {
      const stored = store ? readMe() : null;
      return complete(stored) && changed(stored, cur);
    };
    const CHANGE_TITLE = "Change your data?";
    const CHANGE_TEXT = "Changing your own data erases all previous data in this browser, including the hidden data shared by friends. Continue?";
    const pending = () => {
      const box = form.querySelector("[data-pending-friend]");
      if (!box) return;
      const h = box.querySelector('input[name="h"]');
      const n = box.querySelector('input[name="nick"]');
      const c = h ? validCode(h.value) : "";
      if (c === "" || !store) return "";
      let result = "known";
      if (!hasFriend(c)) {
        addFriend(c, n ? n.value : "");
        result = "added";
      }
      // Once handled, the code and nickname no longer travel with the form.
      if (box.parentNode) box.parentNode.removeChild(box);
      return result;
    };
    const FRIEND_TEXT = { added: "Friend added to Friends hidden codes.", known: "This friend is already in Friends hidden codes." };
    const refresh = () => {
      setEnabled(codeBtn, complete(store ? readMe() : null));
      setEnabled(clearBtn, hasAnyStored());
    };

    if (store) {
      const me = readMe();
      if (me && isBlank(fs)) writeFields(fs, me);
    }
    if (bar) bar.hidden = false;
    if (actions && saveBtn) saveBtn.hidden = false;

    const afterSubmitStore = () => {
      const cur = entryNow();
      if (hasContent(cur)) writeMe(cur);
      const friend = pending();
      try {
        if (friend !== "") window.sessionStorage.setItem(FRIEND_FLAG, friend);
        window.sessionStorage.setItem(FLAG, "1");
      } catch (e) {
        /* never block the submit */
      }
    };
    form.addEventListener("submit", (ev) => {
      if (!store) return;
      if (needsConfirm(entryNow())) {
        ev.preventDefault();
        confirmBox(CHANGE_TITLE, CHANGE_TEXT).then((ok) => {
          if (!ok) return;
          forgetAll();
          afterSubmitStore();
          form.submit();
        });
        return;
      }
      afterSubmitStore();
    });

    if (saveBtn) {
      saveBtn.addEventListener("click", () => {
        if (!store) {
          setText(status, "This browser does not allow storage, so nothing can be saved.");
          return;
        }
        const cur = entryNow();
        if (!complete(cur)) {
          setText(status, "To save, fill in the birth date and time and pick the birth city from the suggestions.");
          return;
        }
        const proceed = () => {
          writeMe(cur);
          const friend = pending();
          setText(status, "Saved in this browser." + (friend !== "" ? " " + FRIEND_TEXT[friend] : ""));
          refresh();
          unlockSections();
        };
        if (needsConfirm(cur)) {
          confirmBox(CHANGE_TITLE, CHANGE_TEXT).then((ok) => {
            if (!ok) return;
            forgetAll();
            proceed();
          });
        } else {
          proceed();
        }
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener("click", () => {
        if (clearBtn.getAttribute("aria-disabled") === "true") {
          setText(status, store ? "There is nothing stored in this browser to clear." : NO_STORE_TEXT);
          return;
        }
        confirmBox(
          "Clear data?",
          "This erases everything this browser remembers: your Self Discovery data, saved people and the hidden codes your friends shared. It cannot be undone."
        ).then((ok) => {
          if (!ok) return;
          forgetAll();
          window.location.assign("./?mode=self");
        });
      });
    }
    if (codeBtn) {
      codeBtn.addEventListener("click", () => {
        if (codeBtn.getAttribute("aria-disabled") === "true") {
          setText(status, store ? "Save your Self Discovery data first, then you can generate a hidden code." : NO_STORE_TEXT);
          return;
        }
        const fresh = readMe();
        if (fresh) document.dispatchEvent(new CustomEvent("magic:share-hidden", { detail: fresh }));
      });
    }
    try {
      if (store && window.sessionStorage.getItem(FLAG)) {
        window.sessionStorage.removeItem(FLAG);
        const cur = entryNow();
        if (hasContent(cur)) writeMe(cur);
        let friend = window.sessionStorage.getItem(FRIEND_FLAG);
        window.sessionStorage.removeItem(FRIEND_FLAG);
        setText(status, "Saved in this browser." + (friend === "added" || friend === "known" ? " " + FRIEND_TEXT[friend] : ""));
      }
    } catch (e) {
      /* ignore */
    }
    refresh();
  };

  // ---- Soul Affinity ----
  const initLove = (form) => {
    const carried = form.querySelector("fieldset[data-carried]");
    const notice = form.querySelector("[data-carried-notice]");
    const useBtn = form.querySelector("[data-carried-use]");
    const bar = form.querySelector("[data-memory-bar]");
    const status = form.querySelector("[data-memory-status]");
    const savedBox = form.querySelector("[data-saved]");
    const select = form.querySelector("[data-saved-select]");
    const removeBtn = form.querySelector("[data-saved-remove]");
    const saveBtn = form.querySelector("[data-memory-save]");
    const hiddenMode = !!form.querySelector("[data-hidden-person]");
    const lovedFs = hiddenMode ? null : form.querySelector("fieldset[data-person='loved']");
    const me = readMe();
    const meFull = complete(me) ? Object.assign({}, me, { name: me.name || "Me" }) : null;

    if (carried && meFull) {
      if (isBlank(carried)) {
        writeFields(carried, meFull);
        unlockSections();
      } else if (!same(carried, meFull) && notice) {
        notice.hidden = false;
        if (useBtn) {
          useBtn.addEventListener("click", () => {
            writeFields(carried, meFull);
            notice.hidden = true;
            setText(status, "Your Self Discovery data is used now. Press Explore our connection.");
          });
        }
      }
    }

    // A hidden code is added to Friends only when the form is submitted.
    const hInput = form.querySelector('input[name="h"]');
    const impInput = form.querySelector('input[name="import"]');
    const nickInput = form.querySelector('input[name="nick"]');
    form.addEventListener("submit", () => {
      const code = hInput ? hInput.value : impInput ? extractCode(impInput.value) : "";
      if (code !== "") addFriend(code, nickInput ? nickInput.value : "");
    });

    if (!lovedFs) {
      if (bar) bar.hidden = false;
      return;
    }
    let auto = true;
    const saveLoved = () => {
      const entry = cleanEntry(readFields(lovedFs));
      if (entry.name === "") return;
      entry.id = entry.name.toLowerCase();
      entry.t = Date.now();
      const list = readLoved().filter((x) => x.id !== entry.id);
      list.push(entry);
      writeLoved(list);
    };
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
      if (saveBtn) saveBtn.hidden = auto || !hasContent(cleanEntry(readFields(lovedFs)));
    };
    if (!isBlank(lovedFs)) {
      const stored = readLoved().find((x) => x.id === cleanEntry(readFields(lovedFs)).name.toLowerCase());
      auto = same(lovedFs, stored);
    }
    form.addEventListener("submit", () => {
      try {
        if (auto) {
          saveLoved();
          window.sessionStorage.removeItem(FRIEND_FLAG);
          window.sessionStorage.setItem(FLAG, "1");
        }
      } catch (e) {
        /* never block the submit */
      }
    });
    if (select) {
      select.addEventListener("change", () => {
        const e = readLoved().find((x) => x.id === select.value);
        if (!e) return;
        writeFields(lovedFs, e);
        auto = true;
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
        saveLoved();
        auto = true;
        refreshSaved();
        refreshSave();
        setText(status, "Saved in this browser.");
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
  };

  // ---- Friends hidden codes ----
  const initFriends = (root) => {
    const search = root.querySelector("[data-friends-search]");
    const count = root.querySelector("[data-friends-count]");
    const list = root.querySelector("[data-friends-list]");
    const tpl = root.querySelector("template[data-friends-row]");
    const empty = root.querySelector("[data-friends-empty]");
    const selectAll = root.querySelector("[data-friends-selectall]");
    const removeSel = root.querySelector("[data-friends-remove-selected]");
    const status = root.querySelector("[data-friends-status]");
    const selected = root.querySelector("[data-friends-selected]");
    if (!search || !list || !tpl || !tpl.content) return;
    const me = readMe();

    const compareHref = (f) =>
      complete(me)
        ? "./?mode=love&h=" + encodeURIComponent(f.code) + (f.nick !== "" ? "&nick=" + encodeURIComponent(f.nick) : "") + "&" + aParams(me)
        : "./?mode=self";
    const checks = () => Array.from(list.querySelectorAll("[data-friend-check]"));
    const label = (el, t) => {
      if (!el) return;
      el.setAttribute("aria-label", t);
      el.setAttribute("title", t);
    };
    const updateBulk = () => {
      const all = checks();
      const n = all.filter((c) => c.checked).length;
      if (removeSel) {
        removeSel.disabled = n === 0;
        label(removeSel, "Remove selected (" + n + ")");
      }
      setText(selected, n > 0 ? String(n) : "");
      label(selectAll, all.length > 0 && n === all.length ? "Select none" : "Select all shown");
    };
    const askRemove = (codes) =>
      confirmBox("Remove hidden codes?", "Remove " + codes.length + " hidden code(s) from this browser?").then((ok) => {
        if (!ok) return;
        writeFriends(readFriends().filter((x) => codes.indexOf(x.code) < 0));
        setText(status, "Removed from this browser.");
        render();
        search.focus();
      });
    const render = () => {
      const q = fold(search.value.trim());
      const all = readFriends().sort((a, b) => b.t - a.t);
      while (list.firstChild) list.removeChild(list.firstChild);
      let shown = 0;
      all.forEach((f) => {
        if (q !== "" && fold(f.nick).indexOf(q) < 0) return;
        shown++;
        const li = tpl.content.firstElementChild.cloneNode(true);
        li.dataset.code = f.code;
        const nick = li.querySelector("[data-friend-nick]");
        const date = li.querySelector("[data-friend-date]");
        const cmp = li.querySelector("[data-friend-compare]");
        const rm = li.querySelector("[data-friend-remove]");
        const chk = li.querySelector("[data-friend-check]");
        nick.value = f.nick;
        const names = (n) => {
          label(cmp, n !== "" ? "Compare with " + n : "Compare with this friend");
          label(rm, n !== "" ? "Remove " + n : "Remove this friend");
        };
        names(f.nick);
        if (f.t > 0) date.textContent = "Added " + new Date(f.t).toLocaleDateString();
        cmp.setAttribute("href", compareHref(f));
        nick.addEventListener("change", () => {
          const n = nickText(nick.value);
          nick.value = n;
          writeFriends(readFriends().map((x) => (x.code === f.code ? { code: x.code, nick: n, t: x.t } : x)));
          cmp.setAttribute("href", compareHref({ code: f.code, nick: n }));
          names(n);
        });
        rm.addEventListener("click", () => askRemove([f.code]));
        chk.addEventListener("change", updateBulk);
        list.appendChild(li);
      });
      setText(count, "Showing " + shown + " of " + all.length);
      if (empty) empty.hidden = all.length > 0;
      updateBulk();
    };
    search.addEventListener("input", render);
    if (selectAll) {
      selectAll.addEventListener("click", () => {
        const all = checks();
        const every = all.length > 0 && all.every((c) => c.checked);
        all.forEach((c) => {
          c.checked = !every;
        });
        updateBulk();
      });
    }
    if (removeSel) {
      removeSel.addEventListener("click", () => {
        const codes = checks()
          .filter((c) => c.checked)
          .map((c) => c.closest("li").dataset.code);
        if (codes.length > 0) askRemove(codes);
      });
    }
    render();
  };

  // ---- start ----
  if (document.querySelector("[data-forget-memory]")) {
    forgetAll();
    return;
  }
  document.querySelectorAll("[data-memory-forget-on-submit]").forEach((form) => {
    form.addEventListener("submit", forgetAll);
  });
  const selfForm = document.querySelector("form[data-memory='self']");
  if (selfForm) initSelf(selfForm);
  if (!store) {
    document.querySelectorAll("[data-nostore]").forEach((el) => {
      el.hidden = false;
    });
    return;
  }
  if (complete(readMe())) unlockSections();
  const loveForm = document.querySelector("form[data-memory='love']");
  if (loveForm) initLove(loveForm);
  const friendsRoot = document.querySelector("[data-friends]");
  if (friendsRoot) initFriends(friendsRoot);
})();

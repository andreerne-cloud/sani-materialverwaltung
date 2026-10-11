/*
 * WordPress-Anbindung der Materialverwaltung.
 *
 * Die App (app/app.html) ist dieselbe Datei wie die Claude-Version. Sie spricht ihre
 * Datenbank über window.claude.use("db") an. Dieser Adapter stellt genau diese Schnittstelle
 * bereit, speichert aber über die REST-API des Plugins in der WordPress-Datenbank.
 * Änderungen anderer Benutzer kommen per regelmässigem Abgleich (alle 15 s) herein.
 */
(function () {
  "use strict";
  var C = window.SANI_MV || {};
  var store = new Map();          // Sammlung → Map(id → Daten)
  var subs = [];                  // aktive onSnapshot-Abos
  var since = 0, loaded = false, nonceDead = false;
  var pending = new Map();        // "Sammlung/id" → laufende eigene Schreibvorgänge (Abgleich überschreibt sie nicht)

  function err(code, message) { var e = new Error(message || code); e.code = code; e.message = message || code; return e; }

  function sessionExpired() {
    if (nonceDead) return; nonceDead = true;
    var bar = document.createElement("div");
    bar.setAttribute("role", "alert");
    bar.style.cssText = "position:fixed;left:0;right:0;top:0;z-index:100;background:#c4001a;color:#fff;padding:10px 16px;font:14px system-ui,sans-serif;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap";
    bar.innerHTML = "Deine Anmeldung ist abgelaufen. Änderungen werden nicht mehr gespeichert. ";
    var b = document.createElement("button"); b.textContent = "Seite neu laden";
    b.style.cssText = "font:inherit;padding:4px 10px;border-radius:6px;border:0;cursor:pointer";
    b.onclick = function () { location.reload(); };
    bar.appendChild(b); document.body.appendChild(bar);
  }

  // Funktioniert mit schönen Permalinks (/wp-json/…) und ohne (?rest_route=…).
  function endpoint(path, params) {
    var u = new URL(C.rest, location.href);
    if (u.searchParams.has("rest_route")) u.searchParams.set("rest_route", u.searchParams.get("rest_route") + path);
    else u.pathname += path;
    Object.keys(params || {}).forEach(function (k) { u.searchParams.set(k, params[k]); });
    return u.toString();
  }

  async function api(method, path, body, params) {
    var res;
    try {
      res = await fetch(endpoint(path, params), {
        method: method, credentials: "same-origin", cache: "no-store",
        headers: {"X-WP-Nonce": C.nonce, "Content-Type": "application/json"},
        body: body === undefined ? undefined : JSON.stringify(body)
      });
    } catch (e) { throw err("unavailable", "Keine Verbindung zum Server."); }
    if (res.ok) return res.json();
    var info = {}; try { info = await res.json(); } catch (e) {}
    if (info.code === "rest_cookie_invalid_nonce" || res.status === 401) { sessionExpired(); throw err("revoked", "Anmeldung abgelaufen."); }
    if (res.status === 403) throw err("invalid_argument", "Keine Schreibrechte.");
    if (res.status === 400) throw err("invalid_argument", info.message);
    throw err("unavailable", info.message || ("Serverfehler " + res.status));
  }

  function coll(name) { if (!store.has(name)) store.set(name, new Map()); return store.get(name); }
  function docSnap(c, id) { var d = coll(c).get(id); return {id: id, exists: d !== undefined, data: function () { return d; }, metadata: {fromCache: false, hasPendingWrites: false}}; }
  function collSnap(c) {
    var docs = []; coll(c).forEach(function (d, id) { docs.push({id: id, exists: true, data: function () { return d; }, metadata: {fromCache: false, hasPendingWrites: false}}); });
    return {docs: docs, size: docs.length, empty: !docs.length, docChanges: function () { return []; }, metadata: {fromCache: false, hasPendingWrites: false}};
  }
  function deliver(s) { try { s.cb(s.id ? docSnap(s.coll, s.id) : collSnap(s.coll)); } catch (e) { console.error(e); } }
  function notify(changed) { subs.forEach(function (s) { if (!changed || changed.has(s.coll)) deliver(s); }); }

  async function pull() {
    if (nonceDead) return;
    try {
      var res = await api("GET", "docs", undefined, since ? {since: since} : null);
      var changed = new Set();
      res.docs.forEach(function (d) {
        if (pending.get(d.coll + "/" + d.id)) return;
        var m = coll(d.coll), before = m.get(d.id);
        if (d.deleted) { if (before !== undefined) { m.delete(d.id); changed.add(d.coll); } }
        else if (JSON.stringify(before) !== JSON.stringify(d.data)) { m.set(d.id, d.data); changed.add(d.coll); }
      });
      since = res.now;
      if (!loaded) { loaded = true; notify(); } else if (changed.size) notify(changed);
    } catch (e) {
      if (!loaded && e.code !== "revoked") setTimeout(pull, 3000);
    }
  }

  function subscribe(s) {
    subs.push(s);
    if (loaded) setTimeout(function () { deliver(s); }, 0);
    return function () { var i = subs.indexOf(s); if (i >= 0) subs.splice(i, 1); };
  }

  async function track(c, id, promise) {
    var k = c + "/" + id; pending.set(k, (pending.get(k) || 0) + 1);
    try { return await promise; } finally { var n = pending.get(k) - 1; n ? pending.set(k, n) : pending.delete(k); }
  }

  function docRef(c, id) {
    return {
      id: id, path: c + "/" + id,
      get: async function () { return docSnap(c, id); },
      onSnapshot: function (cb) { return subscribe({coll: c, id: id, cb: cb}); },
      set: async function (data) {
        var copy = JSON.parse(JSON.stringify(data));
        coll(c).set(id, copy); notify(new Set([c]));
        await track(c, id, api("PUT", "docs/" + encodeURIComponent(c) + "/" + encodeURIComponent(id), {data: copy}));
      },
      update: async function (patch) { var cur = coll(c).get(id) || {}; return this.set(Object.assign({}, cur, patch)); },
      delete: async function () {
        coll(c).delete(id); notify(new Set([c]));
        await track(c, id, api("DELETE", "docs/" + encodeURIComponent(c) + "/" + encodeURIComponent(id)));
      }
    };
  }

  var db = {
    collection: function (c) {
      return {
        path: c,
        doc: function (id) { return docRef(c, id || Math.random().toString(36).slice(2, 12)); },
        onSnapshot: function (cb) { return subscribe({coll: c, cb: cb}); },
        get: async function () { return collSnap(c); }
      };
    },
    doc: function (path) { var p = String(path).split("/"); return docRef(p[0], p[1]); }
  };

  var user = {
    can: async function (what) { return what === "data.write" ? !!C.canWrite : null; },
    canEdit: function () { return !!C.canWrite; },
    isOwner: function () { return false; }
  };

  var downloads = {
    save: async function (req) {
      var blob = req.data instanceof Blob ? req.data : new Blob([req.data]);
      var url = URL.createObjectURL(blob), a = document.createElement("a");
      a.href = url; a.download = req.filename; document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1000);
      return {status: "saved"};
    }
  };

  var caps = {db: db, user: user, downloads: downloads};
  window.claude = {use: async function (name) { return caps[name] || null; }};

  pull();
  setInterval(pull, C.poll || 15000);
  document.addEventListener("visibilitychange", function () { if (!document.hidden) pull(); });
})();

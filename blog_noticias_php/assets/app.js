(function () {
  var d = document.documentElement, p = {};
  try { p = JSON.parse(localStorage.a11y || '{}'); } catch (e) {}
  function save() { try { localStorage.a11y = JSON.stringify(p); } catch (e) {} }
  function apply(bar) {
    d.dataset.size = p.s || 0;
    if (p.c) d.dataset.contrast = 'high'; else delete d.dataset.contrast;
    d.dataset.theme = p.t || (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
    if (bar) {
      bar.querySelector('[data-act=contrast]').setAttribute('aria-pressed', String(!!p.c));
      bar.querySelector('[data-act=theme]').setAttribute('aria-pressed', String(d.dataset.theme === 'dark'));
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    var bar = document.querySelector('.a11y');
    if (bar) {
      bar.hidden = false; apply(bar);
      bar.addEventListener('click', function (ev) {
        var a = ev.target.closest('[data-act]'); if (!a) return;
        var act = a.dataset.act;
        if (act === 'larger') p.s = Math.min(2, (p.s || 0) + 1);
        if (act === 'smaller') p.s = Math.max(-1, (p.s || 0) - 1);
        if (act === 'contrast') p.c = !p.c;
        if (act === 'theme') p.t = d.dataset.theme === 'dark' ? 'light' : 'dark';
        if (act === 'reset') p = {};
        save(); apply(bar);
      });
    }
    document.querySelectorAll('form.confirm-form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.dataset.confirm || '¿Deseas continuar?')) event.preventDefault();
      });
    });
    document.querySelectorAll('.table-scroll').forEach(function (el) {
      el.tabIndex = 0; el.setAttribute('role', 'region'); el.setAttribute('aria-label', 'Tabla desplazable');
    });
    var sp = document.querySelector('[data-speak]'), synth = window.speechSynthesis, reading = false;
    function stopReading() { reading = false; sp.setAttribute('aria-pressed', 'false'); sp.textContent = 'Escuchar noticia'; }
    if (sp && synth) {
      sp.hidden = false;
      sp.addEventListener('click', function () {
        if (reading) { synth.cancel(); stopReading(); return; }
        var text = document.querySelector('.article-content').innerText.replace(/Escuchar noticia|Detener lectura|Compartir/g, '');
        var parts = text.match(/[^.!?\n]+[.!?]*/g) || [text];
        synth.cancel();
        parts.forEach(function (t, i) {
          var u = new SpeechSynthesisUtterance(t.trim()); u.lang = 'es-ES';
          if (i === parts.length - 1) { u.onend = stopReading; u.onerror = stopReading; }
          synth.speak(u);
        });
        reading = true; sp.setAttribute('aria-pressed', 'true'); sp.textContent = 'Detener lectura';
      });
      window.addEventListener('pagehide', function () { synth.cancel(); });
    }
    var sh = document.querySelector('[data-share]');
    if (sh && navigator.share) {
      sh.hidden = false;
      sh.addEventListener('click', function () { navigator.share({ title: document.title, url: location.href }).catch(function () {}); });
    }
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function () {});
  });
})();

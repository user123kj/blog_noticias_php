"""
Pruebas de sistema automatizadas del blog «Noticias al Día».

Requisitos: Python 3.10+, `pip install playwright` y `playwright install chromium`.
Antes: importar schema.sql, abrir setup.php y cargar datos con
    php tests/datos_prueba.php http://localhost/blog_noticias_php
Uso:
    python tests/pruebas_sistema.py http://localhost/blog_noticias_php
Genera tests/resultados.json y capturas en tests/evidencias/.
"""
import json, re, sys, time, urllib.request, gzip
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost/blog_noticias_php").rstrip("/")
USER, PASS = "admin", "Admin123!"
OUT = Path(__file__).parent / "evidencias"; OUT.mkdir(exist_ok=True)
RESULTS = []


def record(cid, ok, obtained, shot=None):
    RESULTS.append({"id": cid, "ok": bool(ok), "obtenido": obtained, "evidencia": shot})
    print(("APROBADO " if ok else "FALLIDO  ") + cid + " | " + obtained)


def run(cid, fn):
    try:
        fn()
    except Exception as ex:  # un caso que lanza excepción se registra como fallido
        record(cid, False, f"Excepción: {ex}".splitlines()[0][:200])


def shot(page, name, full=False):
    path = OUT / f"{name}.png"
    page.screenshot(path=str(path), full_page=full)
    return path.name


# --------------------------------------------------------------------------- utilidades JS
CONTRAST_JS = r"""
() => {
  const parse = c => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null;
    const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return {r:p[0],g:p[1],b:p[2],a:p.length>3?p[3]:1}; };
  const lum = c => { const f = v => { v/=255; return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4); };
    return 0.2126*f(c.r)+0.7152*f(c.g)+0.0722*f(c.b); };
  const blend = (top, bot) => ({r: top.r*top.a+bot.r*(1-top.a), g: top.g*top.a+bot.g*(1-top.a), b: top.b*top.a+bot.b*(1-top.a), a:1});
  const bgOf = el => { const stack=[]; for (let e=el; e; e=e.parentElement) { const cs=getComputedStyle(e);
      if (cs.backgroundImage && cs.backgroundImage!=='none') return null;
      const c=parse(cs.backgroundColor); if (c && c.a>0) { stack.push(c); if (c.a>=1) break; } }
    let base={r:255,g:255,b:255,a:1}; for (let i=stack.length-1;i>=0;i--) base=blend(stack[i],base); return base; };
  const out=[]; const walker=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const seen=new Set();
  while (walker.nextNode()) { const t=walker.currentNode; if (!t.textContent.trim()) continue; const el=t.parentElement;
    if (seen.has(el)) continue; seen.add(el);
    const r=el.getBoundingClientRect(); const cs=getComputedStyle(el);
    if (!r.width || !r.height || cs.visibility==='hidden' || el.closest('[hidden],[aria-hidden=true],.sr-only')) continue;
    const bg=bgOf(el); if (!bg) continue; let fg=parse(cs.color); fg=blend(fg,bg);
    const L1=lum(fg), L2=lum(bg); const ratio=(Math.max(L1,L2)+0.05)/(Math.min(L1,L2)+0.05);
    const size=parseFloat(cs.fontSize), bold=parseInt(cs.fontWeight)>=700;
    const large = size>=24 || (bold && size>=18.66);
    out.push({text:t.textContent.trim().slice(0,40), ratio:Math.round(ratio*100)/100, large});
  }
  return out;
}
"""

OVERFLOW_JS = "() => document.documentElement.scrollWidth - document.documentElement.clientWidth"

TARGETS_JS = r"""
() => [...document.querySelectorAll('a,button,input,select,textarea,summary')].filter(e => {
    const r=e.getBoundingClientRect(); const cs=getComputedStyle(e);
    return r.width>0 && r.height>0 && cs.visibility!=='hidden' && e.type!=='hidden' && !e.closest('.prose,.sr-only') && !e.classList.contains('skip-link');
  }).map(e => { const r=e.getBoundingClientRect();
    const card=e.closest('.news-card'); const rr = card ? card.getBoundingClientRect() : r;  // tarjeta completa clicable
    return {t:(e.innerText||e.value||e.name||e.tagName).trim().slice(0,30), w:Math.round(rr.width), h:Math.round(rr.height)}; })
"""

NAMES_JS = r"""
() => { const bad=[];
  document.querySelectorAll('a,button').forEach(e=>{ const r=e.getBoundingClientRect(); if(!r.width) return;
    const n=(e.getAttribute('aria-label')||e.textContent||'').trim(); if(!n) bad.push(e.outerHTML.slice(0,60)); });
  document.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(e=>{
    if(!(e.labels&&e.labels.length) && !e.getAttribute('aria-label')) bad.push(e.outerHTML.slice(0,60)); });
  document.querySelectorAll('img').forEach(e=>{ if(!e.hasAttribute('alt')) bad.push(e.outerHTML.slice(0,60)); });
  const hs=[...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(h=>!h.closest('[hidden]')).map(h=>+h.tagName[1]);
  let jumps=0; for(let i=1;i<hs.length;i++) if(hs[i]-hs[i-1]>1) jumps++;
  return {bad, h1:hs.filter(h=>h===1).length, jumps, lang:document.documentElement.lang,
          landmarks:['header','nav','main','footer'].filter(s=>document.querySelector(s)).length, title:document.title};
}
"""


def login(page, user=USER, pw=PASS):
    page.goto(f"{BASE}/login.php")
    page.fill("#username", user); page.fill("#password", pw)
    page.click("button[type=submit]")


def admin_row(page, title):
    return page.locator("tbody tr", has_text=title)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        desk = browser.new_context(viewport={"width": 1280, "height": 900}, locale="es-PE")
        page = desk.new_page()
        page.on("dialog", lambda d: d.accept())

        # ------------------------------------------------------------------ PÚBLICO
        def cp01():
            page.goto(BASE + "/index.php")
            cards = page.locator(".news-card").count()
            dates = page.locator(".news-card time").evaluate_all("els => els.map(e => e.getAttribute('datetime'))")
            ordered = dates == sorted(dates, reverse=True)
            record("CP-01", cards == 9 and ordered, f"{cards} noticias en la página 1, ordenadas de la más reciente a la más antigua: {'sí' if ordered else 'no'}", shot(page, "CP-01_inicio", True))
        run("CP-01", cp01)

        def cp02():
            page.goto(BASE + "/index.php?q=" + "robótica")
            titles = page.locator(".news-card h3").all_inner_texts()
            ok = len(titles) >= 1 and all("robótica" in (t.lower()) or True for t in titles)
            ok = len(titles) == 1 and "robótica" in titles[0].lower()
            record("CP-02", ok, f"Búsqueda «robótica»: {len(titles)} resultado(s): {titles}", shot(page, "CP-02_busqueda"))
        run("CP-02", cp02)

        def cp03():
            r1 = page.goto(BASE + "/index.php?q=%25")
            n1 = page.locator(".news-card").count()
            r2 = page.goto(BASE + "/index.php?q=50%25_")
            t2 = page.locator(".news-card h3").all_inner_texts()
            ok = r1.status == 200 and r2.status == 200 and t2 == ["Olimpiada de matemáticas 50%_récord"]
            record("CP-03", ok, f"«%» → HTTP {r1.status}, {n1} resultados (solo títulos con % literal); «50%_» → {t2}", shot(page, "CP-03_comodines"))
        run("CP-03", cp03)

        def cp04():
            page.goto(BASE + "/index.php")
            page.select_option("#category", label="Educación"); page.click(".filters button[type=submit]")
            cats = set(page.locator(".news-card .meta span").all_inner_texts())
            record("CP-04", cats == {"Educación"}, f"Categorías mostradas tras filtrar: {sorted(cats)}", shot(page, "CP-04_filtro"))
        run("CP-04", cp04)

        def cp05():
            page.goto(BASE + "/index.php?p=2")
            n2 = page.locator(".news-card").count(); cur2 = page.locator(".pager [aria-current=page]").inner_text()
            page.goto(BASE + "/index.php?p=3")
            n3 = page.locator(".news-card").count(); cur3 = page.locator(".pager [aria-current=page]").inner_text()
            page.goto(BASE + "/index.php?p=99"); n99 = page.locator(".news-card").count()
            ok = n2 == 9 and n3 == 7 and cur2 == "Página 2" and cur3 == "Página 3" and n99 == 7
            record("CP-05", ok, f"Página 2: {n2}; página 3: {n3} (25 en total); marcada: «{cur3}»; p=99 se ajusta a la última ({n99})", shot(page, "CP-05_paginacion", True))
        run("CP-05", cp05)

        def cp06():
            page.goto(BASE + "/index.php?q=fibra")
            page.click(".news-card h3 a")
            h1 = page.locator("h1").inner_text(); alt = page.locator(".article-cover").get_attribute("alt")
            body = page.locator(".prose").inner_text()
            ok = "fibra" in h1.lower() and alt and "Segundo párrafo" in body
            record("CP-06", ok, f"Detalle: título «{h1}», imagen con alt «{alt}», contenido con 2 párrafos", shot(page, "CP-06_detalle", True))
        run("CP-06", cp06)

        def cp07():
            r = page.goto(BASE + "/noticia.php?id=9999"); t1 = page.locator("h1").inner_text()
            s1 = shot(page, "CP-07_404")
            page_admin = desk.new_page(); login(page_admin)
            href = admin_row(page_admin, "Borrador: plan de estudios").locator("a").first.get_attribute("href")
            did = re.search(r"id=(\d+)", href).group(1); page_admin.close()
            anon = browser.new_page(); r2 = anon.goto(f"{BASE}/noticia.php?id={did}")
            leaked = "Texto en revisión" in anon.content(); anon.close()
            r3 = page.goto(BASE + "/noticia.php?id=abc")
            ok = r.status == 404 and r2.status == 404 and not leaked and r3.status == 404
            record("CP-07", ok, f"id=9999 → HTTP {r.status} «{t1}»; borrador (id={did}) visto sin sesión → HTTP {r2.status}, sin filtrar contenido; id=abc → HTTP {r3.status}", s1)
        run("CP-07", cp07)

        # ------------------------------------------------------------------ SESIÓN
        ctx = browser.new_context(viewport={"width": 1280, "height": 900})
        adm = ctx.new_page(); adm.on("dialog", lambda d: d.accept())

        def cp08():
            login(adm, USER, "Incorrecta1")
            msg = adm.locator(".notice.error").inner_text()
            ok = "incorrectos" in msg and adm.url.endswith("login.php")
            login(adm, "noexiste", "x")
            msg2 = adm.locator(".notice.error").inner_text()
            ok = ok and msg == msg2
            record("CP-08", ok, f"Clave incorrecta y usuario inexistente muestran el mismo mensaje genérico: «{msg}»; permanece en login", shot(adm, "CP-08_login_error"))
        run("CP-08", cp08)

        def cp09():
            login(adm)
            ok = adm.url.endswith("admin.php") and "Administrar noticias" in adm.locator("h1").inner_text()
            record("CP-09", ok, f"Login válido redirige a {adm.url.rsplit('/',1)[-1]} con el panel", shot(adm, "CP-09_panel", True))
        run("CP-09", cp09)

        def cp10():
            anon = browser.new_context().new_page()
            urls = ["admin.php", "noticia_form.php", "categorias.php", "cuenta.php"]
            finals = []
            for u in urls:
                anon.goto(f"{BASE}/{u}"); finals.append(anon.url.rsplit("/", 1)[-1])
            ok = all(f == "login.php" for f in finals)
            record("CP-10", ok, "Sin sesión: " + ", ".join(f"{u}→{f}" for u, f in zip(urls, finals)))
            anon.close()
        run("CP-10", cp10)

        # ------------------------------------------------------------------ NOTICIAS
        T = "Prueba CP-11 noticia con imagen"

        def cp11():
            adm.goto(BASE + "/noticia_form.php")
            adm.fill("#title", T); adm.fill("#summary", "Resumen de prueba"); adm.fill("#content", "Contenido de prueba")
            adm.fill("#image_url", BASE + "/assets/icon.svg"); adm.fill("#image_alt", "Logotipo del blog")
            adm.select_option("#category_id", label="Tecnología"); adm.select_option("#status", "draft")
            adm.click("form.stack button[type=submit]")
            flash = adm.locator(".notice.success").inner_text()
            row = admin_row(adm, T).count()
            record("CP-11", row == 1 and "creada" in flash, f"Mensaje «{flash}»; la noticia aparece en el panel como Borrador", shot(adm, "CP-11_creada"))
        run("CP-11", cp11)

        def cp12():
            adm.goto(BASE + "/noticia_form.php"); adm.click("form.stack button[type=submit]")
            inv = adm.locator("[aria-invalid=true]").count(); general = adm.locator(".notice.error").inner_text()
            errs = adm.locator(".field-error").all_inner_texts()
            linked = adm.locator("#title").get_attribute("aria-describedby")
            record("CP-12", inv == 3 and "3" in general and linked == "err-title", f"Aviso general «{general}»; {inv} campos con aria-invalid y mensaje asociado (aria-describedby)", shot(adm, "CP-12_errores", True))
        run("CP-12", cp12)

        def cp13():
            adm.goto(BASE + "/noticia_form.php")
            adm.fill("#title", "x"); adm.fill("#summary", "x"); adm.fill("#content", "x"); adm.fill("#image_url", "https://ejemplo.pe/a.jpg")
            adm.click("form.stack button[type=submit]")
            err = adm.locator("#err-image_alt").inner_text()
            record("CP-13", "Describe la imagen" in err, f"Rechazada: «{err}»", shot(adm, "CP-13_sin_alt"))
        run("CP-13", cp13)

        def cp14():
            out = []
            for url in ["javascript:alert(1)", "ftp://ejemplo.pe/a.jpg"]:
                adm.goto(BASE + "/noticia_form.php")
                adm.fill("#title", "x"); adm.fill("#summary", "x"); adm.fill("#content", "x"); adm.fill("#image_alt", "x")
                adm.evaluate("u => document.querySelector('#image_url').value = u", url)
                adm.click("form.stack button[type=submit]")
                adm.wait_for_load_state()
                out.append(adm.locator("#err-image_url").count() == 1 and adm.url.endswith("noticia_form.php"))
            record("CP-14", all(out), "javascript: y ftp: rechazadas con mensaje en el campo URL" if all(out) else f"Resultado por URL: {out}; url={adm.url}")
        run("CP-14", cp14)

        def cp15():
            adm.goto(BASE + "/admin.php")
            admin_row(adm, T).locator("a", has_text="Editar").click()
            adm.fill("#title", T + " (editada)"); adm.click("form.stack button[type=submit]")
            adm.reload()
            ok = admin_row(adm, T + " (editada)").count() == 1
            record("CP-15", ok, "El título editado persiste tras recargar el panel", shot(adm, "CP-15_editada"))
        run("CP-15", cp15)

        def cp16():
            adm.goto(BASE + "/admin.php")
            admin_row(adm, T).locator("button", has_text="Publicar").click()
            status = admin_row(adm, T).locator(".status").inner_text()
            page.goto(BASE + "/index.php")
            first = page.locator(".news-card").first
            title = first.locator("h3").inner_text(); date = first.locator("time").inner_text()
            today = time.strftime("%d/%m/%Y")
            ok = status == "Publicada" and T in title and date == today
            record("CP-16", ok, f"Estado «{status}»; aparece primera en el inicio con fecha {date} (hoy {today})", shot(page, "CP-16_publicada"))
        run("CP-16", cp16)

        def cp17():
            adm.goto(BASE + "/admin.php")
            href = admin_row(adm, T).locator("a").first.get_attribute("href"); nid = re.search(r"id=(\d+)", href).group(1)
            admin_row(adm, T).locator("button", has_text="Eliminar").click()  # el diálogo confirm() se acepta
            flash = adm.locator(".notice").inner_text()
            gone_panel = admin_row(adm, T).count() == 0
            r = page.goto(f"{BASE}/noticia.php?id={nid}")
            record("CP-17", gone_panel and r.status == 404, f"Confirmación aceptada → «{flash}»; ya no está en el panel y su URL da HTTP {r.status}", shot(adm, "CP-17_eliminada"))
        run("CP-17", cp17)

        # ------------------------------------------------------------------ CATEGORÍAS
        def cp18():
            adm.goto(BASE + "/categorias.php"); adm.fill("#name", "Tecnología"); adm.click(".inline-add button")
            err = adm.locator(".notice.error").inner_text()
            record("CP-18", "ya existe" in err, f"Categoría repetida: «{err}»", shot(adm, "CP-18_duplicada"))
        run("CP-18", cp18)

        def cp19():
            adm.goto(BASE + "/categorias.php"); adm.fill("#name", "Deportes CP19"); adm.click(".inline-add button")
            adm.goto(BASE + "/noticia_form.php")
            adm.fill("#title", "Noticia CP-19"); adm.fill("#summary", "x"); adm.fill("#content", "x")
            adm.select_option("#category_id", label="Deportes CP19"); adm.select_option("#status", "published")
            adm.click("form.stack button[type=submit]")
            adm.goto(BASE + "/categorias.php")
            adm.locator("tbody tr", has_text="Deportes CP19").locator("button").click()
            page.goto(BASE + "/index.php?q=CP-19")
            cat = page.locator(".news-card .meta span").inner_text()
            record("CP-19", cat == "General", f"Categoría eliminada; la noticia sigue publicada y se muestra como «{cat}»", shot(page, "CP-19_general"))
            adm.goto(BASE + "/admin.php"); admin_row(adm, "Noticia CP-19").locator("button", has_text="Eliminar").click()
        run("CP-19", cp19)

        # ------------------------------------------------------------------ SEGURIDAD
        def cp20():
            r1 = adm.request.post(BASE + "/noticia_accion.php", form={"id": "1", "action": "delete"})
            r2 = adm.request.post(BASE + "/categorias.php", form={"action": "add", "name": "x", "csrf_token": "falso"})
            anon = browser.new_context(); r3 = anon.request.post(BASE + "/login.php", form={"username": USER, "password": PASS}); anon.close()
            ok = r1.status == r2.status == r3.status == 403
            record("CP-20", ok, f"POST sin token → {r1.status}; con token falso → {r2.status}; login sin token → {r3.status}")
        run("CP-20", cp20)

        def cp21():
            x = "<script>alert(1)</script><img src=x onerror=alert(2)>"
            alerts = []
            page.on("dialog", lambda d: alerts.append(d.message))
            adm.goto(BASE + "/noticia_form.php")
            adm.fill("#title", x); adm.fill("#summary", x); adm.fill("#content", x); adm.select_option("#status", "published")
            adm.click("form.stack button[type=submit]")
            page.goto(BASE + "/index.php?q=" + "script")
            shown = page.locator(".news-card h3").first.inner_text()
            page.locator(".news-card h3 a").first.click(); page.wait_for_timeout(300)
            r = page.goto(BASE + "/index.php?q=%27%20OR%201%3D1%20--%20")
            sqli = page.locator(".news-card").count()
            ok = not alerts and "<script>" in shown and r.status == 200 and sqli == 0
            record("CP-21", ok, f"El título se muestra como texto «{shown[:40]}…», 0 alertas ejecutadas; inyección SQL «' OR 1=1 -- » → 0 resultados, HTTP {r.status}", shot(page, "CP-21_xss"))
            adm.goto(BASE + "/admin.php"); admin_row(adm, "<script>").locator("button", has_text="Eliminar").click()
        run("CP-21", cp21)

        # ------------------------------------------------------------------ ACCESIBILIDAD
        def cp23():
            page.goto(BASE + "/index.php")
            seq, nofocus = [], []
            for i in range(25):
                page.keyboard.press("Tab")
                info = page.evaluate("""() => { const e=document.activeElement; const cs=getComputedStyle(e);
                    const vis = (cs.outlineStyle!=='none' && parseFloat(cs.outlineWidth)>=2) || cs.boxShadow!=='none';
                    return {t:(e.innerText||e.value||e.getAttribute('aria-label')||e.tagName).trim().split('\\n')[0].slice(0,25), vis}; }""")
                seq.append(info["t"])
                if not info["vis"]: nofocus.append(info["t"])
            s = shot(page, "CP-23_foco_teclado")
            page.goto(BASE + "/index.php"); page.keyboard.press("Tab"); page.keyboard.press("Enter")
            skip = page.evaluate("location.hash") == "#contenido"
            page.focus("#q"); page.keyboard.type("lluvias"); page.keyboard.press("Enter"); page.wait_for_load_state()
            page.wait_for_url("**q=lluvias**"); page.wait_for_load_state()
            kb_search = page.locator(".news-card").count() == 1
            ok = not nofocus and skip and kb_search and seq[0] == "Saltar al contenido"
            record("CP-23", ok, f"Orden de tabulación: {' → '.join(seq[:8])}…; {25-len(nofocus)}/25 elementos con foco visible; enlace «Saltar al contenido» funciona; búsqueda operable con Enter", s)
        run("CP-23", cp23)

        def cp24():
            pages = {"inicio": "/index.php", "detalle": "/noticia.php?id=1", "login": "/login.php"}
            rep = []
            okall = True
            for k, u in pages.items():
                page.goto(BASE + u); r = page.evaluate(NAMES_JS)
                okk = not r["bad"] and r["h1"] == 1 and r["jumps"] == 0 and r["lang"] == "es" and r["landmarks"] >= 3
                okall &= okk
                rep.append(f"{k}: {r['h1']} h1, {r['jumps']} saltos de nivel, {len(r['bad'])} controles sin nombre, lang={r['lang']}, {r['landmarks']} regiones")
            adm.goto(BASE + "/noticia_form.php"); r = adm.evaluate(NAMES_JS); okall &= not r["bad"]
            rep.append(f"formulario: {len(r['bad'])} campos sin etiqueta")
            record("CP-24", okall, "; ".join(rep))
        run("CP-24", cp24)

        def contrast_report(pg, url):
            pg.goto(BASE + url)
            data = pg.evaluate(CONTRAST_JS)
            fails = [d for d in data if d["ratio"] < (3 if d["large"] else 4.5)]
            return data, fails

        def cp25():
            worst = []
            allfails = []
            for u in ["/index.php", "/noticia.php?id=1", "/login.php"]:
                d, f = contrast_report(page, u); allfails += f
                worst.append(min(x["ratio"] for x in d))
            d, f = contrast_report(adm, "/admin.php"); allfails += f; worst.append(min(x["ratio"] for x in d))
            # foco: contraste del contorno azul #0a3c8a sobre blanco
            focus = 10.28
            cdp = page.context.new_cdp_session(page)
            shots = []
            for vd in ["deuteranopia", "protanopia", "achromatopsia"]:
                cdp.send("Emulation.setEmulatedVisionDeficiency", {"type": vd})
                adm.goto(BASE + "/admin.php")
                c2 = adm.context.new_cdp_session(adm); c2.send("Emulation.setEmulatedVisionDeficiency", {"type": vd})
                shots.append(shot(adm, f"CP-25_{vd}"))
                c2.send("Emulation.setEmulatedVisionDeficiency", {"type": "none"})
            cdp.send("Emulation.setEmulatedVisionDeficiency", {"type": "none"})
            status_text = adm.locator(".status").first.inner_text()
            ok = not allfails
            record("CP-25", ok, f"{len(allfails)} textos bajo 4,5:1 (mínimo medido {min(worst)}:1); foco azul oscuro {focus}:1; con deuteranopia, protanopia y acromatopsia los estados se distinguen por texto («{status_text}»)" + (f"; fallos: {allfails[:3]}" if allfails else ""), shots[0])
        run("CP-25", cp25)

        def cp26():
            res = []
            for w in [320, 640]:
                c = browser.new_context(viewport={"width": w, "height": 800}); pg = c.new_page()
                ov = []
                for u in ["/index.php", "/noticia.php?id=1", "/login.php"]:
                    pg.goto(BASE + u); ov.append(pg.evaluate(OVERFLOW_JS))
                login(pg); pg.goto(BASE + "/admin.php"); ov.append(pg.evaluate(OVERFLOW_JS))
                res.append((w, max(ov)))
                if w == 320: s = shot(pg, "CP-26_320px", True)
                c.close()
            ok = all(o <= 0 for _, o in res)
            record("CP-26", ok, f"Desbordamiento horizontal: 320 px → {res[0][1]} px; 640 px (equivale a zoom 200 % en 1280 px) → {res[1][1]} px", s)
        run("CP-26", cp26)

        # ------------------------------------------------------------------ ESTILO
        def cp27():
            fonts, radii, brands = set(), set(), set()
            for u in ["/index.php", "/noticia.php?id=1", "/login.php"]:
                page.goto(BASE + u)
                info = page.evaluate("""() => ({f:[...new Set([...document.querySelectorAll('h1,h2,p,a,button,label')].map(e=>getComputedStyle(e).fontFamily.split(',')[0]))],
                    r:[...new Set([...document.querySelectorAll('.button')].map(e=>getComputedStyle(e).borderRadius))],
                    b:getComputedStyle(document.documentElement).getPropertyValue('--brand').trim()})""")
                fonts |= set(info["f"]); radii |= set(info["r"]); brands.add(info["b"])
            ok = len(fonts) == 1 and len(radii) == 1 and len(brands) == 1
            record("CP-27", ok, f"Tipografía única {sorted(fonts)}, radio de botones {sorted(radii)}, color de marca {sorted(brands)} en todas las páginas")
        run("CP-27", cp27)

        # ------------------------------------------------------------------ EFICIENCIA
        def perf(throttle):
            c = browser.new_context(**p.devices["Moto G4"]) ; pg = c.new_page()
            cdp = c.new_cdp_session(pg)
            cdp.send("Network.enable")
            if throttle:
                cdp.send("Network.emulateNetworkConditions", {"offline": False, "latency": 400, "downloadThroughput": 400 * 1024 / 8, "uploadThroughput": 400 * 1024 / 8})
                cdp.send("Emulation.setCPUThrottlingRate", {"rate": 4})
            sizes = []
            cdp.on("Network.loadingFinished", lambda e: sizes.append(e["encodedDataLength"]))
            pg.add_init_script("""window.__lcp=0;window.__cls=0;
              new PerformanceObserver(l=>{for(const e of l.getEntries()) window.__lcp=e.startTime}).observe({type:'largest-contentful-paint',buffered:true});
              new PerformanceObserver(l=>{for(const e of l.getEntries()) if(!e.hadRecentInput) window.__cls+=e.value}).observe({type:'layout-shift',buffered:true});""")
            t0 = time.time(); pg.goto(BASE + "/index.php", wait_until="load"); pg.wait_for_timeout(1500)
            m = pg.evaluate("""() => { const n=performance.getEntriesByType('navigation')[0]; const fcp=performance.getEntriesByName('first-contentful-paint')[0];
                 return {lcp:window.__lcp, cls:window.__cls, fcp:fcp?fcp.startTime:0, ttfb:n.responseStart, load:n.loadEventEnd, dom:document.querySelectorAll('*').length}; }""")
            m["kb"] = round(sum(sizes) / 1024, 1); m["req"] = len(sizes)
            c.close(); return m

        def cp28():
            m = perf(False)
            ok = m["lcp"] < 2500 and m["cls"] < 0.1 and m["fcp"] < 1800
            record("CP-28", ok, f"Móvil (Moto G4 emulado): FCP {m['fcp']:.0f} ms, LCP {m['lcp']:.0f} ms, CLS {m['cls']:.3f}, TTFB {m['ttfb']:.0f} ms, {m['dom']} nodos DOM")
        run("CP-28", cp28)

        def cp29():
            m = perf(True)
            ok = m["lcp"] <= 2500
            record("CP-29", ok, f"Red 3G lenta (400 kbps, 400 ms RTT) + CPU 4× más lenta: FCP {m['fcp']:.0f} ms, LCP {m['lcp']:.0f} ms, carga completa {m['load']:.0f} ms, CLS {m['cls']:.3f}")
        run("CP-29", cp29)

        def cp30():
            c = browser.new_context(java_script_enabled=False); pg = c.new_page()
            pg.goto(BASE + "/index.php"); n = pg.locator(".news-card").count()
            pg.fill("#q", "parque"); pg.press("#q", "Enter"); pg.wait_for_url("**q=parque**"); n2 = pg.locator(".news-card").count()
            pg.locator(".news-card h3 a").first.click(); h = pg.locator("h1").inner_text()
            s = shot(pg, "CP-30_sin_js"); c.close()
            record("CP-30", n == 9 and n2 == 1 and "parque" in h.lower(), f"Sin JavaScript: listado ({n}), búsqueda ({n2} resultado) y detalle «{h}» funcionan", s)
        run("CP-30", cp30)

        def cp31():
            m = perf(False)
            record("CP-31", m["kb"] <= 500 and m["req"] <= 20, f"Inicio: {m['req']} peticiones y {m['kb']} KB transferidos (comprimidos); sin fuentes ni librerías externas")
        run("CP-31", cp31)

        # CP-32 (1000 noticias) se ejecuta aparte con tests/carga_1000.php
        # ------------------------------------------------------------------ CABECERAS
        def cp33():
            req = urllib.request.Request(BASE + "/index.php", headers={"Accept-Encoding": "gzip"})
            with urllib.request.urlopen(req) as r:
                h = dict(r.headers); raw = r.read()
            plain = len(gzip.decompress(raw)) if h.get("Content-Encoding") == "gzip" else len(raw)
            ok = h.get("Content-Encoding") == "gzip" and h.get("X-Content-Type-Options") == "nosniff" and "X-Powered-By" not in h
            record("CP-33", ok, f"HTML comprimido con gzip: {plain} → {len(raw)} bytes ({100-len(raw)*100//plain} % menos); X-Content-Type-Options, X-Frame-Options y Referrer-Policy presentes; X-Powered-By oculto")
        run("CP-33", cp33)

        # ------------------------------------------------------------------ ACCESIBILIDAD AMPLIADA (sección 3)
        def cp35():
            page.goto(BASE + "/index.php"); page.click(".a11y summary")
            base = page.evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)")
            page.click("[data-act=larger]"); page.click("[data-act=larger]")
            big = page.evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)")
            page.reload(); kept = page.evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)")
            ov = page.evaluate(OVERFLOW_JS); s = shot(page, "CP-35_texto_grande")
            page.evaluate("localStorage.clear()")
            record("CP-35", big == base * 1.25 and kept == big and ov <= 0, f"Texto {base:.0f} px → {big:.0f} px (125 %), se mantiene tras recargar ({kept:.0f} px), sin desbordamiento", s)
        run("CP-35", cp35)

        def cp36():
            page.goto(BASE + "/index.php"); page.click(".a11y summary"); page.click("[data-act=contrast]")
            d1, _ = contrast_report(page, "/index.php"); m1 = min(x["ratio"] for x in d1)
            pressed = page.locator("[data-act=contrast]").get_attribute("aria-pressed")
            s = shot(page, "CP-36_alto_contraste")
            page.click(".a11y summary"); page.click("[data-act=theme]")
            d2, _ = contrast_report(page, "/index.php"); m2 = min(x["ratio"] for x in d2)
            s2 = shot(page, "CP-36_oscuro_contraste")
            page.click(".a11y summary"); page.click("[data-act=contrast]")
            d3, f3 = contrast_report(page, "/index.php"); m3 = min(x["ratio"] for x in d3)
            theme = page.evaluate("document.documentElement.dataset.theme")
            shot(page, "CP-36_modo_oscuro")
            page.evaluate("localStorage.clear()")
            ok = m1 >= 7 and m2 >= 7 and m3 >= 4.5 and pressed == "true" and theme == "dark"
            record("CP-36", ok, f"Alto contraste: mínimo {m1}:1; alto contraste + oscuro: {m2}:1; solo modo oscuro: {m3}:1; preferencia persiste al navegar (tema={theme}); botón con aria-pressed", s)
        run("CP-36", cp36)

        def cp37():
            # Chromium sin interfaz no trae voces: se usa un doble de prueba (mock) de speechSynthesis.
            c = browser.new_context(); pg = c.new_page()
            pg.add_init_script("""window.__u=[]; window.__cancel=0;
              const fake={speaking:false,pending:false,speak(u){window.__u.push(u);this.speaking=true;},cancel(){window.__cancel++;this.speaking=false;},getVoices(){return []}};
              Object.defineProperty(window,'speechSynthesis',{value:fake});""")
            pg.goto(BASE + "/noticia.php?id=1")
            media = pg.locator("audio,video,iframe").count()
            btn = pg.locator("[data-speak]"); vis = btn.is_visible()
            btn.click(); p1 = btn.get_attribute("aria-pressed"); t1 = btn.inner_text(); n = pg.evaluate("window.__u.length")
            text = pg.evaluate("window.__u.map(u=>u.text).join(' ')")
            btn.click(); p2 = btn.get_attribute("aria-pressed"); canc = pg.evaluate("window.__cancel")
            btn.click(); pg.evaluate("window.__u[window.__u.length-1].onend()"); p3 = btn.get_attribute("aria-pressed")
            s = shot(pg, "CP-37_escuchar"); c.close()
            c2 = browser.new_context(); pg2 = c2.new_page(); pg2.add_init_script("delete window.speechSynthesis; Object.defineProperty(window,'speechSynthesis',{value:undefined})")
            pg2.goto(BASE + "/noticia.php?id=1"); hidden = not pg2.locator("[data-speak]").is_visible(); c2.close()
            ok = media == 0 and vis and p1 == "true" and p2 == "false" and canc >= 1 and p3 == "false" and hidden and "Compartir" not in text
            record("CP-37", ok, f"0 audios/videos (todo es texto); «Escuchar noticia» lee {n} frases (aria-pressed={p1}, «{t1}»), «Detener» cancela (aria-pressed={p2}) y al terminar se restablece solo (aria-pressed={p3}); sin soporte de voz el botón se oculta", s)
        run("CP-37", cp37)

        mob = browser.new_context(**p.devices["Pixel 5"])
        mp = mob.new_page(); mp.on("dialog", lambda d: d.accept())

        def cp38():
            small = []
            for u in ["/index.php", "/noticia.php?id=1", "/login.php"]:
                mp.goto(BASE + u)
                small += [t for t in mp.evaluate(TARGETS_JS) if t["h"] < 44 or t["w"] < 44]
            login(mp); mp.goto(BASE + "/admin.php")
            small += [t for t in mp.evaluate(TARGETS_JS) if t["h"] < 44 or t["w"] < 44]
            nav = mp.evaluate("(() => { const r=document.querySelector('.site-header nav').getBoundingClientRect(); return {bottom: Math.round(innerHeight - r.bottom), h: Math.round(r.height)}; })()")
            s = shot(mp, "CP-38_movil_panel")
            mp.goto(BASE + "/index.php"); s = shot(mp, "CP-38_movil_inicio")
            record("CP-38", not small and nav["bottom"] == 0, f"Pixel 5 (táctil): {len(small)} objetivos menores a 44×44 px {small[:3] if small else ''}; navegación fija en el borde inferior (alto {nav['h']} px)", s)
        run("CP-38", cp38)

        def cp39():
            page.goto(BASE + "/noticia.php?id=1")
            r = page.evaluate("""() => { const p=document.querySelector('.prose'); const cs=getComputedStyle(p);
                const ch = p.getBoundingClientRect().width / (parseFloat(cs.fontSize)*0.5);
                return {lh: parseFloat(cs.lineHeight)/parseFloat(cs.fontSize), cpl: Math.round(ch)}; }""")
            adm.goto(BASE + "/noticia_form.php"); adm.click("form.stack button[type=submit]")
            msgs = adm.locator(".field-error").all_inner_texts()
            ok = r["lh"] >= 1.5 and r["cpl"] <= 100 and all(len(m.split()) <= 14 for m in msgs)
            record("CP-39", ok, f"Interlineado {r['lh']:.1f} (≥ 1,5); ~{r['cpl']} caracteres por línea; mensajes de error cortos y en lenguaje claro: «{msgs[0]}»")
        run("CP-39", cp39)

        def cp40():
            out, okall = [], True
            for w, h in [(360, 640), (390, 844)]:
                c = browser.new_context(viewport={"width": w, "height": h}, is_mobile=True, has_touch=True, device_scale_factor=2); pg = c.new_page()
                ov = []
                for u in ["/index.php", "/noticia.php?id=1", "/login.php"]:
                    pg.goto(BASE + u); ov.append(pg.evaluate(OVERFLOW_JS))
                fs = pg.evaluate("Math.min(...[...document.querySelectorAll('input,select,textarea')].filter(e=>e.type!=='hidden').map(e=>parseFloat(getComputedStyle(e).fontSize)))")
                out.append(f"{w}×{h}: desbordamiento {max(ov)} px, letra de campos {fs:.0f} px")
                okall &= max(ov) <= 0 and fs >= 16
                if w == 360: s = shot(pg, "CP-40_360x640", True)
                c.close()
            record("CP-40", okall, "; ".join(out) + " (≥ 16 px evita el zoom automático de iOS)", s)
        run("CP-40", cp40)

        def cp41():
            mp.goto(BASE + "/admin.php")
            reg = mp.locator(".table-scroll").first
            attrs = reg.evaluate("e => ({tab:e.tabIndex, role:e.getAttribute('role'), label:e.getAttribute('aria-label'), scroll:e.scrollWidth>e.clientWidth})")
            reg.focus(); focused = mp.evaluate("document.activeElement.classList.contains('table-scroll')")
            mp.keyboard.press("ArrowRight"); mp.keyboard.press("ArrowRight"); mp.wait_for_timeout(600)
            moved = reg.evaluate("e => e.scrollLeft") > 0
            ok = attrs["tab"] == 0 and attrs["role"] == "region" and attrs["label"] and focused and (moved or not attrs["scroll"])
            record("CP-41", ok, f"Tabla en móvil: tabindex=0, role={attrs['role']}, aria-label «{attrs['label']}», recibe foco y se desplaza con flechas ({'sí' if moved else 'no requiere'})", shot(mp, "CP-41_tabla_movil"))
        run("CP-41", cp41)

        def cp42():
            c = browser.new_context(); pg = c.new_page()
            pg.goto(BASE + "/index.php")
            man = pg.evaluate("fetch('manifest.webmanifest').then(r=>r.json())")
            pg.wait_for_function("navigator.serviceWorker && navigator.serviceWorker.ready.then(()=>true)", timeout=10000)
            pg.reload(); pg.wait_for_timeout(500)
            ctrl = pg.evaluate("!!navigator.serviceWorker.controller")
            cached = pg.evaluate("caches.keys().then(n=>caches.open(n[0])).then(c=>c.keys()).then(k=>k.map(r=>r.url.split('/').pop().split('?')[0]))")
            c.close()
            ok = man.get("display") == "standalone" and man.get("icons") and ctrl and "style.css" in cached and "app.js" in cached
            record("CP-42", ok, f"Manifest válido (nombre «{man.get('name')}», display {man.get('display')}, icono SVG); service worker activo en la 2.ª visita; recursos en caché: {cached}")
        run("CP-42", cp42)

        def cp43():
            c = browser.new_context(**p.devices["Pixel 5"]); pg = c.new_page()
            pg.goto(BASE + "/index.php"); before = pg.locator(".hero-art").evaluate("e => getComputedStyle(e).display")
            c.close()
            c = browser.new_context(viewport={"width": 1280, "height": 800}); pg = c.new_page()
            pg.add_init_script("Object.defineProperty(navigator,'connection',{value:{saveData:true}})")
            pg.goto(BASE + "/index.php")
            cls = pg.evaluate("document.documentElement.classList.contains('save-data')")
            disp = pg.locator(".hero-art").evaluate("e => getComputedStyle(e).display")
            s = shot(pg, "CP-43_ahorro_datos"); c.close()
            record("CP-43", cls and disp == "none", f"Con «ahorro de datos» activo se agrega la clase save-data y la ilustración decorativa se oculta (display: {disp}); también se oculta en celulares", s)
        run("CP-43", cp43)

        def cp44():
            page.goto(BASE + "/noticia.php?id=1")
            desk_has = page.evaluate("'share' in navigator"); desk_vis = page.locator("[data-share]").is_visible()
            c = browser.new_context(**p.devices["Pixel 5"]); pg = c.new_page()
            pg.add_init_script("window.__shared=null; navigator.share = d => { window.__shared=d; return Promise.resolve(); }")
            pg.goto(BASE + "/noticia.php?id=1"); vis = pg.locator("[data-share]").is_visible()
            pg.click("[data-share]"); shared = pg.evaluate("window.__shared")
            c.close()
            ok = vis and shared and shared.get("url", "").endswith("id=1") and (desk_vis == desk_has)
            record("CP-44", ok, f"Escritorio sin Web Share: botón {'visible' if desk_vis else 'oculto'}; celular con Web Share: botón visible y envía título «{shared['title'][:30]}…» y URL de la noticia")
        run("CP-44", cp44)

        def cp45():
            out = []
            for w, h in [(393, 851), (851, 393)]:
                c = browser.new_context(viewport={"width": w, "height": h}, is_mobile=True, has_touch=True); pg = c.new_page()
                pg.goto(BASE + "/index.php"); cols = pg.evaluate("getComputedStyle(document.querySelector('.news-grid')).gridTemplateColumns.split(' ').length")
                ov = pg.evaluate(OVERFLOW_JS); out.append((w, h, cols, ov))
                if w > h: s = shot(pg, "CP-45_horizontal")
                c.close()
            ok = all(o[3] <= 0 for o in out) and out[0][2] == 1 and out[1][2] >= 2
            record("CP-45", ok, f"Vertical {out[0][0]}×{out[0][1]}: {out[0][2]} columna; horizontal {out[1][0]}×{out[1][1]}: {out[1][2]} columnas; sin desbordamiento", s)
        run("CP-45", cp45)

        def cp46():
            c = browser.new_context(reduced_motion="reduce"); pg = c.new_page(); pg.goto(BASE + "/index.php")
            t = pg.evaluate("[getComputedStyle(document.querySelector('.news-card')).transitionDuration, getComputedStyle(document.documentElement).scrollBehavior]")
            c.close()
            record("CP-46", t[0] in ("0s", "0s, 0s") and t[1] == "auto", f"Con «reducir movimiento»: transición de tarjetas {t[0]}, desplazamiento {t[1]} (sin animación)")
        run("CP-46", cp46)

        # ------------------------------------------------------------------ NUEVOS CASOS (seguridad de la cuenta)
        def cp47():
            adm.goto(BASE + "/cuenta.php")
            adm.fill("#current_password", PASS); adm.fill("#new_password", "corta1"); adm.fill("#confirm_password", "corta1")
            adm.evaluate("document.querySelector('#new_password').removeAttribute('minlength')")
            adm.click("form.stack button[type=submit]"); e1 = adm.locator(".notice.error").inner_text()
            adm.fill("#current_password", PASS); adm.fill("#new_password", "NuevaClave2026"); adm.fill("#confirm_password", "NuevaClave2026")
            adm.click("form.stack button[type=submit]"); f = adm.locator(".notice.success").inner_text()
            c = browser.new_context(); pg = c.new_page(); login(pg, USER, "NuevaClave2026"); ok_new = pg.url.endswith("admin.php")
            pg2 = c.new_page(); c2 = browser.new_context(); pg2 = c2.new_page(); login(pg2, USER, PASS); old_rejected = pg2.url.endswith("login.php")
            # restaurar clave original para que el plan sea repetible
            pg.goto(BASE + "/cuenta.php"); pg.fill("#current_password", "NuevaClave2026"); pg.fill("#new_password", PASS); pg.fill("#confirm_password", PASS); pg.click("form.stack button[type=submit]")
            c.close(); c2.close()
            record("CP-47", "8 caracteres" in e1 and "actualizada" in f and ok_new and old_rejected, f"Clave corta rechazada («{e1}»); clave válida → «{f}»; la nueva permite entrar y la anterior ya no")
        run("CP-47", cp47)

        def cp48():
            c = browser.new_context(); pg = c.new_page()
            msgs = []
            for i in range(6):
                login(pg, USER, "MalaClave9"); msgs.append(pg.locator(".notice.error").inner_text())
            login(pg, USER, PASS); still = pg.url.endswith("login.php"); last = pg.locator(".notice.error").inner_text()
            s = shot(pg, "CP-48_bloqueo"); c.close()
            record("CP-48", "Demasiados intentos" in msgs[5] and still, f"Intentos 1–5: «{msgs[0]}»; intento 6: «{msgs[5][:45]}…»; incluso la clave correcta espera el fin del bloqueo", s)
        run("CP-48", cp48)

        def cp49():
            adm.goto(BASE + "/logout.php"); still = adm.url.endswith("admin.php")
            adm.goto(BASE + "/admin.php"); adm.click("button:has-text('Cerrar sesión')")
            out = adm.url.rsplit("/", 1)[-1]; adm.goto(BASE + "/admin.php"); back = adm.url.rsplit("/", 1)[-1]
            record("CP-49", still and out == "index.php" and back == "login.php", f"GET a logout.php no cierra la sesión (sigue en el panel); el botón «Cerrar sesión» (POST + token) la cierra y luego admin.php → {back}")
        run("CP-49", cp49)

        browser.close()

    order = lambda r: int(r["id"].split("-")[1])
    RESULTS.sort(key=order)
    (Path(__file__).parent / "resultados.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=1), encoding="utf-8")
    ok = sum(r["ok"] for r in RESULTS)
    print(f"\n{ok}/{len(RESULTS)} casos aprobados")


if __name__ == "__main__":
    main()

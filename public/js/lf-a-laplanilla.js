// LiveFutbol → La Planilla
// Se corre en la pestaña "Alineación" de un partido de livefutbol.com
// (/match-report/.../lineup/). Lee la página y copia un JSON al portapapeles.
// No manda nada a ningún lado: el JSON se pega en /admin/import-detalles/livefutbol.
// El admin arma el favorito con este archivo (LivefutbolDetalleController::bookmarklet).
//
// Además lee la FICHA de cada persona (fecha de nacimiento, nacionalidad,
// lugar, puesto, altura, foto) pidiéndola desde esta misma pestaña: el servidor
// de La Planilla no puede bajar livefutbol (Cloudflare), el navegador sí.
// Como eso tarda unos segundos, al final no copia solo: muestra un recuadro
// con el botón «Copiar» (el portapapeles necesita un clic del usuario).
(function () {
  var d = document;
  var txt = function (el) { return el ? el.textContent.trim() : ''; };
  var idDe = function (href, pref) {
    var m = (href || '').match(new RegExp('/' + pref + '(\\d+)/'));
    return m ? +m[1] : null;
  };
  // "13." → {min:13, extra:0} · "90.+3" o "90+3" → {min:90, extra:3}
  var minuto = function (s) {
    var m = (s || '').match(/(\d+)\.?\s*(?:\+\s*(\d+))?/);
    return m ? { min: +m[1], extra: m[2] ? +m[2] : 0, txt: s.trim() } : null;
  };
  // "goal goal" → goal · "goal penalty" → penalty · "card yellow-red" → yellow-red
  var tipo = function (el) {
    var c = el.className.trim().split(/\s+/);
    return c.length > 1 ? c.slice(1).join(' ') : c[0];
  };
  var persona = function (a) {
    // url: el link a su ficha, para que el admin la lea (fecha de nacimiento, nacionalidad…)
    return a ? { nombre: txt(a), lf_id: idDe(a.getAttribute('href'), 'pe'), url: a.href } : null;
  };

  if (!d.querySelector('.hs-lineup')) {
    alert('LiveFutbol → La Planilla: abrí la pestaña «Alineación» del partido.');
    return;
  }

  var jugadores = function (sel) {
    return [].map.call(d.querySelectorAll(sel + ' .event.playing'), function (row) {
      var p = persona(row.querySelector('.person-name a')) || {};
      var j = {
        dorsal: txt(row.querySelector('.team_person-shirtnumber')) || null,
        nombre: p.nombre || '',
        lf_id: p.lf_id || null,
        url: p.url || null,
        goles: [], tarjetas: [], entra: null, sale: null
      };
      [].forEach.call(row.querySelectorAll('.match_event-goal > div'), function (e) {
        j.goles.push(Object.assign({ tipo: tipo(e) }, minuto(txt(e))));
      });
      [].forEach.call(row.querySelectorAll('.match_event-card > div'), function (e) {
        j.tarjetas.push(Object.assign({ tipo: tipo(e) }, minuto(txt(e))));
      });
      [].forEach.call(row.querySelectorAll('.match_event-playing > div'), function (e) {
        if (e.classList.contains('substitute-in')) j.entra = minuto(txt(e));
        if (e.classList.contains('substitute-out')) j.sale = minuto(txt(e));
      });
      return j;
    });
  };

  // DT y asistentes: los roles vienen como divs sueltos antes de cada grupo
  var cuerpoTecnico = function (sel) {
    var out = [], rol = '';
    var box = d.querySelector(sel);
    if (!box) return out;
    [].forEach.call(box.querySelectorAll('.role, .event'), function (el) {
      if (el.classList.contains('role')) { rol = txt(el); return; }
      var p = persona(el.querySelector('.person-name a'));
      if (p) out.push(Object.assign({ rol: rol }, p));
    });
    return out;
  };

  var lado = function (l) {
    return {
      titulares: jugadores('.hs-lineup--starter.' + l),
      suplentes: jugadores('.hs-lineup--bench.' + l),
      cuerpo_tecnico: cuerpoTecnico('.hs-lineup--coaches.' + l)
    };
  };

  var sb = d.querySelector('.hs-scoreboard');
  var ma = sb.querySelector('[data-match_id]');
  var equipo = function (l) {
    var a = sb.querySelector('.team-shortname-' + l + ' a') || sb.querySelector('.team-image-' + l + ' a');
    var img = sb.querySelector('.team-image-' + l + ' img');
    return {
      nombre: img ? img.getAttribute('alt') : txt(a),
      lf_id: a ? idDe(a.getAttribute('href'), 'te') : null,
      ganador: !!sb.querySelector('.team-image-' + l + '.hs-winner')
    };
  };
  var r = function (k) { return txt(sb.querySelector('.match-result-' + k)) || null; };

  var arbitros = [].map.call(sb.querySelectorAll('.hs-scoreboard-details .entry'), function (li) {
    var a = li.querySelector('.person-name a');
    if (!a) return null;
    return Object.assign({ rol: txt(li.querySelector('.match_person-name')) }, persona(a));
  }).filter(Boolean);

  var estadio = sb.querySelector('.venue-name a');
  var resumenGoles = [].map.call(sb.querySelectorAll('.hs-events li.event.goal'), function (li) {
    var p = persona(li.querySelector('.person-name a')) || {};
    return {
      lado: li.classList.contains('home') ? 'local' : 'visitante',
      tipo: tipo(li.querySelector('.match_event-kind')).replace('match_event-kind ', '').replace('match_event-kind-', ''),
      marcador: txt(li.querySelector('.match_event-content')),
      nombre: p.nombre || '',
      lf_id: p.lf_id || null,
      minuto: minuto(txt(li.querySelector('.match_event-minute')))
    };
  });
  var tarjetasRojas = [].map.call(sb.querySelectorAll('.hs-events li.event.card'), function (li) {
    var p = persona(li.querySelector('.person-name a')) || {};
    return {
      lado: li.classList.contains('home') ? 'local' : 'visitante',
      tipo: li.className.replace(/\b(event|card|home|away)\b/g, '').trim(),
      nombre: p.nombre || '', lf_id: p.lf_id || null,
      minuto: minuto(txt(li.querySelector('.match_event-minute')))
    };
  });

  var data = {
    fuente: 'livefutbol',
    version: 1,
    url: location.href.split('#')[0],
    lf_match_id: +(d.body.getAttribute('data-ma') || (ma && ma.getAttribute('data-match_id')) || 0) || null,
    fecha_utc: ma ? ma.getAttribute('data-datetime') : null,
    ronda: txt(sb.querySelector('.round-head')),
    local: equipo('home'),
    visitante: equipo('away'),
    resultado: {
      final: r(0),            // con penales, acá viene la tanda (ver "incidente")
      primer_tiempo: r(1),
      noventa: r(2),
      alargue: r(21),
      incidente: txt(sb.querySelector('.match-incident')) || null   // "pen." / "pró."
    },
    estadio: estadio ? { nombre: txt(estadio), lf_id: idDe(estadio.getAttribute('href'), 've') } : null,
    arbitros: arbitros,
    resumen_goles: resumenGoles,
    resumen_tarjetas: tarjetasRojas,
    equipos: { local: lado('home'), visitante: lado('away') }
  };

  // ── Fichas ────────────────────────────────────────────────────────────
  // dd-mm-aaaa tal cual: el admin la normaliza.
  // Manda TODOS los pares etiqueta → valor de la ficha (`pares`): qué es cada
  // uno lo decide el admin (LivefutbolPersona::normalizar), así un cambio de
  // etiqueta en livefutbol se arregla allá sin volver a instalar el favorito.
  var leerFicha = function (html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var pares = {};
    var poner = function (k, v) {
      k = (k || '').replace(/\s+/g, ' ').trim().replace(/:$/, '');
      v = (v || '').replace(/\s+/g, ' ').trim();
      if (k && v && !(k in pares)) pares[k] = v;
      else if (k && v && k === 'Nombre') pares['Nombre 2'] = v;
    };
    [].forEach.call(doc.querySelectorAll('dl dt'), function (dt) {
      var dd = dt.nextElementSibling;
      if (dd && dd.tagName === 'DD') poner(dt.textContent, dd.textContent);
    });
    [].forEach.call(doc.querySelectorAll('tr'), function (tr) {
      var c = tr.querySelectorAll('th, td');
      if (c.length === 2) poner(c[0].textContent, c[1].textContent);
    });
    var f = { pares: pares };
    var img = doc.querySelector('#previewArea img') || doc.querySelector('.person-image img, .person img');
    if (img && img.getAttribute('src') && !/avatar|silhouette|dummy|placeholder/i.test(img.getAttribute('src'))) {
      f.foto = new URL(img.getAttribute('src'), location.href).href;
    }
    return Object.keys(pares).length ? f : null;
  };

  var personas = [];
  ['local', 'visitante'].forEach(function (l) {
    var e = data.equipos[l];
    personas = personas.concat(e.titulares, e.suplentes, e.cuerpo_tecnico);
  });
  personas = personas.concat(data.arbitros).filter(function (p) { return p && p.url; });

  var porUrl = {};
  personas.forEach(function (p) { (porUrl[p.url] = porUrl[p.url] || []).push(p); });
  var urls = Object.keys(porUrl);

  var caja = d.createElement('div');
  caja.style.cssText = 'position:fixed;top:20px;right:20px;z-index:2147483647;background:#fff;color:#222;'
    + 'border:2px solid #15714e;border-radius:8px;padding:14px 16px;width:360px;font:14px/1.4 sans-serif;'
    + 'box-shadow:0 6px 24px rgba(0,0,0,.25)';
  caja.innerHTML = '<b>LiveFutbol → La Planilla</b><div class="lfp-estado" style="margin:8px 0">Leyendo fichas… 0/'
    + urls.length + '</div>';
  d.body.appendChild(caja);
  var estado = caja.querySelector('.lfp-estado');

  var hechas = 0, leidas = 0, i = 0;
  var una = function () {
    if (i >= urls.length) return Promise.resolve();
    var u = urls[i++];
    return fetch(u, { credentials: 'include' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        var f = html ? leerFicha(html) : null;
        if (f) { leidas++; porUrl[u].forEach(function (p) { p.ficha = f; }); }
      })
      .catch(function () {})
      .then(function () { hechas++; estado.textContent = 'Leyendo fichas… ' + hechas + '/' + urls.length; return una(); });
  };
  var hilos = [];
  for (var h = 0; h < Math.min(6, urls.length); h++) hilos.push(una());

  Promise.all(hilos).then(function () {
    data.fichas_leidas = leidas;
    var json = JSON.stringify(data);
    var n = data.equipos.local.titulares.length + '+' + data.equipos.visitante.titulares.length;
    estado.innerHTML = data.local.nombre + ' – ' + data.visitante.nombre + '<br>titulares ' + n
      + ' · fichas leídas ' + leidas + '/' + urls.length;
    var b = d.createElement('button');
    b.textContent = 'Copiar para La Planilla';
    b.style.cssText = 'background:#15714e;color:#fff;border:0;border-radius:4px;padding:8px 12px;font-weight:600;cursor:pointer';
    var x = d.createElement('button');
    x.textContent = 'Cerrar';
    x.style.cssText = 'margin-left:8px;background:#eee;border:0;border-radius:4px;padding:8px 12px;cursor:pointer';
    x.onclick = function () { caja.remove(); };
    b.onclick = function () {
      var listo = function () { b.textContent = 'Copiado ✓ — pegalo en el admin'; };
      var aMano = function () {
        var t = d.createElement('textarea');
        t.value = json; d.body.appendChild(t); t.select();
        try { d.execCommand('copy'); listo(); } catch (e) { prompt('Copiá esto:', json); }
        d.body.removeChild(t);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(json).then(listo, aMano);
      } else {
        aMano();
      }
    };
    caja.appendChild(b);
    caja.appendChild(x);
  });
})();

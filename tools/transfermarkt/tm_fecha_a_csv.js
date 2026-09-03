/*
 * TM → CSV  (Torneos Piña)
 * -------------------------------------------------------------
 * Genera el CSV de una fecha para importar en:
 *   Administracion → Importar fechas  (campo "Archivo CSV")
 *
 * CÓMO USAR
 *   1. Abrí en Transfermarkt la página de la JORNADA, por ejemplo:
 *      https://www.transfermarkt.com.ar/torneo-clausura/spieltag/wettbewerb/ARGC/saison_id/2025/spieltag/2
 *   2. Ejecutá este script (bookmarklet o pegándolo en la consola F12).
 *   3. Se descarga un CSV. Subilo en el formulario, elegí el Grupo y Guardar.
 *
 * FORMATO DE SALIDA (separador ";")
 *   numero ; grupo_id(vacío) ; fecha(YYYY-MM-DD) ; hora ; local ; resultado ; visitante
 *   - grupo_id va vacío a propósito: lo toma del Grupo que elegís en el formulario.
 *   - resultado vacío = partido no jugado (no fuerza 0:0).
 *
 * NOMBRES DE EQUIPOS
 *   El importador busca por  nombre LIKE %texto% , así que el texto del CSV debe
 *   estar CONTENIDO en el nombre de tu base. Editá el mapa ALIAS de abajo si algún
 *   equipo sale "NO encontrado": clave = nombre corto de TM (en minúscula, sin la
 *   posición), valor = un trozo distintivo del nombre en tu base.
 */
(function () {
  var DELIM = ';';

  // Mapa editable: nombre corto TM (minúscula, sin "(NN.)") -> texto que matchea tu base.
  var ALIAS = {
    'gimnasia (m)': 'Gimnasia de Mendoza',
    'gimnasia': 'Gimnasia de La Plata',
    'argentinos jrs.': 'Argentinos',
    'estudiantes rc': 'Río Cuarto',
    'estudiantes lp': 'Estudiantes (LP)',
    'racing': 'Racing',
    'barracas': 'Barracas',
    'def. y justicia': 'Defensa',
    'dep. riestra': 'Riestra',
    'instituto acc': 'Instituto',
    'vélez sarsfield': 'Vélez',
    'ind. rivadavia': 'Rivadavia',
    'atl. tucumán': 'Tucumán',
    'boca juniors': 'Boca',
    'unión': 'Unión de Santa Fe',
    'sarmiento': 'Sarmiento'
  };

  function strip(s) {
    return String(s || '')
      .replace(/\s+/g, ' ').trim()
      .replace(/^\(\d+\.\)\s*/, '')   // posición al inicio (local)
      .replace(/\s*\(\d+\.\)$/, '')   // posición al final (visitante)
      .trim();
  }
  function mapName(n) {
    var k = n.toLowerCase();
    return ALIAS[k] || n;
  }

  var mJ = location.pathname.match(/spieltag\/(\d+)/);
  if (!mJ) {
    alert('Abrí primero la página de la JORNADA en Transfermarkt\n(.../spieltag/wettbewerb/XXX/saison_id/AAAA/spieltag/N)');
    return;
  }
  var jornada = mJ[1];
  var mComp = location.pathname.match(/wettbewerb\/([A-Z0-9]+)/);
  var comp = mComp ? mComp[1] : 'TM';

  var gameRows = [].slice.call(document.querySelectorAll('tr')).filter(function (r) {
    return r.querySelector('a[href*="/spielbericht/index/spielbericht/"]') &&
           r.querySelector('.spieltagsansicht-vereinsname');
  });

  var lines = [];
  gameRows.forEach(function (r) {
    var cells = [].slice.call(r.querySelectorAll('td.spieltagsansicht-vereinsname.hide-for-small'))
      .map(function (c) { return strip(c.textContent); });
    if (cells.length < 2) return;
    var home = mapName(cells[0]);
    var away = mapName(cells[1]);

    var resCell = r.querySelector('.spieltagsansicht-ergebnis');
    var res = (resCell ? resCell.textContent : '').trim().replace(/\s+/g, '');
    res = /^\d+:\d+$/.test(res) ? res : '';

    var fecha = '', hora = '', node = r;
    for (var k = 0; k < 4 && node; k++) {
      node = node.nextElementSibling;
      if (!node) break;
      var m = node.textContent.match(/(\d{2})\/(\d{2})\/(\d{2,4}).*?(\d{1,2}:\d{2})/);
      if (m) {
        var yy = m[3].length === 2 ? ('20' + m[3]) : m[3];
        fecha = yy + '-' + m[2] + '-' + m[1];
        hora = m[4];
        break;
      }
    }
    lines.push([jornada, '', fecha, hora, home, res, away].join(DELIM));
  });

  if (!lines.length) {
    alert('No se encontraron partidos en esta página. ¿Es la vista de jornada (spieltag)?');
    return;
  }

  var csv = lines.join('\r\n') + '\r\n';
  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'fecha_' + comp + '_' + String(jornada).padStart(2, '0') + '.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();

  // Copia al portapapeles como respaldo (por si el navegador bloquea la descarga).
  try { navigator.clipboard.writeText(csv); } catch (e) {}
  alert('CSV generado: ' + lines.length + ' partidos (fecha ' + jornada + ').\nSe descargó y se copió al portapapeles.');
})();

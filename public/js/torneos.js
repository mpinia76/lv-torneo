/* torneos.js — tema, densidad, filas clickeables, menús y torneos recientes.
   No depende de jQuery. */
(function () {
    'use strict';

    var raiz = document.documentElement;
    var MAX_RECIENTES = 5;

    function guardar(clave, valor) {
        try { localStorage.setItem(clave, valor); } catch (e) { /* modo privado */ }
    }
    function leer(clave) {
        try { return localStorage.getItem(clave); } catch (e) { return null; }
    }

    /* ---------- tema ---------- */

    function temaActual() {
        return raiz.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function pintarBotonTema() {
        var icono = document.getElementById('icono-tema');
        if (!icono) return;
        var oscuro = temaActual() === 'dark';
        icono.className = oscuro ? 'bi bi-sun' : 'bi bi-moon-stars';
        var boton = icono.closest('button');
        if (boton) boton.setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
    }

    function alternarTema() {
        var nuevo = temaActual() === 'dark' ? 'light' : 'dark';
        raiz.setAttribute('data-bs-theme', nuevo);
        guardar('tema', nuevo);
        pintarBotonTema();
    }

    /* ---------- densidad ---------- */

    function aplicarDensidad(valor) {
        document.body.setAttribute('data-densidad', valor);
        guardar('densidad', valor);

        var botones = document.querySelectorAll('[data-densidad-valor]');
        for (var i = 0; i < botones.length; i++) {
            botones[i].classList.toggle('activo', botones[i].getAttribute('data-densidad-valor') === valor);
        }

        var icono = document.getElementById('icono-densidad');
        if (icono) {
            var compacto = valor === 'compacto';
            icono.className = compacto ? 'bi bi-arrows-expand' : 'bi bi-arrows-collapse';
            var boton = icono.closest('button');
            if (boton) boton.title = compacto ? 'Filas cómodas' : 'Filas compactas';
        }
    }

    function alternarDensidad() {
        aplicarDensidad(document.body.getAttribute('data-densidad') === 'compacto' ? 'comodo' : 'compacto');
    }

    /* ---------- torneos recientes ---------- */

    function leerRecientes() {
        try {
            var datos = JSON.parse(leer('torneosRecientes') || '[]');
            return Array.isArray(datos) ? datos : [];
        } catch (e) { return []; }
    }

    function registrarTorneoActual() {
        var barra = document.querySelector('.t-barra-torneo[data-torneo-id]');
        if (!barra) return;

        var torneo = {
            id: barra.getAttribute('data-torneo-id'),
            nombre: barra.getAttribute('data-torneo-nombre') || '',
            escudo: barra.getAttribute('data-torneo-escudo') || '',
            url: barra.getAttribute('data-torneo-url') || ''
        };
        if (!torneo.id || !torneo.url) return;

        var lista = leerRecientes().filter(function (t) { return t.id !== torneo.id; });
        lista.unshift(torneo);
        guardar('torneosRecientes', JSON.stringify(lista.slice(0, MAX_RECIENTES)));
    }

    function pintarRecientes() {
        var contenedor = document.getElementById('recientesMenu');
        var bloque = document.getElementById('mega-recientes');
        if (!contenedor || !bloque) return;

        var lista = leerRecientes();
        if (!lista.length) return;

        contenedor.innerHTML = '';
        lista.forEach(function (t) {
            var a = crear('a', 't-mega-chip');
            a.href = t.url;
            a.title = t.nombre;
            if (t.escudo) {
                var img = crear('img', 'escudo escudo-sm');
                img.src = t.escudo;
                img.alt = '';
                a.appendChild(img);
            }
            a.appendChild(crear('span', '', t.nombre));
            contenedor.appendChild(a);
        });

        bloque.hidden = false;
    }

    function crear(tag, clase, texto) {
        var nodo = document.createElement(tag);
        if (clase) nodo.className = clase;
        if (texto !== undefined && texto !== null) nodo.textContent = texto;
        return nodo;
    }

    /* ---------- menú Torneos: país / región -> competencia -> temporada ----------
       El panel viene vacío en el HTML; la primera vez que se abre (o que el mouse
       pasa por encima) baja el JSON de /torneos-menu y lo arma acá.            */

    var MAX_ANIOS = 6;       // temporadas a la vista por competencia; el resto con "+N"
    var MAX_RESULTADOS = 40;

    var mega = { panel: null, datos: null, cargando: false, zona: null };

    function normalizar(s) {
        s = (s || '').toLowerCase();
        if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
        return s.replace(/\s+/g, ' ').trim();
    }

    function esCelular() {
        return window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches;
    }

    function cargarMega() {
        var panel = mega.panel;
        if (!panel || mega.datos || mega.cargando) return;
        mega.cargando = true;

        fetch(panel.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (d) {
                mega.cargando = false;
                mega.datos = prepararMega(d);
                pintarZonas();

                var inicial = panel.getAttribute('data-zona');
                if (!mega.datos.porClave[inicial]) inicial = mega.datos.zonas.length ? mega.datos.zonas[0].c : null;
                if (inicial) elegirZona(inicial, false);

                var campo = document.getElementById('mega-buscar');
                if (campo && campo.value) buscarMega(campo.value);
            })
            .catch(function () {
                mega.cargando = false;
                var lista = document.getElementById('mega-lista');
                if (!lista) return;
                lista.innerHTML = '';
                var aviso = crear('div', 't-mega-aviso', 'No se pudo cargar el menú. ');
                var enlace = crear('a', '', 'Ver todas las competiciones');
                enlace.href = panel.getAttribute('data-explorar');
                aviso.appendChild(enlace);
                lista.appendChild(aviso);
            });
    }

    function prepararMega(d) {
        var datos = { url: d.url, zonas: d.zonas || [], porClave: {} };
        datos.zonas.forEach(function (z) {
            datos.porClave[z.c] = z;
            z.cs.forEach(function (c) {
                c.clave = normalizar(c.n + ' ' + z.n);
            });
        });
        return datos;
    }

    function pintarZonas() {
        var nav = document.getElementById('mega-zonas');
        if (!nav) return;
        nav.innerHTML = '';

        var titulos = { local: '', inter: 'Internacional', paises: 'Ligas del mundo' };
        var grupoActual = null;

        mega.datos.zonas.forEach(function (z) {
            if (z.g !== grupoActual) {
                grupoActual = z.g;
                if (titulos[z.g]) nav.appendChild(crear('div', 't-mega-rot', titulos[z.g]));
            }

            var boton = crear('button', 't-mega-zona');
            boton.type = 'button';
            boton.setAttribute('data-zona', z.c);
            boton.appendChild(iconoZona(z));
            boton.appendChild(crear('span', 't-mega-zona-nombre', z.n));
            boton.appendChild(crear('span', 't-mega-cuenta', String(z.cs.length)));
            nav.appendChild(boton);
        });
    }

    function iconoZona(z) {
        if (z.b) {
            var img = crear('img', 'bandera');
            img.src = z.b;
            img.alt = '';
            img.onerror = function () {
                var globo = crear('i', 'bi bi-flag');
                if (img.parentNode) img.parentNode.replaceChild(globo, img);
            };
            return img;
        }
        return crear('i', 'bi bi-globe2');
    }

    function escudoMini(c) {
        if (c.e) {
            var img = crear('img', 'escudo escudo-sm');
            img.src = c.e;
            img.alt = '';
            img.loading = 'lazy';
            return img;
        }
        var ini = c.n.split(/\s+/).filter(Boolean).slice(0, 2).map(function (p) { return p.charAt(0); }).join('').toUpperCase();
        return crear('span', 'escudo escudo-sm escudo-txt', ini);
    }

    function enlaceEdicion(ed, clase) {
        var a = crear('a', clase, ed[1]);
        a.href = mega.datos.url + ed[0];
        if (String(ed[0]) === mega.panel.getAttribute('data-activo')) a.classList.add('activo');
        return a;
    }

    function filaCompetencia(c, zona) {
        var fila = crear('div', 't-mega-comp');

        var nombre = crear('a', 't-mega-comp-nombre');
        nombre.href = mega.datos.url + c.ed[0][0];
        nombre.title = c.n + ' ' + c.ed[0][1];
        nombre.appendChild(escudoMini(c));
        var txt = crear('span', 't-mega-comp-txt');
        txt.appendChild(crear('span', 't-mega-comp-n', c.n));
        if (zona) {
            var z = crear('span', 't-mega-comp-zona');
            z.appendChild(iconoZona(zona));
            z.appendChild(document.createTextNode(zona.n));
            txt.appendChild(z);
        }
        nombre.appendChild(txt);
        fila.appendChild(nombre);

        var anios = crear('div', 't-mega-anios');
        c.ed.forEach(function (ed, i) {
            var chip = enlaceEdicion(ed, 't-mega-anio');
            if (i >= MAX_ANIOS) chip.hidden = true;
            anios.appendChild(chip);
        });
        if (c.ed.length > MAX_ANIOS) {
            var mas = crear('button', 't-mega-anio t-mega-mas', '+' + (c.ed.length - MAX_ANIOS));
            mas.type = 'button';
            mas.title = 'Ver todas las temporadas';
            mas.addEventListener('click', function () {
                var ocultos = anios.querySelectorAll('[hidden]');
                for (var i = 0; i < ocultos.length; i++) ocultos[i].hidden = false;
                mas.remove();
            });
            anios.appendChild(mas);
        }
        fila.appendChild(anios);

        return fila;
    }

    function cabeceraLista(icono, titulo, enlace) {
        var cab = crear('div', 't-mega-lista-cab');

        var volver = crear('button', 't-mega-volver');
        volver.type = 'button';
        volver.setAttribute('aria-label', 'Volver a la lista de países');
        volver.appendChild(crear('i', 'bi bi-chevron-left'));
        volver.addEventListener('click', function () {
            mega.panel.classList.remove('t-mega--detalle');
        });
        cab.appendChild(volver);

        if (icono) cab.appendChild(icono);
        cab.appendChild(crear('span', 't-mega-lista-titulo', titulo));

        if (enlace) {
            var a = crear('a', 't-mega-lista-link', 'Ver página');
            a.href = enlace;
            cab.appendChild(a);
        }
        return cab;
    }

    function elegirZona(clave, abrirDetalle) {
        var z = mega.datos && mega.datos.porClave[clave];
        if (!z) return;
        mega.zona = clave;

        var botones = document.querySelectorAll('#mega-zonas .t-mega-zona');
        for (var i = 0; i < botones.length; i++) {
            var activo = botones[i].getAttribute('data-zona') === clave;
            botones[i].classList.toggle('activo', activo);
            botones[i].setAttribute('aria-current', activo ? 'true' : 'false');
        }

        var lista = document.getElementById('mega-lista');
        lista.innerHTML = '';
        lista.appendChild(cabeceraLista(iconoZona(z), z.n,
            mega.panel.getAttribute('data-explorar') + '?zona=' + encodeURIComponent(z.c)));

        var vigentes = z.cs.filter(function (c) { return !c.h; });
        var historicas = z.cs.filter(function (c) { return c.h; });
        var hayLigas = vigentes.some(function (c) { return c.t === 'L'; });
        var hayCopas = vigentes.some(function (c) { return c.t === 'C'; });

        var tipoActual = null;
        vigentes.forEach(function (c) {
            if (hayLigas && hayCopas && c.t !== tipoActual) {
                tipoActual = c.t;
                lista.appendChild(crear('div', 't-mega-rot', c.t === 'L' ? 'Ligas' : 'Copas'));
            }
            lista.appendChild(filaCompetencia(c));
        });

        if (historicas.length) {
            var caja = crear('div', 't-mega-historicas');
            var boton = crear('button', 't-mega-toggle');
            boton.type = 'button';
            boton.setAttribute('aria-expanded', vigentes.length ? 'false' : 'true');
            boton.appendChild(crear('i', 'bi bi-chevron-right'));
            boton.appendChild(document.createTextNode(' Torneos que ya no se juegan (' + historicas.length + ')'));
            var cuerpo = crear('div', 't-mega-historicas-cuerpo');
            cuerpo.hidden = vigentes.length > 0;
            historicas.forEach(function (c) { cuerpo.appendChild(filaCompetencia(c)); });
            boton.addEventListener('click', function () {
                cuerpo.hidden = !cuerpo.hidden;
                boton.setAttribute('aria-expanded', String(!cuerpo.hidden));
            });
            caja.appendChild(boton);
            caja.appendChild(cuerpo);
            lista.appendChild(caja);
        }

        lista.scrollTop = 0;
        if (abrirDetalle) mega.panel.classList.add('t-mega--detalle');
    }

    function buscarMega(texto) {
        if (!mega.datos) return;
        var q = normalizar(texto);

        if (q.length < 2) {
            if (mega.zona) elegirZona(mega.zona, false);
            mega.panel.classList.remove('t-mega--busca');
            return;
        }

        var palabras = q.split(' ');
        var anio = null;
        palabras = palabras.filter(function (p) {
            if (/^\d{2,4}$/.test(p)) { anio = p; return false; }
            return true;
        });

        var resultados = [];
        mega.datos.zonas.some(function (z) {
            z.cs.some(function (c) {
                for (var i = 0; i < palabras.length; i++) {
                    if (c.clave.indexOf(palabras[i]) === -1) return false;
                }
                if (anio) {
                    var eds = c.ed.filter(function (ed) { return String(ed[1]).indexOf(anio) > -1; });
                    if (!eds.length) return false;
                    resultados.push({ c: { n: c.n, e: c.e, ed: eds }, z: z });
                } else {
                    resultados.push({ c: c, z: z });
                }
                return resultados.length >= MAX_RESULTADOS;
            });
            return resultados.length >= MAX_RESULTADOS;
        });

        var lista = document.getElementById('mega-lista');
        lista.innerHTML = '';
        var titulo = resultados.length
            ? (resultados.length >= MAX_RESULTADOS ? 'Primeros ' + MAX_RESULTADOS + ' resultados' : resultados.length + (resultados.length === 1 ? ' resultado' : ' resultados'))
            : 'Sin resultados';
        lista.appendChild(cabeceraLista(crear('i', 'bi bi-search'), titulo, null));

        if (!resultados.length) {
            lista.appendChild(crear('div', 't-mega-aviso', 'No hay torneos que coincidan con «' + texto.trim() + '».'));
        }
        resultados.forEach(function (r) { lista.appendChild(filaCompetencia(r.c, r.z)); });

        mega.panel.classList.add('t-mega--busca', 't-mega--detalle');
        lista.scrollTop = 0;
    }

    function iniciarMega() {
        mega.panel = document.getElementById('menu-torneos');
        if (!mega.panel) return;

        var toggle = document.getElementById('torneosDropdown');
        var campo = document.getElementById('mega-buscar');

        // Se baja apenas el mouse pasa por encima: cuando llega el clic ya está.
        if (toggle) {
            toggle.addEventListener('pointerenter', cargarMega);
            toggle.addEventListener('focus', cargarMega);
            toggle.addEventListener('show.bs.dropdown', cargarMega);
            toggle.addEventListener('shown.bs.dropdown', function () {
                if (campo && !esCelular()) campo.focus();
            });
        }

        document.getElementById('mega-zonas').addEventListener('click', function (ev) {
            var boton = ev.target.closest('.t-mega-zona');
            if (!boton) return;
            if (campo) campo.value = '';
            mega.panel.classList.remove('t-mega--busca');
            elegirZona(boton.getAttribute('data-zona'), true);
        });

        if (campo) {
            campo.addEventListener('input', function () { buscarMega(campo.value); });
            campo.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    var primero = document.querySelector('#mega-lista .t-mega-comp a');
                    if (primero) { ev.preventDefault(); window.location = primero.href; }
                } else if (ev.key === 'Escape' && campo.value) {
                    ev.stopPropagation();
                    campo.value = '';
                    buscarMega('');
                }
            });
        }
    }

    /* ---------- filtro de los menús de torneos ----------
       Además de las opciones, esconde el encabezado del año cuando se queda
       sin torneos, y deja buscar por año ("2015") además de por nombre.     */

    window.filterDropdown = function (input, contenedorId) {
        var contenedor = document.getElementById(contenedorId);
        if (!contenedor) return;

        var texto = (input.value || '').toUpperCase().trim();
        var grupos = contenedor.querySelectorAll('.t-menu-grupo');
        var visiblesTotal = 0;

        if (grupos.length === 0) {
            var enlaces = contenedor.querySelectorAll('a');
            for (var i = 0; i < enlaces.length; i++) {
                var coincide = (enlaces[i].textContent || '').toUpperCase().indexOf(texto) > -1;
                enlaces[i].parentElement.style.display = coincide ? '' : 'none';
                if (coincide) visiblesTotal++;
            }
        } else {
            for (var g = 0; g < grupos.length; g++) {
                var grupo = grupos[g];
                var anio = (grupo.getAttribute('data-anio') || '').toUpperCase();
                var porAnio = texto !== '' && anio.indexOf(texto) > -1;
                var opciones = grupo.querySelectorAll('a');
                var visibles = 0;

                for (var o = 0; o < opciones.length; o++) {
                    var ok = porAnio || (opciones[o].textContent || '').toUpperCase().indexOf(texto) > -1;
                    opciones[o].parentElement.style.display = ok ? '' : 'none';
                    if (ok) visibles++;
                }
                grupo.style.display = visibles ? '' : 'none';
                visiblesTotal += visibles;
            }
        }

        var vacio = contenedor.parentElement.querySelector('.t-menu-vacio');
        if (vacio) vacio.hidden = visiblesTotal > 0;
    };

    /* ---------- arranque ---------- */

    document.addEventListener('DOMContentLoaded', function () {
        pintarBotonTema();

        var botonTema = document.getElementById('boton-tema');
        if (botonTema) botonTema.addEventListener('click', alternarTema);

        aplicarDensidad(leer('densidad') === 'compacto' ? 'compacto' : 'comodo');

        var botonDensidad = document.getElementById('boton-densidad');
        if (botonDensidad) botonDensidad.addEventListener('click', alternarDensidad);

        registrarTorneoActual();
        pintarRecientes();
        iniciarMega();

        document.addEventListener('click', function (ev) {
            var boton = ev.target.closest('[data-densidad-valor]');
            if (boton) {
                ev.preventDefault();
                aplicarDensidad(boton.getAttribute('data-densidad-valor'));
            }
        });

        /* al abrir un menú con buscador, el cursor va directo al campo */
        document.addEventListener('shown.bs.dropdown', function (ev) {
            var menu = ev.target.querySelector('.dropdown-menu');
            if (!menu) return;
            var campo = menu.querySelector('.t-menu-buscador input');
            if (campo) campo.focus();
        });

        /* "/" lleva al buscador general, como en cualquier sitio de datos */
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
            var etiqueta = (ev.target.tagName || '').toLowerCase();
            if (etiqueta === 'input' || etiqueta === 'textarea' || ev.target.isContentEditable) return;
            var campo = document.getElementById('buscador-general');
            if (campo) { ev.preventDefault(); campo.focus(); }
        });

        /* filas de partido: toda la fila lleva al detalle, menos los enlaces internos */
        document.addEventListener('click', function (ev) {
            var fila = ev.target.closest('[data-href]');
            if (!fila) return;
            if (ev.target.closest('a')) return;
            window.location = fila.getAttribute('data-href');
        });
    });
})();

/* ------------------------------------------------------------------
   Listados de Protagonistas: abre y cierra la fila con el detalle por
   club. Delegado en document para que sirva en las cinco pantallas.
   ------------------------------------------------------------------ */
document.addEventListener('click', function (ev) {
    var boton = ev.target.closest ? ev.target.closest('[data-lista-abre]') : null;
    if (!boton) { return; }

    var fila = document.getElementById(boton.getAttribute('data-lista-abre'));
    if (!fila) { return; }

    var abierta = !fila.hidden;
    fila.hidden = abierta;
    boton.classList.toggle('activo', !abierta);
    boton.setAttribute('aria-expanded', String(!abierta));
});

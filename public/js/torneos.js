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
        var item = document.getElementById('menu-recientes');
        if (!contenedor || !item) return;

        var lista = leerRecientes();
        if (!lista.length) return;

        contenedor.innerHTML = '';
        lista.forEach(function (t) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.className = 'dropdown-item';
            a.href = t.url;

            if (t.escudo) {
                var img = document.createElement('img');
                img.className = 'escudo escudo-sm';
                img.src = t.escudo;
                img.alt = '';
                a.appendChild(img);
            }
            var texto = document.createElement('span');
            texto.textContent = t.nombre;
            a.appendChild(texto);

            li.appendChild(a);
            contenedor.appendChild(li);
        });

        item.hidden = false;
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

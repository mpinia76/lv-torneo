/* torneos.js — tema claro/oscuro, densidad y filas de partido clickeables.
   No depende de jQuery. */
(function () {
    'use strict';

    var raiz = document.documentElement;

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
    }

    /* ---------- arranque ---------- */

    document.addEventListener('DOMContentLoaded', function () {
        pintarBotonTema();

        var botonTema = document.getElementById('boton-tema');
        if (botonTema) botonTema.addEventListener('click', alternarTema);

        aplicarDensidad(leer('densidad') === 'compacto' ? 'compacto' : 'comodo');

        document.addEventListener('click', function (ev) {
            var boton = ev.target.closest('[data-densidad-valor]');
            if (boton) {
                ev.preventDefault();
                aplicarDensidad(boton.getAttribute('data-densidad-valor'));
            }
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

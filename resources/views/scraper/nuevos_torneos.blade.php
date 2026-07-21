@extends('layouts.app')

@section('pageTitle', 'Torneos nuevos')

@section('content')
    <div class="container">
        <h3 class="mb-3">Torneos nuevos a cargar</h3>
        <p class="text-muted">
            Recorre los jugadores y DTs que tienen URL de Transfermarkt guardada y busca torneos
            que todavía no cargaste (los que trae el scraper, sin contar los excluidos ni los ya cargados).
        </p>

        <div class="d-flex align-items-center mb-3">
            <button type="button" id="btnBuscar" class="btn btn-primary mr-3" onclick="buscarNuevos()">
                🔎 Buscar torneos nuevos
            </button>
            <span id="progreso" class="text-muted"></span>
        </div>

        <div id="resumen" class="mb-3"></div>
        <div id="resultado"></div>
    </div>

    <script>
        const ENTIDADES   = @json($entidades);
        const EP_JUGADOR  = "{{ url('/admin/scraper/jugador-transfermarkt') }}";
        const EP_TECNICO  = "{{ url('/admin/scraper/tecnico-transfermarkt') }}";
        const URL_JUGADOR = "{{ url('/admin/jugador-estadisticas/createPorJugador') }}";
        const URL_TECNICO = "{{ url('/admin/tecnico-estadisticas/createPorTecnico') }}";

        function esc(s) {
            return (s == null ? '' : String(s)).replace(/[&<>"']/g, m => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        async function buscarNuevos() {
            const btn = document.getElementById('btnBuscar');
            const prog = document.getElementById('progreso');
            const cont = document.getElementById('resultado');
            const resumen = document.getElementById('resumen');

            if (!ENTIDADES.length) {
                resumen.innerHTML = '<div class="alert alert-warning">No hay jugadores ni DTs con URL de Transfermarkt guardada. Scrapeá alguno primero y su URL queda guardada sola.</div>';
                return;
            }

            btn.disabled = true;
            cont.innerHTML = '';
            resumen.innerHTML = '';
            let conNuevos = 0, alDia = 0, errores = 0, totalTorneos = 0;

            for (let i = 0; i < ENTIDADES.length; i++) {
                const e = ENTIDADES[i];
                prog.innerHTML = `⏳ ${i + 1}/${ENTIDADES.length} — ${esc(e.nombre)}`;

                const ep  = e.tipo === 'jugador' ? EP_JUGADOR : EP_TECNICO;
                const idp = e.tipo === 'jugador' ? 'jugador_id' : 'tecnico_id';
                const base = e.tipo === 'jugador' ? URL_JUGADOR : URL_TECNICO;

                try {
                    const res = await fetch(`${ep}?url=${encodeURIComponent(e.url)}&${idp}=${e.id}`);
                    const r = await res.json();
                    const data = Array.isArray(r) ? r : (r.data || []);

                    if (data.length) {
                        conNuevos++;
                        totalTorneos += data.length;
                        const filas = data.map(t =>
                            `<li>${esc(t.competition)} — <span class="text-muted">${esc(t.equipo)}</span></li>`
                        ).join('');
                        cont.insertAdjacentHTML('beforeend', `
                            <div class="card mb-2">
                                <div class="card-header d-flex align-items-center" style="background:#f1f8f1;">
                                    <strong>${e.tipo === 'jugador' ? '👤' : '🎽'} ${esc(e.nombre)}</strong>
                                    <span class="badge badge-success ml-2">${data.length} nuevo(s)</span>
                                    <a href="${base}/${e.id}" target="_blank" class="btn btn-sm btn-primary ml-auto">Cargar ▸</a>
                                </div>
                                <div class="card-body py-2">
                                    <ul class="mb-0" style="font-size:0.9rem;">${filas}</ul>
                                </div>
                            </div>`);
                    } else {
                        alDia++;
                    }
                } catch (err) {
                    console.error('Error en ' + e.nombre, err);
                    errores++;
                }
            }

            prog.innerHTML = '';
            btn.disabled = false;
            resumen.innerHTML = `<div class="alert alert-info">
                Revisados <strong>${ENTIDADES.length}</strong> ·
                <strong>${conNuevos}</strong> con torneos nuevos (${totalTorneos} en total) ·
                <strong>${alDia}</strong> al día` +
                (errores ? ` · <span class="text-danger">${errores} con error</span>` : '') +
                `</div>`;
            if (!conNuevos) {
                cont.innerHTML = '<div class="alert alert-success">Todo al día, no hay torneos nuevos.</div>';
            }
        }
    </script>
@endsection

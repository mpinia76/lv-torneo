@extends('layouts.app')

@section('pageTitle', 'Torneos nuevos')

@section('content')
    <div class="container">
        <h3 class="mb-1">Torneos nuevos a cargar</h3>
        <p class="text-muted">
            Recorre los jugadores y DTs con URL de Transfermarkt guardada y busca torneos que
            todavía no cargaste (sin contar excluidos ni ya cargados).
        </p>

        {{-- Pestañas Jugadores / DTs --}}
        <ul class="nav nav-tabs mb-3" id="tabs">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-jugadores">
                    👤 Jugadores <span class="badge badge-secondary" id="cnt-jugador">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-tecnicos">
                    🎽 DTs <span class="badge badge-secondary" id="cnt-tecnico">0</span>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-jugadores">
                <div class="d-flex align-items-center mb-3">
                    <button type="button" class="btn btn-primary mr-3" onclick="buscarNuevos('jugador')">
                        🔎 Buscar torneos nuevos (jugadores)
                    </button>
                    <span id="progreso-jugador" class="text-muted"></span>
                </div>
                <div id="resumen-jugador" class="mb-3"></div>
                <div id="resultado-jugador"></div>
            </div>

            <div class="tab-pane fade" id="tab-tecnicos">
                <div class="d-flex align-items-center mb-3">
                    <button type="button" class="btn btn-primary mr-3" onclick="buscarNuevos('tecnico')">
                        🔎 Buscar torneos nuevos (DTs)
                    </button>
                    <span id="progreso-tecnico" class="text-muted"></span>
                </div>
                <div id="resumen-tecnico" class="mb-3"></div>
                <div id="resultado-tecnico"></div>
            </div>
        </div>
    </div>

    <script>
        const ENTIDADES   = @json($entidades);
        const EP = {
            jugador: "{{ url('/admin/scraper/jugador-transfermarkt') }}",
            tecnico: "{{ url('/admin/scraper/tecnico-transfermarkt') }}",
        };
        const URL_FICHA = {
            jugador: "{{ url('/admin/jugador-estadisticas/createPorJugador') }}",
            tecnico: "{{ url('/admin/tecnico-estadisticas/createPorTecnico') }}",
        };
        const ICONO = { jugador: '👤', tecnico: '🎽' };

        // Contadores por tipo
        document.getElementById('cnt-jugador').textContent = ENTIDADES.filter(e => e.tipo === 'jugador').length;
        document.getElementById('cnt-tecnico').textContent = ENTIDADES.filter(e => e.tipo === 'tecnico').length;

        function esc(s) {
            return (s == null ? '' : String(s)).replace(/[&<>"']/g, m => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        async function buscarNuevos(tipo) {
            const lista = ENTIDADES.filter(e => e.tipo === tipo);
            const prog    = document.getElementById('progreso-' + tipo);
            const cont    = document.getElementById('resultado-' + tipo);
            const resumen = document.getElementById('resumen-' + tipo);

            if (!lista.length) {
                resumen.innerHTML = '<div class="alert alert-warning">No hay ' +
                    (tipo === 'jugador' ? 'jugadores' : 'DTs') +
                    ' con URL de Transfermarkt guardada. Scrapeá alguno primero y la URL queda guardada sola.</div>';
                return;
            }

            cont.innerHTML = '';
            resumen.innerHTML = '';
            let conNuevos = 0, alDia = 0, errores = 0, totalTorneos = 0;

            for (let i = 0; i < lista.length; i++) {
                const e = lista[i];
                prog.innerHTML = `⏳ ${i + 1}/${lista.length} — ${esc(e.nombre)}`;
                const idp = tipo === 'jugador' ? 'jugador_id' : 'tecnico_id';

                try {
                    const res = await fetch(`${EP[tipo]}?url=${encodeURIComponent(e.url)}&${idp}=${e.id}`);
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
                                    <strong>${ICONO[tipo]} ${esc(e.nombre)}</strong>
                                    <span class="badge badge-success ml-2">${data.length} nuevo(s)</span>
                                    <a href="${URL_FICHA[tipo]}/${e.id}" target="_blank" class="btn btn-sm btn-primary ml-auto">Cargar ▸</a>
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
            resumen.innerHTML = `<div class="alert alert-info">
                Revisados <strong>${lista.length}</strong> ·
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

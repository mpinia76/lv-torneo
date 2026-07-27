@extends('layouts.app')

@section('pageTitle', 'Torneos nuevos')

@section('content')
    <div class="container">
        <h3 class="mb-1">Torneos nuevos a cargar</h3>
        <p class="text-muted">
            Recorre los jugadores y DTs con URL de Transfermarkt guardada y busca torneos que
            todavía no cargaste. Abajo de cada uno, los que <strong>faltan</strong> (sin URL).
        </p>

        {{-- Pestañas (toggle propio, sin depender de Bootstrap) --}}
        <div class="btn-group mb-3" role="group">
            <button type="button" id="tab-btn-jugador" class="btn btn-outline-primary active" onclick="mostrarTab('jugador')">
                👤 Jugadores <span class="badge badge-light" id="cnt-jugador">0</span>
            </button>
            <button type="button" id="tab-btn-tecnico" class="btn btn-outline-primary" onclick="mostrarTab('tecnico')">
                🎽 DTs <span class="badge badge-light" id="cnt-tecnico">0</span>
            </button>
        </div>

        @foreach (['jugador' => 'jugadores', 'tecnico' => 'DTs'] as $tipo => $label)
            @php
                $sinUrl   = $tipo === 'jugador' ? $sinUrlJug : $sinUrlTec;
                $qActual  = $tipo === 'jugador' ? $qj : $qt;
                $qParam   = $tipo === 'jugador' ? 'qj' : 'qt';
                $rutaFicha = $tipo === 'jugador' ? 'jugador-estadisticas.createPorJugador' : 'tecnico-estadisticas.createPorTecnico';
            @endphp
            <div id="seccion-{{ $tipo }}" class="seccion-tab" style="{{ $tipo === 'jugador' ? '' : 'display:none;' }}">

                {{-- Buscar torneos nuevos (los que tienen URL) --}}
                <div class="d-flex align-items-center mb-3">
                    <button type="button" class="btn btn-primary mr-3" onclick="buscarNuevos('{{ $tipo }}')">
                        🔎 Buscar torneos nuevos ({{ $label }})
                    </button>
                    <span id="progreso-{{ $tipo }}" class="text-muted"></span>
                </div>
                <div id="resumen-{{ $tipo }}" class="mb-3"></div>
                <div id="resultado-{{ $tipo }}"></div>

                {{-- Faltan cargar URL --}}
                <hr>
                <h5 class="mb-2">Faltan cargar URL <span class="badge badge-warning">{{ $sinUrl->total() }}</span></h5>
                <form method="GET" class="form-inline mb-2">
                    <input type="hidden" name="tab" value="{{ $tipo }}">
                    <input type="text" name="{{ $qParam }}" value="{{ $qActual }}" class="form-control form-control-sm mr-2"
                           placeholder="Buscar por nombre…">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Buscar</button>
                    @if ($qActual !== '')
                        <a href="{{ route('scraper.nuevos-torneos', ['tab' => $tipo]) }}" class="btn btn-sm btn-link">limpiar</a>
                    @endif
                </form>

                @if ($sinUrl->count())
                    <ul class="list-unstyled" style="font-size:0.9rem;">
                        @foreach ($sinUrl as $item)
                            <li class="d-flex align-items-center py-1 border-bottom">
                                <span>{{ optional($item->persona)->name ?: ('#'.$item->id) }}</span>
                                <a href="{{ route($rutaFicha, $item->id) }}" target="_blank"
                                   class="btn btn-sm btn-outline-primary ml-auto">Cargar URL ▸</a>
                            </li>
                        @endforeach
                    </ul>
                    {{ $sinUrl->links() }}
                @else
                    <div class="alert alert-success">No falta ninguno.</div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        const ENTIDADES = @json($entidades);
        const EP = {
            jugador: "{{ url('/admin/scraper/jugador-transfermarkt') }}",
            tecnico: "{{ url('/admin/scraper/tecnico-transfermarkt') }}",
        };
        const URL_FICHA = {
            jugador: "{{ url('/admin/jugador-estadisticas/createPorJugador') }}",
            tecnico: "{{ url('/admin/tecnico-estadisticas/createPorTecnico') }}",
        };
        const ICONO = { jugador: '👤', tecnico: '🎽' };
        let tabActual = 'jugador';

        document.getElementById('cnt-jugador').textContent = ENTIDADES.filter(e => e.tipo === 'jugador').length;
        document.getElementById('cnt-tecnico').textContent = ENTIDADES.filter(e => e.tipo === 'tecnico').length;

        function mostrarTab(tipo) {
            tabActual = tipo;
            document.getElementById('seccion-jugador').style.display = (tipo === 'jugador') ? '' : 'none';
            document.getElementById('seccion-tecnico').style.display = (tipo === 'tecnico') ? '' : 'none';
            document.getElementById('tab-btn-jugador').classList.toggle('active', tipo === 'jugador');
            document.getElementById('tab-btn-tecnico').classList.toggle('active', tipo === 'tecnico');
        }

        // Mantener la pestaña al paginar/buscar (viene en ?tab=)
        (function () {
            const t = new URLSearchParams(location.search).get('tab');
            if (t === 'tecnico') mostrarTab('tecnico');
        })();

        // Al paginar, preservar la pestaña activa
        document.addEventListener('click', function (ev) {
            const a = ev.target.closest('.pagination a');
            if (!a || !a.href) return;
            ev.preventDefault();
            const u = new URL(a.href);
            u.searchParams.set('tab', tabActual);
            window.location.href = u.toString();
        });

        function esc(s) {
            return (s == null ? '' : String(s)).replace(/[&<>"']/g, m => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        async function buscarNuevos(tipo) {
            const lista   = ENTIDADES.filter(e => e.tipo === tipo);
            const prog    = document.getElementById('progreso-' + tipo);
            const cont    = document.getElementById('resultado-' + tipo);
            const resumen = document.getElementById('resumen-' + tipo);

            if (!lista.length) {
                resumen.innerHTML = '<div class="alert alert-warning">No hay ' +
                    (tipo === 'jugador' ? 'jugadores' : 'DTs') +
                    ' con URL guardada todavía. Cargá alguno abajo (en "Faltan cargar URL").</div>';
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

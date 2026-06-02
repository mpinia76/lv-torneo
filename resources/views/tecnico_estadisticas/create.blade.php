@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $tecnico->persona->foto ? url('images/'.$tecnico->persona->foto) : url('images/sin_foto_tecnico.png') }}"
                     alt="Foto tecnico" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $tecnico->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $tecnico->persona->nombre }} {{ $tecnico->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $tecnico->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $tecnico->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $tecnico->persona->nacimiento ? $tecnico->persona->getAgeAttribute() : '' }}
                    </div>
                </div>

                @if($tecnico->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $tecnico->persona->observaciones }}</div>
                    </div>
                @endif
            </div>
        </div>
        <hr/>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-3 mt-3">
            <label>Importar desde FootballDatabase</label>
            <div class="d-flex">
                <input type="text" id="footballdbUrl" class="form-control mr-2"
                       placeholder="https://www.footballdatabase.eu/es/jugador/detalles/31288-julio_cesar-falcioni">
                <button type="button" class="btn btn-warning" onclick="scrapearFootballDB()" style="white-space:nowrap;">
                    🌐 Scrapear
                </button>
            </div>
        </div>
        <div class="mb-3 mt-3">
            <label>Importar desde Transfermarkt</label>
            <div class="d-flex">
                <input type="text" id="tmUrlTecnico" class="form-control mr-2"
                       placeholder="https://www.transfermarkt.com.ar/<slug>/leistungsdatenDetail/trainer/17428?saison_id=2024">
                <button type="button" class="btn btn-warning" onclick="scrapearTransfermarktTecnico()" style="white-space:nowrap;">🌐 Scrapear TM</button>
            </div>
            <small class="text-muted">Pegá la URL de "Datos de rendimiento" con <code>saison_id</code>. Para otra temporada cambialo y volvé a scrapear.</small>
            <div id="tmProgresoTecnico" class="mt-2"></div>
        </div>
        <div id="loadingScraper" style="display:none;" class="alert alert-info">
            ⏳ Cargando historial, puede tardar unos segundos...
        </div>
        <div id="resultadoScraper" class="mt-3"></div>

        <hr>

        <form action="{{ route('tecnico-estadisticas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- 🔒 tecnico --}}
            <input type="hidden" name="tecnico_id" value="{{ $tecnico->id }}">

            <div class="row">

                {{-- torneo --}}
                <div class="form-group col-md-4">
                    <label>Torneo</label>
                    <input type="text" name="torneo_nombre" class="form-control"
                           value="{{ old('torneo_nombre') }}">
                </div>

                {{-- logo --}}
                <div class="form-group col-md-3">
                    <label>Logo torneo</label>
                    <input type="file" class="form-control" name="escudoTmp">
                    <input type="hidden" name="torneo_logo_guardado" value="">
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    {{Form::label('tipo', 'Tipo')}}
                    {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],'', ['class' => 'form-control']) }}
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('ambito', 'Ambito')}}
                    {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],'', ['class' => 'form-control']) }}
                </div>

            </div>

            <hr>

            {{-- stats base --}}
            <div class="row">
                {{-- equipo --}}
                <div class="form-group col-md-5">
                    <label>Equipo</label>
                    <select name="equipo_id" class="form-control js-example-basic-single">
                        @foreach($equipos as $id => $nombre)
                            <option value="{{ $id }}"
                                {{ old('equipo_id') == $id ? 'selected' : '' }}>
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Posición</label>
                    <input type="number" name="posicion" class="form-control" value="{{ old('posicion') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Partidos</label>
                    <input type="number" name="partidos" class="form-control" value="{{ old('partidos') }}">
                </div>
            </div>

            <hr>

            <h5>Partidos</h5>
            <div class="row">
                <div class="form-group col-md-2">
                    <label>Ganados</label>
                    <input type="number" name="ganados" class="form-control" value="{{ old('ganados') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Empatados</label>
                    <input type="number" name="empatados" class="form-control" value="{{ old('empatados') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Perdidos</label>
                    <input type="number" name="perdidos" class="form-control" value="{{ old('perdidos') }}">
                </div>
            </div>

            <hr>

            {{-- ⚽ GOLES --}}
            <h5>Goles</h5>
            <div class="row">
                <div class="form-group col-md-2">
                    <label>Favor</label>
                    <input type="number" name="goles_favor" class="form-control" value="{{ old('goles_favor') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>En contra</label>
                    <input type="number" name="goles_en_contra" class="form-control" value="{{ old('goles_en_contra') }}">
                </div>
            </div>

            <hr>

            <button class="btn btn-primary">Guardar</button>

            <a href="{{ route('tecnico-estadisticas.indexPorTecnico', $tecnico->id) }}"
               class="btn btn-success">
                Volver
            </a>

        </form>
    </div>

    <script>
        // Build the <option> list for the team selects, reusing the page's main equipo select.
        function opcionesEquipos(selectedId) {
            let base = document.querySelector('[name="equipo_id"]');
            let html = '';
            for (let option of base.options) {
                let sel = (String(option.value) === String(selectedId)) ? 'selected' : '';
                html += `<option value="${option.value}" ${sel}>${option.text}</option>`;
            }
            return html;
        }

        function opcionesSelect(valores, seleccionado) {
            let html = '';
            valores.forEach(v => {
                let sel = (v === (seleccionado ?? '')) ? 'selected' : '';
                html += `<option value="${v}" ${sel}>${v === '' ? 'Seleccionar...' : v}</option>`;
            });
            return html;
        }

        function normalizar(texto) {
            return texto
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/\./g, "")
                .replace(/club|fc|de|la|el/g, "")
                .trim();
        }

        function clean(texto) {
            return texto ? texto.trim().replace(/\s+/g, ' ') : '';
        }

        // Resolve a DB equipo_id from a scraped team name.
        // Returns null when there's no confident match, so the card's select
        // stays empty (same behaviour footballdb already has for foreign clubs).
        function matchEquipoId(nombreEquipo) {
            let select = document.querySelector('[name="equipo_id"]');
            let equipoScraper = normalizar(nombreEquipo);
            if (!equipoScraper) return null; // empty scraped name -> no match

            let mejorMatch = null;
            let maxScore = 0;

            for (let option of select.options) {
                if (!option.value) continue;
                let equipoDB = normalizar(option.text);
                if (!equipoDB) continue;

                let score = 0;
                if (equipoDB.includes(equipoScraper)) score += 2;
                if (equipoScraper.includes(equipoDB)) score += 2;

                // Ignore matches based on a too-short overlap (e.g. "al" matching
                // "Al-Wasl" against half your list). Require the shorter of the
                // two normalized names to be at least 4 chars.
                if (score > 0 && Math.min(equipoDB.length, equipoScraper.length) < 4) {
                    score = 0;
                }

                if (score > maxScore) {
                    maxScore = score;
                    mejorMatch = option;
                }
            }

            return maxScore > 0 ? (mejorMatch ? mejorMatch.value : null) : null;
        }

        function usarDato(item) {
            document.querySelector('[name="tipo"]').value = item.tipo ?? '';
            document.querySelector('[name="ambito"]').value = item.ambito ?? '';
            document.querySelector('[name="partidos"]').value = item.partidos ?? 0;
            document.querySelector('[name="posicion"]').value = item.posicion ?? 0;
            document.querySelector('[name="ganados"]').value = item.ganados ?? 0;
            document.querySelector('[name="empatados"]').value = item.empatados ?? 0;
            document.querySelector('[name="perdidos"]').value = item.perdidos ?? 0;
            document.querySelector('[name="goles_favor"]').value = item.gf ?? 0;
            document.querySelector('[name="goles_en_contra"]').value = item.ge ?? 0;
            document.querySelector('[name="torneo_nombre"]').value = clean(item.competition);

            if (item.torneo_logo) {
                document.querySelector('[name="torneo_logo_guardado"]').value = item.torneo_logo;
            }

            let select = document.querySelector('[name="equipo_id"]');
            let equipoMatch = matchEquipoId(item.equipo);
            if (equipoMatch) {
                select.value = equipoMatch;
                $(select).trigger('change');
            }
        }

        // ------------------------------------------------------------------
        // Card rendering, split so footballdb (one shot) and Transfermarkt
        // (club by club) can both reuse it.
        // ------------------------------------------------------------------
        let _idxTorneo = 0;

        // Render the toolbar + empty list container once, before adding cards.
        function iniciarResultados() {
            _idxTorneo = 0;
            document.getElementById('resultadoScraper').innerHTML = `
            <div class="d-flex align-items-center mb-3">
                <button type="button" class="btn btn-primary mr-2" onclick="guardarSeleccionados()">
                    💾 Guardar seleccionados
                </button>
                <label class="mb-0">
                    <input type="checkbox" id="checkTodos" onclick="toggleTodos(this)"> Seleccionar todos
                </label>
                <span id="resumenMasivo" class="ml-3"></span>
            </div>
            <div id="listaTorneos"></div>`;
        }

        // Build the markup for a single tournament card.
        function cardTorneo(c, idx) {
            let equipoMatch = matchEquipoId(c.equipo);
            // Build logo preview separately to avoid nested template-literal issues.
            let baseImg = "{{ url('images') }}";
            let logoPreview = c.torneo_logo
                ? '<img src="' + baseImg + '/' + c.torneo_logo + '" alt="logo" height="28" class="d-block mb-1">'
                : '';

            return `
        <div class="card mb-3 fila-torneo" data-idx="${idx}">
            <div class="card-header d-flex align-items-center" style="background:#f1f8f1;">
                <input type="checkbox" class="check-torneo mr-2" value="${idx}">
                <strong style="color: darkgreen;">${clean(c.competition)}</strong>
                <span class="ml-auto">
                    <button type="button" onclick='excluirCompetencia(${JSON.stringify(c.competition)}, this)'
                        class="btn btn-danger btn-sm" title="No mostrar más esta competencia">🚫 Comp.</button>
                    <button type="button" onclick='excluirEquipo(${JSON.stringify(c.equipo)}, this)'
                        class="btn btn-danger btn-sm" title="No mostrar más este equipo">🚫 Equipo</button>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-3">
                        <label class="small mb-0">Torneo</label>
                        <input type="text" class="form-control form-control-sm f-torneo_nombre" value="${clean(c.competition)}">
                    </div>
                    <div class="form-group col-md-3">
                        <label class="small mb-0">Equipo</label>
                        <select class="form-control form-control-sm f-equipo_id select2-equipo" style="width:100%">${opcionesEquipos(equipoMatch)}</select>
                        <small class="text-muted d-block mt-1">Scrapeado: <strong>${clean(c.equipo)}</strong> (sin match)</small>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small mb-0">Tipo</label>
                        <select class="form-control form-control-sm f-tipo">${opcionesSelect(['', 'Liga', 'Copa'], c.tipo)}</select>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small mb-0">Ámbito</label>
                        <select class="form-control form-control-sm f-ambito">${opcionesSelect(['', 'Nacional', 'Internacional'], c.ambito)}</select>
                    </div>
                     <div class="form-group col-md-2">
                        <label class="small mb-0">Logo</label>
                        ${logoPreview}
                        <input type="file" class="form-control-file form-control-sm f-logo_file">
                        <input type="hidden" class="f-torneo_logo" value="${c.torneo_logo ?? ''}">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"><label class="small mb-0">Posición</label>
                        <input type="number" class="form-control form-control-sm f-posicion" value="${c.posicion ?? ''}"></div>
                    <div class="form-group col-md-2"><label class="small mb-0">Partidos</label>
                        <input type="number" class="form-control form-control-sm f-partidos" value="${c.partidos}"></div>
                    <div class="form-group col-md-2"><label class="small mb-0">Ganados</label>
                        <input type="number" class="form-control form-control-sm f-ganados" value="${c.ganados}"></div>
                    <div class="form-group col-md-2"><label class="small mb-0">Empatados</label>
                        <input type="number" class="form-control form-control-sm f-empatados" value="${c.empatados}"></div>
                    <div class="form-group col-md-2"><label class="small mb-0">Perdidos</label>
                        <input type="number" class="form-control form-control-sm f-perdidos" value="${c.perdidos}"></div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"><label class="small mb-0">Goles Favor</label>
                        <input type="number" class="form-control form-control-sm f-goles_favor" value="${c.gf}"></div>
                    <div class="form-group col-md-2"><label class="small mb-0">Goles Contra</label>
                        <input type="number" class="form-control form-control-sm f-goles_en_contra" value="${c.ge}"></div>
                </div>
            </div>
        </div>`;
        }

        // Aggregate incoming rows by competition|equipo (same logic the old
        // renderResultados used), then append them as cards. Safe to call many
        // times: footballdb calls it once, Transfermarkt once per club.
        function agregarCards(items) {
            let lista = document.getElementById('listaTorneos');
            if (!lista) { iniciarResultados(); lista = document.getElementById('listaTorneos'); }

            let torneos = {};
            items.forEach(row => {
                let key = clean(row.competition) + '|' + clean(row.equipo);
                if (!torneos[key]) {
                    torneos[key] = {
                        competition: clean(row.competition),
                        equipo:      row.equipo,
                        partidos:    0,
                        posicion:    (row.posicion === null || row.posicion === undefined || row.posicion === '')
                            ? '' : parseInt(row.posicion),
                        ganados:     0,
                        empatados:   0,
                        perdidos:    0,
                        gf:          0,
                        ge:          0,
                        tipo:        row.tipo ?? '',
                        ambito:      row.ambito ?? '',
                    };
                }
                torneos[key].partidos  += parseInt(row.partidos ?? 0);
                torneos[key].ganados   += parseInt(row.ganados ?? 0);
                torneos[key].empatados += parseInt(row.empatados ?? 0);
                torneos[key].perdidos  += parseInt(row.perdidos ?? 0);
                torneos[key].gf        += parseInt(row.gf ?? 0);
                torneos[key].ge        += parseInt(row.ge ?? 0);
            });

            Object.values(torneos).forEach(c => {
                lista.insertAdjacentHTML('beforeend', cardTorneo(c, _idxTorneo++));
            });

            // Init Select2 only on the newly added selects (avoid re-init).
            if (window.jQuery && $.fn.select2) {
                $('#resultadoScraper .select2-equipo:not(.select2-hidden-accessible)').select2({ width: '100%' });
            }
        }

        // footballdb: render everything in one shot (replaces previous results).
        function renderResultados(items) {
            if (!items.length) {
                document.getElementById('resultadoScraper').innerHTML =
                    '<div class="alert alert-warning">Sin resultados.</div>';
                return;
            }
            iniciarResultados();
            agregarCards(items);
        }

        function scrapearFootballDB() {
            let url = document.getElementById('footballdbUrl').value.trim();
            if (!url) {
                alert('Ingresá la URL');
                return;
            }

            document.getElementById('loadingScraper').style.display = 'block';
            document.getElementById('resultadoScraper').innerHTML = '';
            let tecnicoId = document.querySelector('[name="tecnico_id"]').value;
            fetch("{{ url('/admin/scraper/tecnico-footballdb') }}?url=" + encodeURIComponent(url) + "&tecnico_id=" + tecnicoId)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('resultadoScraper').innerHTML =
                            '<div class="alert alert-danger">' + data.error + '</div>';
                        return;
                    }
                    renderResultados(data);
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('resultadoScraper').innerHTML =
                        '<div class="alert alert-danger">Error scrapeando</div>';
                })
                .finally(() => {
                    document.getElementById('loadingScraper').style.display = 'none';
                });
        }

        // ------------------------------------------------------------------
        // Transfermarkt: phase A lists the directed clubs, then one fetch per
        // club appends its tournaments. Can be stopped mid-way.
        // ------------------------------------------------------------------


        async function scrapearTransfermarktTecnico() {
            let url = document.getElementById('tmUrlTecnico').value.trim();
            if (!url) { alert('Pegá la URL del DT en Transfermarkt'); return; }

            let tecnicoId = document.querySelector('[name="tecnico_id"]').value;
            let endpoint  = "{{ url('/admin/scraper/tecnico-transfermarkt') }}";
            let progreso  = document.getElementById('tmProgresoTecnico');

            iniciarResultados();
            progreso.innerHTML = '⏳ Leyendo clubes de la temporada...';

            // Phase A: get clubs.
            let faseA;
            try {
                let res = await fetch(`${endpoint}?url=${encodeURIComponent(url)}&tecnico_id=${tecnicoId}`);
                faseA = await res.json();
                if (faseA.error) { progreso.innerHTML = `<span style="color:#a94442">${faseA.error}</span>`; return; }
            } catch (e) {
                console.error(e); progreso.innerHTML = '<span style="color:#a94442">Error leyendo clubes</span>'; return;
            }

            let clubes = faseA.clubes || [];
            if (!clubes.length) { progreso.innerHTML = '<span style="color:#856404">No se encontraron clubes.</span>'; return; }

            // Phase B: one fetch per club (filtered), append cards.
            let total = 0;
            for (let i = 0; i < clubes.length; i++) {
                let c = clubes[i];
                progreso.innerHTML = `⏳ ${i + 1}/${clubes.length} — <strong>${c.nombre}</strong> (${total} torneos)`;
                try {
                    let res = await fetch(`${endpoint}?url=${encodeURIComponent(url)}&tecnico_id=${tecnicoId}`
                        + `&verein_id=${encodeURIComponent(c.id)}&equipo_nombre=${encodeURIComponent(c.nombre)}`);
                    let r = await res.json();
                    if (r.data && r.data.length) { agregarCards(r.data); total += r.data.length; }
                } catch (e) { console.error('Club ' + c.nombre, e); }
            }

            progreso.innerHTML = `✅ Listo. ${total} torneos de ${clubes.length} clubes.`;
        }

        function excluirCompetencia(nombre, btn) {
            if (!confirm('¿Excluir "' + nombre + '" de futuros scrapeos?\n\n' +
                'Se guardará el patrón sin el año (ej: "Segunda B 2024" → "segunda b").')) return;

            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            fetch("{{ url('/admin/competencias-excluidas/excluir-rapido') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: 'nombre=' + encodeURIComponent(nombre)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        let card = btn.closest('.fila-torneo');
                        if (card) card.remove();

                        let msg = document.createElement('div');
                        msg.className = 'alert alert-warning';
                        msg.innerText = data.creado
                            ? '✅ "' + data.patron + '" agregado a la lista de exclusiones.'
                            : 'ℹ️ "' + data.patron + '" ya estaba excluido.';
                        document.getElementById('resultadoScraper').prepend(msg);
                        setTimeout(() => msg.remove(), 3500);
                    } else {
                        alert(data.msg || 'No se pudo excluir');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error al excluir');
                });
        }

        function excluirEquipo(nombre, btn) {
            if (!confirm('¿Excluir "' + nombre + '" de futuros scrapeos?\n\n' +
                'Se ignorarán todas las filas de este equipo.')) return;

            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            fetch("{{ url('/admin/equipos-excluidos/excluir-rapido') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: 'nombre=' + encodeURIComponent(nombre)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        let nombreNorm = normalizar(nombre);

                        document.querySelectorAll('#resultadoScraper .fila-torneo').forEach(card => {
                            let sel = card.querySelector('.f-equipo_id');
                            let texto = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : '';
                            let eqNorm = normalizar(texto);
                            if (eqNorm === nombreNorm || eqNorm.includes(nombreNorm) || nombreNorm.includes(eqNorm)) {
                                card.remove();
                            }
                        });

                        let msg = document.createElement('div');
                        msg.className = 'alert alert-warning';
                        msg.innerText = data.creado
                            ? '✅ Equipo "' + data.nombre + '" agregado a la lista de exclusiones.'
                            : 'ℹ️ Equipo "' + data.nombre + '" ya estaba excluido.';
                        document.getElementById('resultadoScraper').prepend(msg);
                        setTimeout(() => msg.remove(), 3500);
                    } else {
                        alert(data.msg || 'No se pudo excluir');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error al excluir equipo');
                });
        }

        function toggleTodos(master) {
            document.querySelectorAll('.check-torneo').forEach(c => c.checked = master.checked);
        }

        async function guardarSeleccionados() {
            let seleccionados = Array.from(document.querySelectorAll('.check-torneo:checked'));

            if (!seleccionados.length) {
                alert('No seleccionaste ningún torneo.');
                return;
            }

            let tecnicoId = document.querySelector('[name="tecnico_id"]').value;

            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            // Numeric/select fields read straight from each row's editable inputs.
            let campos = [
                'torneo_nombre', 'equipo_id', 'tipo', 'ambito',
                'posicion', 'partidos',
                'ganados', 'empatados', 'perdidos',
                'goles_favor', 'goles_en_contra','torneo_logo',
            ];

            // Build multipart FormData so each row can carry its uploaded logo file.
            let formData = new FormData();
            formData.append('tecnico_id', tecnicoId);

            // Map each payload index to its card, so we can remove only the saved ones later.
            let cardsPorIndice = {};

            seleccionados.forEach((chk, i) => {
                let card = chk.closest('.fila-torneo');
                cardsPorIndice[i] = card;

                campos.forEach(campo => {
                    let el = card.querySelector('.f-' + campo);
                    formData.append(`torneos[${i}][${campo}]`, el ? el.value : '');
                });

                // Per-row logo file (optional).
                let fileInput = card.querySelector('.f-logo_file');
                if (fileInput && fileInput.files.length) {
                    formData.append(`torneos[${i}][logo_file]`, fileInput.files[0]);
                }
            });

            let resumen = document.getElementById('resumenMasivo');
            resumen.innerHTML = '⏳ Guardando...';

            try {
                // Note: no Content-Type header — the browser sets the multipart boundary itself.
                let res = await fetch("{{ route('tecnico-estadisticas.storeMasivo') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                let data = await res.json();

                // Remove only the cards that were actually saved; keep skipped/duplicated ones visible.
                if (data.resultados && data.resultados.length) {
                    data.resultados.forEach(r => {
                        let card = cardsPorIndice[r.i];
                        if (!card) return;

                        if (r.ok) {
                            card.remove();
                        } else if (r.motivo === 'duplicado') {
                            card.style.opacity = '0.6';
                            let chk = card.querySelector('.check-torneo');
                            if (chk) chk.checked = false;
                            let header = card.querySelector('.card-header strong');
                            if (header && !header.querySelector('.dup-tag')) {
                                header.insertAdjacentHTML('beforeend',
                                    ' <span class="dup-tag badge badge-warning">ya existe</span>');
                            }
                        } else {
                            let chk = card.querySelector('.check-torneo');
                            if (chk) chk.checked = false;
                        }
                    });
                }

                let msg = `✅ Guardados: ${data.guardados} · ⏭️ Salteados: ${data.salteados}`;
                if (data.con_auto) {
                    msg += ` · ⚠ ${data.con_auto} con stats automáticas existentes`;
                }
                if (data.errores && data.errores.length) {
                    msg += `<br><small style="color:#856404">${data.errores.join('<br>')}</small>`;
                }
                resumen.innerHTML = msg;

                let master = document.getElementById('checkTodos');
                if (master) master.checked = false;
            } catch (e) {
                console.error('Error guardado masivo', e);
                resumen.innerHTML = '<span style="color:#a94442">Error guardando.</span>';
            }
        }
    </script>
@endsection

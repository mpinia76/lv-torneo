@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto jugador" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $jugador->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $jugador->persona->nombre }} {{ $jugador->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $jugador->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $jugador->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $jugador->persona->nacimiento ? $jugador->persona->getAgeAttribute() : '' }}
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Posición</strong><br>{{ $jugador->tipoJugador }}</div>
                    <div class="col-md-3"><strong>Altura</strong><br>{{ $jugador->persona->altura }} m</div>
                    <div class="col-md-3"><strong>Peso</strong><br>{{ $jugador->persona->peso }} kg</div>
                </div>
                @if($jugador->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $jugador->persona->observaciones }}</div>
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

        <div class="mb-3">
            <label>URL Transfermarkt</label>
            <input type="text"
                   id="transfermarktUrl"
                   class="form-control"
                   placeholder="https://www.transfermarkt.com.ar/lionel-messi/profil/spieler/28003">
        </div>
        {{-- Importar desde FootballDatabase --}}
        <div class="mb-3">
            <label>Importar desde FootballDatabase</label>
            <div class="d-flex">
                <input type="text" id="footballdbUrl" class="form-control mr-2"
                       placeholder="https://www.footballdatabase.eu/es/jugador/detalles/10973-lionel-messi">
                <button type="button" class="btn btn-warning" onclick="scrapearFootballDB()" style="white-space:nowrap;">
                    🌐 Scrapear
                </button>
            </div>
        </div>

        <div id="loadingScraper" style="display:none;" class="alert alert-info">
            ⏳ Cargando datos, puede tardar unos segundos...
        </div>
        <div id="resultadoScraper" class="mt-3"></div>

        <div id="loadingTM" style="display:none;" class="alert alert-info">
            ⏳ Trayendo goles desde Transfermarkt, puede tardar unos segundos...
        </div>
        <div id="resultadoTM" class="mt-3"></div>
        <hr>

        <form action="{{ route('jugador-estadisticas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- 🔒 jugador --}}
            <input type="hidden" name="jugador_id" value="{{ $jugador->id }}">

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

            {{-- ⚽ TIPOS DE GOLES --}}
            <h5>Goles</h5>

            <div class="row">
                <div class="form-group col-md-2">
                    <label>Cabeza</label>
                    <input type="number" name="goles_cabeza" class="form-control" value="{{ old('goles_cabeza') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Jugada</label>
                    <input type="number" name="goles_jugada" class="form-control" value="{{ old('goles_jugada') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Penal</label>
                    <input type="number" name="goles_penal" class="form-control" value="{{ old('goles_penal') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Tiro Libre</label>
                    <input type="number" name="goles_tiro_libre" class="form-control" value="{{ old('goles_tiro_libre') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>En contra</label>
                    <input type="number" name="goles_en_contra" class="form-control" value="{{ old('goles_en_contra') }}">
                </div>
            </div>

            <hr>

            <h5>Tarjetas</h5>
            <div class="row">
                <div class="form-group col-md-2">
                    <label>Amarillas</label>
                    <input type="number" name="amarillas" class="form-control" value="{{ old('amarillas') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Rojas</label>
                    <input type="number" name="rojas" class="form-control" value="{{ old('rojas') }}">
                </div>
            </div>

            <hr>

            <h5>Penales</h5>
            <div class="row">
                <div class="form-group col-md-2">
                    <label>Errados</label>
                    <input type="number" name="penales_errados" class="form-control" value="{{ old('penales_errados') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Atajados</label>
                    <input type="number" name="penales_atajados" class="form-control" value="{{ old('penales_atajados') }}">
                </div>
            </div>

            <hr>

            <h5>Arquero</h5>
            <div class="row">
                <div class="form-group col-md-2">
                    <label>G. Recibidos</label>
                    <input type="number" name="goles_recibidos" class="form-control" value="{{ old('goles_recibidos') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>V. invictas</label>
                    <input type="number" name="vallas_invictas" class="form-control" value="{{ old('vallas_invictas') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>P. Atajados</label>
                    <input type="number" name="penales_atajo" class="form-control" value="{{ old('penales_atajo') }}">
                </div>
            </div>

            <hr>

            <button class="btn btn-primary">Guardar</button>

            <a href="{{ route('jugador-estadisticas.indexPorJugador', $jugador->id) }}"
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

        // Show an empty input when the value is 0/null/empty (so zeros don't clutter the form).
        function vacioSiCero(v) {
            return (v === 0 || v === '0' || v === null || v === undefined || v === '') ? '' : v;
        }

        function clean(texto) {
            return texto ? texto.trim().replace(/\s+/g, ' ') : '';
        }

        // ------------------------------------------------------------------
        // Detección de duplicados (igual que el scraper de DTs).
        // Regla: mismo nombre de torneo + mismo año + mismo club = duplicado.
        // Referencias = torneos ya guardados de este jugador (window.TORNEOS_GUARDADOS).
        // ------------------------------------------------------------------
        window.TORNEOS_GUARDADOS = @json($yaGuardados ?? []);

        function nombreSinAnio(txt) {
            return (txt || '')
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/\b(19|20)\d{2}(\/\d{2})?\b/g, '')   // saca años / rangos
                .replace(/[^a-z0-9 ]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        // Años a los que refiere una etiqueta: "2012"->[2012] ; "2011/12"->[2011,2012]
        function aniosDe(txt) {
            let s = (txt || '');
            let cross = s.match(/\b((?:19|20)\d{2})\/(\d{2})\b/);
            if (cross) {
                let y1 = parseInt(cross[1]);
                let y2 = parseInt(cross[1].slice(0, 2) + cross[2]);
                return [y1, y2];
            }
            let single = s.match(/\b((?:19|20)\d{2})\b/);
            return single ? [parseInt(single[1])] : [];
        }

        function aniosSeSolapan(a, b) {
            if (!a.length || !b.length) return false;
            return a.some(y => b.includes(y));
        }

        // Coeficiente de Dice sobre bigramas (sin dependencias).
        function similitud(a, b) {
            if (a === b) return 1;
            if (a.length < 2 || b.length < 2) return 0;
            let bg = s => { let r = []; for (let i = 0; i < s.length - 1; i++) r.push(s.slice(i, i + 2)); return r; };
            let A = bg(a), B = bg(b), inter = 0, used = B.slice();
            A.forEach(g => { let i = used.indexOf(g); if (i >= 0) { inter++; used[i] = null; } });
            return (2 * inter) / (A.length + B.length);
        }

        function mismoClub(a, b) {
            let limpiar = s => normalizar(s)
                .replace(/\b(sc|ec|ad|sa|cf|afc|cd|sad)\b/g, ' ')
                .replace(/\s*-\s*([a-z]{2})\b/g, ' $1')
                .replace(/\s+/g, ' ')
                .trim();

            let x = limpiar(a), y = limpiar(b);
            if (!x || !y) return false;
            if (x === y) return true;

            let corto = x.length <= y.length ? x : y;
            let largo = x.length <= y.length ? y : x;
            if (largo.includes(corto) && corto.length >= 4 && corto.length / largo.length >= 0.6) {
                return true;
            }
            return similitud(x, y) >= 0.88;
        }

        function mismoTorneo(a, b) {
            let x = nombreSinAnio(a);
            let y = nombreSinAnio(b);
            if (!x || !y) return false;
            if (x === y) return true;

            let corto = x.length <= y.length ? x : y;
            let largo = x.length <= y.length ? y : x;
            let palabrasCorto = corto.split(' ').filter(w => w.length >= 2);
            if (palabrasCorto.length) {
                let todasPresentes = palabrasCorto.every(w => largo.includes(w));
                let distintivo = palabrasCorto.length >= 2 || palabrasCorto.some(w => w.length >= 5);
                if (todasPresentes && distintivo) return true;
            }
            return similitud(x, y) >= 0.82;
        }

        // Devuelve { ref, sim } si (competition + equipo) ya existe, o null.
        function buscarSimilar(competition, equipo, referencias) {
            let yA = aniosDe(competition);
            if (!nombreSinAnio(competition)) return null;

            for (let ref of referencias) {
                let yB = aniosDe(ref.competition);
                if (!mismoTorneo(competition, ref.competition)) continue;
                if (!aniosSeSolapan(yA, yB)) continue;
                if (!mismoClub(equipo, ref.equipo)) continue;
                return { ref: ref.competition, sim: 1 };
            }
            return null;
        }

        // Resolve a DB equipo_id from a scraped team name using the same fuzzy match as usarDato().
        function matchEquipoId(nombreEquipo) {
            let select = document.querySelector('[name="equipo_id"]');
            let equipoScraper = normalizar(nombreEquipo);
            let mejorMatch = null;
            let maxScore = 0;

            for (let option of select.options) {
                if (!option.value) continue;
                let equipoDB = normalizar(option.text);
                let score = 0;
                if (equipoDB.includes(equipoScraper)) score += 2;
                if (equipoScraper.includes(equipoDB)) score += 2;
                if (score > maxScore) {
                    maxScore = score;
                    mejorMatch = option;
                }
            }
            return mejorMatch ? mejorMatch.value : null;
        }

        function avisoFallbackGoles(item, motivo) {
            let total = parseInt(item.goles_jugada ?? 0);
            return `
        <div class="alert alert-warning">
            ⚠️ <strong>Goles cargados como "Jugada" (sin desglose de Transfermarkt)</strong><br>
            ${motivo}<br>
            Se asignaron <strong>${total}</strong> goles al campo "Jugada" desde FootballDatabase.
            Si querés desglosarlos por tipo, editalos a mano antes de guardar.
        </div>
    `;
        }

        async function usarDato(item) {
            document.querySelector('[name="torneo_nombre"]').value = clean(item.competition);
            document.querySelector('[name="tipo"]').value = item.tipo ?? '';
            document.querySelector('[name="ambito"]').value = item.ambito ?? '';
            document.querySelector('[name="partidos"]').value = item.partidos ?? 0;
            document.querySelector('[name="posicion"]').value = item.posicion ?? 0;
            document.querySelector('[name="goles_en_contra"]').value = item.goles_en_contra ?? 0;
            document.querySelector('[name="amarillas"]').value = item.amarillas ?? 0;
            document.querySelector('[name="rojas"]').value = item.rojas ?? 0;

            let esArquero = "{{ mb_strtolower($jugador->tipoJugador ?? '') }}".includes('arquero');
            let golesRecibidosInput = document.querySelector('[name="goles_recibidos"]');
            let vallasInvictasInput = document.querySelector('[name="vallas_invictas"]');
            if (golesRecibidosInput) golesRecibidosInput.value = esArquero ? (item.goles_recibidos ?? 0) : 0;
            if (vallasInvictasInput) vallasInvictasInput.value = esArquero ? (item.vallas_invictas ?? 0) : 0;

            document.querySelector('[name="goles_cabeza"]').value = 0;
            document.querySelector('[name="goles_jugada"]').value = item.goles_jugada ?? 0;
            document.querySelector('[name="goles_penal"]').value = 0;
            document.querySelector('[name="goles_tiro_libre"]').value = 0;

            if (item.torneo_logo) {
                document.querySelector('[name="torneo_logo_guardado"]').value = item.torneo_logo;
            }

            let select = document.querySelector('[name="equipo_id"]');
            let equipoMatch = matchEquipoId(item.equipo);
            if (equipoMatch) {
                select.value = equipoMatch;
                $(select).trigger('change');
            }

            let tmUrl = document.getElementById('transfermarktUrl').value.trim();
            if (!tmUrl) {
                document.getElementById('resultadoTM').innerHTML =
                    avisoFallbackGoles(item, 'No cargaste URL de Transfermarkt.');
                return;
            }

            try {
                document.getElementById('loadingTM')?.style.setProperty('display', 'block');
                document.getElementById('resultadoTM').innerHTML = '';

                let response = await fetch(
                    "{{ url('/admin/scraper/jugador-transfermarkt-goles') }}"
                    + "?url=" + encodeURIComponent(tmUrl)
                    + "&competicion=" + encodeURIComponent(item.competition)
                    + "&club=" + encodeURIComponent(item.equipo)
                );

                let data = await response.json();

                if (data.error) {
                    document.getElementById('resultadoTM').innerHTML =
                        `<div class="alert alert-danger">${data.error}</div>`;
                    return;
                }

                if (!data.length) {
                    document.getElementById('resultadoTM').innerHTML =
                        avisoFallbackGoles(item,
                            `No se encontraron coincidencias en Transfermarkt para <strong>${item.competition}</strong> / ${item.equipo}.`);
                    return;
                }

                let html = `
            <h5 style="color:#0a6ebd">Tipos de goles encontrados en Transfermarkt</h5>
            <table class="table table-sm table-bordered">
                <thead><tr>
                    <th>Temp.</th><th>Competición</th><th>Club</th><th>Total</th>
                    <th>Cabeza</th><th>Jugada</th><th>Penal</th><th>T. Libre</th><th></th>
                </tr></thead><tbody>`;

                data.forEach(g => {
                    let totalTM = parseInt(g.total ?? 0);
                    let totalFDB = parseInt(item.goles_jugada ?? 0);
                    let diferencia = totalFDB !== totalTM;

                    html += `
                <tr ${diferencia ? 'style="background:#fff3cd;"' : ''}>
                    <td>${g.temporada}</td>
                    <td>${g.competicion}</td>
                    <td>${g.club}</td>
                    <td><strong>${g.total}</strong>
                        ${diferencia ? `<br><small style="color:#856404">FDB: ${totalFDB}</small>` : ''}
                    </td>
                    <td>${g.cabeza}</td>
                    <td>${g.jugada}</td>
                    <td>${g.penal}</td>
                    <td>${g.tiro_libre}</td>
                    <td><button class="btn btn-success btn-sm" onclick='usarGolesTM(${JSON.stringify(g)})'>Usar</button></td>
                </tr>`;
                });

                html += `</tbody></table>`;
                document.getElementById('resultadoTM').innerHTML = html;

            } catch (e) {
                console.error('Error TM', e);
                document.getElementById('resultadoTM').innerHTML =
                    avisoFallbackGoles(item, 'Error consultando Transfermarkt (timeout o red).');
            } finally {
                document.getElementById('loadingTM')?.style.setProperty('display', 'none');
            }
        }

        function renderResultados(items) {
            let torneos = {};

            items.forEach(row => {
                let key = clean(row.competition) + '|' + clean(row.equipo);
                if (!torneos[key]) {
                    torneos[key] = {
                        competition:      clean(row.competition),
                        equipo:           row.equipo,
                        partidos:         0,
                        posicion:         0,
                        goles_jugada:     0,
                        goles_en_contra:  0,
                        amarillas:        0,
                        rojas:            0,
                        goles_recibidos:  0,
                        vallas_invictas:  0,
                        tipo:             row.tipo ?? '',
                        ambito:           row.ambito ?? '',
                        torneo_logo:      row.torneo_logo ?? '',
                    };
                }
                torneos[key].partidos        += parseInt(row.partidos ?? 0);
                torneos[key].posicion        += parseInt(row.posicion ?? 0);
                torneos[key].goles_jugada    += parseInt(row.goles_jugada ?? 0);
                torneos[key].goles_en_contra += parseInt(row.goles_en_contra ?? 0);
                torneos[key].amarillas       += parseInt(row.amarillas ?? 0);
                torneos[key].rojas           += parseInt(row.rojas ?? 0);
                torneos[key].goles_recibidos += parseInt(row.goles_recibidos ?? 0);
                torneos[key].vallas_invictas += parseInt(row.vallas_invictas ?? 0);
            });

            let lista = Object.values(torneos);

            if (!lista.length) {
                document.getElementById('resultadoScraper').innerHTML =
                    '<div class="alert alert-warning">Sin resultados.</div>';
                return;
            }

            let esArquero = "{{ mb_strtolower($jugador->tipoJugador ?? '') }}".includes('arquero');

            let html = `
                <div class="d-flex align-items-center mb-3">
                    <button type="button" class="btn btn-primary mr-2" onclick="guardarSeleccionados()">
                        💾 Guardar seleccionados
                    </button>
                    <label class="mb-0">
                        <input type="checkbox" id="checkTodos" onclick="toggleTodos(this)"> Seleccionar todos
                    </label>
                    <span id="resumenMasivo" class="ml-3"></span>
                </div>`;

            lista.forEach((c, idx) => {
                let equipoMatch = matchEquipoId(c.equipo);
                let baseImg = "{{ url('images') }}";
                let logoPreview = c.torneo_logo
                    ? '<img src="' + baseImg + '/' + c.torneo_logo + '" alt="logo" height="28" class="d-block mb-1">'
                    : '';

                // Aviso de duplicado: comparo contra lo ya guardado + tarjetas previas de este lote.
                let refs = (window.TORNEOS_GUARDADOS || []).slice();
                lista.slice(0, idx).forEach(prev => {
                    let eqPrev = matchEquipoId(prev.equipo);
                    let opt = eqPrev ? document.querySelector('[name="equipo_id"] option[value="' + eqPrev + '"]') : null;
                    refs.push({ competition: prev.competition, equipo: opt ? opt.text : prev.equipo });
                });
                let hit = buscarSimilar(c.competition, c.equipo, refs);

                html += `
                <div class="card mb-3 fila-torneo ${hit ? 'border-warning' : ''}" data-idx="${idx}">
                    <div class="card-header d-flex align-items-center" style="background:#f1f8f1;">
                        <input type="checkbox" class="check-torneo mr-2" value="${idx}">
                        <strong style="color: darkgreen;">${clean(c.competition)}</strong>
                        ${hit ? `<span class="badge badge-warning ml-2" title="Parecido a: ${hit.ref}">⚠ Posible duplicado</span>` : ''}
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
                                <small class="text-muted d-block mt-1">Scrapeado: <strong>${clean(c.equipo)}</strong></small>
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
                            <div class="form-group col-md-1"><label class="small mb-0">PJ</label>
                                <input type="number" class="form-control form-control-sm f-partidos" value="${vacioSiCero(c.partidos)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Pos.</label>
                                <input type="number" class="form-control form-control-sm f-posicion" value="${vacioSiCero(c.posicion)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Cabeza</label>
                                <input type="number" class="form-control form-control-sm f-goles_cabeza" value=""></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Jugada</label>
                                <input type="number" class="form-control form-control-sm f-goles_jugada" value="${vacioSiCero(c.goles_jugada)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Penal</label>
                                <input type="number" class="form-control form-control-sm f-goles_penal" value=""></div>
                            <div class="form-group col-md-1"><label class="small mb-0">T.Libre</label>
                                <input type="number" class="form-control form-control-sm f-goles_tiro_libre" value=""></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Contra</label>
                                <input type="number" class="form-control form-control-sm f-goles_en_contra" value="${vacioSiCero(c.goles_en_contra)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Amar.</label>
                                <input type="number" class="form-control form-control-sm f-amarillas" value="${vacioSiCero(c.amarillas)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">Rojas</label>
                                <input type="number" class="form-control form-control-sm f-rojas" value="${vacioSiCero(c.rojas)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">P.Err</label>
                                <input type="number" class="form-control form-control-sm f-penales_errados" value="${vacioSiCero(c.penales_errados)}"></div>
                            <div class="form-group col-md-1"><label class="small mb-0">P.Ataj</label>
                                <input type="number" class="form-control form-control-sm f-penales_atajados" value=""></div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-2"><label class="small mb-0">G. Recibidos</label>
                                <input type="number" class="form-control form-control-sm f-goles_recibidos" value="${esArquero ? vacioSiCero(c.goles_recibidos) : ''}"></div>
                            <div class="form-group col-md-2"><label class="small mb-0">V. Invictas</label>
                                <input type="number" class="form-control form-control-sm f-vallas_invictas" value="${esArquero ? vacioSiCero(c.vallas_invictas) : ''}"></div>
                            <div class="form-group col-md-2"><label class="small mb-0">P. Atajó (arq)</label>
                                <input type="number" class="form-control form-control-sm f-penales_atajo" value=""></div>
                        </div>
                    </div>
                </div>`;
            });

            document.getElementById('resultadoScraper').innerHTML = html;

            // Init Select2 on the team selects of the freshly rendered cards.
            // jQuery is required (same as the page's main select). The cards are
            // built dynamically, so this must run after they are in the DOM.
            if (window.jQuery && $.fn.select2) {
                $('#resultadoScraper .select2-equipo').select2({ width: '100%' });
            }
        }

        function scrapearFootballDB() {
            let url = document.getElementById('footballdbUrl').value.trim();
            if (!url) {
                alert('Ingresá la URL');
                return;
            }

            document.getElementById('loadingScraper').style.display = 'block';
            document.getElementById('resultadoScraper').innerHTML = '';

            let jugadorId = document.querySelector('[name="jugador_id"]').value;
            fetch("{{ url('/admin/scraper/jugador-footballdb') }}?url=" + encodeURIComponent(url)
                + "&jugador_id=" + jugadorId)
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

        function scrapearTransfermarkt() {
            let url = document.getElementById('transfermarktUrl').value.trim();
            if (!url) {
                alert('Ingresá la URL del jugador en Transfermarkt');
                return;
            }

            document.getElementById('loadingTM').style.display = 'block';
            document.getElementById('resultadoTM').innerHTML = '';

            fetch("{{ url('/admin/scraper/jugador-transfermarkt-goles') }}?url=" + encodeURIComponent(url))
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('resultadoTM').innerHTML =
                            `<div class="alert alert-danger">Error de Transfermarkt: ${data.error}</div>`;
                        return;
                    }
                    if (!data.length) {
                        document.getElementById('resultadoTM').innerHTML =
                            '<div class="alert alert-warning">Sin resultados después de filtrar (Argentina + CONMEBOL + < 2000).</div>';
                        return;
                    }

                    let html = '<h5 style="color:#0a6ebd">Goles por temporada y competición (Transfermarkt)</h5>';
                    html += '<table class="table table-sm table-bordered">';
                    html += '<thead><tr>'
                        + '<th>Temporada</th><th>Competición</th><th>Club</th>'
                        + '<th class="text-center">Total</th>'
                        + '<th class="text-center">Cabeza</th>'
                        + '<th class="text-center">Jugada</th>'
                        + '<th class="text-center">Penal</th>'
                        + '<th class="text-center">T. Libre</th>'
                        + '</tr></thead><tbody>';

                    data.forEach(r => {
                        html += `<tr>
                    <td>${r.temporada}</td>
                    <td>${r.competicion}</td>
                    <td>${r.club}</td>
                    <td class="text-center"><strong>${r.total}</strong></td>
                    <td class="text-center">${r.cabeza || ''}</td>
                    <td class="text-center">${r.jugada || ''}</td>
                    <td class="text-center">${r.penal  || ''}</td>
                    <td class="text-center">${r.tiro_libre || ''}</td>
                </tr>`;
                    });

                    html += '</tbody></table>';
                    document.getElementById('resultadoTM').innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('resultadoTM').innerHTML =
                        '<div class="alert alert-danger">Error scrapeando Transfermarkt</div>';
                })
                .finally(() => {
                    document.getElementById('loadingTM').style.display = 'none';
                });
        }

        function usarGolesTM(goles) {
            document.querySelector('[name="goles_cabeza"]').value = goles.cabeza ?? 0;
            document.querySelector('[name="goles_jugada"]').value = goles.jugada ?? 0;
            document.querySelector('[name="goles_penal"]').value = goles.penal ?? 0;
            document.querySelector('[name="goles_tiro_libre"]').value = goles.tiro_libre ?? 0;
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

            let jugadorId = document.querySelector('[name="jugador_id"]').value;

            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            // Numeric/select fields read straight from each row's editable inputs.
            let campos = [
                'torneo_nombre', 'equipo_id', 'tipo', 'ambito',
                'partidos', 'posicion',
                'goles_cabeza', 'goles_jugada', 'goles_penal', 'goles_tiro_libre', 'goles_en_contra',
                'amarillas', 'rojas',
                'penales_errados', 'penales_atajados',
                'goles_recibidos', 'vallas_invictas', 'penales_atajo', 'torneo_logo',
            ];

            // Build multipart FormData so each row can carry its uploaded logo file.
            let formData = new FormData();
            formData.append('jugador_id', jugadorId);

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
                let res = await fetch("{{ route('jugador-estadisticas.storeMasivo') }}", {
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
                            // Flag duplicates so the user notices why they stayed.
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

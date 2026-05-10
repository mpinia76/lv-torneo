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
        {{-- Importar tipos de goles desde Transfermarkt --}}
        <!--<div class="mb-3">
            <label>Importar tipos de goles desde Transfermarkt</label>
            <div class="d-flex">
                <input type="text" id="transfermarktUrl" class="form-control mr-2"
                       placeholder="https://www.transfermarkt.com.ar/lionel-messi/profil/spieler/28003">
                <button type="button" class="btn btn-info" onclick="scrapearTransfermarkt()" style="white-space:nowrap;">
                    ⚽ Tipos de goles
                </button>
            </div>
            <small class="text-muted">
                Pegá cualquier URL del jugador en Transfermarkt. Muestra los goles agrupados por temporada y competición para que los cargues a mano. Excluye Argentina, competiciones CONMEBOL y temporadas previas al 2000.
            </small>
        </div>-->

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

            // =========================
            // DATOS BASE
            // =========================

            document.querySelector('[name="torneo_nombre"]').value =
                clean(item.competition);

            document.querySelector('[name="tipo"]').value =
                item.tipo ?? '';

            document.querySelector('[name="ambito"]').value =
                item.ambito ?? '';

            document.querySelector('[name="partidos"]').value =
                item.partidos ?? 0;

            document.querySelector('[name="posicion"]').value =
                item.posicion ?? 0;

            document.querySelector('[name="goles_en_contra"]').value =
                item.goles_en_contra ?? 0;

            document.querySelector('[name="amarillas"]').value =
                item.amarillas ?? 0;

            document.querySelector('[name="rojas"]').value =
                item.rojas ?? 0;

            // =========================
            // ARQUERO
            // =========================

            let esArquero =
                "{{ mb_strtolower($jugador->tipoJugador ?? '') }}".includes('arquero');

            let golesRecibidosInput =
                document.querySelector('[name="goles_recibidos"]');

            let vallasInvictasInput =
                document.querySelector('[name="vallas_invictas"]');

            if (golesRecibidosInput) {

                golesRecibidosInput.value = esArquero
                    ? (item.goles_recibidos ?? 0)
                    : 0;
            }

            if (vallasInvictasInput) {

                vallasInvictasInput.value = esArquero
                    ? (item.vallas_invictas ?? 0)
                    : 0;
            }

            // =========================
            // RESET GOLES TM
            // =========================

            // Fallback: si TM no trae datos, todos los goles van a "jugada"
            document.querySelector('[name="goles_cabeza"]').value = 0;
            document.querySelector('[name="goles_jugada"]').value = item.goles_jugada ?? 0;
            document.querySelector('[name="goles_penal"]').value = 0;
            document.querySelector('[name="goles_tiro_libre"]').value = 0;

            // =========================
            // LOGO
            // =========================

            if (item.torneo_logo) {

                document.querySelector('[name="torneo_logo_guardado"]').value =
                    item.torneo_logo;
            }

            // =========================
            // MATCH EQUIPO
            // =========================

            let select = document.querySelector('[name="equipo_id"]');

            let equipoScraper = normalizar(item.equipo);

            let mejorMatch = null;

            let maxScore = 0;

            for (let option of select.options) {

                let equipoDB = normalizar(option.text);

                let score = 0;

                if (equipoDB.includes(equipoScraper)) score += 2;
                if (equipoScraper.includes(equipoDB)) score += 2;

                if (score > maxScore) {

                    maxScore = score;
                    mejorMatch = option;
                }
            }

            if (mejorMatch) {

                select.value = mejorMatch.value;

                $(select).trigger('change');
            }

            // =========================
            // TRANSFERMARKT
            // =========================

            let tmUrl = document.getElementById('transfermarktUrl').value.trim();

            if (!tmUrl) {

                document.getElementById('resultadoTM').innerHTML =
                    avisoFallbackGoles(item, 'No cargaste URL de Transfermarkt.');

                return;
            }

            try {

                document.getElementById('loadingTM')
                    ?.style.setProperty('display', 'block');

                document.getElementById('resultadoTM').innerHTML = '';

                let response = await fetch(
                    "{{ url('/admin/scraper/jugador-transfermarkt-goles') }}"
                    + "?url=" + encodeURIComponent(tmUrl)
                    + "&competicion=" + encodeURIComponent(item.competition)
                    + "&club=" + encodeURIComponent(item.equipo)
                );

                let data = await response.json();

                console.log('TM DATA', data);

                if (data.error) {

                    document.getElementById('resultadoTM').innerHTML =
                        `<div class="alert alert-danger">${data.error}</div>`;

                    return;
                }

                if (!data.length) {

                    document.getElementById('resultadoTM').innerHTML =
                        avisoFallbackGoles(
                            item,
                            `No se encontraron coincidencias en Transfermarkt para <strong>${item.competition}</strong> / ${item.equipo}.`
                        );

                    return;
                }

                // =========================
                // TABLA TM
                // =========================

                let html = `
            <h5 style="color:#0a6ebd">
                Tipos de goles encontrados en Transfermarkt
            </h5>

            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Temp.</th>
                        <th>Competición</th>
                        <th>Club</th>
                        <th>Total</th>
                        <th>Cabeza</th>
                        <th>Jugada</th>
                        <th>Penal</th>
                        <th>T. Libre</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
        `;

                data.forEach(g => {

                    let totalTM =
                        parseInt(g.total ?? 0);

                    let totalFDB =
                        parseInt(item.goles_jugada ?? 0);

                    let diferencia =
                        totalFDB !== totalTM;

                    html += `
                <tr ${diferencia ? 'style="background:#fff3cd;"' : ''}>
                    <td>${g.temporada}</td>
                    <td>${g.competicion}</td>
                    <td>${g.club}</td>

                    <td>
                        <strong>${g.total}</strong>

                        ${
                        diferencia
                            ? `<br><small style="color:#856404">
                                FDB: ${totalFDB}
                              </small>`
                            : ''
                    }
                    </td>

                    <td>${g.cabeza}</td>
                    <td>${g.jugada}</td>
                    <td>${g.penal}</td>
                    <td>${g.tiro_libre}</td>

                    <td>
                        <button
                            class="btn btn-success btn-sm"
                            onclick='usarGolesTM(${JSON.stringify(g)})'>
                            Usar
                        </button>
                    </td>
                </tr>
            `;
                });

                html += `
                </tbody>
            </table>
        `;

                document.getElementById('resultadoTM').innerHTML = html;

            } catch (e) {

                console.error('Error TM', e);

                document.getElementById('resultadoTM').innerHTML =
                    avisoFallbackGoles(item, 'Error consultando Transfermarkt (timeout o red).');

            } finally {

                document.getElementById('loadingTM')
                    ?.style.setProperty('display', 'none');
            }
        }

        function renderResultados(items) {
            let html = '';
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
                        torneo_logo:      row.torneo_logo ?? null,
                        tipo:             row.tipo ?? '',
                        ambito:           row.ambito ?? '',
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

            Object.values(torneos).forEach(competition => {
                let logoHtml = competition.torneo_logo
                    ? `<img src="${competition.torneo_logo}" height="40" style="object-fit:contain;">`
                    : '<span class="text-muted">—</span>';

                html += `<h5 style="color: darkgreen">${clean(competition.competition)}</h5>`;
                html += '<table class="table table-sm">';
                html += '<tr><th>Logo</th><th>Equipo</th><th>Tipo</th><th>Ámbito</th><th>PJ</th><th>Goles</th><th>Propios</th><th>Amarillas</th><th>Rojas</th><th>G.Rec</th><th>V.Inv</th><th>Usar</th><th>Excluir</th></tr>';
                html += `<tr>
                    <td>${logoHtml}</td>
                    <td style="color:#0a6ebd">${competition.equipo}</td>
                    <td>${competition.tipo}</td>
                    <td>${competition.ambito}</td>
                    <td>${competition.partidos}</td>
                    <td>${competition.goles_jugada}</td>
                    <td>${competition.goles_en_contra}</td>
                    <td>${competition.amarillas}</td>
                    <td>${competition.rojas}</td>
                    <td>${competition.goles_recibidos}</td>
                    <td>${competition.vallas_invictas}</td>
                    <td>
                        <button onclick='usarDato(${JSON.stringify(competition)})'
                            class="btn btn-success btn-sm">Usar</button>
                    </td>
                    <td>
                        <button onclick='excluirCompetencia(${JSON.stringify(competition.competition)}, this)'
                            class="btn btn-danger btn-sm" title="No mostrar más esta competencia">
                            🚫
                        </button>

                        <button onclick='excluirEquipo(${JSON.stringify(competition.equipo)}, this)'
                            class="btn btn-danger btn-sm" title="No mostrar más este equipo">
                            🚫
                        </button>
                    </td>
                </tr>`;
                html += '</table>';
            });

            document.getElementById('resultadoScraper').innerHTML = html;
        }

        function scrapearFootballDB() {
            let url = document.getElementById('footballdbUrl').value.trim();
            if (!url) {
                alert('Ingresá la URL');
                return;
            }

            document.getElementById('loadingScraper').style.display = 'block';
            document.getElementById('resultadoScraper').innerHTML = '';

            fetch("{{ url('/admin/scraper/jugador-footballdb') }}?url=" + encodeURIComponent(url))
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
                        // Ocultar el bloque (h5 + table) o la fila según el scraper
                        let table  = btn.closest('table');
                        let row    = btn.closest('tr');
                        let isOneRowTable = table && table.querySelectorAll('tbody tr, tr').length <= 2;

                        if (isOneRowTable) {
                            let header = table.previousElementSibling;
                            if (header && header.tagName === 'H5') header.remove();
                            table.remove();
                        } else if (row) {
                            row.remove();
                        }

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
                            avisoFallbackGoles(item, `Error de Transfermarkt: ${data.error}`);

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

            document.querySelector('[name="goles_cabeza"]').value =
                goles.cabeza ?? 0;

            document.querySelector('[name="goles_jugada"]').value =
                goles.jugada ?? 0;

            document.querySelector('[name="goles_penal"]').value =
                goles.penal ?? 0;

            document.querySelector('[name="goles_tiro_libre"]').value =
                goles.tiro_libre ?? 0;
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
                        // Ocultar TODAS las tablas/filas de ese equipo en pantalla
                        let nombreNorm = normalizar(nombre);

                        document.querySelectorAll('#resultadoScraper table').forEach(table => {
                            let rows = table.querySelectorAll('tr');
                            let dataRows = Array.from(rows).slice(1); // saltea header

                            let allMatch = dataRows.length > 0 && dataRows.every(r => {
                                let equipoCell = r.cells[1]?.innerText ?? '';
                                return normalizar(equipoCell) === nombreNorm
                                    || normalizar(equipoCell).includes(nombreNorm)
                                    || nombreNorm.includes(normalizar(equipoCell));
                            });

                            if (allMatch) {
                                let header = table.previousElementSibling;
                                if (header && header.tagName === 'H5') header.remove();
                                table.remove();
                            } else {
                                dataRows.forEach(r => {
                                    let equipoCell = r.cells[1]?.innerText ?? '';
                                    let eqNorm = normalizar(equipoCell);
                                    if (eqNorm === nombreNorm
                                        || eqNorm.includes(nombreNorm)
                                        || nombreNorm.includes(eqNorm)) {
                                        r.remove();
                                    }
                                });
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
    </script>

@endsection

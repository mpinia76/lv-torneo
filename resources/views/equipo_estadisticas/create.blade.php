@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')
<style>
    dd img { margin-left: 5px; vertical-align: middle; }
</style>
@section('content')
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-2"><dt>Nombre</dt>
                        <dd>{{$equipo->nombre}} <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></dd></div>
                    <div class="col-md-6 mb-2"><dt>Socios</dt><dd>{{$equipo->socios}}</dd></div>
                    <div class="col-md-6 mb-2"><dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd></div>
                    <div class="col-md-6 mb-2"><dt>Estadio</dt><dd>{{$equipo->estadio}}</dd></div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-center align-items-center">
                @if($equipo->escudo)
                    <img src="{{ url('images/'.$equipo->escudo) }}" style="width: 200px" class="img-fluid">
                @endif
            </div>
        </div>
        <hr/>

        @if ($errors->any())
            <div class="alert alert-danger"><ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
        @endif

        {{-- 🌐 Importar desde Transfermarkt (spielplan/verein) --}}
        <div class="mb-3 mt-3">
            <label>Importar desde Transfermarkt</label>
            <div class="d-flex">
                <input type="text" id="tmUrl" class="form-control mr-2"
                       placeholder="https://www.transfermarkt.com.ar/.../spielplan/verein/131/plus/0?saison_id=2025">
                <button type="button" class="btn btn-warning" onclick="scrapearTransfermarkt()" style="white-space:nowrap;">
                    🌐 Scrapear
                </button>
            </div>
            <small class="text-muted">Pegá la URL del calendario de una temporada (cambiá <code>saison_id</code> para otras).</small>
        </div>

        <div id="loadingScraper" style="display:none;" class="alert alert-info">
            ⏳ Cargando estadísticas, puede tardar unos segundos...
        </div>
        <div id="resultadoScraper" class="mt-3"></div>

        <hr>

        {{-- Carga manual / "Usar" individual --}}
        <form action="{{ route('equipo-estadisticas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">

            <div class="row">
                <div class="form-group col-md-4">
                    <label>Torneo</label>
                    <input type="text" name="torneo_nombre" class="form-control" value="{{ old('torneo_nombre') }}">
                </div>
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
            <div class="row">
                <div class="form-group col-md-2"><label>Posición</label>
                    <input type="number" name="posicion" class="form-control" value="{{ old('posicion') }}"></div>
                <div class="form-group col-md-2"><label>Partidos</label>
                    <input type="number" name="partidos" class="form-control" value="{{ old('partidos') }}"></div>
            </div>
            <hr>
            <h5>Partidos</h5>
            <div class="row">
                <div class="form-group col-md-2"><label>Ganados</label>
                    <input type="number" name="ganados" class="form-control" value="{{ old('ganados') }}"></div>
                <div class="form-group col-md-2"><label>Empatados</label>
                    <input type="number" name="empatados" class="form-control" value="{{ old('empatados') }}"></div>
                <div class="form-group col-md-2"><label>Perdidos</label>
                    <input type="number" name="perdidos" class="form-control" value="{{ old('perdidos') }}"></div>
            </div>
            <hr>
            <h5>Goles</h5>
            <div class="row">
                <div class="form-group col-md-2"><label>Favor</label>
                    <input type="number" name="goles_favor" class="form-control" value="{{ old('goles_favor') }}"></div>
                <div class="form-group col-md-2"><label>En contra</label>
                    <input type="number" name="goles_en_contra" class="form-control" value="{{ old('goles_en_contra') }}"></div>
            </div>
            <hr>
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('equipo-estadisticas.indexPorEquipo', $equipo->id) }}" class="btn btn-success">Volver</a>
        </form>
    </div>

    <script>
        function clean(t) { return t ? t.trim().replace(/\s+/g, ' ') : ''; }

        function opcionesSelect(valores, seleccionado) {
            let html = '';
            valores.forEach(function (v) {
                let sel = (v === (seleccionado ?? '')) ? 'selected' : '';
                html += `<option value="${v}" ${sel}>${v === '' ? 'Seleccionar...' : v}</option>`;
            });
            return html;
        }

        // Fill the manual form from a single scraped row.
        function usarDato(item) {
            document.querySelector('[name="torneo_nombre"]').value = clean(item.competition);
            document.querySelector('[name="tipo"]').value = item.tipo ?? '';
            document.querySelector('[name="ambito"]').value = item.ambito ?? '';
            document.querySelector('[name="posicion"]').value = item.posicion ?? '';
            document.querySelector('[name="partidos"]').value = item.partidos ?? 0;
            document.querySelector('[name="ganados"]').value = item.ganados ?? 0;
            document.querySelector('[name="empatados"]').value = item.empatados ?? 0;
            document.querySelector('[name="perdidos"]').value = item.perdidos ?? 0;
            document.querySelector('[name="goles_favor"]').value = item.gf ?? 0;
            document.querySelector('[name="goles_en_contra"]').value = item.ge ?? 0;
            if (item.torneo_logo) {
                document.querySelector('[name="torneo_logo_guardado"]').value = item.torneo_logo;
            }
        }

        function renderResultados(items) {
            if (!items.length) {
                document.getElementById('resultadoScraper').innerHTML =
                    '<div class="alert alert-warning">Sin resultados.</div>';
                return;
            }

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

            items.forEach(function (c, idx) {
                html += `
                <div class="card mb-3 fila-torneo" data-idx="${idx}">
                    <div class="card-header d-flex align-items-center" style="background:#f1f8f1;">
                        <input type="checkbox" class="check-torneo mr-2" value="${idx}">
                        <strong style="color: darkgreen;">${clean(c.competition)}</strong>
                        <span class="ml-auto">
                            <button type="button" onclick='excluirCompetencia(${JSON.stringify(c.competition)}, this)'
                                class="btn btn-danger btn-sm" title="No mostrar más esta competencia">🚫 Comp.</button>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4"><label class="small mb-0">Torneo</label>
                                <input type="text" class="form-control form-control-sm f-torneo_nombre" value="${clean(c.competition)}"></div>
                            <div class="form-group col-md-2"><label class="small mb-0">Tipo</label>
                                <select class="form-control form-control-sm f-tipo">${opcionesSelect(['', 'Liga', 'Copa'], c.tipo)}</select></div>
                            <div class="form-group col-md-3"><label class="small mb-0">Ámbito</label>
                                <select class="form-control form-control-sm f-ambito">${opcionesSelect(['', 'Nacional', 'Internacional'], c.ambito)}</select></div>
                            <div class="form-group col-md-3"><label class="small mb-0">Logo</label>
                                <input type="file" class="form-control-file form-control-sm f-logo_file"></div>
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
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success btn-sm" onclick='usarDato(${JSON.stringify(c)})'>Usar arriba</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            });

            document.getElementById('resultadoScraper').innerHTML = html;
        }

        function scrapearTransfermarkt() {
            let url = document.getElementById('tmUrl').value.trim();
            if (!url) { alert('Ingresá la URL'); return; }

            document.getElementById('loadingScraper').style.display = 'block';
            document.getElementById('resultadoScraper').innerHTML = '';
            let equipoId = document.querySelector('[name="equipo_id"]').value;

            fetch("{{ url('/admin/scraper/equipo-transfermarkt') }}?url=" + encodeURIComponent(url) + "&equipo_id=" + equipoId)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.error) {
                        document.getElementById('resultadoScraper').innerHTML =
                            '<div class="alert alert-danger">' + data.error + '</div>';
                        return;
                    }
                    renderResultados(data);
                })
                .catch(function (err) {
                    console.error(err);
                    document.getElementById('resultadoScraper').innerHTML =
                        '<div class="alert alert-danger">Error scrapeando</div>';
                })
                .finally(function () {
                    document.getElementById('loadingScraper').style.display = 'none';
                });
        }

        function excluirCompetencia(nombre, btn) {
            if (!confirm('¿Excluir "' + nombre + '" de futuros scrapeos?\n\nSe guardará el patrón sin el año.')) return;

            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            fetch("{{ url('/admin/competencias-excluidas/excluir-rapido') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': token, 'Accept': 'application/json'
                },
                body: 'nombre=' + encodeURIComponent(nombre)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok) {
                        let card = btn.closest('.fila-torneo');
                        if (card) card.remove();
                        let msg = document.createElement('div');
                        msg.className = 'alert alert-warning';
                        msg.innerText = data.creado
                            ? '✅ "' + data.patron + '" agregado a exclusiones.'
                            : 'ℹ️ "' + data.patron + '" ya estaba excluido.';
                        document.getElementById('resultadoScraper').prepend(msg);
                        setTimeout(function () { msg.remove(); }, 3500);
                    } else {
                        alert(data.msg || 'No se pudo excluir');
                    }
                })
                .catch(function (err) { console.error(err); alert('Error al excluir'); });
        }

        function toggleTodos(master) {
            document.querySelectorAll('.check-torneo').forEach(function (c) { c.checked = master.checked; });
        }

        async function guardarSeleccionados() {
            let seleccionados = Array.from(document.querySelectorAll('.check-torneo:checked'));
            if (!seleccionados.length) { alert('No seleccionaste ningún torneo.'); return; }

            let equipoId = document.querySelector('[name="equipo_id"]').value;
            let token = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            let campos = [
                'torneo_nombre', 'tipo', 'ambito',
                'posicion', 'partidos',
                'ganados', 'empatados', 'perdidos',
                'goles_favor', 'goles_en_contra',
            ];

            let formData = new FormData();
            formData.append('equipo_id', equipoId);

            let cardsPorIndice = {};
            seleccionados.forEach(function (chk, i) {
                let card = chk.closest('.fila-torneo');
                cardsPorIndice[i] = card;

                campos.forEach(function (campo) {
                    let el = card.querySelector('.f-' + campo);
                    formData.append(`torneos[${i}][${campo}]`, el ? el.value : '');
                });

                let fileInput = card.querySelector('.f-logo_file');
                if (fileInput && fileInput.files.length) {
                    formData.append(`torneos[${i}][logo_file]`, fileInput.files[0]);
                }
            });

            let resumen = document.getElementById('resumenMasivo');
            resumen.innerHTML = '⏳ Guardando...';

            try {
                let res = await fetch("{{ route('equipo-estadisticas.storeMasivo') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: formData
                });
                let data = await res.json();

                if (data.resultados && data.resultados.length) {
                    data.resultados.forEach(function (r) {
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
                if (data.con_auto) msg += ` · ⚠ ${data.con_auto} con stats automáticas existentes`;
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

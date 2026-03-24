@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $tecnico->persona->foto ? url('images/'.$tecnico->persona->foto) : url('images/sin_foto.png') }}"
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
        <button type="button" class="btn btn-info" onclick="verHistorial()">
            🔍 Ver historial
        </button>
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
            {{-- stats base --}}
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

            {{-- ⚽ TIPOS DE GOLES --}}
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
@endsection
<script>
    function verHistorial() {
        let tecnico_id = document.querySelector('[name="tecnico_id"]').value;
        let equipo_id = document.querySelector('[name="equipo_id"]').value;

        let url = "{{ url('admin/scraper/tecnico') }}";

        // 🔥 mostrar loading
        document.getElementById('loadingScraper').style.display = 'block';
        document.getElementById('resultadoScraper').innerHTML = '';

        fetch(`${url}?tecnico_id=${tecnico_id}&equipo_id=${equipo_id}`)
            .then(res => res.json())
            .then(data => {

                let html = '';
                let items = data.original ?? [];

                // 🔥 totales
                let totPartidos = 0;
                let totGanados = 0;
                let totEmpatados = 0;
                let totPerdidos = 0;
                let totGF = 0;
                let totGE = 0;

                items.forEach(competition => {

                    totPartidos += parseInt(competition.partidos || 0);
                    totGanados += parseInt(competition.ganados || 0);
                    totEmpatados += parseInt(competition.empatados || 0);
                    totPerdidos += parseInt(competition.perdidos || 0);
                    totGF += parseInt(competition.gf || 0);
                    totGE += parseInt(competition.ge || 0);

                    html += `<h5 style="color: darkgreen">${clean(competition.competition)}</h5>`;
                    html += '<table class="table table-sm">';
                    html += '<tr><th>Equipo</th><th>Partidos</th><th>Ganados</th><th>Empatados</th><th>Perdidos</th><th>GF</th><th>GE</th><th>Usar</th></tr>';

                    html += `<tr>
                    <td style="color: #0a6ebd">${competition.equipo}</td>
                    <td>${competition.partidos}</td>
                    <td>${competition.ganados}</td>
                    <td>${competition.empatados}</td>
                    <td>${competition.perdidos}</td>
                    <td>${competition.gf}</td>
                    <td>${competition.ge}</td>
                    <td>
                        <button onclick='usarDato(${JSON.stringify(competition)})' class="btn btn-success btn-sm">
                            Usar
                        </button>
                    </td>
                </tr>`;

                    html += '</table>';
                });

                // 🔥 tabla totales
                if (items.length) {
                    html += `
                <table class="table table-sm table-bordered mt-3">
                    <tr class="table-dark">
                        <th>Totales</th>
                        <th>Partidos</th>
                        <th>Ganados</th>
                        <th>Empatados</th>
                        <th>Perdidos</th>
                        <th>GF</th>
                        <th>GE</th>
                        <th></th>
                    </tr>
                    <tr class="table-warning">
                        <td><strong>Total</strong></td>
                        <td>${totPartidos}</td>
                        <td>${totGanados}</td>
                        <td>${totEmpatados}</td>
                        <td>${totPerdidos}</td>
                        <td>${totGF}</td>
                        <td>${totGE}</td>
                        <td></td>
                    </tr>
                </table>`;
                }

                document.getElementById('resultadoScraper').innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('resultadoScraper').innerHTML =
                    '<div class="alert alert-danger">Error cargando datos</div>';
            })
            .finally(() => {
                document.getElementById('loadingScraper').style.display = 'none';
            });
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

    function usarDato(item) {

        // ⚽ partidos
        document.querySelector('[name="partidos"]').value = item.partidos;
        document.querySelector('[name="ganados"]').value = item.ganados ?? 0;
        document.querySelector('[name="empatados"]').value = item.empatados ?? 0;
        document.querySelector('[name="perdidos"]').value = item.perdidos ?? 0;
        document.querySelector('[name="goles_favor"]').value = item.gf ?? 0;
        document.querySelector('[name="goles_en_contra"]').value = item.ge ?? 0;

        document.querySelector('[name="torneo_nombre"]').value = clean(item.competition);

        // 🧠 match equipo
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
            $(select).trigger('change'); // Select2
        }
    }

</script>

@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')
<style>


    /* --- Equipo info --- */
    dd img {
        margin-left: 5px;
        vertical-align: middle;
    }
</style>
@section('content')
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <dt>Nombre</dt>
                        <dd>{{$equipo->nombre}} <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Socios</dt>
                        <dd>{{$equipo->socios}}</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Estadio</dt>
                        <dd>{{$equipo->estadio}}</dd>
                    </div>
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
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(!$equipo->url_id)
            <div class="alert alert-warning">
                ⚠️ Este equipo no tiene configurado el <strong>LiveFutbol ID</strong>.
                No se podrá consultar historial automático hasta cargarlo manualmente.
            </div>
        @else
        <button type="button" class="btn btn-info mb-3" onclick="verHistorialEquipo()">
            ⚽ Ver historial
        </button>

        <div id="loadingScraper" style="display:none;" class="alert alert-info">
            ⏳ Cargando historial del equipo...
        </div>

        <div id="resultadoScraper" class="mt-3"></div>
        @endif
        <hr>
        <form action="{{ route('equipo-estadisticas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- 🔒 equipo --}}
            <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">

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

            <a href="{{ route('equipo-estadisticas.indexPorEquipo', $equipo->id) }}"
               class="btn btn-success">
                Volver
            </a>

        </form>
    </div>
@endsection
<script>
    function verHistorialEquipo() {

        let equipo_id = document.querySelector('[name="equipo_id"]').value;

        let url = "{{ url('admin/scraper/equipo') }}";

        document.getElementById('loadingScraper').style.display = 'block';
        document.getElementById('resultadoScraper').innerHTML = '';

        fetch(`${url}?equipo_id=${equipo_id}`)
            .then(res => res.json())
            .then(data => {

                let html = '';
                let items = data.original ?? data;

                items.forEach(competition => {

                    html += `<h5 style="color: darkgreen">${competition.liga} - ${competition.year}</h5>`;
                    html += '<table class="table table-sm table-bordered">';
                    html += '<tr><th>Pos</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>GF</th><th>GC</th><th></th></tr>';

                    html += `<tr>
                    <td>${competition.posicion ?? ''}</td>
                    <td>${competition.partidos ?? ''}</td>
                    <td>${competition.ganados ?? ''}</td>
                    <td>${competition.empatados ?? ''}</td>
                    <td>${competition.perdidos ?? ''}</td>
                    <td>${competition.gf ?? ''}</td>
                    <td>${competition.ge ?? ''}</td>
                    <td>
                        <button onclick='usarDatoEquipo(${JSON.stringify(competition)})'
                            class="btn btn-success btn-sm">
                            Usar
                        </button>
                    </td>
                </tr>`;

                    html += '</table>';
                });

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

    function usarDatoEquipo(item) {

        document.querySelector('[name="torneo_nombre"]').value = item.competition;
        document.querySelector('[name="posicion"]').value = item.posicion ?? '';
        document.querySelector('[name="partidos"]').value = item.partidos ?? '';

        document.querySelector('[name="ganados"]').value = item.ganados ?? 0;
        document.querySelector('[name="empatados"]').value = item.empatados ?? 0;
        document.querySelector('[name="perdidos"]').value = item.perdidos ?? 0;

        document.querySelector('[name="goles_favor"]').value = item.gf ?? 0;
        document.querySelector('[name="goles_en_contra"]').value = item.ge ?? 0;
    }
</script>

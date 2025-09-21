@extends('layouts.appPublic')

@section('pageTitle', 'Tabla histórica')

@section('content')
    <style>
        .select2-container {
            margin-right: 20px;
        }
    </style>
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🏆 Posiciones Histórico</h1>



                <form class="form-inline mb-3 d-flex justify-content-between align-items-center" id="formulario">


                    <div class="d-flex align-items-center">
                        <select class="form-control js-example-basic-single mr-3" id="tipo" name="tipo" onchange="enviarForm()">


                            <option value=""/>Todos</option>
                            <option value="liga" {{ (isset($_GET['tipo'])&&$_GET['tipo']=='liga')?'selected':''}}/>Ligas nacionales</option>
                            <option value="copa" {{ (isset($_GET['tipo'])&&$_GET['tipo']=='copa')?'selected':''}}/>Copas nacionales</option>

                        </select>
                        <select class="form-control js-example-basic-single mr-3" id="ambito" name="ambito" onchange="enviarForm()" style="margin-right: 20px;margin-left: 20px;">


                            <option value=""/>Todos</option>
                            <option value="nacional" {{ (isset($_GET['ambito'])&&$_GET['ambito']=='nacional')?'selected':''}}/>Nacionales</option>
                            <option value="internacional" {{ (isset($_GET['ambito'])&&$_GET['ambito']=='internacional')?'selected':''}}/>Internacional</option>

                        </select>

                        <div class="form-check" style="margin-right: 20px;margin-left: 20px;">
                            <input type="checkbox" class="form-check-input" id="argentinos" name="argentinos" @if ($argentinos == 1) checked @endif onchange="enviarForm()">
                            <label class="form-check-label" for="argentinos">Argentinos</label>
                        </div>

                    </div>



                    <div class="d-flex align-items-center">
                        <input type="search" name="buscarpor" class="form-control mr-2" placeholder="Buscar" value="{{ request('buscarpor', session('nombre_filtro_jugador')) }}">
                        <button class="btn btn-success" type="button" onclick="enviarForm()">Buscar</button>
                    </div>

                </form>

                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark">
            <th>#</th>
            <th>Equipo</th>
            <th>Punt.</th>
            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>

            <th>Prom.</th>
            <th>Títulos</th>
            </thead>
            <tbody>

            @foreach($posiciones as $equipo)
                <tr>
                    <td>{{$i++}}</td>
                    <td>
                        <a href="{{route('equipos.ver', array('equipoId' => $equipo->equipo_id))}}" >
                        @if($equipo->foto)
                            <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                        @endif
                        </a>
                        {{$equipo->equipo}} <img id="original" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></td>
                    <td>{{$equipo->puntaje}}</td>
                    <td>{{$equipo->jugados}}</td>
                    <td>{{$equipo->ganados}}</td>
                    <td>{{$equipo->empatados}}</td>
                    <td>{{$equipo->perdidos}}</td>
                    <td>{{$equipo->golesl}}</td>
                    <td>{{$equipo->golesv}}</td>
                    <td>{{$equipo->diferencia}}</td>

                    <td>
                        @if($equipo->jugados > 0)
                            {{ round(($equipo->puntaje * 100) / ($equipo->jugados * 3), 2) }}%
                        @else
                            0%
                        @endif
                    </td>

                    <td>{{$equipo->titulos}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $posiciones->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $posiciones->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
        </div>
    </div>
    <script>


        function enviarForm() {
            //$('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>
@endsection

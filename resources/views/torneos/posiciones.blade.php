@extends('layouts.appPublic')

@section('pageTitle', 'Tabla histórica')

@section('content')
    <div class="container">
        <form class="form-inline" id="formulario">


            <select class="form-control" id="tipo" name="tipo" onchange="enviarForm()">


                    <option value=""/>Todos</option>
                <option value="liga" {{ (isset($_GET['tipo'])&&$_GET['tipo']=='liga')?'selected':''}}/>Ligas nacionales</option>
                <option value="copa" {{ (isset($_GET['tipo'])&&$_GET['tipo']=='copa')?'selected':''}}/>Copas nacionales</option>

            </select>
            <select class="form-control" id="ambito" name="ambito" onchange="enviarForm()">


                <option value=""/>Todos</option>
                <option value="nacional" {{ (isset($_GET['ambito'])&&$_GET['ambito']=='nacional')?'selected':''}}/>Nacionales</option>
                <option value="internacional" {{ (isset($_GET['ambito'])&&$_GET['ambito']=='internacional')?'selected':''}}/>Internacional</option>

            </select>


            </input>
            <nav class="navbar navbar-light float-right">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_equipo') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="button" onClick="enviarForm()">Buscar</button>
            </nav>


        </form>



        <table class="table">
            <thead>
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

    <script>


        function enviarForm() {
            //$('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>
@endsection

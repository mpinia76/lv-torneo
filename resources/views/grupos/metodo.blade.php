@extends('layouts.appPublic')

@section('pageTitle', 'Método Paenza')

@section('content')
    <div class="container">

        <form class="form-inline">
            <input type="hidden" name="torneoId" value="{{ (isset($_GET['torneoId']))?$_GET['torneoId']:'' }}">

            <select class="form-control js-example-basic-single" id="fechaNumero" name="fechaNumero" onchange="this.form.submit()" style="width: 150px">
                @foreach($fechas as $f)

                    <option value="{{$f->numero}}" @if($f->numero==$fecha->numero)
                        selected

                        @endif />Fecha {{$f->numero}}</option>
                @endforeach

            </select>



        </form>
        <br>


            @php
                $i = 1;
            @endphp

            <table class="table">
                <thead>
                <th>#</th>
                <th style="width: 300px;">Equipo</th>

                <th>Puntos que enfrenta</th>

                </thead>
                <tbody>

                @foreach($arrPrimeros as $equipo)
                    <tr>
                        <td>{{$i++}}</td>
                        <td>
                            <a href="{{route('equipos.ver', array('equipoId' => $equipo->equipo_id))}}" >
                            @if($equipo->foto)
                                <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                            @endif
                            </a>
                            {{$equipo->equipo}}</td>

                        <td>{{$equipo->puntos}}</td>



                    </tr>
                @endforeach
                </tbody>
            </table>

        @foreach($arrPosiciones as $nombre => $posiciones)
            @php
                $i = 1;
            @endphp
            @if(count($arrPosiciones)>1)
                Grupo {{$nombre}}
            @endif
            <table class="table">
                <thead>
                <th>#</th>
                <th style="width: 300px;">Equipo</th>
                <th>Punt.</th>
                <th>J</th>
                <th>G</th>
                <th>E</th>
                <th>P</th>
                <th>GF</th>
                <th>GC</th>
                <th>Dif.</th>


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
                            {{$equipo->equipo}}</td>
                        <td>{{$equipo->puntaje}}</td>
                        <td>{{$equipo->jugados}}</td>
                        <td>{{$equipo->ganados}}</td>
                        <td>{{$equipo->empatados}}</td>
                        <td>{{$equipo->perdidos}}</td>
                        <td>{{$equipo->golesl}}</td>
                        <td>{{$equipo->golesv}}</td>
                        <td>{{$equipo->diferencia}}</td>




                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach



        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

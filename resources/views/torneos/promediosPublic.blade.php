@extends('layouts.appPublic')

@section('pageTitle', 'Promedios')

@section('content')
    <div class="container">




    <table class="table">
        <thead>
        <th>#</th>
        <th>Equipo</th>
        <th>Prom.</th>
        <th>Jugados</th>

        <th>Punt.</th>

        </thead>
        <tbody>

        @foreach($promedios as $equipo)
            <tr>
                <td>{{$i++}}</td>
                <td>
                    <a href="{{route('equipos.ver', array('equipoId' => $equipo->equipo_id))}}" >
                    @if($equipo->foto)
                        <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                    @endif
                    </a>
                {{$equipo->equipo}}</td>
                <td>{{$equipo->promedio}}</td>
                <td>{{$equipo->jugados}}</td>

                <td>{{$equipo->puntaje}}</td>



            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

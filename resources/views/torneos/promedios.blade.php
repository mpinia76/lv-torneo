@extends('layouts.app')

@section('pageTitle', 'Promedios')

@section('content')
    <div class="container">
    <h1 class="display-6">Promedios de {{$torneo->nombre}} {{$torneo->year}}</h1>

    <hr/>



    <table class="table">
        <thead>
        <th>#</th>
        <th>Equipo</th>
        <th>Punt.</th>

        <th>Jugados</th>
        <th>Prom.</th>


        </thead>
        <tbody>

        @foreach($promedios as $equipo)
            <tr>
                <td>{{$i++}}</td>
                <td>@if($equipo->foto)
                        <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                    @endif

                {{$equipo->equipo}}</td>
                <td>{{$equipo->puntaje}}</td>

                <td>{{$equipo->jugados}}</td>

                <td>{{$equipo->promedio}}</td>



            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="d-flex">

            <a href="{{ route('torneos.show',$torneo->id)}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

@extends('layouts.app')

@section('pageTitle', 'Posiciones')

@section('content')
    <div class="container">
    <h1 class="display-6">Posiciones del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

    <hr/>



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


        </thead>
        <tbody>

        @foreach($posiciones as $equipo)
            <tr>
                <td>{{$i++}}</td>
                <td>@if($equipo->foto)
                        <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                    @endif

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
        <div class="d-flex">

            <a href="{{ route('torneos.show',$grupo->torneo->id)}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

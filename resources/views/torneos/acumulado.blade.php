@extends('layouts.appPublic')

@section('pageTitle', 'Acumulado')

@section('content')
    <div class="container">




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



        </thead>
        <tbody>

        @foreach($acumulado as $equipo)
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
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

@extends('layouts.appPublic')

@section('pageTitle', 'Tabla histórica')

@section('content')
    <div class="container">




        <table class="table">
            <thead>
            <th>#</th>
            <th>Equipo</th>
            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            <th>Punt.</th>
            <th>Prom.</th>

            </thead>
            <tbody>
            @php
                $i = 1;
            @endphp
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
                    <td>{{$equipo->jugados}}</td>
                    <td>{{$equipo->ganados}}</td>
                    <td>{{$equipo->empatados}}</td>
                    <td>{{$equipo->perdidos}}</td>
                    <td>{{$equipo->golesl}}</td>
                    <td>{{$equipo->golesv}}</td>
                    <td>{{$equipo->diferencia}}</td>
                    <td>{{$equipo->puntaje}}</td>
                    <td>{{$equipo->promedio}}</td>


                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

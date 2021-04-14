@extends('layouts.appPublic')

@section('pageTitle', 'Posiciones')

@section('content')
    <div class="container">



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
                <th>J</th>
                <th>G</th>
                <th>E</th>
                <th>P</th>
                <th>GF</th>
                <th>GC</th>
                <th>Dif.</th>
                <th>Punt.</th>

                </thead>
                <tbody>

                @foreach($posiciones as $equipo)
                    <tr>
                        <td>{{$i++}}</td>
                        <td>@if($equipo->foto)
                                <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                            @endif

                            {{$equipo->equipo}}</td>
                        <td>{{$equipo->jugados}}</td>
                        <td>{{$equipo->ganados}}</td>
                        <td>{{$equipo->empatados}}</td>
                        <td>{{$equipo->perdidos}}</td>
                        <td>{{$equipo->golesl}}</td>
                        <td>{{$equipo->golesv}}</td>
                        <td>{{$equipo->diferencia}}</td>
                        <td>{{$equipo->puntaje}}</td>



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

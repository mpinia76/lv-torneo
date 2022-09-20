@extends('layouts.appPublic')

@section('pageTitle', 'Método Paenza')

@section('content')
    <div class="container">




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





        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

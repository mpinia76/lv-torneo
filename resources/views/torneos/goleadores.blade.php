@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
    <h1 class="display-6">Histórico de goleadores</h1>

    <hr/>



    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>

        <th>Goles</th>
        <th>Jugada</th>
        <th>Cabeza</th>
        <th>Penal</th>
        <th>Tiro Libre</th>
        <th>Equipos</th>
        </thead>
        <tbody>

        @foreach($goleadores as $jugador)
            <tr>
                <td>{{$i++}}</td>
                <td>
                    <a href="{{route('jugadores.ver', array('jugadorId' => $jugador->id))}}" >
                    @if($jugador->foto)
                        <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                    @else
                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                    @endif
                    </a>
                {{$jugador->jugador}}</td>

                <td>{{$jugador->goles}}</td>
                <td>{{$jugador->Jugada}}</td>
                <td>{{$jugador->Cabeza}}</td>
                <td>{{$jugador->Penal}}</td>
                <td>{{$jugador->Tiro_Libre}}</td>
                <td>@if($jugador->escudo)
                        @php
                            $escudos = explode(',',$jugador->escudo);
                        @endphp
                        @foreach($escudos as $escudo)
                            @if($escudo!='')
                                @php
                                    $escudoArr = explode('_',$escudo);
                                @endphp
                                <a href="{{route('equipos.ver', array('equipoId' => $escudoArr[1]))}}" >
                                    <img id="original" src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                </a>
                                Goles {{$escudoArr[2]}} <br>
                            @endif
                        @endforeach
                    @endif

                </td>

            </tr>
        @endforeach
        </tbody>
    </table>
        {{$goleadores->links()}}
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">




    <table class="table" style="width: 100%">
        <thead>
        <th>#</th>
        <th>Jugador</th>


        <th>Amarillas</th>

        <th>Rojas</th>
        <th>Jugados</th>
        <th>Prom. A</th>
        <th>Prom. R</th>
        <th>Equipos</th>
        </thead>
        <tbody>

        @foreach($tarjetas as $jugador)
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



                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Amarillas'))}}" >{{$jugador->amarillas}}</a></td>

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Rojas'))}}" >{{$jugador->rojas}}</a></td>
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id))}}" >{{$jugador->jugados}}</a></td>
                <td>{{round($jugador->amarillas / $jugador->jugados,2)}}</td>
                <td>{{round($jugador->rojas / $jugador->jugados,2)}}</td>
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
                                Amarillas {{$escudoArr[3]}}
                                Rojas {{$escudoArr[2]}} <br>
                            @endif
                        @endforeach
                    @endif

                </td>


            </tr>
        @endforeach
        </tbody>
    </table>
        {{$tarjetas->links()}}
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

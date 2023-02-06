@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
    <h1 class="display-6">Histórico de goleadores</h1>

    <hr/>
        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp


    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>

        <th><a href="{{route('torneos.goleadores', array('order'=>'Goles','tipoOrder'=>$tipoOrder))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.goleadores', array('order'=>'Jugada','tipoOrder'=>$tipoOrder))}}" > Jugada @if($order=='Jugada') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.goleadores', array('order'=>'Cabeza','tipoOrder'=>$tipoOrder))}}" >Cabeza @if($order=='Cabeza')<img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.goleadores', array('order'=>'Penal','tipoOrder'=>$tipoOrder))}}" > Penal @if($order=='Penal') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.goleadores', array('order'=>'Tiro_Libre','tipoOrder'=>$tipoOrder))}}" > Tiro Libre @if($order=='Tiro_Libre') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th>Jugados</th>
        <th>Prom.</th>
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

                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id))}}" >{{$jugador->goles}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'tipo'=>'Jugada'))}}" >{{$jugador->Jugada}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'tipo'=>'Cabeza'))}}" >{{$jugador->Cabeza}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'tipo'=>'Penal'))}}" >{{$jugador->Penal}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'tipo'=>'Tiro Libre'))}}" >{{$jugador->Tiro_Libre}}</a></td>
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id))}}" >{{$jugador->jugados}}</a></td>
                <td>{{round($jugador->goles / $jugador->jugados,2)}}</td>
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

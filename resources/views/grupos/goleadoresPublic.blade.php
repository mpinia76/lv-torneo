@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp



    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Equipos</th>
        <th><a href="{{route('grupos.goleadoresPublic', array('torneoId' => $torneo->id,'order'=>'Goles','tipoOrder'=>$tipoOrder))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.goleadoresPublic', array('torneoId' => $torneo->id,'order'=>'Jugada','tipoOrder'=>$tipoOrder))}}" > Jugada @if($order=='Jugada') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.goleadoresPublic', array('torneoId' => $torneo->id,'order'=>'Cabeza','tipoOrder'=>$tipoOrder))}}" >Cabeza @if($order=='Cabeza')<img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.goleadoresPublic', array('torneoId' => $torneo->id,'order'=>'Penal','tipoOrder'=>$tipoOrder))}}" > Penal @if($order=='Penal') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.goleadoresPublic', array('torneoId' => $torneo->id,'order'=>'Tiro_Libre','tipoOrder'=>$tipoOrder))}}" > Tiro Libre @if($order=='Tiro_Libre') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th>Jugados</th>
        <th>Prom.</th>
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
                            @endif
                        @endforeach
                    @endif

                    </td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id))}}" >{{$jugador->goles}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id,'tipo'=>'Jugada'))}}" >{{$jugador->Jugada}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id,'tipo'=>'Cabeza'))}}" >{{$jugador->Cabeza}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id,'tipo'=>'Penal'))}}" >{{$jugador->Penal}}</a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id,'tipo'=>'Tiro Libre'))}}" >{{$jugador->Tiro_Libre}}</a></td>
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id,'torneoId' => $torneo->id))}}" >{{$jugador->jugados}}</a></td>
                <td>{{round($jugador->goles / $jugador->jugados,2)}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $goleadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $goleadores->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id)) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

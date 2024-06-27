@extends('layouts.appPublic')

@section('pageTitle', 'Jugadores')

@section('content')
    <div class="container">
        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp

        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input type="hidden" id="torneoId" name="torneoId" value="{{$torneo->id}}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_jugador') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Equipos</th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'jugados','tipoOrder'=>$tipoOrder))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'Goles','tipoOrder'=>$tipoOrder))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'amarillas','tipoOrder'=>$tipoOrder))}}" > Amarillas @if($order=='amarillas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'rojas','tipoOrder'=>$tipoOrder))}}" > Rojas @if($order=='rojas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'recibidos','tipoOrder'=>$tipoOrder))}}" >Arq. Recibidos @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('grupos.jugadores', array('torneoId' => $torneo->id,'order'=>'invictas','tipoOrder'=>$tipoOrder))}}" > Arq. V. Invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        </thead>
        <tbody>

        @foreach($jugadores as $jugador)
            <tr>
                <td>{{$i++}}</td>
                <td>
                    <a href="{{route('jugadores.ver', array('jugadorId' => $jugador->jugador_id))}}" >
                        @if($jugador->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                        @else
                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                        @endif
                    </a>
                    {{$jugador->jugador}} <img id="original" src="{{ url('images/'.$jugador->nacionalidad.'.gif') }}" alt="{{ $jugador->nacionalidad }}"></td>
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
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id))}}" >{{$jugador->jugados}} </a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id))}}" >{{$jugador->goles}}</a></td>
                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id,'tipo'=>'Amarillas'))}}" >{{$jugador->amarillas}}</a></td>

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id,'tipo'=>'Rojas'))}}" >{{$jugador->rojas}}</a></td>
                <td>{{$jugador->recibidos}} (@if($jugador->jugados){{round($jugador->recibidos / $jugador->jugados,2)}} @else 0 @endif)</td>
                <td>{{$jugador->invictas}} (@if($jugador->jugados){{round($jugador->invictas / $jugador->jugados,2)}} @else 0 @endif)</td>
            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $jugadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $jugadores->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id)) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

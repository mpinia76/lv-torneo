@extends('layouts.appPublic')

@section('pageTitle', 'Arqueros')

@section('content')
    <div class="container">

        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp


    <table class="table" style="width: 100%">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Actual</th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'jugados','tipoOrder'=>$tipoOrder))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'recibidos','tipoOrder'=>$tipoOrder))}}" > Goles @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'invictas','tipoOrder'=>$tipoOrder))}}" > Vallas invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        <th>Equipos</th>
        </thead>
        <tbody>

        @foreach($arqueros as $jugador)
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

                <td>@if($jugador->jugando)
                        @php
                            $escs = explode(',',$jugador->jugando);
                        @endphp
                        @foreach($escs as $esc)

                            @if($esc!='')
                                @php
                                    $escArr = explode('_',$esc);
                                @endphp
                                <a href="{{route('equipos.ver', array('equipoId' => $escArr[1]))}}" >
                                    <img id="original" src="{{ url('images/'.$escArr[0]) }}" height="25">
                                </a>
                            @endif
                        @endforeach

                    @endif</td>

                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id))}}" >{{$jugador->jugados}}</a> </td>
                <td>{{$jugador->recibidos}} ({{round($jugador->recibidos / $jugador->jugados,2)}})</td>
                <td>{{$jugador->invictas}} ({{round($jugador->invictas / $jugador->jugados,2)}})</td>
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
                                Recibidos {{$escudoArr[2]}}
                                Vallas Invictas {{$escudoArr[3]}} <br>
                            @endif
                        @endforeach
                    @endif

                </td>


            </tr>
        @endforeach
        </tbody>
    </table>
        {{$arqueros->links()}}
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection

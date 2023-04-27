@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">

        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp
        <form class="form-inline">

            <input type="hidden" name="tipoOrder" value="{{$tipoOrder}}">
            <input type="hidden" name="imgOrder" value="{{$imgOrder}}">
            <select class="orm-control js-example-basic-single" id="torneoId" name="torneoId" onchange="this.form.submit()">
                @foreach($torneos as $torneo)

                    <option value="{{$torneo->id}}" @if($torneo->id==$torneoId)
                        selected

                        @endif />{{$torneo->nombre}} - {{$torneo->year}}</option>
                @endforeach

            </select>
            <input type="checkbox" class="orm-control" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="this.form.submit()">

            <strong>Jugando</strong>
            </input>



        </form>
        <br>

    <table class="table" style="width: 100%">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Actual</th>
        <th><a href="{{route('torneos.tarjetas', array('order'=>'amarillas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > Amarillas @if($order=='amarillas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.tarjetas', array('order'=>'rojas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > Rojas @if($order=='rojas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>


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

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Amarillas'))}}" >{{$jugador->amarillas}}</a></td>

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Rojas'))}}" >{{$jugador->rojas}}</a></td>
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id))}}" >{{$jugador->jugados}}</a></td>
                <td>@if($jugador->jugados){{round($jugador->amarillas / $jugador->jugados,2)}} @else 0 @endif</td>
                <td>@if($jugador->jugados){{round($jugador->rojas / $jugador->jugados,2)}} @else 0 @endif</td>
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

@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp
        <form class="form-inline" id="formulario">

            <input type="hidden" id="tipoOrder" name="tipoOrder" value="{{$tipoOrder}}">
            <input type="hidden" name="imgOrder" value="{{$imgOrder}}">
            <select class="orm-control js-example-basic-single" id="torneoId" name="torneoId" onchange="enviarForm()">
                @foreach($torneos as $torneo)

                    <option value="{{$torneo->id}}" @if($torneo->id==$torneoId)
                        selected

                        @endif />{{$torneo->nombre}} - {{$torneo->year}}</option>
                @endforeach

            </select>
            <input type="checkbox" class="orm-control" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="enviarForm()">

           <strong>Jugando</strong>
            </input>



        </form>
        <br>





    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Equipos</th>
        <th>Actual</th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'jugados','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'titulos','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Títulos @if($order=='titulos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'Goles','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'amarillas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Amarillas @if($order=='amarillas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'rojas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Rojas @if($order=='rojas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'recibidos','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" >Arq. Recibidos @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.jugadores', array('order'=>'invictas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Arq. V. Invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

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
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->jugador_id))}}" >{{$jugador->jugados}} </a></td>
                <td>{{$jugador->titulos}}</td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->jugador_id))}}" >{{$jugador->goles}}</a></td>
                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'tipo'=>'Amarillas'))}}" >{{$jugador->amarillas}}</a></td>

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'tipo'=>'Rojas'))}}" >{{$jugador->rojas}}</a></td>
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

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
    <script>


        function enviarForm() {
            $('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>

@endsection

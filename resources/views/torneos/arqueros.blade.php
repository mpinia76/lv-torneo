@extends('layouts.appPublic')

@section('pageTitle', 'Arqueros')

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
        <input type="checkbox" class="form-control" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="enviarForm()">

        <strong>Jugando</strong>
        </input>
            <nav class="navbar navbar-light float-right">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_jugador') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="button" onClick="enviarForm()">Buscar</button>
            </nav>


        </form>
        <br>

    <table class="table" style="width: 100%">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Actual</th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'jugados','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'recibidos','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Goles @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.arqueros', array('order'=>'invictas','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales, 'torneoId'=>$torneoId))}}" > Vallas invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

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
                    {{$jugador->jugador}} <img id="original" src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}"></td>

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

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $arqueros->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $arqueros->total() }}</strong>
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

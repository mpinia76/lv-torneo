@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">⚽ Goleadores</h1>


                <form class="form-inline mb-3 d-flex justify-content-between align-items-center" id="formulario">


                    <div class="d-flex align-items-center">
                        <!--<select class="form-control js-example-basic-single mr-3" id="torneoId" name="torneoId" onchange="enviarForm()">
                            @foreach($torneos as $torneo)
                                <option value="{{ $torneo->id }}" @if($torneo->id==$torneoId) selected @endif>
                                    {{ $torneo->nombre }} - {{ $torneo->year }}
                                </option>
                            @endforeach
                        </select>-->

                        <div class="form-check" style="margin-right: 20px;margin-left: 20px;">
                            <input type="checkbox" class="form-check-input" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="enviarForm()">
                            <label class="form-check-label" for="actuales">Jugando</label>
                        </div>
                    </div>



                    <div class="d-flex align-items-center">
                        <input type="search" name="buscarpor" class="form-control mr-2" placeholder="Buscar" value="{{ request('buscarpor', session('nombre_filtro_jugador')) }}">
                        <button class="btn btn-success" type="button" onclick="enviarForm()">Buscar</button>
                    </div>

                </form>

                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark">
        <th>#</th>
        <th>Jugador</th>
        <th>Actual</th>
        @php
            $columns = [
                'Goles' => 'Goles',
                'Jugada' => 'Jugada',
                'Cabeza' => 'Cabeza',
                'Penal' => 'Penal',
                'Tiro_Libre' => 'Tiro Libre',
            ];
        @endphp
        @foreach($columns as $key => $label)
            <th>
                <a href="{{ route('torneos.goleadores', [
                            'torneoId' => $torneoId,
                            'order' => $key,
                            'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC',
                            'actuales' => $actuales
                        ]) }}" class="text-decoration-none text-white">
                    {{ $label }}
                    @if($order==$key)
                        <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                    @endif
                </a>
            </th>
        @endforeach
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
                                ({{$escudoArr[2]}})
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
        </div>
    </div>
    <script>


        function enviarForm() {
            $('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>
@endsection

@extends('layouts.appPublic')

@section('pageTitle', 'Tecnicos')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">👨‍💼 Técnicos</h1>


                <form class="form-inline mb-3 d-flex justify-content-between align-items-center" id="formulario">


                    <div class="d-flex align-items-center">
                        <!--<select class="form-control js-example-basic-single mr-3" id="torneoId" name="torneoId" onchange="enviarForm()" >
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
                        <div class="form-check" >
                            <input type="checkbox" class="form-check-input" id="campeones" name="campeones" @if ($campeones == 1) checked @endif onchange="enviarForm()">
                            <label class="form-check-label" for="campeones">Campeones</label>
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
            <th>Técnico</th>
            <th>Actual</th>
            @php
                $columns = [
                    'puntaje' => 'Punt.',
                    'Jugados' => 'J',
                    'Ganados' => 'G',
                    'Empatados' => 'E',
                    'Perdidos' => 'P',
                    'golesl' => 'GF',
                    'golesv' => 'GC',
                    'diferencia' => 'Dif.',
                    'prom' => '%',
                ];
            @endphp
            @foreach($columns as $key => $label)
                <th>
                    <a href="{{ route('torneos.tecnicos', [
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

            <th>Títulos

                </th>
            <th>Equipos</th>
            </thead>
            <tbody>

            @foreach($goleadores as $tecnico)
                <tr>
                    <td>{{$i++}}</td>
                    <td>
                        <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnico->tecnico_id))}}" >
                            @if($tecnico->fotoTecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/'.$tecnico->fotoTecnico) }}" >
                            @else
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @endif
                        </a>
                        {{$tecnico->tecnico}} <img id="original" src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}" alt="{{ $tecnico->nacionalidadTecnico }}"></td>
                    <td>@if($tecnico->jugando)
                            @php
                                $escs = explode(',',$tecnico->jugando);
                            @endphp
                            @foreach($escs as $esc)

                                @if($esc!='')
                                    @php
                                        $escArr = explode('_',$esc);
                                    @endphp
                                    <a href="{{route('equipos.ver', array('equipoId' => $escArr[1]))}}">
                                        <img id="original" src="{{ url('images/'.$escArr[0]) }}" height="25" title="{{$escArr[2]}}" alt="{{$escArr[2]}}">
                                    </a>
                                @endif
                            @endforeach

                        @endif</td>
                    <td>{{$tecnico->puntaje}}</td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id))}}" >{{$tecnico->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Ganados'))}}" >{{$tecnico->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Empatados'))}}" >{{$tecnico->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Perdidos'))}}" >{{$tecnico->perdidos}}</a></td>
                    <td>{{$tecnico->golesl}}</td>
                    <td>{{$tecnico->golesv}}</td>
                    <td>{{$tecnico->diferencia}}</td>

                    <td>{{$tecnico->porcentaje}}</td>
                    <td>{{$tecnico->titulos}}</td>
                    <td>@if($tecnico->escudo)
                            @php
                                $escudos = explode(',',$tecnico->escudo);
                            @endphp
                            @foreach($escudos as $escudo)
                                @if($escudo!='')
                                    @php
                                        $escudoArr = explode('_',$escudo);
                                    @endphp
                                    <a href="{{route('equipos.ver', array('equipoId' => $escudoArr[1]))}}" >
                                        <img id="original" src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                    </a>
                                    ({{$escudoArr[2]}} pts.) ({{$escudoArr[3]}})
                                    @if(isset($escudoArr[4]) && $escudoArr[4] != '')
                                        <!-- Mostrar datos adicionales de $escudoArr[2] aquí -->
                                        {{  $escudoArr[4] }}
                                    @endif
                                    <br>
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
                {{ $goleadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $goleadores->total() }}</strong>
            </div>
        </div>
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

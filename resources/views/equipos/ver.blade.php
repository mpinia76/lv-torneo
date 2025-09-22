@extends('layouts.appPublic')

@section('pageTitle', 'Ver equipo')

<style>
    /* --- General --- */
    a {
        text-decoration: none;
        color: inherit;
    }
    a:hover {
        color: #007bff;
    }

    /* --- Tabs --- */
    .nav-tabs .nav-link {
        border: 1px solid transparent;
        border-radius: 0.375rem 0.375rem 0 0;
        margin-right: 4px;
        padding: 0.5rem 1rem;
        transition: 0.3s;
    }
    .nav-tabs .nav-link:hover {
        background-color: #e9f2ff;
    }
    .nav-tabs .nav-link.active {
        background-color: #007bff;
        color: #fff;
        border-color: #007bff #007bff #fff;
        font-weight: bold;
    }

    /* --- Tab content --- */
    .tab-content {
        margin-top: 20px;
        padding: 15px;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 0.375rem 0.375rem;
        background-color: #f8f9fa;
    }

    /* --- Tables --- */
    .table {
        background-color: #fff;
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
    }
    .table th, .table td {
        vertical-align: middle;
    }
    .table th {
        background-color: #e9f2ff;
    }
    .table tbody tr:hover {
        background-color: #f1f7ff;
    }

    /* --- Imágenes de jugadores y escudos --- */
    .imgCircle {
        border-radius: 50%;
        width: 40px;
        height: 40px;
        object-fit: cover;
        margin-right: 8px;
    }
    /*#original {
        max-width: 100%;
        height: auto;
    }*/

    /* --- Equipo info --- */
    dd img {
        margin-left: 5px;
        vertical-align: middle;
    }

    /* --- Botones --- */
    .btn-success {
        border-radius: 0.375rem;
    }
</style>

@section('content')
    <div class="container">

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <dt>Nombre</dt>
                        <dd>{{$equipo->nombre}} <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Socios</dt>
                        <dd>{{$equipo->socios}}</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Estadio</dt>
                        <dd>{{$equipo->estadio}}</dd>
                    </div>
                </div>
            </div>

            <div class="col-md-4 d-flex justify-content-center align-items-center">
                @if($equipo->escudo)
                    <img src="{{ url('images/'.$equipo->escudo) }}" style="width: 200px" class="img-fluid">
                @endif
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ request()->get('pestActiva') == 'jugadores' ? '' : 'active' }}" id="historia-tab" data-bs-toggle="tab" href="#historia" role="tab">Historia</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="titulos-tab" data-bs-toggle="tab" href="#titulos" role="tab">Títulos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tabla-tab" data-bs-toggle="tab" href="#tabla" role="tab">Torneos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->get('pestActiva') == 'jugadores' ? 'active' : '' }}" id="jugadores-tab" data-bs-toggle="tab" href="#jugadores" role="tab">Jugadores</a>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Historia -->
            <div class="tab-pane fade {{ request()->get('pestActiva') == 'jugadores' ? '' : 'show active' }}" id="historia" role="tabpanel">
                <dd>{!! nl2br(e($equipo->historia)) !!}</dd>
            </div>

            <!-- Títulos -->
            <div class="tab-pane fade" id="titulos" role="tabpanel">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Total</dt>
                        <dd>{{$titulosLiga + $titulosCopa+ $titulosInternacional}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Ligas nacionales</dt>
                        <dd>{{$titulosLiga}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Copas nacionales</dt>
                        <dd>{{$titulosCopa}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Internacionales</dt>
                        <dd>{{$titulosInternacional}}</dd>
                    </div>
                </div>

              <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                <thead class="table-dark">

                    <th>#</th>
                    <th>Torneo</th>
                    <th>Punt.</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>

                    <th>Prom.</th>

                    </thead>
                    <tbody>
                    @php
                        $j = 1;

                    @endphp
                    @foreach($torneosTitulos as $torneoTitulo)
                            <?php //dd($torneoTitulo);?>
                        <tr>
                            <td>{{$j++}}</td>
                            <td>@if($torneoTitulo->escudoTorneo)
                                    <img id="original" src="{{ url('images/'.$torneoTitulo->escudoTorneo) }}" height="25">
                                @endif {{$torneoTitulo->nombreTorneo}}</td>
                            <td>{{$torneoTitulo->porcentaje}}</td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo))}}" >{{$torneoTitulo->jugados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneoTitulo->ganados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneoTitulo->empatados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneoTitulo->perdidos}}</a></td>
                            <td>{{$torneoTitulo->favor}}</td>
                            <td>{{$torneoTitulo->contra}}</td>
                            <td>{{$torneoTitulo->favor - $torneoTitulo->contra}}</td>
                            <td>{{$torneoTitulo->puntaje}}</td>




                        </tr>
                    @endforeach

                    </tbody>
                </table>
            </div>

            <!-- Torneos -->
            <div class="tab-pane fade" id="tabla" role="tabpanel">
                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark">
                    <th>#</th>
                    <th>Torneo</th>
                    <th>Punt.</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>

                    <th>Prom.</th>
                    <th>PosiciÃ³n</th>
                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                        $totalJugados = 0;
                        $totalGanados = 0;
                        $totalEmpatados = 0;
                        $totalPerdidos = 0;
                        $totalFavor = 0;
                        $totalContra = 0;
                        $totalPuntaje = 0;
                    @endphp
                    @foreach($torneosEquipo as $torneo)
                        @php
                            $totalJugados += $torneo->jugados;
                            $totalGanados += $torneo->ganados;
                            $totalEmpatados += $torneo->empatados;
                            $totalPerdidos += $torneo->perdidos;
                            $totalFavor += $torneo->favor;
                            $totalContra += $torneo->contra;
                            $totalPuntaje += $torneo->puntaje;
                        @endphp
                        <tr>
                            <td>{{$i++}}</td>
                            <td>
                                @if($torneo->escudoTorneo)
                                    <img id="original" src="{{ url('images/'.$torneo->escudoTorneo) }}" height="25">
                                @endif
                                {{$torneo->nombreTorneo}}</td>
                            <td>{{$torneo->puntaje}}</td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneo->ganados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneo->empatados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneo->perdidos}}</a></td>
                            <td>{{$torneo->favor}}</td>
                            <td>{{$torneo->contra}}</td>
                            <td>{{$torneo->favor - $torneo->contra}}</td>

                            <td>{{$torneo->porcentaje}}</td>
                            <td>{!!$torneo->posicion!!}</td>


                        </tr>
                    @endforeach
                    <tr>
                        <td></td>

                        <td><strong>Totales</strong></td>
                        <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id))}}" >{{ $totalJugados}}</a></strong></td>
                        <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Ganados'))}}" >{{ $totalGanados}}</a></strong></td>
                        <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Empatados'))}}" >{{ $totalEmpatados}}</a></strong></td>
                        <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Perdidos'))}}" >{{ $totalPerdidos}}</a></strong></td>
                        <td><strong>{{ $totalFavor}}</strong></td>
                        <td><strong>{{ $totalContra}}</strong></td>
                        <td><strong>{{ $totalFavor-$totalContra}}</strong></td>
                        <td><strong>{{ $totalPuntaje}}</strong></td>
                        <td><strong>{{ ROUND(
                (
                $totalPuntaje
                * 100/($totalJugados*3) ),
                2
                )}}%</strong></td>

                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Jugadores -->
            <div class="tab-pane fade {{ request()->get('pestActiva') == 'jugadores' ? 'show active' : '' }}" id="jugadores" role="tabpanel">

                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Jugador</th>
                        @php
                            $columns = [
                                'jugados' => 'Jugados',
                                'titulos' => 'Títulos',
                                'Goles' => 'Goles',
                                'amarillas' => 'Amarillas',
                                'rojas' => 'Rojas',
                                'recibidos' => 'Arq. Recibidos',
                                'invictas' => 'Arq. V. Invictas',
                            ];
                        @endphp

                        @foreach($columns as $key => $label)
                            <th>
                                <a href="{{ route('equipos.ver', [
                        'equipoId' => $equipo->id,
                        'pestActiva' => 'jugadores',
                        'order' => $key,
                        'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC'
                    ]) }}" class="text-decoration-none text-white">
                                    {{ $label }}
                                    @if($order==$key)
                                        <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }} text-white"></i>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($jugadores as $jugador)
                        <tr>
                            <td>{{$iterator++}}</td>
                            <td class="d-flex align-items-center gap-2">
                                <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->jugador_id]) }}">
                                    <img class="imgCircle" src="{{ url('images/'.($jugador->foto ?? 'sin_foto.png')) }}" width="35" height="35" alt="Foto">
                                </a>
                                {{$jugador->jugador}}
                            </td>
                            <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->jugador_id]) }}">{{$jugador->jugados}}</a></td>
                            <td><a href="{{ route('jugadores.titulos', ['jugadorId' => $jugador->jugador_id]) }}">{{$jugador->titulos}}</a></td>
                            <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->jugador_id]) }}">{{$jugador->goles}}</a></td>
                            <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->jugador_id, 'tipo'=>'Amarilla']) }}">{{$jugador->amarillas}}</a></td>
                            <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->jugador_id, 'tipo'=>'Roja']) }}">{{$jugador->rojas}}</a></td>
                            <td>{{$jugador->recibidos}} (@if($jugador->jugados){{ round($jugador->recibidos / $jugador->jugados,2) }}@else 0 @endif)</td>
                            <td>{{$jugador->invictas}} (@if($jugador->jugados){{ round($jugador->invictas / $jugador->jugados,2) }}@else 0 @endif)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        {{ $jugadores->links() }}
                    </div>
                    <div>
                        <strong>Total: {{ $jugadores->total() }}</strong>
                    </div>
                </div>


            </div>
        </div>

        <div class="d-flex mt-3 mb-5">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>

    </div>
@endsection

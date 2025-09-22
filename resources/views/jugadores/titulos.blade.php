@extends('layouts.appPublic')

@section('pageTitle', 'Títulos')

@section('content')
    <div class="container">

        {{-- Info del jugador --}}
        <div class="row mb-4">
            <div class="col-md-3 text-center">
                @if($jugador->persona->foto)
                    <img src="{{ url('images/'.$jugador->persona->foto) }}" class="img-fluid rounded shadow" height="200">
                @else
                    <img src="{{ url('images/sin_foto.png') }}" class="img-fluid rounded shadow" height="200">
                @endif
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-3"><dt>Nombre</dt><dd>{{ $jugador->persona->nombre }}</dd></div>
                    <div class="col-md-3"><dt>Apellido</dt><dd>{{ $jugador->persona->apellido }}</dd></div>
                    <div class="col-md-3"><dt>Ciudad Nacimiento</dt><dd>{{ $jugador->persona->ciudad }}</dd></div>
                    <div class="col-md-3">
                        <dt>Edad</dt>
                        {!! ($jugador->persona->fallecimiento) ? '<img src="'.url('images/death.png').'" height="20">' : '' !!}
                        <dd>{{ ($jugador->persona->nacimiento)?$jugador->persona->getAgeAttribute():'' }}</dd>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3"><dt>Posición</dt><dd>{{ $jugador->tipoJugador }}</dd></div>
                    <div class="col-md-3"><dt>Altura</dt><dd>{{ $jugador->persona->altura }} m.</dd></div>
                    <div class="col-md-3"><dt>Peso</dt><dd>{{ $jugador->persona->peso }} kg.</dd></div>
                </div>
            </div>
        </div>

        {{-- Observaciones --}}
        @if($jugador->persona->observaciones)
            <div class="row mb-4">
                <div class="col"><dd>{{ $jugador->persona->observaciones }}</dd></div>
            </div>
        @endif

        {{-- Estadísticas Jugador --}}
        <h1 class="display-6 mb-3">Jugador</h1>
        <div class="row text-center mb-3">
            <div class="col-md-3"><dt>Títulos</dt><dd>{{ $titulosJugadorLiga + $titulosJugadorCopa + $titulosJugadorInternacional }}</dd></div>
            <div class="col-md-3"><dt>Ligas nacionales</dt><dd>{{ $titulosJugadorLiga }}</dd></div>
            <div class="col-md-3"><dt>Copas nacionales</dt><dd>{{ $titulosJugadorCopa }}</dd></div>
            <div class="col-md-3"><dt>Internacionales</dt><dd>{{ $titulosJugadorInternacional }}</dd></div>
        </div>

        <table class="table table-striped align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>Torneo</th>
                <th>Equipos</th>
                <th>Jugados</th>
                <th>Goles</th>
                <th>Amarillas</th>
                <th>Rojas</th>
                <th>Arq. Recibidos</th>
                <th>Arq. V. Invictas</th>
            </tr>
            </thead>
            <tbody>
            @php
                $i = 1;
                $totalJugados = $totalGoles = $totalAmarillas = $totalRojas = $totalRecibidos = $totalInvictas = 0;
            @endphp
            @foreach($torneosJugador as $torneo)
                @php

                    $totalJugados += $torneo->jugados;
                    $totalGoles += $torneo->goles;
                    $totalAmarillas += $torneo->amarillas;
                    $totalRojas += $torneo->rojas;
                    $totalRecibidos += $torneo->recibidos ?? 0;
                    $totalInvictas += $torneo->invictas ?? 0;
                    $jugo = $torneo->jugados > 0;
                @endphp
                <tr>
                    <td>{{ $i++ }}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            @if($torneo->escudoTorneo)
                                <img src="{{ url('images/'.$torneo->escudoTorneo) }}" alt="{{ $torneo->nombreTorneo }}" height="25" class="me-2 rounded shadow-sm">
                            @endif
                            <span class="fw-semibold">{{ $torneo->nombreTorneo }}</span>
                        </div>
                    </td>
                    <td>
                        @if($torneo->escudo)
                            @php $escudos = explode(',',$torneo->escudo); @endphp
                            @foreach($escudos as $escudo)
                                @if($escudo!='')
                                    @php $escudoArr = explode('_',$escudo); @endphp
                                    <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}" class="me-2">
                                        <img src="{{ url('images/'.$escudoArr[0]) }}" height="25" class="rounded shadow-sm">
                                        @if(isset($escudoArr[2]) && $escudoArr[2] != '')
                                            <small class="text-muted">Pos: {!! $escudoArr[2] !!}</small>
                                        @endif
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    </td>
                    <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo]) }}">{{ $torneo->jugados }}</a></td>
                    <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo]) }}">{{ $torneo->goles }} ({{ $jugo ? round($torneo->goles / $torneo->jugados,2) : 0 }})</a></td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Amarillas']) }}">{{ $torneo->amarillas }} ({{ $jugo ? round($torneo->amarillas / $torneo->jugados,2) : 0 }})</a></td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Rojas']) }}">{{ $torneo->rojas }} ({{ $jugo ? round($torneo->rojas / $torneo->jugados,2) : 0 }})</a></td>
                    <td>{{ $torneo->recibidos ?? 0 }} ({{ $torneo->jugados > 0 ? round(($torneo->recibidos ?? 0) / $torneo->jugados, 2) : 0 }})</td>
                    <td>{{ $torneo->invictas ?? 0 }} ({{ $torneo->jugados > 0 ? round(($torneo->invictas ?? 0) / $torneo->jugados, 2) : 0 }})</td>

                </tr>
            @endforeach
            {{-- Totales --}}
            <tr class="fw-bold">
                <td></td>
                <td></td>
                <td>Totales</td>
                <td><a href="{{ route('jugadores.jugados',['jugadorId' => $jugador->id]) }}">{{ $totalJugados }}</a></td>
                <td><a href="{{ route('jugadores.goles',['jugadorId' => $jugador->id]) }}">{{ $totalGoles }} ({{ $totalJugados ? round($totalGoles/$totalJugados,2) : 0 }})</a></td>
                <td><a href="{{ route('jugadores.tarjetas',['jugadorId' => $jugador->id,'tipo'=>'Amarillas']) }}">{{ $totalAmarillas }} ({{ $totalJugados ? round($totalAmarillas/$totalJugados,2) : 0 }})</a></td>
                <td><a href="{{ route('jugadores.tarjetas',['jugadorId' => $jugador->id,'tipo'=>'Rojas']) }}">{{ $totalRojas }} ({{ $totalJugados ? round($totalRojas/$totalJugados,2) : 0 }})</a></td>
                <td>{{ $totalRecibidos }} ({{ $totalJugados ? round($totalRecibidos/$totalJugados,2) : 0 }})</td>
                <td>{{ $totalInvictas }} ({{ $totalJugados ? round($totalInvictas/$totalJugados,2) : 0 }})</td>
            </tr>
            </tbody>
        </table>

        {{-- Técnico --}}
        @if(count($torneosTecnico)>0)
            <h1 class="display-6 mb-3">Técnico</h1>
            <div class="row text-center mb-3">
                <div class="col-md-3"><dt>Títulos</dt><dd>{{ $titulosTecnicoLiga + $titulosTecnicoCopa + $titulosTecnicoInternacional }}</dd></div>
                <div class="col-md-3"><dt>Ligas nacionales</dt><dd>{{ $titulosTecnicoLiga }}</dd></div>
                <div class="col-md-3"><dt>Copas nacionales</dt><dd>{{ $titulosTecnicoCopa }}</dd></div>
                <div class="col-md-3"><dt>Internacionales</dt><dd>{{ $titulosTecnicoInternacional }}</dd></div>
            </div>

            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Torneo</th>
                    <th>Equipos</th>
                    <th>Punt.</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                    <th>%</th>
                </tr>
                </thead>
                <tbody>
                @php
                    $i = 1;
                    $totalJugados = $totalGanados = $totalEmpatados = $totalPerdidos = $totalFavor = $totalContra = $totalPuntaje = 0;
                @endphp
                @foreach($torneosTecnico as $torneo)
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
                        <td>{{ $i++ }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                @if($torneo->escudoTorneo)
                                    <img src="{{ url('images/'.$torneo->escudoTorneo) }}" alt="{{ $torneo->nombreTorneo }}" height="25" class="me-2 rounded shadow-sm">
                                @endif
                                <span class="fw-semibold">{{ $torneo->nombreTorneo }}</span>
                            </div>
                        </td>
                        <td>
                            @if($torneo->escudo)
                                @php $escudos = explode(',',$torneo->escudo); @endphp
                                @foreach($escudos as $escudo)
                                    @if($escudo!='')
                                        @php $escudoArr = explode('_',$escudo); @endphp
                                        <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}" class="me-2">
                                            <img src="{{ url('images/'.$escudoArr[0]) }}" height="25" class="rounded shadow-sm">
                                            @if(isset($escudoArr[2]) && $escudoArr[2] != '')
                                                <small class="text-muted">Pos: {!! $escudoArr[2] !!}</small>
                                            @endif
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $torneo->puntaje }}</td>
                        <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo]) }}">{{ $torneo->jugados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Ganados']) }}">{{ $torneo->ganados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Empatados']) }}">{{ $torneo->empatados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Perdidos']) }}">{{ $torneo->perdidos }}</a></td>
                        <td>{{ $torneo->favor }}</td>
                        <td>{{ $torneo->contra }}</td>
                        <td>{{ $torneo->favor - $torneo->contra }}</td>
                        <td>{{ $torneo->porcentaje }}</td>
                    </tr>
                @endforeach
                {{-- Totales --}}
                <tr class="fw-bold">
                    <td></td>
                    <td></td>
                    <td>Totales</td>
                    <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico]) }}">{{ $totalJugados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Ganados']) }}">{{ $totalGanados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Empatados']) }}">{{ $totalEmpatados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados',['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Perdidos']) }}">{{ $totalPerdidos }}</a></td>
                    <td>{{ $totalFavor }}</td>
                    <td>{{ $totalContra }}</td>
                    <td>{{ $totalFavor - $totalContra }}</td>
                    <td>{{ $totalPuntaje }}</td>
                    <td>{{ round(($totalPuntaje * 100 / ($totalJugados*3)),2) }}%</td>
                </tr>
                </tbody>
            </table>
        @endif

        {{-- Volver --}}
        <div class="d-flex mt-4">
            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

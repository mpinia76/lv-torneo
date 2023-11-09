@extends('layouts.appPublic')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container">

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-4">
                        <div class="form-group">

                            @if($jugador->persona->foto)
                                <img id="original" src="{{ url('images/'.$jugador->persona->foto) }}" height="200">
                            @else
                                <img id="original" src="{{ url('images/sin_foto.png') }}" height="200">
                            @endif


                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Nombre</dt>
                        <dd>{{$jugador->persona->nombre}}</dd>
                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Apellido</dt>
                        <dd>{{$jugador->persona->apellido}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Ciudad Nacimiento</dt>
                        <dd>{{$jugador->persona->ciudad}}</dd>

                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Edad</dt>
                        <dd>{{($jugador->persona->nacimiento)?$jugador->persona->getAgeAttribute():''}}</dd>

                    </div>
                </div>

                <div class="row">


                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Posición</dt>
                        <dd>{{$jugador->tipoJugador}}</dd>


                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Altura</dt>
                        <dd>{{$jugador->persona->altura}} m.</dd>

                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Peso</dt>
                        <dd>{{$jugador->persona->peso}} kg.</dd>

                    </div>
                </div>
            </div>

        </div>

        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$jugador->persona->observaciones}}</dd>

            </div>

        </div>
        <h1 class="display-6">Jugador</h1>
        <table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>Equipos</th>
            <th>Jugados</th>
            <th>Goles</th>

            <th>Amarillas</th>

            <th>Rojas</th>

            <th>Arq. Recibidos</th>
            <th>Arq. V. Invictas</th>

            </thead>
            <tbody>


            @php
                $i = 1;
                $totalJugados = 0;
                $totalGoles = 0;
                $totalAmarillas = 0;
                $totalRojas = 0;
                $totalRecibidos = 0;
                $totalInvictas = 0;
            @endphp
            @foreach($torneosJugador as $torneo)
                @php
                    $totalJugados += $torneo->jugados;
                    $totalGoles += $torneo->goles;
                    $totalAmarillas += $torneo->amarillas;
                    $totalRojas += $torneo->rojas;
                    $totalRecibidos += $torneo->recibidos;
                    $totalInvictas += $torneo->invictas;
                    $jugo = 0;
                    if($torneo->jugados>0){
                        $jugo=1;
                    }


                @endphp
                <tr>
                    <td>{{$i++}}</td>
                    <td>{{$torneo->nombreTorneo}}</td>
                    <td>@if($torneo->escudo)
                            @php
                                $escudos = explode(',',$torneo->escudo);
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
                    <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                    <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->goles}}
                        @if($jugo)
                            ({{round($torneo->goles / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                        </a>
                    </td>
                    <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Amarillas'))}}" >{{$torneo->amarillas}}
                        @if($jugo)
                            ({{round($torneo->amarillas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                        </a>
                    </td>
                    <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Rojas'))}}" >{{$torneo->rojas}}
                        @if($jugo)
                            ({{round($torneo->rojas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                        </a>
                    </td>
                    <td>{{$torneo->recibidos}}
                        @if($jugo)
                            ({{round($torneo->recibidos / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                    </td>
                    <td>{{$torneo->invictas}}
                        @if($jugo)
                            ({{round($torneo->invictas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                    </td>
                </tr>

            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Totales</strong></td>
                <td><strong><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->id))}}" >{{ $totalJugados}}</a></strong></td>
                <td><strong><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->id))}}" >{{ $totalGoles}} ({{($totalJugados)?round($totalGoles / $totalJugados,2):0}})</a></strong></td>
                <td><strong><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Amarillas'))}}" >{{ $totalAmarillas}} ({{($totalJugados)?round($totalAmarillas / $totalJugados,2):0}})</a></strong></td>
                <td><strong><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->id,'tipo'=>'Rojas'))}}" >{{ $totalRojas}} ({{($totalJugados)?round($totalRojas / $totalJugados,2):0}})</a></strong></td>
                <td><strong>{{ $totalRecibidos}} ({{($totalJugados)?round($totalRecibidos / $totalJugados,2):0}})</strong></td>
                <td><strong>{{ $totalInvictas}} ({{($totalJugados)?round($totalInvictas / $totalJugados,2):0}})</strong></td>
            </tr>
            </tbody>
        </table>
        @if(count($torneosTecnico)>0)
            <h1 class="display-6">Técnico</h1>
        <table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>Equipos</th>

            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            <th>Punt.</th>
            <th>%</th>

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
                    <td>{{$i++}}</td>
                    <td>{{$torneo->nombreTorneo}}</td>
                    <td>@if($torneo->escudo)
                            @php
                                $escudos = explode(',',$torneo->escudo);
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
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'torneoId' => $torneo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneo->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'torneoId' => $torneo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneo->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'torneoId' => $torneo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneo->perdidos}}</a></td>
                    <td>{{$torneo->favor}}</td>
                    <td>{{$torneo->contra}}</td>
                    <td>{{$torneo->favor - $torneo->contra}}</td>
                    <td>{{$torneo->puntaje}}</td>
                    <td>{{$torneo->porcentaje}}</td>
                </tr>

            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Totales</strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico))}}" >{{ $totalJugados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'tipo'=>'Ganados'))}}" >{{ $totalGanados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'tipo'=>'Empatados'))}}" >{{ $totalEmpatados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $torneo->idTecnico,'tipo'=>'Perdidos'))}}" >{{ $totalPerdidos}}</a></strong></td>
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
        @endif
    <div class="d-flex">

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>

@endsection

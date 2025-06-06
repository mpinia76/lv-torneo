@extends('layouts.appPublic')

@section('pageTitle', 'Ver tecnico')
<style>
    /* Estilos personalizados para resaltar la pestaña activa */
    .nav-link.active {
        background-color: #007bff; /* Cambia el color de fondo de la pestaña activa */
        color: #fff; /* Cambia el color del texto de la pestaña activa */
        border-color: #007bff; /* Cambia el color del borde de la pestaña activa */
    }

    /* Agrega un espacio entre las pestañas y el contenido */
    .tab-content {
        margin: 20px; /* Ajusta el margen superior del contenido */
    }
</style>
@section('content')
    <div class="container">


        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <div class="form-group col-xs-12 col-sm-6 col-md-4">
                    <div class="form-group">

                        @if($tecnico->persona->foto)
                            <img id="original" src="{{ url('images/'.$tecnico->persona->foto) }}" height="200">
                        @else
                            <img id="original" src="{{ url('images/sin_foto_tecnico.png') }}" height="200">
                        @endif


                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Nombre</dt>
                    <dd>{{$tecnico->persona->name}}</dd>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Completo</dt>
                    <dd>{{$tecnico->persona->nombre}} {{$tecnico->persona->apellido}}</dd>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Ciudad Nacimiento</dt>
                    <dd>{{$tecnico->persona->ciudad}}</dd>

                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Edad</dt>
                    {!! ($tecnico->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!}
                    <dd>{{($tecnico->persona->nacimiento)?$tecnico->persona->getAgeAttribute():''}}</dd>

                </div>

            </div>

        </div>

        <div class="row">
            <div style="text-align: center;" class="form-group col-xs-12 col-sm-6 col-md-2">
                <img id="original" src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}">

            </div>
        </div>
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$tecnico->persona->observaciones}}</dd>

            </div>

        </div>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tecnico-tab" data-toggle="tab" href="#tecnico" role="tab" aria-controls="tecnico" aria-selected="true">Técnico</a>
            </li>
            @if(count($torneosJugador)>0)
                <li class="nav-item">
                    <a class="nav-link" id="jugador-tab" data-toggle="tab" href="#jugador" role="tab" aria-controls="jugador" aria-selected="false">Jugador</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="tecnico">
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Titulos</dt>
                <dd>{{$titulosTecnicoLiga+$titulosTecnicoCopa+$titulosTecnicoInternacional}}</dd>
            </div>


            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Ligas nacionales</dt>
                <dd>{{$titulosTecnicoLiga}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Copas nacionales</dt>
                <dd>{{$titulosTecnicoCopa}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Internacionales</dt>
                <dd>{{$titulosTecnicoInternacional}}</dd>
            </div>

        </div>
        <table class="table" style="font-size: 14px;">
            <thead>
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
                    <td>@if($torneo->escudoTorneo)
                            <img id="original" src="{{ url('images/'.$torneo->escudoTorneo) }}" height="25">
                        @endif {{$torneo->nombreTorneo}}</td>
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
                                        @if(isset($escudoArr[2]) && $escudoArr[2] != '')
                                            <!-- Mostrar datos adicionales de $escudoArr[2] aquí -->
                                            Pos: {!!  $escudoArr[2] !!}
                                        @endif
                                    </a>
                                @endif
                            @endforeach
                        @endif

                    </td>
                    <td>{{$torneo->puntaje}}</td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneo->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneo->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'torneoId' => $torneo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneo->perdidos}}</a></td>
                    <td>{{$torneo->favor}}</td>
                    <td>{{$torneo->contra}}</td>
                    <td>{{$torneo->favor - $torneo->contra}}</td>

                    <td>{{$torneo->porcentaje}}</td>
                </tr>

            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Totales</strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id))}}" >{{ $totalJugados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'tipo'=>'Ganados'))}}" >{{ $totalGanados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'tipo'=>'Ganados'))}}" >{{ $totalEmpatados}}</a></strong></td>
                <td><strong><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->id,'tipo'=>'Ganados'))}}" >{{ $totalPerdidos}}</a></strong></td>
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
        @if(count($torneosJugador)>0)
                <div role="tabpanel" class="tab-pane" id="jugador">
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Titulos</dt>
                    <dd>{{$titulosJugadorLiga+$titulosJugadorCopa}}</dd>
                </div>


                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Titulos Liga</dt>
                    <dd>{{$titulosJugadorLiga}}</dd>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Titulos Copa</dt>
                    <dd>{{$titulosJugadorCopa}}</dd>
                </div>

            </div>
        <table class="table" style="font-size: 14px;">
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
                    <td>@if($torneo->escudoTorneo)
                            <img id="original" src="{{ url('images/'.$torneo->escudoTorneo) }}" height="25">
                        @endif {{$torneo->nombreTorneo}}</td>
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
                                        @if(isset($escudoArr[2]) && $escudoArr[2] != '')
                                            <!-- Mostrar datos adicionales de $escudoArr[2] aquí -->
                                            Pos: {!!  $escudoArr[2] !!}
                                        @endif
                                    </a>

                                @endif
                            @endforeach
                        @endif

                    </td>
                    <td><a href="{{route('jugadores.jugados', array('jugadorId' => $torneo->idJugador,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                    <td><a href="{{route('jugadores.goles', array('jugadorId' => $torneo->idJugador,'torneoId' => $torneo->idTorneo))}}" >{{$torneo->goles}}
                        @if($jugo)
                            ({{round($torneo->goles / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                        </a>
                    </td>
                    <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $torneo->idJugador,'torneoId' => $torneo->idTorneo,'tipo'=>'Amarillas'))}}" >{{$torneo->amarillas}}
                        @if($jugo)
                            ({{round($torneo->amarillas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                        </a>
                    </td>
                    <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $torneo->idJugador,'torneoId' => $torneo->idTorneo,'tipo'=>'Rojas'))}}" >{{$torneo->rojas}}
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
                <td><strong><a href="{{route('jugadores.jugados', array('jugadorId' => $torneo->idJugador))}}" >{{ $totalJugados}}</a></strong></td>
                <td><strong><a href="{{route('jugadores.goles', array('jugadorId' => $torneo->idJugador))}}" >{{ $totalGoles}} ({{round($totalGoles / $totalJugados,2)}})</a></strong></td>
                <td><strong><a href="{{route('jugadores.tarjetas', array('jugadorId' => $torneo->idJugador,'tipo'=>'Amarillas'))}}" >{{ $totalAmarillas}} ({{round($totalAmarillas / $totalJugados,2)}})</a></strong></td>
                <td><strong><a href="{{route('jugadores.tarjetas', array('jugadorId' => $torneo->idJugador,'tipo'=>'Rojas'))}}" >{{ $totalRojas}} ({{round($totalRojas / $totalJugados,2)}})</a></strong></td>
                <td><strong>{{ $totalRecibidos}} ({{round($totalRecibidos / $totalJugados,2)}})</strong></td>
                <td><strong>{{ $totalInvictas}} ({{round($totalInvictas / $totalJugados,2)}})</strong></td>
            </tr>
            </tbody>
        </table>
                </div>
        @endif
        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

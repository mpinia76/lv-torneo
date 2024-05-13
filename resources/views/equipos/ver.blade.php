@extends('layouts.appPublic')

@section('pageTitle', 'Ver equipo')
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
            <!-- Div para los datos del equipo -->
            <div class="col-xs-12 col-sm-6 col-md-8">
                <div class="row">
                    <div class="form-group col-md-6">
                        <dt>Nombre</dt>
                        <dd>{{$equipo->nombre}}</dd>
                    </div>

                    <div class="form-group col-md-6">
                        <dt>Socios</dt>
                        <dd>{{$equipo->socios}}</dd>
                    </div>

                    <div class="form-group col-md-6">
                        <dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>
                    </div>

                    <div class="form-group col-md-6">
                        <dt>Estadio</dt>
                        <dd>{{$equipo->estadio}}</dd>
                    </div>
                </div>
            </div>

            <!-- Div para el escudo del equipo -->
            <div class="col-xs-12 col-sm-6 col-md-4 d-flex justify-content-center align-items-center">
                <div class="form-group">
                    @if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                    @endif
                </div>
            </div>
        </div>




        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ request()->get('pestActiva') == 'jugadores' ? '' : 'active' }}" id="historia-tab" data-toggle="tab" href="#historia" role="tab" aria-controls="historia" aria-selected="{{ request()->get('pestActiva') == 'jugadores' ? 'false' : 'true' }}">Historia</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="titulos-tab" data-toggle="tab" href="#titulos" role="tab" aria-controls="titulos" aria-selected="false">Títulos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tabla-tab" data-toggle="tab" href="#tabla" role="tab" aria-controls="tabla" aria-selected="false">Torneos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->get('pestActiva') == 'jugadores' ? 'active' : '' }}" id="jugadores-tab" data-toggle="tab" href="#jugadores" role="tab" aria-controls="jugadores" aria-selected="{{ request()->get('pestActiva') == 'jugadores' ? 'true' : 'false' }}">Jugadores</a>
            </li>
        </ul>


        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane {{ request()->get('pestActiva') == 'jugadores' ? '' : 'active' }}" id="historia">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-12">

                        <dd>{{$equipo->historia}}</dd>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="titulos">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Total</dt>
                        <dd>{{$titulosLiga + $titulosCopa}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Títulos Liga</dt>
                        <dd>{{$titulosLiga}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Títulos Copa</dt>
                        <dd>{{$titulosCopa}}</dd>
                    </div>
                </div>
                <table class="table" style="font-size: 14px;">
                    <thead>
                    <th>#</th>
                    <th>Torneo</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                    <th>Punt.</th>
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
                            <td>{{$torneoTitulo->nombreTorneo}}</td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo))}}" >{{$torneoTitulo->jugados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneoTitulo->ganados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneoTitulo->empatados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneoTitulo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneoTitulo->perdidos}}</a></td>
                            <td>{{$torneoTitulo->favor}}</td>
                            <td>{{$torneoTitulo->contra}}</td>
                            <td>{{$torneoTitulo->favor - $torneoTitulo->contra}}</td>
                            <td>{{$torneoTitulo->puntaje}}</td>
                            <td>{{$torneoTitulo->porcentaje}}</td>



                        </tr>
                    @endforeach

                    </tbody>
                </table>
            </div>
            <div role="tabpanel" class="tab-pane" id="tabla">
                <table class="table" style="font-size: 14px;">
                    <thead>
                    <th>#</th>
                    <th>Torneo</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                    <th>Punt.</th>
                    <th>Prom.</th>
                    <th>Posición</th>
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
                            <td>{{$torneo->nombreTorneo}}</td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneo->ganados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneo->empatados}}</a></td>
                            <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneo->perdidos}}</a></td>
                            <td>{{$torneo->favor}}</td>
                            <td>{{$torneo->contra}}</td>
                            <td>{{$torneo->favor - $torneo->contra}}</td>
                            <td>{{$torneo->puntaje}}</td>
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
            <div role="tabpanel" class="tab-pane {{ request()->get('pestActiva') == 'jugadores' ? 'active' : '' }}" id="jugadores">
                @php
                    $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
                    $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

                @endphp
                <table class="table" style="font-size: 14px;">


                    <thead>
                    <th>#</th>
                    <th>Jugador</th>


                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'jugados','tipoOrder'=>$tipoOrder))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'titulos','tipoOrder'=>$tipoOrder))}}" > Títulos @if($order=='titulos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'Goles','tipoOrder'=>$tipoOrder))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'amarillas','tipoOrder'=>$tipoOrder))}}" > Amarillas @if($order=='amarillas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'rojas','tipoOrder'=>$tipoOrder))}}" > Rojas @if($order=='rojas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'recibidos','tipoOrder'=>$tipoOrder))}}" >Arq. Recibidos @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
                    <th><a href="{{route('equipos.ver', array('equipoId' => $equipo->id,'pestActiva'=>'jugadores','order'=>'invictas','tipoOrder'=>$tipoOrder))}}" > Arq. V. Invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

                    </thead>
                    <tbody>

                    @foreach($jugadores as $jugador)
                        <tr>
                            <td>{{$iterator++}}</td>
                            <td>
                                <a href="{{route('jugadores.ver', array('jugadorId' => $jugador->jugador_id))}}" >
                                    @if($jugador->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                </a>
                                {{$jugador->jugador}}</td>


                            <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->jugador_id))}}" >{{$jugador->jugados}} </a></td>
                            <td><a href="{{route('jugadores.titulos', array('jugadorId' => $jugador->jugador_id))}}" >{{$jugador->titulos}}</a></td>
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
            </div>
        </div>







        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection




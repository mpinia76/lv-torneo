@extends('layouts.appPublic')

@section('pageTitle', 'Detalle Fecha')

@section('content')
    <div class="container">

        <h1 class="display-6">Fecha {{$partido->fecha->numero}} </h1>


        <div class="row">

            <div class="form-group col-md-12">

                <table class="table">
                    <thead>
                    <th>Fecha</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>

                    <tr>
                        <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                        <td>
                            <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol->id))}}" >
                            @if($partido->equipol)
                                @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                @endif
                            </a>
                                {{$partido->equipol->nombre}}
                            @endif
                        </td>
                        <td>{{$partido->golesl}}
                            @if($partido->penalesl)
                                ({{$partido->penalesl}})
                            @endif
                        </td>
                        <td>{{$partido->golesv}}
                            @if($partido->penalesv)
                                ({{$partido->penalesv}})
                            @endif
                        </td>
                        <td>
                            <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov->id))}}" >
                            @if($partido->equipov)
                                @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                @endif
                            </a>
                                {{$partido->equipov->nombre}}
                            @endif
                        </td>

                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Titulares</td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            <table class="table">

                                @foreach($titularesL ?? '' as $titularl)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $titularl->jugador->id))}}" >
                                    <span style="font-weight: bold">{{$titularl->dorsal}}</span>
                                    @if($titularl->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$titularl->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                            </a>
                                    <span style="font-weight: bold"> {{ $titularl->jugador->persona->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach


                            </table>
                        </td>
                        <td colspan="2" style="font-weight: bold"></td>
                        <td>
                            <table class="table">

                                @foreach($titularesV ?? '' as $titularv)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $titularv->jugador->id))}}" >
                                            <span style="font-weight: bold">{{$titularv->dorsal}}</span>
                                            @if($titularv->jugador->persona->foto)
                                                <img id="original" class="imgCircle" src="{{ url('images/'.$titularv->jugador->persona->foto) }}" >
                                            @else
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                            @endif
                                            </a>
                                            <span style="font-weight: bold"> {{ $titularv->jugador->persona->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach


                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Suplentes</td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            <table class="table">


                                @foreach($suplentesL ?? '' as $suplentel)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $suplentel->jugador->id))}}" >
                                                <span style="font-weight: bold">{{$suplentel->dorsal}}</span>
                                                @if($suplentel->jugador->persona->foto)
                                                    <img id="original" class="imgCircle" src="{{ url('images/'.$suplentel->jugador->persona->foto) }}" >
                                                @else
                                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                                @endif</a>
                                            <span style="font-weight: bold"> {{ $suplentel->jugador->persona->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach

                            </table>
                        </td>
                        <td colspan="2" style="font-weight: bold"></td>
                        <td>
                            <table class="table">


                                @foreach($suplentesV ?? '' as $suplentev)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $suplentev->jugador->id))}}" >
                                                <span style="font-weight: bold">{{$suplentev->dorsal}}</span>
                                                @if($suplentev->jugador->persona->foto)
                                                    <img id="original" class="imgCircle" src="{{ url('images/'.$suplentev->jugador->persona->foto) }}" >
                                                @else
                                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                                @endif
                                            </a>
                                            <span style="font-weight: bold"> {{ $suplentev->jugador->persona->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach

                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Técnicos</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                    <table class="table">
                        @foreach($tecnicosL ?? '' as $tecnicol)
                            <tr>

                                <td>
                                    <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnicol->tecnico->id))}}" >

                                        @if($tecnicol->tecnico->persona->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$tecnicol->tecnico->persona->foto) }}" >
                                        @else
                                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                        @endif</a>
                                    <span style="font-weight: bold"> {{ $tecnicol->tecnico->persona->full_name}}</span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                        </td>
                        <td colspan="2" style="font-weight: bold"></td>
                        <td>
                    <table class="table">
                        @foreach($tecnicosV ?? '' as $tecnicov)
                            <tr>

                                <td>
                                    <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnicov->tecnico->id))}}" >

                                        @if($tecnicov->tecnico->persona->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$tecnicov->tecnico->persona->foto) }}" >
                                        @else
                                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                        @endif</a>
                                    <span style="font-weight: bold"> {{ $tecnicov->tecnico->persona->full_name}}</span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                        </td></tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Jueces</td>
                    </tr>

                    @foreach($arbitros ?? '' as $arbitro)
                        <tr>
                            <td></td>
                            <td></td>
                            <td colspan="3" >
                                <a href="{{route('arbitros.ver', array('arbitroId' => $arbitro->arbitro->id))}}" >

                                    @if($arbitro->arbitro->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$arbitro->arbitro->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                                    @endif</a>
                                <span style="font-weight: bold"> {{ $arbitro->arbitro->persona->full_name}}</span> {{ $arbitro->tipo}}
                            </td>
                        </tr>

                    @endforeach
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Goles</td>
                    </tr>

                    @foreach($goles ?? '' as $gol)
                        @php
                        $esLocal = array_search($gol->jugador->id, array_column(array_merge($titularesL->toArray(), $suplentesL->toArray()), 'jugador_id'));
                        $esVisitante = array_search($gol->jugador->id, array_column(array_merge($titularesV->toArray(), $suplentesV->toArray()), 'jugador_id'));

                        @endphp
                        @if($gol->tipo == 'En Contra')
                            @if($esLocal !== false)
                                @php
                                    $esVisitante = true;
                                    $esLocal = false;
                                @endphp
                            @else
                                @php
                                    $esLocal = true;
                                    $esVisitante = false;
                                @endphp
                            @endif
                        @endif
                            <tr>

                            <td>


                            </td>
                            <td>
                                @if($esLocal !== false)

                                    @if($gol->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$gol->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                        <span style="font-weight: bold"> {{ $gol->jugador->persona->full_name}}</span>  {{$gol->tipo}}

                                @endif
                            </td>
                            <td colspan="2">
                                <span style="color: green"> {{$gol->minuto}}'</span>
                            </td>

                            <td >
                                @if($esVisitante !== false)

                                    @if($gol->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$gol->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                         <span style="font-weight: bold"> {{ $gol->jugador->persona->full_name}}</span>  {{$gol->tipo}}
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Tarjetas</td>
                    </tr>

                    @foreach($tarjetas ?? '' as $tarjeta)
                        @php
                            $esLocal = array_search($tarjeta->jugador->id, array_column(array_merge($titularesL->toArray(), $suplentesL->toArray()), 'jugador_id'));
                            $esVisitante = array_search($tarjeta->jugador->id, array_column(array_merge($titularesV->toArray(), $suplentesV->toArray()), 'jugador_id'));
                        @endphp
                        <tr>

                            <td>


                            </td>
                            <td>
                                @if($esLocal !== false)
                                    @if($tarjeta->tipo == 'Amarilla')
                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Doble Amarilla')
                                        <img id="original" src="{{ url('images/doble_amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Roja')
                                        <img id="original" src="{{ url('images/roja.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$tarjeta->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                    <span style="font-weight: bold"> {{ $tarjeta->jugador->persona->full_name}}</span>

                                @endif
                            </td>
                            <td colspan="2">
                                <span style="color: green"> {{$tarjeta->minuto}}'</span>
                            </td>

                            <td >
                                @if($esVisitante !== false)
                                    @if($tarjeta->tipo == 'Amarilla')
                                        <img id="original" src="{{ url('images/amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Doble Amarilla')
                                        <img id="original" src="{{ url('images/doble_amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Roja')
                                        <img id="original" src="{{ url('images/roja.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$tarjeta->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                    <span style="font-weight: bold"> {{ $tarjeta->jugador->persona->full_name}}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Cambios</td>
                    </tr>

                    @foreach($cambios ?? '' as $cambio)
                        @php
                            $esLocal = array_search($cambio->jugador->id, array_column(array_merge($titularesL->toArray(), $suplentesL->toArray()), 'jugador_id'));
                            $esVisitante = array_search($cambio->jugador->id, array_column(array_merge($titularesV->toArray(), $suplentesV->toArray()), 'jugador_id'));
                        @endphp
                        <tr>

                            <td>


                            </td>
                            <td>
                                @if($esLocal !== false)
                                    @if($cambio->tipo == 'Sale')
                                        <img id="original" src="{{ url('images/sale.png') }}" height="20">
                                    @else
                                        <img id="original" src="{{ url('images/entra.png') }}" height="20">
                                    @endif

                                    @if($cambio->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$cambio->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                    <span style="font-weight: bold"> {{ $cambio->jugador->persona->full_name}}</span>

                                @endif
                            </td>
                            <td colspan="2">
                                <span style="color: green"> {{$cambio->minuto}}'</span>
                            </td>

                            <td >
                                @if($esVisitante !== false)
                                    @if($cambio->tipo == 'Sale')
                                        <img id="original" src="{{ url('images/sale.png') }}" height="20">
                                    @else
                                        <img id="original" src="{{ url('images/entra.png') }}" height="20">
                                    @endif
                                    @if($cambio->jugador->persona->foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$cambio->jugador->persona->foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif
                                    <span style="font-weight: bold"> {{ $cambio->jugador->persona->full_name}}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                </table>
            </div>

        </div>


        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

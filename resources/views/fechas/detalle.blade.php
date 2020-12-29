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
                            <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol->id))}}" class="btn btn-info m-1">
                            @if($partido->equipol)
                                @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                @endif
                            </a>
                                {{$partido->equipol->nombre}}
                            @endif
                        </td>
                        <td>{{$partido->golesl}}</td>
                        <td>{{$partido->golesv}}</td>
                        <td>
                            <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov->id))}}" class="btn btn-info m-1">
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
                        <td>
                            <table class="table">
                                <tr>  <td style="font-weight: bold">Titulares</td></tr>
                                @foreach($titularesL ?? '' as $titularl)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $titularl->jugador->id))}}" class="btn btn-info m-1">
                                    <span style="font-weight: bold">{{$titularl->dorsal}}</span>
                                    @if($titularl->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$titularl->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                            </a>
                                    <span style="font-weight: bold"> {{ $titularl->jugador->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>  <td style="font-weight: bold">Suplentes</td></tr>
                                    @foreach($suplentesL ?? '' as $suplentel)
                                        <tr>

                                            <td>
                                                <a href="{{route('jugadores.ver', array('jugadorId' => $suplentel->jugador->id))}}" class="btn btn-info m-1">
                                                <span style="font-weight: bold">{{$suplentel->dorsal}}</span>
                                                @if($suplentel->jugador->foto)
                                                    <img id="original" src="{{ url('images/'.$suplentel->jugador->foto) }}" height="50">
                                                @else
                                                    <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                                    @endif</a>
                                                <span style="font-weight: bold"> {{ $suplentel->jugador->full_name}}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                            </table>
                        </td>
                        <td colspan="2" style="font-weight: bold"></td>
                        <td>
                            <table class="table">
                                <tr>  <td style="font-weight: bold">Titulares</td></tr>
                                @foreach($titularesV ?? '' as $titularv)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $titularv->jugador->id))}}" class="btn btn-info m-1">
                                            <span style="font-weight: bold">{{$titularv->dorsal}}</span>
                                            @if($titularv->jugador->foto)
                                                <img id="original" src="{{ url('images/'.$titularv->jugador->foto) }}" height="50">
                                            @else
                                                <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                            @endif
                                            </a>
                                            <span style="font-weight: bold"> {{ $titularv->jugador->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>  <td style="font-weight: bold">Suplentes</td></tr>
                                @foreach($suplentesV ?? '' as $suplentev)
                                    <tr>

                                        <td>
                                            <a href="{{route('jugadores.ver', array('jugadorId' => $suplentev->jugador->id))}}" class="btn btn-info m-1">
                                            <span style="font-weight: bold">{{$suplentev->dorsal}}</span>
                                            @if($suplentev->jugador->foto)
                                                <img id="original" src="{{ url('images/'.$suplentev->jugador->foto) }}" height="50">
                                            @else
                                                <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                            @endif
                                            </a>
                                            <span style="font-weight: bold"> {{ $suplentev->jugador->full_name}}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

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
                        @if($esLocal !== false AND $gol->tipo == 'En Contra')
                            @php
                                $esVisitante = true;
                                $esLocal = false;
                            @endphp
                        @endif
                        @if($esVisitante !== false AND $gol->tipo == 'En Contra')
                            @php
                                $esLocal = true;
                                $esVisitante = false;
                            @endphp
                        @endif
                            <tr>

                            <td>


                            </td>
                            <td>
                                @if($esLocal !== false)

                                    @if($gol->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$gol->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                        <span style="font-weight: bold"> {{ $gol->jugador->full_name}}</span>  {{$gol->tipo}}

                                @endif
                            </td>
                            <td colspan="2">
                                <span style="color: green"> {{$gol->minuto}}'</span>
                            </td>

                            <td >
                                @if($esVisitante !== false)

                                    @if($gol->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$gol->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                         <span style="font-weight: bold"> {{ $gol->jugador->full_name}}</span>  {{$gol->tipo}}
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
                                        <img id="original" src="{{ url('images/amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Doble Amarilla')
                                        <img id="original" src="{{ url('images/doble_amarilla.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->tipo == 'Roja')
                                        <img id="original" src="{{ url('images/roja.gif') }}" height="20">
                                    @endif
                                    @if($tarjeta->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$tarjeta->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                    <span style="font-weight: bold"> {{ $tarjeta->jugador->full_name}}</span>

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
                                    @if($tarjeta->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$tarjeta->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                    <span style="font-weight: bold"> {{ $tarjeta->jugador->full_name}}</span>
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

                                    @if($cambio->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$cambio->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                    <span style="font-weight: bold"> {{ $cambio->jugador->full_name}}</span>

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
                                    @if($cambio->jugador->foto)
                                        <img id="original" src="{{ url('images/'.$cambio->jugador->foto) }}" height="50">
                                    @else
                                        <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                    @endif
                                    <span style="font-weight: bold"> {{ $cambio->jugador->full_name}}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                </table>
            </div>

        </div>


        <div class="d-flex">

            <a href="{{ route('fechas.showPublic',array('fechaId' => $partido->fecha->id)) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

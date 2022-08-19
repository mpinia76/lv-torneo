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
                                @php

                                   $arrayGoles = $goles->toArray();
                                   $arrayTarjetas = $tarjetas->toArray();
                                   $arrayCambios = $cambios->toArray();

                                @endphp
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
                                    @php


                                        $goleador=array();
                                        foreach ($arrayGoles as $arrayGol){
                                            if ($titularl->jugador->id==$arrayGol['jugador_id']){
                                                $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                            }
                                        }
                                        $tarjetero=array();
                                        foreach ($arrayTarjetas as $arrayTarjeta){
                                            if ($titularl->jugador->id==$arrayTarjeta['jugador_id']){
                                                $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                            }
                                        }
                                        $tieneCambio=array();
                                        foreach ($arrayCambios as $arrayCambio){
                                            if ($titularl->jugador->id==$arrayCambio['jugador_id']){
                                                $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                            }
                                        }

                                    @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15">

                                                    @endif


                                                {{$t[1]}}'
                                                @endforeach
                                            @endif
                                                @if (!empty($tieneCambio))
                                                    @foreach($tieneCambio as $t)
                                                        @if($t[0]=='Sale')
                                                            <img id="original"  src="{{ url('images/sale.png') }}" height="15">
                                                        @else
                                                            <img id="original"  src="{{ url('images/entra.png') }}" height="15">
                                                        @endif
                                                        {{$t[1]}}'
                                                @endforeach
                                            @endif


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
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as $arrayGol){
                                                    if ($titularv->jugador->id==$arrayGol['jugador_id']){
                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as $arrayTarjeta){
                                                    if ($titularv->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as $arrayCambio){
                                                    if ($titularv->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15">
                                                    @endif
                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
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
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as $arrayGol){
                                                    if ($suplentel->jugador->id==$arrayGol['jugador_id']){
                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as $arrayTarjeta){
                                                    if ($suplentel->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as $arrayCambio){
                                                    if ($suplentel->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15">
                                                    @endif
                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
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
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as $arrayGol){
                                                    if ($suplentev->jugador->id==$arrayGol['jugador_id']){
                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as $arrayTarjeta){
                                                    if ($suplentev->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as $arrayCambio){
                                                    if ($suplentev->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15">
                                                    @endif
                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
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






                </table>
            </div>

        </div>


        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

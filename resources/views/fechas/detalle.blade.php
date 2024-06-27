@extends('layouts.appPublic')

@section('pageTitle', 'Detalle Fecha')
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

        <h1 class="display-6">Fecha {{$partido->fecha->numero}} de {{$partido->fecha->grupo->torneo->nombre}} {{$partido->fecha->grupo->torneo->year}}</h1>


        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="font-size: 14px;">
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
                                {{$partido->equipol->nombre}} <img id="original" src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}">
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
                                {{$partido->equipov->nombre}} <img id="original" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
                            @endif
                        </td>

                    </tr>
                </table>
            </div>
        </div>
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="alineaciones-tab" data-toggle="tab" href="#alineaciones" role="tab" aria-controls="alineaciones" aria-selected="true">Alineaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="goles-tab" data-toggle="tab" href="#goles" role="tab" aria-controls="goles" aria-selected="false">Goles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tarjetas-tab" data-toggle="tab" href="#tarjetas" role="tab" aria-controls="tarjetas" aria-selected="false">Tarjetas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="cambios-tab" data-toggle="tab" href="#cambios" role="tab" aria-controls="cambios" aria-selected="false">Cambios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="arbitros-tab" data-toggle="tab" href="#arbitros" role="tab" aria-controls="arbitros" aria-selected="false">Arbitros</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="incidencias-tab" data-toggle="tab" href="#incidencias" role="tab" aria-controls="incidencias" aria-selected="false">Incidencias</a>
                    </li>
                </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="alineaciones">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-12">
                        <table class="table" style="font-size: 14px;">
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="3" style="font-weight: bold">Titulares</td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            <table class="table" style="font-size: 14px;">
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
                                    <span style="font-weight: bold"> {{ $titularl->jugador->persona->full_name}} <img id="original" src="{{ $titularl->jugador->persona->bandera_url }}" alt="{{ $titularl->jugador->persona->nacionalidad }}"></span>
                                    @php


                                        $goleador=array();
                                        foreach ($arrayGoles as &$arrayGol){
                                            if ($titularl->jugador->id==$arrayGol['jugador_id']){
                                                $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                $arrayGol['dorsal']=$titularl->dorsal;
                                                $arrayGol['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayGol['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipov->escudo:$partido->equipol->escudo;
                                            }
                                        }
                                        $tarjetero=array();
                                        foreach ($arrayTarjetas as &$arrayTarjeta){
                                            if ($titularl->jugador->id==$arrayTarjeta['jugador_id']){
                                                $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                $arrayTarjeta['dorsal']=$titularl->dorsal;
                                                $arrayTarjeta['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayTarjeta['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayTarjeta['escudo']=$partido->equipol->escudo;
                                            }
                                        }
                                        $tieneCambio=array();
                                        foreach ($arrayCambios as &$arrayCambio){
                                            if ($titularl->jugador->id==$arrayCambio['jugador_id']){
                                                $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                $arrayCambio['dorsal']=$titularl->dorsal;
                                                $arrayCambio['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayCambio['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayCambio['escudo']=$partido->equipol->escudo;
                                            }
                                        }

                                    @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20" title="En contra">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20" title="Penal">
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <img id="original"  src="{{ url('images/tiro-libre.png') }}" height="20" title="Tiro libre">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20" title="Jugada">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15" title="Amarilla">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15" title="Roja">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15" title="Doble amarilla">

                                                    @endif


                                                {{$t[1]}}'
                                                @endforeach
                                            @endif
                                                @if (!empty($tieneCambio))
                                                    @foreach($tieneCambio as $t)
                                                        @if($t[0]=='Sale')
                                                            <img id="original"  src="{{ url('images/sale.png') }}" height="15" title="Sale">
                                                        @else
                                                            <img id="original"  src="{{ url('images/entra.png') }}" height="15" title="Entra">
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
                            <table class="table" style="font-size: 14px;">

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
                                            <span style="font-weight: bold"> {{ $titularv->jugador->persona->full_name}} <img id="original" src="{{ $titularv->jugador->persona->bandera_url }}" alt="{{ $titularv->jugador->persona->nacionalidad }}"></span>
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as &$arrayGol){
                                                    //print_r($arrayGol);
                                                    if ($titularv->jugador->id==$arrayGol['jugador_id']){

                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                        $arrayGol['dorsal']=$titularv->dorsal;
                                                        $arrayGol['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayGol['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipol->escudo:$partido->equipov->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($titularv->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                        $arrayTarjeta['dorsal']=$titularv->dorsal;
                                                        $arrayTarjeta['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($titularv->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                        $arrayCambio['dorsal']=$titularv->dorsal;
                                                        $arrayCambio['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayCambio['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';

                                                        $arrayCambio['escudo']=$partido->equipov->escudo;
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20" title="En contra">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20" title="Penal">
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <img id="original"  src="{{ url('images/tiro-libre.png') }}" height="20" title="Tiro libre">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20" title="Jugada">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15" title="Amarilla">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15" title="Roja">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15" title="Doble amarilla">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15" title="Sale">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15" title="Entra">
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
                            <table class="table" style="font-size: 14px;">


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
                                            <span style="font-weight: bold"> {{ $suplentel->jugador->persona->full_name}} <img id="original" src="{{ $suplentel->jugador->persona->bandera_url }}" alt="{{ $suplentel->jugador->persona->nacionalidad }}"></span>
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as &$arrayGol){
                                                    if ($suplentel->jugador->id==$arrayGol['jugador_id']){
                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                        $arrayGol['dorsal']=$suplentel->dorsal;
                                                        $arrayGol['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayGol['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipov->escudo:$partido->equipol->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($suplentel->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                        $arrayTarjeta['dorsal']=$suplentel->dorsal;
                                                        $arrayTarjeta['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipol->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($suplentel->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                        $arrayCambio['dorsal']=$suplentel->dorsal;
                                                        $arrayCambio['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayCambio['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';

                                                        $arrayCambio['escudo']=$partido->equipol->escudo;
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20" title="En contra">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20" title="Penal">
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <img id="original"  src="{{ url('images/tiro-libre.png') }}" height="20" title="Tiro libre">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20" title="Jugada">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15" title="Amarilla">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15" title="Roja">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15" title="Doble amarilla">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15" title="Sale">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15" title="Entra">
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
                            <table class="table" style="font-size: 14px;">


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
                                            <span style="font-weight: bold"> {{ $suplentev->jugador->persona->full_name}} <img id="original" src="{{ $suplentev->jugador->persona->bandera_url }}" alt="{{ $suplentev->jugador->persona->nacionalidad }}"></span>
                                            @php
                                                $goleador=array();
                                                foreach ($arrayGoles as &$arrayGol){
                                                    if ($suplentev->jugador->id==$arrayGol['jugador_id']){
                                                        $goleador[]=array($arrayGol['tipo'],$arrayGol['minuto']);
                                                        $arrayGol['dorsal']=$suplentev->dorsal;
                                                        $arrayGol['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayGol['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipol->escudo:$partido->equipov->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($suplentev->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'],$arrayTarjeta['minuto']);
                                                        $arrayTarjeta['dorsal']=$suplentev->dorsal;
                                                        $arrayTarjeta['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($suplentev->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'],$arrayCambio['minuto']);
                                                        $arrayCambio['dorsal']=$suplentev->dorsal;
                                                        $arrayCambio['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayCambio['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';

                                                        $arrayCambio['escudo']=$partido->equipov->escudo;
                                                    }
                                                }

                                            @endphp
                                            @if (!empty($goleador))
                                                @foreach($goleador as $g)
                                                    @if($g[0]=='En Contra')
                                                        <img id="original"  src="{{ url('images/iconMatchGoalAgainst.gif') }}" height="20" title="En contra">
                                                    @elseif($g[0]=='Penal')
                                                        <img id="original"  src="{{ url('images/iconMatchPenalty.gif') }}" height="20" title="Penal">
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <img id="original"  src="{{ url('images/tiro-libre.png') }}" height="20" title="Tiro libre">
                                                    @else
                                                        <img id="original"  src="{{ url('images/iconMatchGoal.gif') }}" height="20" title="Jugada">
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15" title="Amarilla">

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <img id="original"  src="{{ url('images/roja.gif') }}" height="15" title="Roja">

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15" title="Doble amarilla">

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <img id="original"  src="{{ url('images/sale.png') }}" height="15" title="Sale">
                                                    @else
                                                        <img id="original"  src="{{ url('images/entra.png') }}" height="15" title="Entra">
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
                    <table class="table" style="font-size: 14px;">
                        @foreach($tecnicosL ?? '' as $tecnicol)
                            <tr>

                                <td>
                                    <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnicol->tecnico->id))}}" >

                                        @if($tecnicol->tecnico->persona->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$tecnicol->tecnico->persona->foto) }}" >
                                        @else
                                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                        @endif</a>
                                    <span style="font-weight: bold"> {{ $tecnicol->tecnico->persona->full_name}} <img id="original" src="{{ $tecnicol->tecnico->persona->bandera_url }}" alt="{{ $tecnicol->tecnico->persona->nacionalidad }}"></span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                        </td>
                        <td colspan="2" style="font-weight: bold"></td>
                        <td>
                    <table class="table" style="font-size: 14px;">
                        @foreach($tecnicosV ?? '' as $tecnicov)
                            <tr>

                                <td>
                                    <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnicov->tecnico->id))}}" >

                                        @if($tecnicov->tecnico->persona->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$tecnicov->tecnico->persona->foto) }}" >
                                        @else
                                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                        @endif</a>
                                    <span style="font-weight: bold"> {{ $tecnicov->tecnico->persona->full_name}} <img id="original" src="{{ $tecnicov->tecnico->persona->bandera_url }}" alt="{{ $tecnicov->tecnico->persona->nacionalidad }}"></span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                        </td></tr>




                </table>
            </div>

        </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="goles">
                <div class="row">


                        @foreach($arrayGoles ?? '' as $arrGol)

                        <div class="form-group col-xs-12 col-sm-6 col-md-12">
                            <img id="original" height="20" src="{{ url('images/'.$arrGol['escudo']) }}" >

                            {{ $arrGol['minuto']}}'
                                    <a href="{{route('jugadores.ver', array('jugadorId' => $arrGol['jugador_id']))}}" >


                                            <img id="original" class="imgCircle" src="{{ url('images/'.$arrGol['foto']) }}" >

                                           </a>
                                    <span style="font-weight: bold"> {{ $arrGol['jugador']}}</span> {{ $arrGol['tipo']}}
                        </div>

                        @endforeach

                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="tarjetas">
                <div class="row">


                    @foreach($arrayTarjetas ?? '' as $arrTarjeta)

                        <div class="form-group col-xs-12 col-sm-6 col-md-12">
                            <img id="original" height="20" src="{{ url('images/'.$arrTarjeta['escudo']) }}" >

                            {{ $arrTarjeta['minuto']}}'
                            @if( $arrTarjeta['tipo']=='Amarilla')
                                <img id="original"  src="{{ url('images/amarilla.gif') }}" height="15" title="Amarilla">

                            @endif
                            @if( $arrTarjeta['tipo']=='Roja')
                                <img id="original"  src="{{ url('images/roja.gif') }}" height="15" title="Roja">

                            @endif
                            @if( $arrTarjeta['tipo']=='Doble Amarilla')
                                <img id="original"  src="{{ url('images/doble_amarilla.gif') }}" height="15" title="Doble amarilla">

                            @endif
                            <a href="{{route('jugadores.ver', array('jugadorId' => $arrTarjeta['jugador_id']))}}" >


                                <img id="original" class="imgCircle" src="{{ url('images/'.$arrTarjeta['foto']) }}" >

                            </a>
                            <span style="font-weight: bold"> {{ $arrTarjeta['jugador']}}</span>
                        </div>

                    @endforeach

                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="cambios">
                <div class="row">


                    @foreach($arrayCambios ?? '' as $arrCambio)

                        <div class="form-group col-xs-12 col-sm-6 col-md-12">
                            <img id="original" height="20" src="{{ url('images/'.$arrCambio['escudo']) }}" >

                            {{ $arrCambio['minuto']}}'
                            @if($arrCambio['tipo']=='Sale')
                                <img id="original"  src="{{ url('images/sale.png') }}" height="15" title="Sale">
                            @else
                                <img id="original"  src="{{ url('images/entra.png') }}" height="15" title="Entra">
                            @endif
                            <a href="{{route('jugadores.ver', array('jugadorId' => $arrCambio['jugador_id']))}}" >


                                <img id="original" class="imgCircle" src="{{ url('images/'.$arrCambio['foto']) }}" >

                            </a>
                            <span style="font-weight: bold"> {{ $arrCambio['jugador']}}</span>
                        </div>

                    @endforeach

                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="arbitros">
                <div class="row">


                    @foreach($arbitros ?? '' as $arbitro)
                        <div class="form-group col-xs-12 col-sm-6 col-md-12">
                            <a href="{{route('arbitros.ver', array('arbitroId' => $arbitro->arbitro->id))}}" >

                                @if($arbitro->arbitro->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$arbitro->arbitro->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                                @endif</a>
                            <span style="font-weight: bold"> {{ $arbitro->arbitro->persona->full_name}} <img id="original" src="{{ $arbitro->arbitro->persona->bandera_url }}" alt="{{ $arbitro->arbitro->persona->nacionalidad }}"></span> {{ $arbitro->tipo}}
                        </div>

                    @endforeach

                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="incidencias">
                <div class="row">


                    <div class="mt-4">

                        <ul>
                            @foreach($incidencias as $incidencia)
                                <li>
                                    {{ $incidencia->observaciones }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                </div>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

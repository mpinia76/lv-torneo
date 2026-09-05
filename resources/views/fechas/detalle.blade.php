@extends('layouts.appPublic')

@section('pageTitle', 'Detalle Fecha')

@section('content')
    @php
        $torneoDet   = $partido->fecha->grupo->torneo;
        $numeroFecha = is_numeric($partido->fecha->numero) ? 'Fecha ' . $partido->fecha->numero : $partido->fecha->numero;
        $sinJugar    = is_null($partido->golesl) && is_null($partido->golesv);
    @endphp

        {{-- Íconos de eventos: estilos + sprite SVG.
             Van acá adentro a propósito: así el dibujo y su tamaño viajan
             juntos y no dependen de que se suba (ni se descachee) torneos.css --}}
        <style>
            .ev-sprite { display: none; }

            .ev {
                width: 1.3em;
                height: 1.3em;
                vertical-align: -.3em;
                flex: none;
            }

            .ev-gol      { color: var(--t-ink, #11171A); }
            .ev-contra   { color: var(--t-roja, #D33B2C); }
            .ev-errado   { color: var(--t-roja, #D33B2C); }
            .ev-roja     { color: var(--t-roja, #D33B2C); }
            .ev-atajado  { color: var(--t-accent, #1746A2); }
            .ev-amarilla { color: var(--t-amarilla, #EAB308); }
            .ev-entra    { color: var(--t-win, #1E7F4E); }
            .ev-sale     { color: var(--t-loss, #B0392C); }
        </style>

        <svg class="ev-sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
            <defs>
                {{-- La pelota, dibujada una sola vez. El blanco es el fondo de la
                     tarjeta (var --t-surface) para que en modo oscuro se invierta sola. --}}
                <g id="ev-ball">
                    <circle cx="12" cy="12" r="9.15" style="fill:var(--t-surface,#fff)"
                            stroke="currentColor" stroke-width="1.7"/>
                    <g fill="currentColor">
                        <path d="M12.00 8.45 15.38 10.90 14.09 14.87 9.91 14.87 8.62 10.90Z"/>
                        <path d="M14.06 9.17 13.02 5.98 15.73 4.01 18.44 5.98 17.41 9.17Z"/>
                        <path d="M15.33 13.08 18.04 11.11 20.75 13.08 19.71 16.27 16.36 16.27Z"/>
                        <path d="M12.00 15.50 14.71 17.47 13.68 20.66 10.32 20.66 9.29 17.47Z"/>
                        <path d="M8.67 13.08 7.64 16.27 4.29 16.27 3.25 13.08 5.96 11.11Z"/>
                        <path d="M9.94 9.17 6.59 9.17 5.56 5.98 8.27 4.01 10.98 5.98Z"/>
                    </g>
                </g>
            </defs>

            <symbol id="ev-pelota" viewBox="0 0 24 24">
                <use href="#ev-ball"/>
            </symbol>

            <symbol id="ev-penal" viewBox="0 0 24 24">
                <g transform="translate(-0.4,-0.4) scale(0.84)"><use href="#ev-ball"/></g>
                <circle cx="18" cy="18" r="6" style="fill:var(--t-surface,#fff)"/>
                <circle cx="18" cy="18" r="5.1" fill="currentColor"/>
                <text x="18" y="20.9" text-anchor="middle" font-family="system-ui,-apple-system,'Segoe UI',sans-serif"
                      font-size="8.2" font-weight="700" style="fill:var(--t-surface,#fff)">P</text>
            </symbol>

            <symbol id="ev-cabeza" viewBox="0 0 24 24">
                <g fill="currentColor">
                    <circle cx="8.6" cy="13.9" r="4.9"/>
                    <path d="M1.4 23.7C1.8 19.2 4.9 17.3 8.6 17.3s6.8 1.9 7.2 6.4Z"/>
                </g>
                <circle cx="17.9" cy="5.9" r="6" style="fill:var(--t-surface,#fff)"/>
                <circle cx="17.9" cy="5.9" r="4.39" style="fill:var(--t-surface,#fff)"
                        stroke="currentColor" stroke-width="1.35"/>
                <g fill="currentColor">
                    <path d="M17.90 4.20 19.52 5.37 18.90 7.28 16.90 7.28 16.28 5.37Z"/>
                    <path d="M18.89 4.54 18.39 3.01 19.69 2.07 20.99 3.01 20.50 4.54Z"/>
                    <path d="M19.50 6.42 20.80 5.47 22.10 6.42 21.60 7.95 19.99 7.95Z"/>
                    <path d="M17.90 7.58 19.20 8.53 18.70 10.05 17.10 10.05 16.60 8.53Z"/>
                    <path d="M16.30 6.42 15.81 7.95 14.20 7.95 13.70 6.42 15.00 5.47Z"/>
                    <path d="M16.91 4.54 15.30 4.54 14.81 3.01 16.11 2.07 17.41 3.01Z"/>
                </g>
            </symbol>

            <symbol id="ev-tirolibre" viewBox="0 0 24 24">
                <g transform="translate(-1.6,3.4) scale(0.79)"><use href="#ev-ball"/></g>
                <path d="M10.6 6.9C13.8 2.1 19.3 2.7 21.4 6.6" fill="none" stroke="currentColor"
                      stroke-width="1.9" stroke-linecap="round" stroke-dasharray="2.9 2.4"/>
                <path d="M22.2 7.9 18.6 5.6 21.9 3.6Z" fill="currentColor"/>
            </symbol>

            {{-- Gol olímpico: el banderín del córner y la pelota que entra sola.
                 Mismo lenguaje que ev-tirolibre (arco punteado = trayectoria). --}}
            <symbol id="ev-olimpico" viewBox="0 0 24 24">
                <g transform="translate(9.6,9.4) scale(0.58)"><use href="#ev-ball"/></g>
                <path d="M4 21.4V3.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                <path d="M4.9 3.5 12.4 6.0 4.9 8.6Z" fill="currentColor"/>
                <path d="M5.6 10.6C8.4 5.2 16.2 5.6 18.6 10.0" fill="none" stroke="currentColor"
                      stroke-width="1.7" stroke-linecap="round" stroke-dasharray="2.6 2.3"/>
            </symbol>

            <symbol id="ev-errado" viewBox="0 0 24 24">
                <g color="#98A0A2"><use href="#ev-ball"/></g>
                <path d="M5 19 19 5" fill="none" style="stroke:var(--t-surface,#fff)"
                      stroke-width="4.6" stroke-linecap="round"/>
                <path d="M5 19 19 5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </symbol>

            <symbol id="ev-guante" viewBox="0 0 24 24">
                <g fill="currentColor">
                    <rect x="4.9" y="6.2" width="3.2" height="8" rx="1.6"/>
                    <rect x="8.8" y="3.9" width="3.2" height="10.3" rx="1.6"/>
                    <rect x="12.7" y="4.8" width="3.2" height="9.4" rx="1.6"/>
                    <rect x="16.6" y="7" width="3.2" height="7.2" rx="1.6"/>
                    <rect x="1.6" y="10.7" width="3.4" height="6.2" rx="1.7" transform="rotate(-22 3.3 13.8)"/>
                    <rect x="4.9" y="10.2" width="14.9" height="7.7" rx="2.7"/>
                    <rect x="6.2" y="18.9" width="12.3" height="3" rx="1.3"/>
                </g>
            </symbol>

            <symbol id="ev-tarjeta" viewBox="0 0 24 24">
                <g transform="rotate(9 12 12)">
                    <rect x="7.7" y="3.8" width="8.6" height="16.4" rx="1.8" fill="currentColor"
                          stroke="rgba(17,23,26,.3)" stroke-width=".9"/>
                </g>
            </symbol>

            <symbol id="ev-doble" viewBox="0 0 24 24">
                <g transform="rotate(-13 9 12)">
                    <rect x="3.9" y="4.4" width="8" height="15.2" rx="1.7"
                          style="fill:var(--t-amarilla,#EAB308)" stroke="rgba(17,23,26,.3)" stroke-width=".9"/>
                </g>
                <g transform="rotate(10 15 12)">
                    <rect x="11.7" y="4.4" width="8" height="15.2" rx="1.7"
                          style="fill:var(--t-roja,#D33B2C)" stroke="rgba(17,23,26,.3)" stroke-width=".9"/>
                </g>
            </symbol>

            <symbol id="ev-entra" viewBox="0 0 24 24">
                <g fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20.2V6.6"/><path d="M6.3 12.1 12 5.9 17.7 12.1"/>
                </g>
            </symbol>

            <symbol id="ev-sale" viewBox="0 0 24 24">
                <g fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3.8v13.6"/><path d="M6.3 11.9 12 18.1 17.7 11.9"/>
                </g>
            </symbol>
        </svg>

    <div class="container t-detalle">

        <div class="t-cabecera">
            <div>
                <span class="t-eyebrow">
                    <x-escudo :src="$torneoDet->escudo" :nombre="$torneoDet->nombre" tam="sm"/>
                    {{ $torneoDet->nombre }} {{ $torneoDet->year }} @if($numeroFecha) · {{ $numeroFecha }} @endif
                </span>
            </div>
        </div>

        <div class="t-panel mb-3">
            <div class="t-marcador-grande">
                <div class="t-lado">
                    @if($partido->equipol)
                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">
                            <x-escudo :src="$partido->equipol->escudo" :nombre="$partido->equipol->nombre" tam="xl"/>
                        </a>
                        <b>
                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">{{ $partido->equipol->nombre }}</a>
                            @if($partido->equipol->bandera_url)
                                <img class="bandera" src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" title="{{ $partido->equipol->pais }}">
                            @endif
                        </b>
                    @endif
                </div>

                <div>
                    <div class="t-marcador-cifra">
                        @if($sinJugar)
                            – –
                        @else
                            {{ $partido->golesl }}&thinsp;–&thinsp;{{ $partido->golesv }}
                        @endif
                    </div>
                    @if($partido->penalesl || $partido->penalesv)
                        <div class="t-eyebrow mt-2">Penales {{ $partido->penalesl }}–{{ $partido->penalesv }}</div>
                    @endif
                </div>

                <div class="t-lado">
                    @if($partido->equipov)
                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">
                            <x-escudo :src="$partido->equipov->escudo" :nombre="$partido->equipov->nombre" tam="xl"/>
                        </a>
                        <b>
                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">{{ $partido->equipov->nombre }}</a>
                            @if($partido->equipov->bandera_url)
                                <img class="bandera" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" title="{{ $partido->equipov->pais }}">
                            @endif
                        </b>
                    @endif
                </div>
            </div>

            <div class="t-meta-partido">
                @if($partido->dia)
                    <span>Día <b>{{ date('d/m/Y', strtotime($partido->dia)) }}</b></span>
                    <span>Hora <b>{{ date('H:i', strtotime($partido->dia)) }}</b></span>
                @else
                    <span>Sin fecha confirmada</span>
                @endif
                <span>{{ $torneoDet->nombre }} {{ $torneoDet->year }}</span>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs justify-content-center mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="alineaciones-tab" data-bs-toggle="tab" data-bs-target="#alineaciones" type="button" role="tab" aria-controls="alineaciones" aria-selected="true">
                    Alineaciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="goles-tab" data-bs-toggle="tab" data-bs-target="#goles" type="button" role="tab" aria-controls="goles" aria-selected="false">
                    Goles
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tarjetas-tab" data-bs-toggle="tab" data-bs-target="#tarjetas" type="button" role="tab" aria-controls="tarjetas" aria-selected="false">
                    Tarjetas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cambios-tab" data-bs-toggle="tab" data-bs-target="#cambios" type="button" role="tab" aria-controls="cambios" aria-selected="false">
                    Cambios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="arbitros-tab" data-bs-toggle="tab" data-bs-target="#arbitros" type="button" role="tab" aria-controls="arbitros" aria-selected="false">
                    Árbitros
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="incidencias-tab" data-bs-toggle="tab" data-bs-target="#incidencias" type="button" role="tab" aria-controls="incidencias" aria-selected="false">
                    Incidencias
                </button>
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
                                   $arrayPenales = $penales->toArray();
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
                                                $goleador[]=array($arrayGol['tipo'], \App\Services\MinutoHelper::texto($arrayGol['minuto'], $arrayGol['adicionado'] ?? null, ''));
                                                $arrayGol['dorsal']=$titularl->dorsal;
                                                $arrayGol['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayGol['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipov->escudo:$partido->equipol->escudo;
                                            }
                                        }
                                        $incPenales=array();
                                        foreach ($arrayPenales as &$arrayPenal){
                                            if ($titularl->jugador->id==$arrayPenal['jugador_id']){
                                                $incPenales[]=array($arrayPenal['tipo'], \App\Services\MinutoHelper::texto($arrayPenal['minuto'], $arrayPenal['adicionado'] ?? null, ''));
                                                $arrayPenal['dorsal']=$titularl->dorsal;
                                                $arrayPenal['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayPenal['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayPenal['escudo']=$partido->equipol->escudo;
                                            }
                                        }
                                        $tarjetero=array();
                                        foreach ($arrayTarjetas as &$arrayTarjeta){
                                            if ($titularl->jugador->id==$arrayTarjeta['jugador_id']){
                                                $tarjetero[]=array($arrayTarjeta['tipo'], \App\Services\MinutoHelper::texto($arrayTarjeta['minuto'], $arrayTarjeta['adicionado'] ?? null, ''));
                                                $arrayTarjeta['dorsal']=$titularl->dorsal;
                                                $arrayTarjeta['jugador']=$titularl->jugador->persona->full_name;
                                                $arrayTarjeta['foto']=($titularl->jugador->persona->foto)?$titularl->jugador->persona->foto:'sin_foto.png';

                                                $arrayTarjeta['escudo']=$partido->equipol->escudo;
                                            }
                                        }
                                        $tieneCambio=array();
                                        foreach ($arrayCambios as &$arrayCambio){
                                            if ($titularl->jugador->id==$arrayCambio['jugador_id']){
                                                $tieneCambio[]=array($arrayCambio['tipo'], \App\Services\MinutoHelper::texto($arrayCambio['minuto'], $arrayCambio['adicionado'] ?? null, ''));
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
                                                        <svg class="ev ev-contra" role="img" width="18" height="18"><title>Gol en contra</title><use href="#ev-pelota"/></svg>
                                                    @elseif($g[0]=='Penal')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de penal</title><use href="#ev-penal"/></svg>
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de tiro libre</title><use href="#ev-tirolibre"/></svg>
                                                    @elseif($g[0]=='Olímpico')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol olímpico</title><use href="#ev-olimpico"/></svg>
                                                    @elseif($g[0]=='Cabeza')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de cabeza</title><use href="#ev-cabeza"/></svg>
                                                    @else
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol</title><use href="#ev-pelota"/></svg>
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($incPenales))
                                                @foreach($incPenales as $p)
                                                    @if($p[0]=='Errado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @elseif($p[0]=='Atajado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @else
                                                            <svg class="ev ev-atajado" role="img" width="18" height="18"><title>Penal atajado</title><use href="#ev-guante"/></svg>
                                                    @endif
                                                    {{$p[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <svg class="ev ev-amarilla" role="img" width="18" height="18"><title>Amarilla</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <svg class="ev ev-roja" role="img" width="18" height="18"><title>Roja</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <svg class="ev ev-doble" role="img" width="18" height="18"><title>Doble amarilla</title><use href="#ev-doble"/></svg>

                                                    @endif


                                                {{$t[1]}}'
                                                @endforeach
                                            @endif
                                                @if (!empty($tieneCambio))
                                                    @foreach($tieneCambio as $t)
                                                        @if($t[0]=='Sale')
                                                            <svg class="ev ev-sale" role="img" width="18" height="18"><title>Sale</title><use href="#ev-sale"/></svg>
                                                        @else
                                                            <svg class="ev ev-entra" role="img" width="18" height="18"><title>Entra</title><use href="#ev-entra"/></svg>
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

                                                        $goleador[]=array($arrayGol['tipo'], \App\Services\MinutoHelper::texto($arrayGol['minuto'], $arrayGol['adicionado'] ?? null, ''));
                                                        $arrayGol['dorsal']=$titularv->dorsal;
                                                        $arrayGol['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayGol['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipol->escudo:$partido->equipov->escudo;
                                                    }
                                                }
                                                $incPenales=array();
                                                foreach ($arrayPenales as &$arrayPenal){
                                                    if ($titularv->jugador->id==$arrayPenal['jugador_id']){
                                                        $incPenales[]=array($arrayPenal['tipo'], \App\Services\MinutoHelper::texto($arrayPenal['minuto'], $arrayPenal['adicionado'] ?? null, ''));
                                                        $arrayPenal['dorsal']=$titularv->dorsal;
                                                        $arrayPenal['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayPenal['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';

                                                        $arrayPenal['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($titularv->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'], \App\Services\MinutoHelper::texto($arrayTarjeta['minuto'], $arrayTarjeta['adicionado'] ?? null, ''));
                                                        $arrayTarjeta['dorsal']=$titularv->dorsal;
                                                        $arrayTarjeta['jugador']=$titularv->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($titularv->jugador->persona->foto)?$titularv->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($titularv->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'], \App\Services\MinutoHelper::texto($arrayCambio['minuto'], $arrayCambio['adicionado'] ?? null, ''));
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
                                                        <svg class="ev ev-contra" role="img" width="18" height="18"><title>Gol en contra</title><use href="#ev-pelota"/></svg>
                                                    @elseif($g[0]=='Penal')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de penal</title><use href="#ev-penal"/></svg>
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de tiro libre</title><use href="#ev-tirolibre"/></svg>
                                                    @elseif($g[0]=='Olímpico')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol olímpico</title><use href="#ev-olimpico"/></svg>
                                                    @elseif($g[0]=='Cabeza')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de cabeza</title><use href="#ev-cabeza"/></svg>
                                                    @else
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol</title><use href="#ev-pelota"/></svg>
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($incPenales))
                                                @foreach($incPenales as $p)
                                                    @if($p[0]=='Errado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @elseif($p[0]=='Atajado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @else
                                                        <svg class="ev ev-atajado" role="img" width="18" height="18"><title>Penal atajado</title><use href="#ev-guante"/></svg>
                                                    @endif
                                                    {{$p[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <svg class="ev ev-amarilla" role="img" width="18" height="18"><title>Amarilla</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <svg class="ev ev-roja" role="img" width="18" height="18"><title>Roja</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <svg class="ev ev-doble" role="img" width="18" height="18"><title>Doble amarilla</title><use href="#ev-doble"/></svg>

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <svg class="ev ev-sale" role="img" width="18" height="18"><title>Sale</title><use href="#ev-sale"/></svg>
                                                    @else
                                                        <svg class="ev ev-entra" role="img" width="18" height="18"><title>Entra</title><use href="#ev-entra"/></svg>
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
                                                        $goleador[]=array($arrayGol['tipo'], \App\Services\MinutoHelper::texto($arrayGol['minuto'], $arrayGol['adicionado'] ?? null, ''));
                                                        $arrayGol['dorsal']=$suplentel->dorsal;
                                                        $arrayGol['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayGol['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipov->escudo:$partido->equipol->escudo;
                                                    }
                                                }
                                                $incPenales=array();
                                                foreach ($arrayPenales as &$arrayPenal){
                                                    if ($suplentel->jugador->id==$arrayPenal['jugador_id']){
                                                        $incPenales[]=array($arrayPenal['tipo'], \App\Services\MinutoHelper::texto($arrayPenal['minuto'], $arrayPenal['adicionado'] ?? null, ''));
                                                        $arrayPenal['dorsal']=$suplentel->dorsal;
                                                        $arrayPenal['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayPenal['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';

                                                        $arrayPenal['escudo']=$partido->equipol->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($suplentel->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'], \App\Services\MinutoHelper::texto($arrayTarjeta['minuto'], $arrayTarjeta['adicionado'] ?? null, ''));
                                                        $arrayTarjeta['dorsal']=$suplentel->dorsal;
                                                        $arrayTarjeta['jugador']=$suplentel->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($suplentel->jugador->persona->foto)?$suplentel->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipol->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($suplentel->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'], \App\Services\MinutoHelper::texto($arrayCambio['minuto'], $arrayCambio['adicionado'] ?? null, ''));
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
                                                        <svg class="ev ev-contra" role="img" width="18" height="18"><title>Gol en contra</title><use href="#ev-pelota"/></svg>
                                                    @elseif($g[0]=='Penal')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de penal</title><use href="#ev-penal"/></svg>
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de tiro libre</title><use href="#ev-tirolibre"/></svg>
                                                    @elseif($g[0]=='Olímpico')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol olímpico</title><use href="#ev-olimpico"/></svg>
                                                    @elseif($g[0]=='Cabeza')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de cabeza</title><use href="#ev-cabeza"/></svg>
                                                    @else
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol</title><use href="#ev-pelota"/></svg>
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($incPenales))
                                                @foreach($incPenales as $p)
                                                    @if($p[0]=='Errado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @elseif($p[0]=='Atajado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @else
                                                        <svg class="ev ev-atajado" role="img" width="18" height="18"><title>Penal atajado</title><use href="#ev-guante"/></svg>
                                                    @endif
                                                    {{$p[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <svg class="ev ev-amarilla" role="img" width="18" height="18"><title>Amarilla</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <svg class="ev ev-roja" role="img" width="18" height="18"><title>Roja</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <svg class="ev ev-doble" role="img" width="18" height="18"><title>Doble amarilla</title><use href="#ev-doble"/></svg>

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <svg class="ev ev-sale" role="img" width="18" height="18"><title>Sale</title><use href="#ev-sale"/></svg>
                                                    @else
                                                        <svg class="ev ev-entra" role="img" width="18" height="18"><title>Entra</title><use href="#ev-entra"/></svg>
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
                                                        $goleador[]=array($arrayGol['tipo'], \App\Services\MinutoHelper::texto($arrayGol['minuto'], $arrayGol['adicionado'] ?? null, ''));
                                                        $arrayGol['dorsal']=$suplentev->dorsal;
                                                        $arrayGol['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayGol['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';
                                                        $arrayGol['escudo']=($arrayGol['tipo']=='En Contra')?$partido->equipol->escudo:$partido->equipov->escudo;
                                                    }
                                                }
                                                $incPenales=array();
                                                foreach ($arrayPenales as &$arrayPenal){
                                                    if ($suplentev->jugador->id==$arrayPenal['jugador_id']){
                                                        $incPenales[]=array($arrayPenal['tipo'], \App\Services\MinutoHelper::texto($arrayPenal['minuto'], $arrayPenal['adicionado'] ?? null, ''));
                                                        $arrayPenal['dorsal']=$suplentev->dorsal;
                                                        $arrayPenal['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayPenal['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';

                                                        $arrayPenal['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tarjetero=array();
                                                foreach ($arrayTarjetas as &$arrayTarjeta){
                                                    if ($suplentev->jugador->id==$arrayTarjeta['jugador_id']){
                                                        $tarjetero[]=array($arrayTarjeta['tipo'], \App\Services\MinutoHelper::texto($arrayTarjeta['minuto'], $arrayTarjeta['adicionado'] ?? null, ''));
                                                        $arrayTarjeta['dorsal']=$suplentev->dorsal;
                                                        $arrayTarjeta['jugador']=$suplentev->jugador->persona->full_name;
                                                        $arrayTarjeta['foto']=($suplentev->jugador->persona->foto)?$suplentev->jugador->persona->foto:'sin_foto.png';

                                                        $arrayTarjeta['escudo']=$partido->equipov->escudo;
                                                    }
                                                }
                                                $tieneCambio=array();
                                                foreach ($arrayCambios as &$arrayCambio){
                                                    if ($suplentev->jugador->id==$arrayCambio['jugador_id']){
                                                        $tieneCambio[]=array($arrayCambio['tipo'], \App\Services\MinutoHelper::texto($arrayCambio['minuto'], $arrayCambio['adicionado'] ?? null, ''));
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
                                                        <svg class="ev ev-contra" role="img" width="18" height="18"><title>Gol en contra</title><use href="#ev-pelota"/></svg>
                                                    @elseif($g[0]=='Penal')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de penal</title><use href="#ev-penal"/></svg>
                                                    @elseif($g[0]=='Tiro Libre')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de tiro libre</title><use href="#ev-tirolibre"/></svg>
                                                    @elseif($g[0]=='Olímpico')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol olímpico</title><use href="#ev-olimpico"/></svg>
                                                    @elseif($g[0]=='Cabeza')
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol de cabeza</title><use href="#ev-cabeza"/></svg>
                                                    @else
                                                        <svg class="ev ev-gol" role="img" width="18" height="18"><title>Gol</title><use href="#ev-pelota"/></svg>
                                                    @endif
                                                    {{$g[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($incPenales))
                                                @foreach($incPenales as $p)
                                                    @if($p[0]=='Errado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @elseif($p[0]=='Atajado')
                                                        <svg class="ev ev-errado" role="img" width="18" height="18"><title>Penal errado</title><use href="#ev-errado"/></svg>
                                                    @else
                                                        <svg class="ev ev-atajado" role="img" width="18" height="18"><title>Penal atajado</title><use href="#ev-guante"/></svg>
                                                    @endif
                                                    {{$p[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tarjetero))
                                                @foreach($tarjetero as $t)
                                                    @if($t[0]=='Amarilla')
                                                        <svg class="ev ev-amarilla" role="img" width="18" height="18"><title>Amarilla</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Roja')
                                                        <svg class="ev ev-roja" role="img" width="18" height="18"><title>Roja</title><use href="#ev-tarjeta"/></svg>

                                                    @endif
                                                    @if($t[0]=='Doble Amarilla')
                                                        <svg class="ev ev-doble" role="img" width="18" height="18"><title>Doble amarilla</title><use href="#ev-doble"/></svg>

                                                    @endif


                                                    {{$t[1]}}'
                                                @endforeach
                                            @endif
                                            @if (!empty($tieneCambio))
                                                @foreach($tieneCambio as $t)
                                                    @if($t[0]=='Sale')
                                                        <svg class="ev ev-sale" role="img" width="18" height="18"><title>Sale</title><use href="#ev-sale"/></svg>
                                                    @else
                                                        <svg class="ev ev-entra" role="img" width="18" height="18"><title>Entra</title><use href="#ev-entra"/></svg>
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

                            {{ \App\Services\MinutoHelper::texto($arrGol['minuto'], $arrGol['adicionado'] ?? null, '') }}'
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

                            {{ \App\Services\MinutoHelper::texto($arrTarjeta['minuto'], $arrTarjeta['adicionado'] ?? null, '') }}'
                            @if( $arrTarjeta['tipo']=='Amarilla')
                                <svg class="ev ev-amarilla" role="img" width="18" height="18"><title>Amarilla</title><use href="#ev-tarjeta"/></svg>

                            @endif
                            @if( $arrTarjeta['tipo']=='Roja')
                                <svg class="ev ev-roja" role="img" width="18" height="18"><title>Roja</title><use href="#ev-tarjeta"/></svg>

                            @endif
                            @if( $arrTarjeta['tipo']=='Doble Amarilla')
                                <svg class="ev ev-doble" role="img" width="18" height="18"><title>Doble amarilla</title><use href="#ev-doble"/></svg>

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

                            {{ \App\Services\MinutoHelper::texto($arrCambio['minuto'], $arrCambio['adicionado'] ?? null, '') }}'
                            @if($arrCambio['tipo']=='Sale')
                                <svg class="ev ev-sale" role="img" width="18" height="18"><title>Sale</title><use href="#ev-sale"/></svg>
                            @else
                                <svg class="ev ev-entra" role="img" width="18" height="18"><title>Entra</title><use href="#ev-entra"/></svg>
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


                    @foreach($arrayPenales ?? '' as $arrPenal)

                        <div class="form-group col-xs-12 col-sm-6 col-md-12">
                            <img id="original" height="20" src="{{ url('images/'.$arrPenal['escudo']) }}" >

                            {{ \App\Services\MinutoHelper::texto($arrPenal['minuto'], $arrPenal['adicionado'] ?? null, '') }}'
                            <a href="{{route('jugadores.ver', array('jugadorId' => $arrPenal['jugador_id']))}}" >


                                <img id="original" class="imgCircle" src="{{ url('images/'.$arrPenal['foto']) }}" >

                            </a>
                            <span style="font-weight: bold">{{ $arrPenal['jugador'] }}</span>
                            {{
                                ($arrPenal['tipo'] == 'Errado' || $arrPenal['tipo'] == 'Atajado') ? 'Penal errado' :
                                ($arrPenal['tipo'] == 'Atajó' ? 'Penal atajado' : $arrPenal['tipo'])
                            }}

                        </div>

                    @endforeach

                </div>
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

        <div class="text-center mt-4 mb-5">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
    </div>
@endsection

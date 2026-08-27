@extends('layouts.appPublic')

@section('pageTitle', 'Historiales')

@section('content')

    @php
        /* Prefijo hi: $torneos, $grupo y $i están tomadas a nivel global. */
        $hiFilas = collect($posiciones)->keyBy('equipo_id');
        $hiUno   = !empty($e1->id) ? ($hiFilas[$e1->id] ?? null) : null;
        $hiDos   = !empty($e2->id) ? ($hiFilas[$e2->id] ?? null) : null;

        $hiHay   = $hiUno && $hiDos;
        $hiJug   = $hiHay ? (int) $hiUno->jugados : 0;
        $hiGanoA = $hiHay ? (int) $hiUno->ganados : 0;
        $hiEmp   = $hiHay ? (int) $hiUno->empatados : 0;
        $hiGanoB = $hiHay ? (int) $hiDos->ganados : 0;
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Equipos</span>
            <h1>Historial</h1>
        </div>
    </div>

    <div class="t-panel t-panel-cuerpo">
        <form class="t-duelo-form" method="GET" action="{{ route('torneos.historiales') }}">
            <select class="form-control form-control-sm js-example-basic-single" id="equipo1" name="equipo1" onchange="this.form.submit()">
                <option value="">Elegí un equipo…</option>
                @foreach($equipos as $hiEquipo)
                    <option value="{{ $hiEquipo->id }}" @if($hiEquipo->id == $e1->id) selected @endif>{{ $hiEquipo->nombre }}</option>
                @endforeach
            </select>

            <span class="t-duelo-vs">vs.</span>

            <select class="form-control form-control-sm js-example-basic-single" id="equipo2" name="equipo2" onchange="this.form.submit()">
                <option value="">Elegí un equipo…</option>
                @foreach($equipos as $hiEquipo)
                    <option value="{{ $hiEquipo->id }}" @if($hiEquipo->id == $e2->id) selected @endif>{{ $hiEquipo->nombre }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($hiHay)
        {{-- Cara a cara --}}
        <div class="t-panel t-duelo">
            <a class="t-duelo-lado" href="{{ route('equipos.ver', ['equipoId' => $e1->id]) }}">
                <x-escudo :src="$e1->escudo" :nombre="$e1->nombre" tam="xl"/>
                <span class="t-duelo-nombre">{{ $e1->nombre }}</span>
            </a>

            <div class="t-duelo-centro">
                <div class="t-duelo-marcador">
                    <span class="g">{{ $hiGanoA }}</span>
                    <span class="e">{{ $hiEmp }}</span>
                    <span class="p">{{ $hiGanoB }}</span>
                </div>
                <div class="t-duelo-rot">victorias · empates · victorias</div>
                @if($hiJug > 0)
                    <span class="t-ge t-ge-ancha" title="{{ $hiGanoA }} · {{ $hiEmp }} · {{ $hiGanoB }}">
                        <i class="g" style="width: {{ round($hiGanoA * 100 / $hiJug, 1) }}%"></i>
                        <i class="e" style="width: {{ round($hiEmp * 100 / $hiJug, 1) }}%"></i>
                        <i class="p" style="width: {{ round($hiGanoB * 100 / $hiJug, 1) }}%"></i>
                    </span>
                @endif
                <div class="t-duelo-pie">{{ $hiJug }} {{ $hiJug == 1 ? 'partido' : 'partidos' }} · {{ $hiUno->golesl }}–{{ $hiDos->golesl }} en goles</div>
            </div>

            <a class="t-duelo-lado" href="{{ route('equipos.ver', ['equipoId' => $e2->id]) }}">
                <x-escudo :src="$e2->escudo" :nombre="$e2->nombre" tam="xl"/>
                <span class="t-duelo-nombre">{{ $e2->nombre }}</span>
            </a>
        </div>

        {{-- Los números de la serie --}}
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla t-lista-tabla">
                    <thead>
                    <tr>
                        <th>Equipo</th>
                        <th title="Puntos">Pts</th>
                        <th title="Partidos jugados">J</th>
                        <th title="Ganados">G</th>
                        <th title="Empatados">E</th>
                        <th title="Perdidos">P</th>
                        <th title="Goles a favor">GF</th>
                        <th title="Goles en contra">GC</th>
                        <th title="Diferencia de gol">Dif.</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($posiciones as $hiFila)
                        <tr>
                            <td>
                                <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $hiFila->equipo_id])"
                                                :nombre="$hiFila->equipo"
                                                :escudo="$hiFila->foto"/>
                            </td>
                            <td class="t-pts">{{ $hiFila->puntaje }}</td>
                            <td>{{ $hiFila->jugados }}</td>
                            <td>{{ $hiFila->ganados }}</td>
                            <td>{{ $hiFila->empatados }}</td>
                            <td>{{ $hiFila->perdidos }}</td>
                            <td>{{ $hiFila->golesl }}</td>
                            <td>{{ $hiFila->golesv }}</td>
                            <td class="{{ $hiFila->diferencia > 0 ? 't-dif-pos' : ($hiFila->diferencia < 0 ? 't-dif-neg' : 't-cero') }}">
                                {{ $hiFila->diferencia > 0 ? '+' : '' }}{{ $hiFila->diferencia }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Los partidos --}}
    @if(count($partidos))
        <div class="t-panel t-lista-partidos">
            @foreach($partidos as $hiPartido)
                <x-partido :p="$hiPartido" :neutral="true"/>
            @endforeach
        </div>
    @elseif(!empty($e1->id) && !empty($e2->id))
        <div class="t-panel">
            <div class="t-vacio"><i class="bi bi-calendar-x"></i>No hay partidos cargados entre estos dos equipos.</div>
        </div>
    @else
        <div class="t-panel">
            <div class="t-vacio"><i class="bi bi-shield"></i>Elegí dos equipos para ver el historial.</div>
        </div>
    @endif

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection

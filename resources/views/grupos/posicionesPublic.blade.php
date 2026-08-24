@extends('layouts.appPublic')

@section('pageTitle', 'Posiciones')

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>Tabla de posiciones</h1>
        </div>
    </div>

    @foreach($arrPosiciones as $nombre => $data)
        @php
            $i = 1;
            $posiciones = $data['equipos'];
            $numClasificados = $data['clasificados'] ?? 0;
        @endphp

        <div class="t-panel">
            @if(count($arrPosiciones) > 1)
                <div class="t-grupo">
                    <span class="t-grupo-nombre">Grupo {{ $nombre }}</span>
                </div>
            @endif

            <div class="t-tabla-wrap">
                <table class="t-tabla">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipo</th>
                        <th>Pts</th>
                        <th>PJ</th>
                        <th>G</th>
                        <th>E</th>
                        <th>P</th>
                        <th>GF</th>
                        <th>GC</th>
                        <th>Dif</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($posiciones as $equipo)
                        <tr class="{{ $i <= $numClasificados ? 't-clasifica' : '' }}">
                            <td class="t-pos">{{ $i }}</td>
                            <td>
                                <span class="t-nombre">
                                    <x-escudo :src="$equipo->foto" :nombre="$equipo->equipo"/>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">{{ $equipo->equipo }}</a>
                                    @if($equipo->pais)
                                        <img class="bandera" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}" title="{{ $equipo->pais }}">
                                    @endif
                                </span>
                            </td>
                            <td class="t-pts">{{ $equipo->puntaje }}</td>
                            <td>{{ $equipo->jugados }}</td>
                            <td>{{ $equipo->ganados }}</td>
                            <td>{{ $equipo->empatados }}</td>
                            <td>{{ $equipo->perdidos }}</td>
                            <td>{{ $equipo->golesl }}</td>
                            <td>{{ $equipo->golesv }}</td>
                            <td>{{ $equipo->diferencia > 0 ? '+' . $equipo->diferencia : $equipo->diferencia }}</td>
                        </tr>
                        @php $i++; @endphp
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if($numClasificados)
                <div class="t-panel-pie">
                    <span class="t-referencia"><i style="background: var(--t-win)"></i> Clasifica</span>
                </div>
            @endif
        </div>
    @endforeach

    @if($incidencias->isNotEmpty())
        <div class="t-panel">
            <div class="t-grupo"><span class="t-grupo-nombre">Incidencias del torneo</span></div>
            <div class="t-panel-cuerpo">
                <ul class="mb-0 ps-3">
                    @foreach($incidencias as $incidencia)
                        <li>{{ $incidencia->observaciones }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-outline-secondary btn-sm">Volver al torneo</a>
    </div>

@endsection

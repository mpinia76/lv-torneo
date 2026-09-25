@extends('layouts.appPublic')

@section('pageTitle', __('Promedios'))

@section('content')

    @php
        // La tabla viene ordenada de mejor a peor promedio: los últimos son los
        // que se van al descenso, según lo cargado en el torneo.
        $cantidad   = count($promedios);
        $descienden = (int) ($torneo->descenso_promedio ?? 0);
        $desdePos   = $descienden > 0 ? $cantidad - $descienden : null;
        $i = 1;
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>{{ __('Promedios') }}</h1>
        </div>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Equipo') }}</th>
                    <th>Pts</th>
                    <th>{{ __('PJ') }}</th>
                    <th>{{ __('Promedio') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($promedios as $equipo)
                    <tr class="{{ $desdePos !== null && $i > $desdePos ? 't-desciende' : '' }}">
                        <td class="t-pos">{{ $i }}</td>
                        <td>
                            <span class="t-nombre">
                                <x-escudo :src="$equipo->foto" :nombre="$equipo->equipo"/>
                                <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">{{ $equipo->equipo }}</a>
                                @if($equipo->pais)
                                    <img class="bandera" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ trad_dato($equipo->pais) }}" title="{{ trad_dato($equipo->pais) }}">
                                @endif
                            </span>
                        </td>
                        <td>{{ $equipo->puntaje }}</td>
                        <td>{{ $equipo->jugados }}</td>
                        <td class="t-pts">{{ number_format($equipo->promedio, 4) }}</td>
                    </tr>
                    @php $i++; @endphp
                @endforeach
                </tbody>
            </table>
        </div>

        @if($descienden > 0)
            <div class="t-panel-pie">
                <span class="t-referencia"><i style="background: var(--t-loss)"></i>
                    {{ $descienden == 1 ? __('Desciende por promedio') : __('Descienden por promedio (:n)', ['n' => $descienden]) }}
                </span>
            </div>
        @endif
    </div>

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver al torneo') }}</a>
    </div>

@endsection

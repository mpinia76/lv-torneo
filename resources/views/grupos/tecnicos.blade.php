@extends('layouts.appPublic')

@section('pageTitle', __('Técnicos'))

@section('content')

    @php
        $columns = [
            'puntaje'    => __('Punt.'),
            'Jugados'    => __('J'),
            'Ganados'    => __('G'),
            'Empatados'  => __('E'),
            'Perdidos'   => __('P'),
            'golesl'     => __('GF'),
            'golesv'     => __('GC'),
            'diferencia' => __('Dif.'),
            'prom'       => '%',
        ];
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>{{ __('Técnicos') }}</h1>
        </div>

        <form class="d-flex gap-2">
            <input type="hidden" name="torneoId" value="{{ $torneo_id }}">
            <input type="search" name="buscarpor" class="form-control form-control-sm" style="width: 220px"
                   placeholder="{{ __('Buscar técnico') }}"
                   value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Buscar') }}</button>
        </form>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Técnico') }}</th>
                    <th class="t-izq">{{ __('Equipos') }}</th>
                    @foreach($columns as $key => $label)
                        <th>
                            <a href="{{ route('grupos.tecnicos', [
                                    'torneoId'  => $torneo_id,
                                    'order'     => $key,
                                    'tipoOrder' => ($order == $key && $tipoOrder == 'ASC') ? 'DESC' : 'ASC',
                                ]) }}">
                                {{ $label }}
                                @if($order == $key)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($goleadores as $tecnico)
                    @php
                        $equiposDt = array_values(array_filter(explode(',', (string) $tecnico->escudo)));
                        $varios = count($equiposDt) > 1;
                    @endphp
                    <tr>
                        <td class="t-pos">{{ $loop->iteration + ($goleadores->firstItem() ? $goleadores->firstItem() - 1 : 0) }}</td>
                        <td>
                            <span class="t-nombre">
                                <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">
                                    <img class="imgCircle" src="{{ url('images/'.($tecnico->fotoTecnico ?? 'sin_foto_tecnico.png')) }}" alt="">
                                </a>
                                <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">{{ $tecnico->tecnico }}</a>
                                @if($tecnico->nacionalidadTecnico)
                                    <img class="bandera" src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}" alt="{{ trad_dato($tecnico->nacionalidadTecnico) }}" title="{{ trad_dato($tecnico->nacionalidadTecnico) }}">
                                @endif
                            </span>
                        </td>
                        <td class="t-izq">
                            <span class="t-dt-equipos">
                                @foreach($equiposDt as $escudo)
                                    @php $e = partesEscudo($escudo); @endphp
                                    <span class="t-dt-equipo">
                                        <a href="{{ route('equipos.ver', ['equipoId' => $e[1]]) }}">
                                            <x-escudo :src="$e[0]" :nombre="$e[4] ?? __('Equipo')"/>
                                        </a>
                                        {{-- Con un solo equipo el desglose repite las columnas Punt. y %. --}}
                                        @if($varios)
                                            <small class="t-mono">{{ $e[2] ?? '' }} · {{ $e[3] ?? '' }}%</small>
                                        @endif
                                    </span>
                                @endforeach
                            </span>
                        </td>
                        <td class="t-pts">{{ $tecnico->puntaje }}</td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'torneoId' => $torneo_id]) }}">{{ $tecnico->jugados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'torneoId' => $torneo_id, 'tipo' => 'Ganados']) }}">{{ $tecnico->ganados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'torneoId' => $torneo_id, 'tipo' => 'Empatados']) }}">{{ $tecnico->empatados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'torneoId' => $torneo_id, 'tipo' => 'Perdidos']) }}">{{ $tecnico->perdidos }}</a></td>
                        <td>{{ $tecnico->golesl }}</td>
                        <td>{{ $tecnico->golesv }}</td>
                        <td>{{ $tecnico->diferencia }}</td>
                        <td>{{ $tecnico->porcentaje }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n técnico|:n técnicos', $goleadores->total(), ['n' => $goleadores->total()]) }}</div>
            <div class="ms-auto t-paginacion">{{ $goleadores->appends(request()->except('page'))->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo_id]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver al torneo') }}</a>
    </div>

@endsection

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
            <table class="t-tabla t-lista-tabla">
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
                        // Mismo molde que el histórico (torneos/tecnicos): escudos en la
                        // celda y el desglose por club en una fila que se despliega.
                        $dtClubes = clubesDesdeCadena($tecnico->escudo, ['pts', 'pct']);
                        foreach ($dtClubes as $dtIdx => $dtClub) {
                            $dtClubes[$dtIdx]['dato'] = __(':n pts', ['n' => $dtClub['pts']]).' · '.rtrim($dtClub['pct'], '%').'%';
                        }
                        $dtFilaId = 'dt-eq-' . $tecnico->tecnico_id;
                    @endphp
                    <tr>
                        <td class="t-pos">{{ $loop->iteration + ($goleadores->firstItem() ? $goleadores->firstItem() - 1 : 0) }}</td>
                        <td>
                            <x-celda-persona :href="route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id])"
                                             :nombre="$tecnico->tecnico"
                                             :foto="$tecnico->fotoTecnico"
                                             fotoDefecto="sin_foto_tecnico.png"
                                             :nacionalidad="$tecnico->nacionalidadTecnico"/>
                        </td>
                        <td class="t-izq">
                            <x-clubes-celda :clubes="$dtClubes" :id="$dtFilaId"/>
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

                    <x-clubes-detalle :clubes="$dtClubes" :id="$dtFilaId" :cols="3 + count($columns)"/>
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

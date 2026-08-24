@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')

    @php
        $columns = [
            'goles'      => 'Goles',
            'Jugada'     => 'Jugada',
            'Cabeza'     => 'Cabeza',
            'Penal'      => 'Penal',
            'Tiro_Libre' => 'T. Libre',
            'jugados'    => 'PJ',
            'promedio'   => 'Prom.',
        ];
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>Goleadores</h1>
        </div>

        <form class="d-flex gap-2">
            <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
            <input type="search" name="buscarpor" class="form-control form-control-sm" style="width: 220px"
                   placeholder="Buscar jugador"
                   value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
        </form>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Jugador</th>
                    <th class="t-izq">Equipos</th>
                    @foreach($columns as $key => $label)
                        <th>
                            @if($key != 'promedio')
                                <a href="{{ route('grupos.goleadoresPublic', [
                                        'torneoId'  => $torneo->id,
                                        'order'     => $key,
                                        'tipoOrder' => ($order == $key && $tipoOrder == 'ASC') ? 'DESC' : 'ASC',
                                    ]) }}">
                                    {{ $label }}
                                    @if($order == $key)
                                        <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                    @endif
                                </a>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($goleadores as $jugador)
                    <tr>
                        <td class="t-pos">{{ $loop->iteration + ($goleadores->firstItem() ? $goleadores->firstItem() - 1 : 0) }}</td>
                        <td>
                            <span class="t-nombre">
                                <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                    <img class="imgCircle" src="{{ url('images/'.($jugador->foto ?? 'sin_foto.png')) }}" alt="">
                                </a>
                                <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">{{ $jugador->jugador }}</a>
                                @if($jugador->nacionalidad)
                                    <img class="bandera" src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}" title="{{ $jugador->nacionalidad }}">
                                @endif
                            </span>
                        </td>
                        <td class="t-izq">
                            <span class="t-nombre">
                                @if($jugador->escudo)
                                    @foreach(explode(',', $jugador->escudo) as $escudo)
                                        @if($escudo)
                                            @php $escudoArr = explode('_', $escudo); @endphp
                                            <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                                <x-escudo :src="$escudoArr[0]" nombre="Equipo"/>
                                            </a>
                                        @endif
                                    @endforeach
                                @endif
                            </span>
                        </td>
                        <td class="t-pts"><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id]) }}">{{ $jugador->goles }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Jugada']) }}">{{ $jugador->Jugada }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Cabeza']) }}">{{ $jugador->Cabeza }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Penal']) }}">{{ $jugador->Penal }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Tiro Libre']) }}">{{ $jugador->Tiro_Libre }}</a></td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id]) }}">{{ $jugador->jugados }}</a></td>
                        <td>{{ $jugador->jugados ? number_format($jugador->goles / $jugador->jugados, 2) : '0,00' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ $goleadores->total() }} jugadores</div>
            <div class="ms-auto t-paginacion">{{ $goleadores->appends(request()->except('page'))->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-outline-secondary btn-sm">Volver al torneo</a>
    </div>

@endsection

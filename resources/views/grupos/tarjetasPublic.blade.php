@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="h3 mb-4 text-center text-primary">🟨🟥 Tarjetas</h1>


                {{-- Barra de búsqueda --}}
                <form class="d-flex justify-content-center mb-4">
                    <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
                    <input type="search" name="buscarpor" class="form-control me-2" placeholder="Buscar jugador"
                           value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}" style="width: 250px;">
                    <button class="btn btn-success" type="submit">Buscar</button>
                </form>


        @php
            $columns = [
                'amarillas' => 'Amarillas',
                'rojas' => 'Rojas',
                'jugados' => 'Jugados',
                'prom_amarillas' => 'Prom. A',
                'prom_rojas' => 'Prom. R',
            ];
        @endphp

        <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
            <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Jugador</th>
                <th>Equipos</th>
                @foreach($columns as $key => $label)
                    @php
                        $colOrder = ($order == $key) ? ($tipoOrder == 'ASC' ? 'DESC' : 'ASC') : 'ASC';
                    @endphp
                    <th>
                        <a href="{{ route('grupos.tarjetasPublic', ['torneoId' => $torneo->id, 'order' => $key, 'tipoOrder' => $colOrder]) }}" class="text-decoration-none text-white">
                            {{ $label }}
                            @if($order == $key)
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }} text-white"></i>
                            @endif
                        </a>
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($tarjetas as $jugador)
                <tr>
                    <td>{{ $i++ }}</td>
                    <td class="d-flex align-items-center gap-2">
                        <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                            <img class="imgCircle" src="{{ url('images/' . ($jugador->foto ?? 'sin_foto.png')) }}" width="35" height="35" alt="Foto">
                        </a>
                        {{ $jugador->jugador }}
                        <img src="{{ url('images/' . removeAccents($jugador->nacionalidad) . '.gif') }}" alt="{{ $jugador->nacionalidad }}">
                    </td>
                    <td>
                        @if($jugador->escudo)
                            @foreach(explode(',', $jugador->escudo) as $escudo)
                                @if($escudo != '')
                                    @php $escudoArr = explode('_', $escudo); @endphp
                                    <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                        <img src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    </td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id, 'tipo' => 'Amarillas']) }}">{{ $jugador->amarillas }}</a></td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id, 'tipo' => 'Rojas']) }}">{{ $jugador->rojas }}</a></td>
                    <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id]) }}">{{ $jugador->jugados }}</a></td>
                    <td>{{ $jugador->jugados ? round($jugador->amarillas / $jugador->jugados,2) : 0 }}</td>
                    <td>{{ $jugador->jugados ? round($jugador->rojas / $jugador->jugados,2) : 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Paginación y total --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>{{ $tarjetas->links() }}</div>
            <div><strong>Total: {{ $tarjetas->total() }}</strong></div>
        </div>

        <div class="d-flex mt-3">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success m-1">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection

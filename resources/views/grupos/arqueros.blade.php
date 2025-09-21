@extends('layouts.appPublic')

@section('pageTitle', 'Arqueros')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🧤 Arqueros</h1>

                {{-- Búsqueda --}}
        <form class="d-flex justify-content-center mb-4">
            <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
            <input type="search" name="buscarpor"
                   value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}"
                   class="form-control me-2" placeholder="Buscar arquero" style="width: 250px;">
            <button class="btn btn-success" type="submit">Buscar</button>
        </form>

        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
        @endphp

        {{-- Tabla de arqueros --}}
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                <thead class="table-dark text-center">
                <tr>
                    <th>#</th>
                    <th>Jugador</th>
                    <th>Equipos</th>
                    <th>
                        <a href="{{ route('grupos.arqueros', ['torneoId' => $torneo->id,'order'=>'jugados','tipoOrder'=>$tipoOrder]) }}" class="text-white text-decoration-none">
                            Jugados
                            @if($order=='jugados')
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('grupos.arqueros', ['torneoId' => $torneo->id,'order'=>'recibidos','tipoOrder'=>$tipoOrder]) }}" class="text-white text-decoration-none">
                            Goles
                            @if($order=='recibidos')
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('grupos.arqueros', ['torneoId' => $torneo->id,'order'=>'invictas','tipoOrder'=>$tipoOrder]) }}" class="text-white text-decoration-none">
                            Vallas invictas
                            @if($order=='invictas')
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                            @endif
                        </a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @foreach($arqueros as $i => $jugador)
                    <tr class="text-center">
                        <td>{{ $i + 1 }}</td>
                        <td class="d-flex align-items-center gap-2">
                            <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                <img src="{{ url('images/' . ($jugador->foto ?? 'sin_foto.png')) }}" class="imgCircle" width="35" height="35" alt="Foto">
                            </a>
                            {{ $jugador->jugador }}
                            <img src="{{ url('images/' . removeAccents($jugador->nacionalidad) . '.gif') }}" alt="{{ $jugador->nacionalidad }}">
                        </td>
                        <td>
                            @if($jugador->escudo)
                                @php $escudos = explode(',', $jugador->escudo); @endphp
                                @foreach($escudos as $escudo)
                                    @if($escudo)
                                        @php $escudoArr = explode('_', $escudo); @endphp
                                        <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                            <img src="{{ url('images/' . $escudoArr[0]) }}" height="25">
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id,'torneoId' => $torneo->id]) }}">{{ $jugador->jugados }}</a></td>
                        <td>{{ $jugador->recibidos }} (@if($jugador->jugados){{ round($jugador->recibidos / $jugador->jugados,2) }}@else 0 @endif)</td>
                        <td>{{ $jugador->invictas }} (@if($jugador->jugados){{ round($jugador->invictas / $jugador->jugados,2) }}@else 0 @endif)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginación y total --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>{{ $arqueros->links() }}</div>
            <div><strong>Total: {{ $arqueros->total() }}</strong></div>
        </div>

        {{-- Botón volver --}}
        <div class="d-flex mt-3">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection

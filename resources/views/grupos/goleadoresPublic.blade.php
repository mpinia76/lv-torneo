@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">⚽ Goleadores</h1>

                {{-- Barra de búsqueda --}}
                <form class="d-flex justify-content-center mb-4">
                    <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
                    <input type="search" name="buscarpor" class="form-control me-2" placeholder="Buscar jugador"
                           value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}" style="width: 250px;">
                    <button class="btn btn-success" type="submit">Buscar</button>
                </form>


                {{-- Tabla de goleadores --}}
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                        <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Jugador</th>
                            <th>Equipos</th>
                            @php
                                $columns = [
                                    'goles' => 'Goles',
                                    'Jugada' => 'Jugada',
                                    'Cabeza' => 'Cabeza',
                                    'Penal' => 'Penal',
                                    'Tiro_Libre' => 'Tiro Libre',
                                    'jugados' => 'Jugados',
                                    'promedio' => 'Prom.'
                                ];
                            @endphp

                            @foreach($columns as $key => $label)
                                <th>
                                    @if($key != 'promedio') {{-- Promedio no se ordena --}}
                                    <a href="{{ route('grupos.goleadoresPublic', [
                                            'torneoId' => $torneo->id,
                                            'order' => $key,
                                            'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC'
                                        ]) }}" class="text-decoration-none text-white">
                                        {{ $label }}
                                        @if($order==$key)
                                            <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
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
                                <td>{{ $loop->iteration }}</td>
                                <td class="d-flex align-items-center gap-2">
                                    <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                        <img class="imgCircle" src="{{ url('images/'.($jugador->foto ?? 'sin_foto.png')) }}" width="35" height="35" alt="Foto">
                                    </a>
                                    {{ $jugador->jugador }}
                                    <img src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}">
                                </td>
                                <td>
                                    @if($jugador->escudo)
                                        @foreach(explode(',', $jugador->escudo) as $escudo)
                                            @if($escudo)
                                                @php $escudoArr = explode('_', $escudo); @endphp
                                                <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                                    <img src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                                </a>
                                            @endif
                                        @endforeach
                                    @endif
                                </td>
                                <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->id]) }}">{{ $jugador->goles }}</a></td>
                                <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->id,'tipo'=>'Jugada']) }}">{{ $jugador->Jugada }}</a></td>
                                <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->id,'tipo'=>'Cabeza']) }}">{{ $jugador->Cabeza }}</a></td>
                                <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->id,'tipo'=>'Penal']) }}">{{ $jugador->Penal }}</a></td>
                                <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->id,'tipo'=>'Tiro Libre']) }}">{{ $jugador->Tiro_Libre }}</a></td>
                                <td><a href="{{ route('jugadores.jugados', ['jugadorId'=>$jugador->id]) }}">{{ $jugador->jugados }}</a></td>
                                <td>{{ $jugador->jugados ? round($jugador->goles / $jugador->jugados, 2) : 0 }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación y total --}}
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        {{ $goleadores->links() }}
                    </div>
                    <div>
                        <strong>Total: {{ $goleadores->total() }}</strong>
                    </div>
                </div>

                {{-- Botón volver --}}
                <div class="d-flex mt-3">
                    <a href="{{ route('torneos.ver', ['torneoId'=>$torneo->id]) }}" class="btn btn-success">Volver</a>
                </div>
            </div>
        </div>
    </div>
@endsection

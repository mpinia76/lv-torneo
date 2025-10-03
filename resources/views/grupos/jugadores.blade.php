@extends('layouts.appPublic')

@section('pageTitle', 'Jugadores')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="h3 mb-4 text-center text-primary">⚽ Jugadores</h1>

                <form class="d-flex justify-content-center mb-4">
                    <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
                    <input type="text" name="buscarpor" class="form-control form-control-sm" style="max-width: 200px;"
                           placeholder="Buscar" value="{{ request('buscarpor', session('nombre_filtro_jugador')) }}">
                    <button class="btn btn-success" type="submit">Buscar</button>
                </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle text-center" style="font-size: 14px;">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Jugador</th>
                    <th>Equipos</th>
                    @php
                        $columns = [
                            'jugados' => 'Jugados',
                            'Goles' => 'Goles',
                            'amarillas' => 'Amarillas',
                            'rojas' => 'Rojas',
                            'errados' => 'P. Errados',
                            'atajos' => 'P. Atajados',
                            'recibidos' => 'Arq. Recibidos',
                            'invictas' => 'Arq. V. Invictas',

                        ];
                    @endphp

                    @foreach($columns as $key => $label)
                        @php

                        @endphp
                        <th>
                            <a href="{{ route('grupos.jugadores', [
                                    'torneoId' => $torneo->id,
                                    'order' => $key,
                                    'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC'
                                ]) }}" class="text-decoration-none text-white">
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
                @foreach($jugadores as $jugador)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td class="d-flex align-items-center gap-2">
                            <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->jugador_id]) }}">
                                <img class="imgCircle" src="{{ url('images/' . ($jugador->foto ?? 'sin_foto.png')) }}" width="35" height="35" alt="Foto">
                            </a>
                            {{ $jugador->jugador }}
                            <img src="{{ url('images/' . removeAccents($jugador->nacionalidad) . '.gif') }}" alt="{{ $jugador->nacionalidad }}">
                        </td>
                        <td>
                            @if($jugador->escudo)
                                @php $escudos = explode(',', $jugador->escudo); @endphp
                                @foreach($escudos as $escudo)
                                    @if($escudo != '')
                                        @php $escudoArr = explode('_', $escudo); @endphp
                                        <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                            <img src="{{ url('images/' . $escudoArr[0]) }}" height="25">
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id]) }}">{{ $jugador->jugados }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id]) }}">{{ $jugador->goles }}</a></td>
                        <td><a href="{{ route('jugadores.tarjetas', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id,'tipo'=>'Amarilla']) }}">{{ $jugador->amarillas }}</a></td>
                        <td><a href="{{ route('jugadores.tarjetas', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id,'tipo'=>'Rojas']) }}">{{ $jugador->rojas }}</a></td>
                        <td><a href="{{ route('jugadores.penals', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id]) }}">{{ $jugador->errados+$jugador->atajados }} </a></td>

                        <td><a href="{{ route('jugadores.penals', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id,'tipo'=>'Atajos']) }}">{{ $jugador->atajos }}</a></td>
                        <td>{{ $jugador->recibidos }} (@if($jugador->jugados){{ round($jugador->recibidos / $jugador->jugados, 2) }}@else 0 @endif)</td>
                        <td>{{ $jugador->invictas }} (@if($jugador->jugados){{ round($jugador->invictas / $jugador->jugados, 2) }}@else 0 @endif)</td>

                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginación y total --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>{{ $jugadores->links() }}</div>
            <div><strong>Total: {{ $jugadores->total() }}</strong></div>
        </div>

        <div class="d-flex mt-2">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection

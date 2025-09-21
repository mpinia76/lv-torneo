@extends('layouts.appPublic')

@section('pageTitle', 'Plantillas')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">👕 Plantillas</h1>
        {{-- Selección de equipo --}}
        <form method="GET" class="mb-3 d-flex align-items-center">
            <div class="d-flex justify-content-center align-items-center flex-wrap gap-2">
            <input type="hidden" name="torneoId" value="{{ $torneo->id }}">
            <select class="form-control js-example-basic-single ms-2" name="equipo1" onchange="this.form.submit()">
                @foreach($equipos as $id => $equipo)
                    <option value="{{ $id }}" @if($id == $e1->id) selected @endif>{{ $equipo }}</option>
                @endforeach
            </select>

            </div>
        </form>

        {{-- Escudo --}}
        @if($e1->escudo)
            <div class="mb-3">
                <img src="{{ url('images/'.$e1->escudo) }}" height="100" alt="Escudo {{ $e1->nombre }}">
            </div>
        @endif

        @php
            $campos = [
                'dorsal' => 'Dorsal',
                'jugador' => 'Jugador',
                'nacimiento' => 'Edad',
                'tipoJugador' => 'Tipo',
                'jugados' => 'Jugados',
                'Goles' => 'Goles',
                'amarillas' => 'Amarillas',
                'rojas' => 'Rojas',
                'recibidos' => 'Arq. Recibidos',
                'invictas' => 'Arq. V. Invictas'
            ];
        @endphp

        {{-- Tabla de jugadores --}}
                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark text-center">
            <tr>
                @foreach($campos as $key => $label)
                    @php
                        $nextOrder = ($order == $key && $tipoOrder == 'ASC') ? 'DESC' : 'ASC';
                    @endphp
                    <th>
                        <a href="{{ route('torneos.plantillas', [
                            'torneoId' => $torneo->id,
                            'order' => $key,
                            'tipoOrder' => $nextOrder,
                            'equipo1' => $e1->id
                        ]) }}" class="text-decoration-none text-white">
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
            @foreach($jugadores as $jugador)
                <tr>
                    <td>{{ $jugador->dorsal }}</td>
                    <td>
                        <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->jugador_id]) }}">
                            <img src="{{ url('images/'.($jugador->foto ?? 'sin_foto.png')) }}" class="imgCircle me-1" width="30" height="30" alt="Foto">
                        </a>
                        {{ $jugador->jugador }}
                        <img src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}">
                    </td>
                    <td>{{ $jugador->edad }}</td>
                    <td>{{ $jugador->tipoJugador }}</td>
                    <td><a href="{{ route('jugadores.jugados', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id]) }}">{{ $jugador->jugados }}</a></td>
                    <td><a href="{{ route('jugadores.goles', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id]) }}">{{ $jugador->goles }}</a></td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id,'tipo'=>'Amarillas']) }}">{{ $jugador->amarillas }}</a></td>
                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId'=>$jugador->jugador_id,'torneoId'=>$torneo->id,'tipo'=>'Rojas']) }}">{{ $jugador->rojas }}</a></td>
                    <td>{{ $jugador->recibidos }} (@if($jugador->jugados){{ round($jugador->recibidos / $jugador->jugados, 2) }}@else 0 @endif)</td>
                    <td>{{ $jugador->invictas }} (@if($jugador->jugados){{ round($jugador->invictas / $jugador->jugados, 2) }}@else 0 @endif)</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Paginación y total --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>{{ $jugadores->links() }}</div>
            <div><strong>Total: {{ $jugadores->total() }}</strong></div>
        </div>

        {{-- Tabla de técnicos --}}
                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark text-center">
            <tr>
                <th>Técnico</th>
                <th>Edad</th>
                <th>Punt.</th>
                <th>J</th>
                <th>G</th>
                <th>E</th>
                <th>P</th>
                <th>GF</th>
                <th>GC</th>
                <th>Dif.</th>
                <th>%</th>
            </tr>
            </thead>
            <tbody>
            @foreach($tecnicosEquipo as $tecnico)
                <tr>
                    <td>
                        <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">
                            <img src="{{ url('images/'.($tecnico->fotoTecnico ?? 'sin_foto_tecnico.png')) }}" class="imgCircle me-1" width="30" height="30" alt="Foto Técnico">
                        </a>
                        {{ $tecnico->tecnico }}
                        <img src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}" alt="{{ $tecnico->nacionalidadTecnico }}">
                    </td>
                    <td>{{ $tecnico->edad }}</td>
                    <td>{{ $tecnico->puntaje }}</td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId'=>$tecnico->tecnico_id]) }}">{{ $tecnico->jugados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId'=>$tecnico->tecnico_id,'tipo'=>'Ganados']) }}">{{ $tecnico->ganados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId'=>$tecnico->tecnico_id,'tipo'=>'Empatados']) }}">{{ $tecnico->empatados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId'=>$tecnico->tecnico_id,'tipo'=>'Perdidos']) }}">{{ $tecnico->perdidos }}</a></td>
                    <td>{{ $tecnico->golesl }}</td>
                    <td>{{ $tecnico->golesv }}</td>
                    <td>{{ $tecnico->diferencia }}</td>
                    <td>{{ $tecnico->porcentaje }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="d-flex">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success m-1">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection

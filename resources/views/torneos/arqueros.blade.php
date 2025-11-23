@extends('layouts.appPublic')

@section('pageTitle', 'Arqueros')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="h3 mb-4 text-center text-primary">🧤 Arqueros</h1>

                <form class="form-inline mb-3 d-flex justify-content-between align-items-center" id="formulario">


                    <div class="d-flex align-items-center">
                        <!--<select class="form-control js-example-basic-single mr-3" id="torneoId" name="torneoId" onchange="enviarForm()">
                            @foreach($torneos as $torneo)
                                <option value="{{ $torneo->id }}" @if($torneo->id==$torneoId) selected @endif>
                                    {{ $torneo->nombre }} - {{ $torneo->year }}
                                </option>
                            @endforeach
                        </select>-->

                        <div class="form-check" style="margin-right: 20px;margin-left: 20px;">
                            <input type="checkbox" class="form-check-input" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="enviarForm()">
                            <label class="form-check-label" for="actuales">Jugando</label>
                        </div>
                    </div>



                    <div class="d-flex align-items-center">
                        <input type="search" name="buscarpor" class="form-control mr-2" placeholder="Buscar" value="{{ request('buscarpor', session('nombre_filtro_jugador')) }}">
                        <button class="btn btn-success" type="button" onclick="enviarForm()">Buscar</button>
                    </div>

                </form>


                <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                    <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Jugador</th>
                <th>Actual</th>
                @php
                    $columns = [
                        'jugados' => 'Jugados',
                        'recibidos' => 'Goles',
                        'invictas' => 'Vallas invictas'
                    ];
                @endphp
                @foreach($columns as $key => $label)
                    <th>
                        <a href="{{ route('torneos.arqueros', [
                            'torneoId' => $torneoId,
                            'order' => $key,
                            'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC',
                            'actuales' => $actuales
                        ]) }}" class="text-decoration-none text-white">
                            {{ $label }}
                            @if($order==$key)
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                            @endif
                        </a>
                    </th>
                @endforeach
                <th>Equipos</th>
            </tr>
            </thead>
            <tbody>
            @foreach($arqueros as $i => $jugador)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                            <img src="{{ $jugador->foto ? url('images/'.$jugador->foto) : url('images/sin_foto.png') }}" class="imgCircle" height="40">
                        </a>
                        {{ $jugador->jugador }}
                        <img src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}">
                    </td>
                    <td>
                        @if($jugador->jugando)
                            @foreach(explode(',', $jugador->jugando) as $esc)
                                @if($esc)
                                    @php $escArr = explode('_',$esc); @endphp
                                    <a href="{{ route('equipos.ver', ['equipoId' => $escArr[1]]) }}">
                                        <img src="{{ url('images/'.$escArr[0]) }}" height="25" alt="{{$escArr[2]}}">
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    </td>
                    <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id]) }}">{{ $jugador->jugados }}</a></td>
                    <td>{{ $jugador->recibidos }} ({{ $jugador->jugados ? round($jugador->recibidos / $jugador->jugados,2) : 0 }})</td>
                    <td>{{ $jugador->invictas }} ({{ $jugador->jugados ? round($jugador->invictas / $jugador->jugados,2) : 0 }})</td>
                    <td>
                        @if($jugador->escudo)
                            @foreach(explode(',', $jugador->escudo) as $escudo)
                                @if($escudo)
                                    @php $escudoArr = explode('_',$escudo); @endphp
                                    <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                        <img src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                    </a>
                                    ({{ $escudoArr[2] ?? '' }}) ({{ $escudoArr[3] ?? '' }})
                                @endif
                            @endforeach
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>{{ $arqueros->links() }}</div>
            <strong>Total: {{ $arqueros->total() }}</strong>
        </div>

        <div class="mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
            </div>
        </div>
    </div>

    <script>
        function enviarForm() {
            $('#formulario').submit();
        }
    </script>

@endsection

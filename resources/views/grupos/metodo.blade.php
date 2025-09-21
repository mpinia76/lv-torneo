@extends('layouts.appPublic')

@section('pageTitle', 'Método Paenza')

@section('content')
    <div class="container">

        <form class="form-inline mb-3">
            <input type="hidden" name="torneoId" value="{{ request()->get('torneoId', '') }}">

            <select class="form-control js-example-basic-single" id="fechaNumero" name="fechaNumero" onchange="this.form.submit()" style="width: 150px">
                @foreach($fechas as $f)
                    <option value="{{ $f->numero }}" @if($f->numero==$fecha->numero) selected @endif>
                        @if(is_numeric($f->numero))
                            Fecha {{ $f->numero }}
                        @else
                            {{ $f->numero }}
                        @endif
                    </option>
                @endforeach
            </select>
        </form>

        @php $i = 1; @endphp
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
            <tr>
                <th>#</th>
                <th style="width: 300px;">Equipo</th>
                <th>Puntos que enfrenta</th>
            </tr>
            </thead>
            <tbody>
            @foreach($arrPrimeros as $equipo)
                <tr>
                    <td>{{ $i++ }}</td>
                    <td class="d-flex align-items-center gap-2">
                        @if($equipo->foto)
                            <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                        @endif
                        <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">{{ $equipo->equipo }}</a>
                    </td>
                    <td>{{ $equipo->puntos }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @foreach($arrPosiciones as $nombre => $posiciones)
            @php $i = 1; @endphp
            @if(count($arrPosiciones) > 1)
                <h5 class="mt-4 mb-2">Grupo {{ $nombre }}</h5>
            @endif
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th style="width: 300px;">Equipo</th>
                    <th>Punt.</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                </tr>
                </thead>
                <tbody>
                @foreach($posiciones as $equipo)
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td class="d-flex align-items-center gap-2">
                            @if($equipo->foto)

                                <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                            @endif
                            <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">{{ $equipo->equipo }}</a>
                        </td>
                        <td>{{ $equipo->puntaje }}</td>
                        <td>{{ $equipo->jugados }}</td>
                        <td>{{ $equipo->ganados }}</td>
                        <td>{{ $equipo->empatados }}</td>
                        <td>{{ $equipo->perdidos }}</td>
                        <td>{{ $equipo->golesl }}</td>
                        <td>{{ $equipo->golesv }}</td>
                        <td>{{ $equipo->diferencia }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach

        <div class="d-flex">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection

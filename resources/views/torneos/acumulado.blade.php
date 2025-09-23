@extends('layouts.appPublic')

@section('pageTitle', 'Acumulado')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🌍 Acumulado</h1>



        @php
            // Colores predefinidos en orden
            $colorPalette = ['table-success', 'table-info', 'table-warning', 'table-secondary'];

            $colores = [];

            // Asignar colores a las clasificaciones según ID ascendente
            if ($torneo->clasificaciones) {
                $i = 0;
                foreach ($torneo->clasificaciones->sortBy('id') as $c) {
                    $colores[$c->nombre] = $colorPalette[$i] ?? 'table-secondary';
                    $i++;
                }
            }

            // Solo agregar color y badge de Descenso si $descenso > 0
            if ($torneo->descenso > 0) {
                $colores['Descenso'] = 'table-danger';
            }
        @endphp

        <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
            <thead class="table-dark">
            <th>#</th>
            <th>Equipo</th>
            <th>Punt.</th>
            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            </thead>
            <tbody>
            @foreach($acumulado as $i => $equipo)
                @php
                    $rowClass = $colores[$equipo->zona] ?? '';
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">
                            @if($equipo->foto)
                                <img src="{{ url('images/'.$equipo->foto) }}" height="25">
                            @endif
                        </a>
                        {{ $equipo->equipo }}
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

        <div class="mt-3">
            @foreach($colores as $nombre => $color)
                <span class="badge {{ str_replace('table-', 'bg-', $color) }}">{{ $nombre }}</span>
            @endforeach
        </div>

        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

        </div>
    </div>
@endsection

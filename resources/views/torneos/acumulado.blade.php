@extends('layouts.appPublic')

@section('pageTitle', __('Acumulado'))

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Acumulado') }}</h1>



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

            // El descenso puede venir por posición ($torneo->descenso) o por promedio
            // ($torneo->descenso_promedio). El controlador marca zona = 'Descenso' en los
            // dos casos, así que el color tiene que existir en los dos casos.
            if (($torneo->descenso ?? 0) > 0 || ($torneo->descenso_promedio ?? 0) > 0) {
                $colores['Descenso'] = 'table-danger';
            }
        @endphp

        <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
            <thead class="table-dark">
            <th>#</th>
            <th>{{ __('Equipo') }}</th>
            <th>{{ __('Punt.') }}</th>
            <th>{{ __('J') }}</th>
            <th>{{ __('G') }}</th>
            <th>{{ __('E') }}</th>
            <th>{{ __('P') }}</th>
            <th>GF</th>
            <th>{{ __('GC') }}</th>
            <th>{{ __('Dif.') }}</th>
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
                <span class="badge {{ str_replace('table-', 'bg-', $color) }}">{{ trad_dato($nombre) }}</span>
            @endforeach
        </div>

        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">{{ __('Volver') }}</a>
        </div>
    </div>

        </div>
    </div>
@endsection

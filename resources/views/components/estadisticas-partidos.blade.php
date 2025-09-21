@props([
    'data',       // colección de partidos
    'showNeutral' => false // si mostrar columna "Neutrales" o no
])

@php $i = 1; @endphp

<table class="table table-striped table-hover align-middle" style="font-size: 14px;">
    <thead class="table-dark">
    <tr>
        <th>#</th>
        <th>Fecha</th>
        <th>Día</th>
        <th>Local</th>
        <th>GL</th>
        <th>GV</th>
        <th>Visitante</th>
        <th>Acción</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $partido)
        <tr>
            <td>{{ $i++ }}</td>
            <td>
                @if(is_numeric($partido->numero))
                    Fecha {{ $partido->numero }}
                @else
                    {{ $partido->numero }}
                @endif
            </td>
            <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
            <td>
                <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol_id]) }}">
                    @if($partido->fotoLocal)<img src="{{ url('images/'.$partido->fotoLocal) }}" height="20">@endif
                    {{ $partido->local }}
                    <img src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                </a>
            </td>
            <td>
                {{ $partido->golesl }} @isset($partido->penalesl) ({{ $partido->penalesl }}) @endisset
            </td>
            <td>
                {{ $partido->golesv }} @isset($partido->penalesv) ({{ $partido->penalesv }}) @endisset
            </td>
            <td>
                <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov_id]) }}">
                    @if($partido->fotoVisitante)<img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">@endif
                    {{ $partido->visitante }}
                    <img src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
                </a>
            </td>
            <td>
                <a href="{{ route('fechas.detalle', ['partidoId' => $partido->partido_id]) }}" class="btn btn-success btn-sm">Detalles</a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

@props([
    'data',               // colección de partidos
    'showNeutral' => false, // si mostrar columna "Neutrales" o no
    'showTorneo' => false   // si mostrar columna "Torneo (Año)"
])

@php $i = 1; @endphp

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle text-center">
        <thead class="table-dark">
        <tr>
            <th>#</th>
            @if($showTorneo)
                <th>Torneo</th>
            @endif
            <th>Fecha</th>
            <th>Día</th>
            <th>Local</th>
            <th>GL</th>
            <th>GV</th>
            <th>Visitante</th>
            @if($showNeutral)
                <th>Neutral</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($data as $partido)
            <tr class="clickable-row"
                data-href="{{ route('fechas.detalle', ['partidoId' => $partido->partido_id]) }}"
                style="cursor: pointer;">
                <td>{{ $i++ }}</td>

                {{-- Torneo --}}
                @if($showTorneo)
                    <td>
                        @if(!empty($partido->escudoTorneo))
                            <img src="{{ url('images/'.$partido->escudoTorneo) }}"
                                 alt="Escudo {{ $partido->nombreTorneo }}"
                                 width="24" height="24"
                                 class="me-2 d-inline img-fluid rounded">
                        @endif
                        {{ $partido->nombreTorneo }} {{ $partido->year }}
                    </td>
                @endif

                {{-- Fecha --}}
                <td>
                    @if(is_numeric($partido->numero))
                        Fecha {{ $partido->numero }}
                    @else
                        {{ $partido->numero }}
                    @endif
                </td>

                {{-- Día --}}
                <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '-' }}</td>

                {{-- Local --}}
                <td class="text-end">
                    @if($partido->fotoLocal)
                        <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="me-1">
                    @endif
                    {{ $partido->local }}
                    <img src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}"
                         alt="{{ $partido->paisLocal }}" height="15">
                </td>

                {{-- Goles Local --}}
                <td class="fw-bold">
                    {{ $partido->golesl }}
                    @if(!empty($partido->penalesl))
                        <small>({{ $partido->penalesl }})</small>
                    @endif
                </td>

                {{-- Goles Visitante --}}
                <td class="fw-bold">
                    {{ $partido->golesv }}
                    @if(!empty($partido->penalesv))
                        <small>({{ $partido->penalesv }})</small>
                    @endif
                </td>

                {{-- Visitante --}}
                <td class="text-start">
                    @if($partido->fotoVisitante)
                        <img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20" class="me-1">
                    @endif
                    {{ $partido->visitante }}
                    <img src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}"
                         alt="{{ $partido->paisVisitante }}" height="15">
                </td>

                {{-- Neutral (si aplica) --}}
                @if($showNeutral)
                    <td>{{ $partido->neutral ?? '-' }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- Script para hacer las filas clickeables --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', () => {
                window.location.href = row.dataset.href;
            });
        });
    });
</script>

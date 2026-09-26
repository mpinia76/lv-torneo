{{-- Tablita de temporadas para los récords de estadísticas totales. Recibe $esFilas y $esNum. --}}
@if(count($esFilas))
    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>{{ __('Temporada') }}</th>
                    <th>{{ __('PJ') }}</th>
                    <th>{{ __('Goles') }}</th>
                    <th>{{ __('Prom.') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($esFilas as $esT)
                    <tr>
                        <td>
                            <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $esT->torneo_id]) }}">
                                <x-escudo :src="$esT->escudo" :nombre="$esT->nombre" tam="sm"/>
                                {{ $esT->nombre }} {{ $esT->year }}
                            </a>
                        </td>
                        <td>{{ $esT->partidos }}</td>
                        <td>{{ $esT->goles }}</td>
                        <td class="t-pts">{{ $esNum($esT->promedio) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <p class="t-lista-ayuda">{{ __('No hay temporadas con partidos suficientes.') }}</p>
@endif

{{--
    Selectores de zona y competencia (tabla histórica, estadísticas totales).
    Va dentro de un <form> GET. Necesita $zonasDatos, $grupos, $zona,
    $zonaActual y $competencia (TorneoController::filtroZona). Con
    $permiteTodas se ofrece "Todas las zonas" (zona vacía). Cambiar de zona
    vuelve a "todas las competencias" y, si está, a "todos los países".
--}}
@php
    /* Zonas agrupadas como en el menú de torneos: Argentina sola arriba, después
       Mundial, Sudamérica, Europa… (la sección 'local' no lleva título). */
    $fzZonasPorGrupo = [];
    foreach ($zonasDatos as $fzZ) {
        $fzZonasPorGrupo[$fzZ['grupo']][] = $fzZ;
    }

    $fzComps = ['vigentes' => [], 'historicas' => []];
    foreach (($zonaActual['competencias'] ?? []) as $fzC) {
        $fzComps[$fzC['historica'] ? 'historicas' : 'vigentes'][] = $fzC;
    }
@endphp

<label class="t-lista-rot" for="fzZona">{{ __('Zona') }}</label>
<select id="fzZona" name="zona" class="t-lista-select"
        onchange="if (this.form.competencia) { this.form.competencia.value=''; } if (this.form.paisEquipo) { this.form.paisEquipo.value=''; } this.form.submit()">
    @if(!empty($permiteTodas))
        <option value="" @if($zona === '') selected @endif>{{ __('Todas las zonas') }}</option>
    @endif
    @foreach($fzZonasPorGrupo as $fzGrupo => $fzZonas)
        @if(($grupos[$fzGrupo] ?? '') === '')
            @foreach($fzZonas as $fzZ)
                <option value="{{ $fzZ['clave'] }}" @if($fzZ['clave'] === $zona) selected @endif>{{ $fzZ['nombre'] }}</option>
            @endforeach
        @else
            <optgroup label="{{ $grupos[$fzGrupo] }}">
                @foreach($fzZonas as $fzZ)
                    <option value="{{ $fzZ['clave'] }}" @if($fzZ['clave'] === $zona) selected @endif>{{ $fzZ['nombre'] }}</option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</select>

@if($zonaActual)
<label class="t-lista-rot" for="fzCompetencia">{{ __('Competencia') }}</label>
<select id="fzCompetencia" name="competencia" class="t-lista-select" onchange="this.form.submit()">
    <option value="">{{ __('Todas las competencias') }}</option>
    @foreach($fzComps['vigentes'] as $fzC)
        <option value="{{ $fzC['clave'] }}" @if($fzC['clave'] === $competencia) selected @endif>{{ $fzC['nombre'] }}</option>
    @endforeach
    @if($fzComps['historicas'])
        <optgroup label="{{ __('Históricas') }}">
            @foreach($fzComps['historicas'] as $fzC)
                <option value="{{ $fzC['clave'] }}" @if($fzC['clave'] === $competencia) selected @endif>{{ $fzC['nombre'] }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
@endif

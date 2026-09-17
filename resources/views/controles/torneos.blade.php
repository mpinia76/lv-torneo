@extends('layouts.app')

@section('pageTitle', 'Control de torneos')

@section('content')
    @php
        $cssControles = public_path('css/controles.css');
        $cssVersion   = is_file($cssControles) ? filemtime($cssControles) : null;
        $ayudas = [
            'sobran'         => 'Tienen más equipos con plantilla que los que dice el torneo (campo «Nro. de equipos»). Abajo de cada uno: los equipos con plantilla que no jugaron ningún partido, que suelen ser los que sobran.',
            'faltan'         => 'Tienen menos equipos con plantilla que los que dice el torneo. Abajo de cada uno: los equipos que juegan partidos pero no tienen plantilla, que suelen explicar el faltante.',
            'partidos_faltan' => 'La cantidad de equipos coincide, pero hay fechas con menos partidos que la mitad de los equipos de su grupo (o el torneo no tiene partidos). Suele ser un torneo cargado DT por DT que no quedó marcado como parcial. Las fechas de playoffs no se miran.',
            'sin_posiciones' => 'Equipos y fechas completos, y no hay ninguna posición guardada («Finalizar»). Los que todavía tienen partidos sin resultado salen marcados «en curso».',
        ];
    @endphp
    <link href="{{ asset('css/controles.css').($cssVersion ? '?v='.$cssVersion : '') }}" rel="stylesheet">
    <style>
        .ctrl-ctor .select2-container { vertical-align: bottom; }
        .ctrl-ctor .ctrl-num { text-align: center; white-space: nowrap; }
        .ctrl-ctor .ctrl-eqs { margin-top: .3em; font-size: .85em; }
        .ctrl-ctor .ctrl-eqs img { height: 1.2em; vertical-align: middle; margin-right: .2em; }
    </style>

    <div class="container-fluid ctrl ctrl-ctor">

        <h1 class="display-6">Control de torneos</h1>
        <p class="ctrl-intro">
            Compara los equipos con plantilla de cada torneo contra el «Nro. de equipos» del torneo,
            y marca los completos que todavía no tienen las posiciones finales guardadas. Solo lee: no cambia nada.
        </p>

        <div class="ctrl-filtros">
            <div class="ctrl-acciones">
                <a href="{{ route('controles.index') }}" class="btn btn-default btn-sm">Controles de carga</a>
                <a href="{{ route('torneos.index') }}" class="btn btn-success btn-sm">Volver a torneos</a>
            </div>

            <form method="GET" action="{{ route('controles.torneos') }}" style="display:inline">
                <input type="hidden" name="lista" value="{{ $lista }}">

                <div class="ctrl-campo">
                    <label>Año</label>
                    <select name="year" class="s2" data-placeholder="Todos" onchange="this.form.submit()">
                        <option value=""></option>
                        @foreach($anios as $anioFiltro)
                            <option value="{{ $anioFiltro }}" @if($filtros['year'] == $anioFiltro) selected @endif>{{ $anioFiltro }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ctrl-campo">
                    <label>Tipo</label>
                    <select name="tipo" class="s2" data-placeholder="Todos" onchange="this.form.submit()">
                        <option value=""></option>
                        @foreach($tiposTorneo as $tipoFiltro)
                            <option value="{{ $tipoFiltro }}" @if($filtros['tipo'] == $tipoFiltro) selected @endif>{{ $tipoFiltro }}</option>
                        @endforeach
                    </select>
                </div>

                @if($lista === 'sin_posiciones')
                    <div class="ctrl-campo">
                        <label>Estado</label>
                        <select name="estado" class="s2" data-placeholder="Todos" onchange="this.form.submit()">
                            <option value=""></option>
                            <option value="terminados" @if($filtros['estado'] === 'terminados') selected @endif>Terminados</option>
                            <option value="en_curso" @if($filtros['estado'] === 'en_curso') selected @endif>En curso</option>
                        </select>
                    </div>
                @endif

                <div class="ctrl-campo">
                    <label>Torneo</label>
                    <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="buscar...">
                </div>

                <div class="ctrl-campo">
                    <label>&nbsp;</label>
                    <label style="text-transform:none;font-weight:400;display:inline">
                        <input type="checkbox" name="parciales" value="1" @if($filtros['parciales']) checked @endif
                               onchange="this.form.submit()">
                        incluir parciales (DT por DT)
                    </label>
                </div>

                <div class="ctrl-campo">
                    <button class="btn btn-primary btn-sm">Filtrar</button>
                    @if($filtros['year'] || $filtros['tipo'] || $filtros['q'] || $filtros['parciales'] || $filtros['estado'])
                        <a class="btn btn-default btn-sm" href="{{ route('controles.torneos', ['lista' => $lista]) }}">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="ctrl-menu">
                    <div class="ctrl-menu-grupo">Torneos</div>
                    @foreach($listas as $claveLista => $tituloLista)
                        <a href="{{ route('controles.torneos', array_filter(['lista' => $claveLista] + $filtros)) }}"
                           class="{{ $claveLista === $lista ? 'activo' : '' }}"
                           title="{{ $ayudas[$claveLista] }}">
                            <span class="ctrl-total {{ $conteos[$claveLista] ? 'hay' : 'limpio' }}">{{ $conteos[$claveLista] }}</span>
                            {{ $tituloLista }}
                        </a>
                    @endforeach
                </div>
                @if(!$filtros['parciales'])
                    <p class="ctrl-sub" style="margin-top:.6em">
                        Los torneos <b>parciales</b> quedan afuera: solo tienen los partidos de un equipo,
                        así que siempre les «faltan» equipos.
                    </p>
                @endif
            </div>

            <div class="col-md-9">
                <div class="ctrl-cabecera">
                    <h2>Torneos · {{ $listas[$lista] }}</h2>
                    <p class="ctrl-ayuda">{{ $ayudas[$lista] }}</p>
                </div>

                @if($filas->total() === 0)
                    <div class="ctrl-vacio">No hay torneos en esta lista con los filtros puestos.</div>
                @else
                    <table class="ctrl-tabla">
                        <thead>
                        <tr>
                            <th>Torneo</th>
                            <th class="ctrl-num">Declarados</th>
                            <th class="ctrl-num">Con plantilla</th>
                            @if(in_array($lista, ['sobran', 'faltan'], true))
                                <th class="ctrl-num">Diferencia</th>
                            @endif
                            <th class="ctrl-num">Partidos</th>
                            <th class="ctrl-num">Posiciones</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($filas as $filaTorneo)
                            @php $detTorneo = $detalle[$filaTorneo->id] ?? null; @endphp
                            <tr>
                                <td>
                                    <b>{{ $filaTorneo->nombre }} {{ $filaTorneo->year }}</b>
                                    <span class="ctrl-sub">#{{ $filaTorneo->id }} · {{ $filaTorneo->tipo }} · {{ $filaTorneo->ambito }}</span>
                                    @if($filaTorneo->parcial)
                                        <span class="ctrl-chip neutro">parcial</span>
                                    @endif
                                    @if($filaTorneo->fechas_incompletas > 0)
                                        <span class="ctrl-chip mal" title="Fechas con menos partidos que la mitad de los equipos del grupo">{{ $filaTorneo->fechas_incompletas }} de {{ $filaTorneo->fechas_tabla }} fechas a medias</span>
                                    @endif
                                    @if($filaTorneo->partidos == 0)
                                        <span class="ctrl-chip neutro">sin partidos</span>
                                    @elseif($filaTorneo->sin_resultado > 0)
                                        <span class="ctrl-chip aviso" title="Partidos sin resultado cargado">en curso · {{ $filaTorneo->sin_resultado }} sin resultado</span>
                                    @endif

                                    @if($detTorneo)
                                        @if(count($detTorneo['sin_partidos']))
                                            <div class="ctrl-eqs">
                                                <span class="ctrl-motivo">Con plantilla y sin partidos:</span>
                                                @foreach($detTorneo['sin_partidos'] as $eqDet)
                                                    <span class="ctrl-chip mal">
                                                        @if($eqDet->escudo)<img src="{{ url('images/'.$eqDet->escudo) }}" alt="">@endif{{ $eqDet->nombre }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(count($detTorneo['sin_plantilla']))
                                            <div class="ctrl-eqs">
                                                <span class="ctrl-motivo">Juegan y no tienen plantilla:</span>
                                                @foreach($detTorneo['sin_plantilla'] as $eqDet)
                                                    <span class="ctrl-chip aviso">
                                                        @if($eqDet->escudo)<img src="{{ url('images/'.$eqDet->escudo) }}" alt="">@endif{{ $eqDet->nombre }} ({{ $eqDet->partidos }} PJ)
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(!count($detTorneo['sin_partidos']) && !count($detTorneo['sin_plantilla']))
                                            <div class="ctrl-eqs ctrl-sub">
                                                Plantillas y partidos coinciden: el que puede estar mal es el «Nro. de equipos» del torneo.
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="ctrl-num">{{ $filaTorneo->esperados }}</td>
                                <td class="ctrl-num">{{ $filaTorneo->cargados }}</td>
                                @if(in_array($lista, ['sobran', 'faltan'], true))
                                    <td class="ctrl-num">
                                        <span class="ctrl-chip {{ $filaTorneo->diferencia > 0 ? 'mal' : 'aviso' }}">{{ $filaTorneo->diferencia > 0 ? '+' : '' }}{{ $filaTorneo->diferencia }}</span>
                                    </td>
                                @endif
                                <td class="ctrl-num">{{ $filaTorneo->partidos }}</td>
                                <td class="ctrl-num">
                                    @if($filaTorneo->posiciones)
                                        <span class="ctrl-chip ok">{{ $filaTorneo->posiciones }}</span>
                                    @else
                                        <span class="ctrl-chip mal">0</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ctrl-botones">
                                        <a href="{{ route('torneos.show', $filaTorneo->id) }}" class="ctrl-b-azul" target="_blank" rel="noopener">Ver</a>
                                        <a href="{{ route('torneos.edit', $filaTorneo->id) }}" class="ctrl-b-gris" target="_blank" rel="noopener">Editar</a>
                                        <a href="{{ route('torneos.finalizar', ['torneoId' => $filaTorneo->id]) }}" class="ctrl-b-verde" target="_blank" rel="noopener">Posiciones</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="ctrl-pie">
                        {{ $filas->links() }}
                        <strong>{{ $filas->total() }} torneos</strong>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('bottom')
    {{-- jQuery y select2 vienen del layout. --}}
    <script>
        $(function () {
            if (!$.fn.select2) { return; }
            $('.ctrl-ctor .s2').each(function () {
                $(this).select2({
                    width: '150px',
                    placeholder: $(this).data('placeholder') || '',
                    allowClear: true
                });
            });
        });
    </script>
@endsection

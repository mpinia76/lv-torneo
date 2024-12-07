@extends('layouts.appPublic')

@section('pageTitle', 'Otras estadísticas')
<style>
    /* Estilos personalizados para resaltar la pestaña activa */
    .nav-link.active {
        background-color: #007bff; /* Cambia el color de fondo de la pestaña activa */
        color: #fff; /* Cambia el color del texto de la pestaña activa */
        border-color: #007bff; /* Cambia el color del borde de la pestaña activa */
    }

    /* Agrega un espacio entre las pestañas y el contenido */
    .tab-content {
        margin: 20px; /* Ajusta el margen superior del contenido */
    }
</style>
@section('content')
    <div class="container">


        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="torneos-tab" data-toggle="tab" href="#torneos" role="tab" aria-controls="torneos" aria-selected="true">Torneos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="fechas-tab" data-toggle="tab" href="#fechas" role="tab" aria-controls="fechas" aria-selected="false">Fechas</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" id="partidos-tab" data-toggle="tab" href="#partidos" role="tab" aria-controls="partidos" aria-selected="false">Partidos</a>
            </li>

        </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="torneos">
        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>


                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>


                    </thead>
                    <tbody>

                    @foreach($estadisticas['torneoMasGoles'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>


                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>
                <h1>Locales</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>


                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>


                    </thead>
                    <tbody>

                    @foreach($estadisticas['torneoMasGolesLocales'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>


                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>
                <h1>Visitantes</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>


                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>


                    </thead>
                    <tbody>

                    @foreach($estadisticas['torneoMasGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>


                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>
            </div>
        </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="fechas">
                <div class="row">

                    <div class="form-group col-md-12">

                        <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>


                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGoles'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>

                <h1>Locales</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGolesLocales'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>

                <h1>Visitantes</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="partidos">
                <div class="row">

                    <div class="form-group col-md-12">

                        <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['maxGoles'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.$partido->paisLocal.'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if($partido->penalesl)
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if($partido->penalesv)
                                    ({{$partido->penalesv}})
                                @endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.$partido->paisVisitante.'.gif') }}" alt="{{ $partido->paisVisitante }}">
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">

                                    <a href="{{route('fechas.detalle', array('partidoId' => $partido->partido_id))}}" class="btn btn-success m-1">Detalles</a>


                                </div>

                            </td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>


                <h1>Locales</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['maxGolesLocales'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.$partido->paisLocal.'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if($partido->penalesl)
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if($partido->penalesv)
                                    ({{$partido->penalesv}})
                                @endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.$partido->paisVisitante.'.gif') }}" alt="{{ $partido->paisVisitante }}">
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">

                                    <a href="{{route('fechas.detalle', array('partidoId' => $partido->partido_id))}}" class="btn btn-success m-1">Detalles</a>


                                </div>

                            </td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>
                <h1>Visitantes</h1>
                <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['maxGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>@if(is_numeric($partido->numero))
                                    Fecha {{ $partido->numero }}
                                @else
                                    {{ $partido->numero }}</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.$partido->paisLocal.'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if($partido->penalesl)
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if($partido->penalesv)
                                    ({{$partido->penalesv}})
                                @endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.$partido->paisVisitante.'.gif') }}" alt="{{ $partido->paisVisitante }}">
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">

                                    <a href="{{route('fechas.detalle', array('partidoId' => $partido->partido_id))}}" class="btn btn-success m-1">Detalles</a>


                                </div>

                            </td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>
                    </div>
                </div>
            </div>

            </div>


        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

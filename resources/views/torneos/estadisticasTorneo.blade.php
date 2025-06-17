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
                <a class="nav-link active" id="goles-tab" data-toggle="tab" href="#goles" role="tab" aria-controls="goles" aria-selected="true">Goles</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="fechaMasGol-tab" data-toggle="tab" href="#fechaMasGol" role="tab" aria-controls="fechaMasGol" aria-selected="false">Fechas con más goles</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="fechaMenosGol-tab" data-toggle="tab" href="#fechaMenosGol" role="tab" aria-controls="fechaMenosGol" aria-selected="false">Fechas con menos goles</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="partidoMasGol-tab" data-toggle="tab" href="#partidoMasGol" role="tab" aria-controls="partidoMasGol" aria-selected="false">Partidos con más goles</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="partidoMenosGol-tab" data-toggle="tab" href="#partidoMenosGol" role="tab" aria-controls="partidoMenosGol" aria-selected="false">Partidos con menos goles</a>
            </li>
        </ul>




        <br>

        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="goles">
                <div class="row">
                    <div class="form-group col-md-12">

                        <table class="table" style="width: 100%;font-size: 14px;">
                            <thead>
                            <th>Partidos</th>
                            <th>Total</th>

                            <th>Promedio</th>
                            <th>Locales</th>
                            <th>Promedio</th>
                            <th>Visitante</th>
                            <th>Promedio</th>
                            <th>Neutrales</th>
                            <th>Promedio</th>
                            </thead>
                            <tbody>

                            @foreach($estadisticas['goles'] as $partido)
                                <tr>

                                    <td>{{$partido->total_partidos}}</td>

                                    <td>{{$partido->total_goles}}</td>
                                    <td>{{$partido->promedio_total}}</td>

                                    <td>{{$partido->goles_local}}</td>
                                    <td>{{$partido->promedio_local}}</td>
                                    <td>{{$partido->goles_visitante}}</td>
                                    <td>{{$partido->promedio_visitante}}</td>
                                    <td>{{$partido->goles_neutral}}</td>
                                    <td>{{$partido->promedio_neutral}}</td>

                                </tr>
                            @endforeach
                            </tbody>


                        </table>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="fechaMasGol">
                <div class="row">
                    <table class="table" style="width: 100%;font-size: 14px;">
                        <thead>
                        <th>#</th>
                        <th>Fecha</th>

                        <th>Goles</th>
                        <th>Promedio</th>
                        <th>Partidos</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['fechaMasGoles'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>
                                    @if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif
                                </td>

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
                        <th>#</th>
                        <th>Fecha</th>

                        <th>Goles</th>
                        <th>Promedio</th>
                        <th>Partidos</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['fechaMasGolesLocales'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

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
                        <th>#</th>
                        <th>Fecha</th>

                        <th>Goles</th>
                        <th>Promedio</th>
                        <th>Partidos</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['fechaMasGolesVisitantes'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

                                <td>{{$partido->goles}}</td>
                                <td>{{$partido->promedio}}</td>

                                <td>{{$partido->partidos}}</td>

                            </tr>
                        @endforeach
                        </tbody>


                    </table>
                    <h1>Neutrales</h1>
                    <table class="table" style="width: 100%;font-size: 14px;">
                        <thead>
                        <th>#</th>
                        <th>Fecha</th>

                        <th>Goles</th>
                        <th>Promedio</th>
                        <th>Partidos</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['fechaMasGolesNeutrales'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

                                <td>{{$partido->goles}}</td>
                                <td>{{$partido->promedio}}</td>

                                <td>{{$partido->partidos}}</td>

                            </tr>
                        @endforeach
                        </tbody>


                    </table>
                </div>
            </div>

            <div role="tabpanel" class="tab-pane" id="partidoMasGol">
                <div class="row">
                    <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <h1>Neutrales</h1>
                    <table class="table" style="width: 100%;font-size: 14px;">
                        <thead>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Local</th>
                        <th>GL</th>
                        <th>GV</th>
                        <th>Visitante</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['maxGolesNeutrales'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                                <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                                <td>
                                    <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                        @if($partido->local)
                                            @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                            @endif
                                    </a>
                                    {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                    @endif
                                </td>
                                <td>{{$partido->golesl}}
                                    @if(isset($partido->penalesl))
                                        ({{$partido->penalesl}})
                                    @endif
                                </td>
                                <td>{{$partido->golesv}}
                                    @if(isset($partido->penalesv))
                                        ({{$partido->penalesv}})
                                    @endif
                                </td>
                                <td>
                                    <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                        @if($partido->visitante)
                                            @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                            @endif
                                    </a>
                                    {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
            <div role="tabpanel" class="tab-pane" id="fechaMenosGol">
                <div class="row">
                    <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

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
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

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
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>

                            <td>{{$partido->partidos}}</td>

                        </tr>
                    @endforeach
                    </tbody>


                </table>
                    <h1>Neutrales</h1>
                    <table class="table" style="width: 100%;font-size: 14px;">
                        <thead>
                        <th>#</th>
                        <th>Fecha</th>

                        <th>Goles</th>
                        <th>Promedio</th>
                        <th>Partidos</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['fechaMinGolesNeutrales'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>

                                <td>{{$partido->goles}}</td>
                                <td>{{$partido->promedio}}</td>

                                <td>{{$partido->partidos}}</td>

                            </tr>
                        @endforeach
                        </tbody>


                    </table>
                </div>
            </div>

            <div role="tabpanel" class="tab-pane" id="partidoMenosGol">
                <div class="row">
                    <table class="table" style="width: 100%;font-size: 14px;">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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
                    <h1>Neutrales</h1>
                    <table class="table" style="width: 100%;font-size: 14px;">
                        <thead>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Local</th>
                        <th>GL</th>
                        <th>GV</th>
                        <th>Visitante</th>

                        </thead>
                        <tbody>
                        @php
                            $i = 1;
                        @endphp
                        @foreach($estadisticas['minGolesNeutrales'] as $partido)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>@if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif</td>
                                <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                                <td>
                                    <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                        @if($partido->local)
                                            @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                            @endif
                                    </a>
                                    {{$partido->local}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                                    @endif
                                </td>
                                <td>{{$partido->golesl}}
                                    @if(isset($partido->penalesl))
                                        ({{$partido->penalesl}})
                                    @endif
                                </td>
                                <td>{{$partido->golesv}}
                                    @if(isset($partido->penalesv))
                                        ({{$partido->penalesv}})
                                    @endif
                                </td>
                                <td>
                                    <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                        @if($partido->visitante)
                                            @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                            @endif
                                    </a>
                                    {{$partido->visitante}} <img id="original" src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
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

        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

@extends('layouts.appPublic')

@section('pageTitle', 'Fecha')

@section('content')
    <div class="container">
        <h1 class="display-6">
            @if(is_numeric($fecha->numero))
                Fecha {{ $fecha->numero }}
            @else
                {{ $fecha->numero }}
            @endif
        </h1>

        <hr/>

        <div class="row">
            <div class="form-group col-md-12">
                <table class="table table-hover" style="width: 70%">
                    <thead>
                    <th>Fecha</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>
                    </thead>

                    <tbody>
                    @foreach($fecha->partidos as $partido)
                        @if($partido->dia)
                            <tr class="clickable-row" data-href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}">
                                <td>{{ date('d/m/Y H:i', strtotime($partido->dia)) }}</td>

                                <td>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">
                                        @if($partido->equipol && $partido->equipol->escudo)
                                            <img src="{{ url('images/'.$partido->equipol->escudo) }}" height="20" alt="{{ $partido->equipol->nombre }}">
                                        @endif
                                    </a>
                                    {{ $partido->equipol->nombre }}
                                    <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}">
                                </td>

                                <td>
                                    {{ $partido->golesl }}
                                    @if(isset($partido->penalesl))
                                        ({{ $partido->penalesl }})
                                    @endif
                                </td>

                                <td>
                                    {{ $partido->golesv }}
                                    @if(isset($partido->penalesv))
                                        ({{ $partido->penalesv }})
                                    @endif
                                </td>

                                <td>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">
                                        @if($partido->equipov && $partido->equipov->escudo)
                                            <img src="{{ url('images/'.$partido->equipov->escudo) }}" height="20" alt="{{ $partido->equipov->nombre }}">
                                        @endif
                                    </a>
                                    {{ $partido->equipov->nombre }}
                                    <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.clickable-row');
            rows.forEach(row => {
                row.addEventListener('click', function() {
                    window.location = this.dataset.href;
                });
            });
        });
    </script>

    <style>
        .clickable-row {
            cursor: pointer;
        }
        .clickable-row:hover {
            background-color: #e6ffe6;
        }
    </style>
@endsection

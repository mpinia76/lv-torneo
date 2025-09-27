@extends('layouts.appPublic')

@section('pageTitle', 'Ver técnico')

@section('content')
    <style>
        /* Estilos de pestañas */
        .nav-link.active {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .tab-content {
            margin: 20px 0;
        }

        /* Tablas */
        table.table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 14px;
        }

        table.table thead {
            background-color: #343a40;
            color: #fff;
        }

        table.table th, table.table td {
            padding: 8px 12px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }

        table.table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        table.table tbody tr:hover {
            background-color: #e2e6ea;
        }

        .btn-volver {
            margin-top: 20px;
        }
    </style>

    <div class="container">

        {{-- Información principal del técnico --}}
        <div class="row">
            <div class="form-group col-md-3 text-center">
                @if($tecnico->persona->foto)
                    <img src="{{ url('images/'.$tecnico->persona->foto) }}" height="200">
                @else
                    <img src="{{ url('images/sin_foto_tecnico.png') }}" height="200">
                @endif
            </div>

            <div class="form-group col-md-9">
                <div class="row">
                    <div class="form-group col-md-3">
                        <dt>Nombre</dt>
                        <dd>{{$tecnico->persona->name}}</dd>
                    </div>
                    <div class="form-group col-md-3">
                        <dt>Completo</dt>
                        <dd>{{$tecnico->persona->nombre}} {{$tecnico->persona->apellido}}</dd>
                    </div>
                    <div class="form-group col-md-3">
                        <dt>Ciudad Nacimiento</dt>
                        <dd>{{$tecnico->persona->ciudad}}</dd>
                    </div>
                    <div class="form-group col-md-3">
                        <dt>Edad</dt>
                        {!! ($tecnico->persona->fallecimiento)?'<img src="'.url('images/death.png').'">':'' !!}
                        <dd>{{($tecnico->persona->nacimiento)?$tecnico->persona->getAgeAttribute():''}}</dd>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="form-group col-md-2 text-center">
                        <img src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}">
                    </div>
                    <div class="form-group col-md-10">
                        <dd>{{$tecnico->persona->observaciones}}</dd>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pestañas Técnico / Jugador --}}
        <ul class="nav nav-tabs mb-3" id="tecnicoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tecnico-tab" data-bs-toggle="tab" data-bs-target="#tecnico" type="button" role="tab">Técnico</button>
            </li>
            @if(count($torneosJugador) > 0)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="jugador-tab" data-bs-toggle="tab" data-bs-target="#jugador" type="button" role="tab">Jugador</button>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tecnico" role="tabpanel">
                @include('tecnicos.tabla')
            </div>

            @if(count($torneosJugador) > 0)
                <div class="tab-pane fade" id="jugador" role="tabpanel">
                    @include('jugadores.tabla')
                </div>
            @endif
        </div>

        {{-- Botón Volver --}}
        <div class="d-flex btn-volver">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>

    </div>
@endsection

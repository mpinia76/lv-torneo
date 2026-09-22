@extends('layouts.app')

@section('pageTitle', 'Nuevo torneo')

@section('content')

    @php
        // Banderas de los grupos que se van a crear.
        // Default segun el Tipo: una Copa se define por penales y no lleva tabla
        // de posiciones; una Liga, al reves. `banderas_grupos` es el hidden que
        // distingue "vengo de un POST que fallo la validacion" (respeto lo que
        // el usuario habia tildado) de "me precargaron por la URL" (aplico el
        // default del Tipo).
        $tipoPrecargado = old('tipo');
        $esCopa         = $tipoPrecargado === 'Copa';
        $vuelveDelPost  = old('banderas_grupos') !== null;

        $posicionesChk = $vuelveDelPost ? old('posiciones_grupos') !== null : !$esCopa;
        $penalesChk    = $vuelveDelPost ? old('penales_grupos')    !== null :  $esCopa;
    @endphp
    <div class="container">
    <h1 class="display-6">Nuevo torneo</h1>

    <hr/>

    <!-- if validation in the controller fails, show the errors -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Open the form with the store function route. -->
    {{ Form::open(['action' => 'TorneoController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-6">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('year', 'Año')}}
            {{Form::text('year', '', ['class' => 'form-control'])}}
        </div>
    </div>
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('equipos', 'Nro. de equipos')}}
            {{Form::number('equipos', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('grupos', 'Nro. de grupos')}}
            {{Form::number('grupos', '', ['class' => 'form-control'])}}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('nombres_grupos', 'Nombre de grupos')}}
            {{ Form::select('nombres_grupos',['letras'=>'Letras (A, B, C...)','numeros'=>'Números (1, 2, 3...)'],old('nombres_grupos', 'letras'), ['class' => 'form-control js-example-basic-single', 'id' => 'nombres_grupos', 'style' => 'width:100%']) }}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('tipo', 'Tipo')}}
            {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],'', ['class' => 'form-control', 'id' => 'tipo']) }}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('ambito', 'Ambito')}}
            {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],'', ['class' => 'form-control']) }}
        </div>
    </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Escudo
                    <input type="file" name="escudoTmp" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('neutral', 'Cancha Neutral', ['class' => 'control-label']) }}
                <div class="checkbox">
                    <label>
                        {{ Form::checkbox('neutral', 1, false) }}
                    </label>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('posiciones_grupos', 'Posiciones', ['class' => 'control-label']) }}
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="posiciones_grupos" id="posiciones_grupos" value="1" {{ $posicionesChk ? 'checked' : '' }}>
                    </label>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('penales_grupos', 'Penales', ['class' => 'control-label']) }}
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="penales_grupos" id="penales_grupos" value="1" {{ $penalesChk ? 'checked' : '' }}>
                    </label>
                </div>
            </div>
            <div class="form-group col-xs-12 col-md-12">
                <input type="hidden" name="banderas_grupos" value="1">
                <small class="text-muted">Nombre, Posiciones y Penales se aplican a <b>todos</b> los grupos que se creen.
                    Se proponen según el Tipo (Copa: penales; Liga: posiciones) y después se ajustan
                    grupo por grupo en <b>Editar torneo</b>.</small>
            </div>
        </div>
        <fieldset>
            <legend>futbol360.com.ar</legend>
        <div class="row">

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('pais', 'País')}}
                    {{Form::text('pais', '', ['class' => 'form-control', 'placeholder' => 'Argentina'])}}
                    <small class="text-muted">Solo para nacionales. En internacionales va vacío.</small>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('region', 'Región / Confederación')}}
                    {{Form::text('region', '', ['class' => 'form-control'])}}
                    <small class="text-muted">Para internacionales: Conmebol, FIFA, UEFA…</small>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('url_nombre', 'Nombre/s')}}
                    {{Form::text('url_nombre', '', ['class' => 'form-control'])}}
                </div>



        </div>
        </fieldset>
        <!--<fieldset>
            <legend>sofascore.com</legend>
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('sofa_category_slug', 'Región')}}
                    {{Form::text('sofa_category_slug', '', ['class' => 'form-control'])}}
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('sofa_slug', 'Nombre/s')}}
                    {{Form::text('sofa_slug', '', ['class' => 'form-control'])}}
                </div>
            </div>
            <div class="row">
                <div class="form-group col-xs-12 col-sm-4 col-md-3">
                    {{Form::label('sofa_category_id', 'Id Región')}}
                    {{Form::text('sofa_category_id', '', ['class' => 'form-control'])}}
                </div>
                <div class="form-group col-xs-12 col-sm-4 col-md-3">
                    {{Form::label('sofa_tournament_id', 'Id Torneo')}}
                    {{Form::text('sofa_tournament_id', '', ['class' => 'form-control'])}}
                </div>
                <div class="form-group col-xs-12 col-sm-4 col-md-3">
                    {{Form::label('sofa_season_id', 'Id Temporada')}}
                    {{Form::text('sofa_season_id', '', ['class' => 'form-control'])}}
                </div>
            </div>
        </fieldset>-->
        <div class="form-group col-md-12">
            <h1 class="display-6">Torneos que cuentan para el promedio</h1>

            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Torneos</th>

                <th><a href="#" class="addRowTorneo"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoTorneo">


                </tbody>




            </table>
        </div>

        <div class="form-group col-md-12">
            <h1 class="display-6">Torneos que cuentan para el acumulado</h1>

            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Torneos</th>

                <th><a href="#" class="addRowTorneoAcumulado"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoTorneoAcumulado">


                </tbody>




            </table>
        </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('torneos.index')}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection

@section('scripts')
<script>
    // Cambiar el Tipo vuelve a proponer el par de banderas. Es una propuesta:
    // el usuario puede destildar despues, y eso se respeta hasta que toque Tipo.
    document.addEventListener('DOMContentLoaded', function () {
        var tipo = document.getElementById('tipo');
        var pos  = document.getElementById('posiciones_grupos');
        var pen  = document.getElementById('penales_grupos');
        if (!tipo || !pos || !pen) return;

        tipo.addEventListener('change', function () {
            var copa = tipo.value === 'Copa';
            pos.checked = !copa;
            pen.checked = copa;
        });
    });
</script>
@endsection

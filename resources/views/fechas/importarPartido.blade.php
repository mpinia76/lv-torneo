@extends('layouts.app')

@section('pageTitle', 'Importar incidencias de un partido')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar incidencias del partido {{$partido->equipol->nombre}} vs {{$partido->equipov->nombre}}</h1>

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
    {{ Form::open(['action' => 'FechaController@importarPartidoProcess']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::hidden('partido_id', (isset($_GET['partidoId']))?$_GET['partidoId']:'' )}}
                <?php echo e(Form::label('url', 'URL')); ?>

                <?php echo e(Form::text('url', '', ['class' => 'form-control'])); ?>

            </div>


        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <?php echo e(Form::label('url2', 'URL 2')); ?>

                <?php echo e(Form::text('url2', '', ['class' => 'form-control'])); ?>


            </div>
        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('fechas.show', $partido->fecha->id)}}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection


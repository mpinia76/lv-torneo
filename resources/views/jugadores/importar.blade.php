@extends('layouts.app')

@section('pageTitle', 'Importar jugador')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar jugador</h1>

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
    {{ Form::open(['action' => 'JugadorController@importarProcess']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <?php echo e(Form::label('url', 'URL')); ?>

                <?php echo e(Form::text('url', '', ['class' => 'form-control'])); ?>

            </div>
        </div>
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-6">

                    <?php echo e(Form::label('url2', 'Transfermarkt')); ?>

                    <?php echo e(Form::text('url2', '', ['class' => 'form-control'])); ?>

                </div>

            </div>

        </div>


        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('jugadores.index')}}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection


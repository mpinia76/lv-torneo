@extends('layouts.app')

@section('pageTitle', 'Importar datos de fechas')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar datos de votación de vidrieras</h1>

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
    {{ Form::open(['action' => 'FechaController@importpollprocess', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    Archivo CSV
                    <input type="file" name="archivoCSV" class="form-control" placeholder="">

                </div>
            </div>

        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}

        {{ Form::close() }}
    </div>


@endsection


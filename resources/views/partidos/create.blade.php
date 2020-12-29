@extends('layouts.app')

@section('pageTitle', 'Nueva fecha')

@section('content')
    <div class="container">
    <h1 class="display-6">Partidos de la fecha {{$fecha->numero}} del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => 'FechaController@store']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">


        <div class="form-group col-xs-12 col-sm-6 col-md-12">

        <partido></partido>
        </div>
    </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>

    <script src="{{asset('components/partido.tag')}}" type="riot/tag"></script>
    <script>
        $(document).ready(function(){
            riot.mount('partido');
        })
    </script>
@endsection

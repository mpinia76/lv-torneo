@extends('layouts.appPublic')

@section('pageTitle', 'Ver equipo')

@section('content')
    <div class="container">


        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$equipo->nombre}}</dd>
            </div>


            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <dt>Socios</dt>
                <dd>{{$equipo->socios}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Fundación</dt>
                <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Estadio</dt>
                <dd>{{$equipo->estadio}}</dd>
            </div>


        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <dt>Historia</dt>
                <dd>{{$equipo->historia}}</dd>

            </div>

        </div>






        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

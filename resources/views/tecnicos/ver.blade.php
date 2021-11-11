@extends('layouts.appPublic')

@section('pageTitle', 'Ver tecnico')

@section('content')
    <div class="container">


        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$tecnico->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Apellido</dt>
                <dd>{{$tecnico->apellido}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$tecnico->ciudad}}</dd>

            </div>

        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                <dd>{{($tecnico->nacimiento)?$tecnico->getAgeAttribute():''}}</dd>

            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($tecnico->foto)
                        <img id="original" src="{{ url('images/'.$tecnico->foto) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$tecnico->observaciones}}</dd>

            </div>

        </div>






        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

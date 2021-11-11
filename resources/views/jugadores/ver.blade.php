@extends('layouts.appPublic')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container">


        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$jugador->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Apellido</dt>
                <dd>{{$jugador->apellido}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$jugador->ciudad}}</dd>

            </div>

        </div>

        <div class="row">

            <div class="row">

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Edad</dt>
                    <dd>{{($jugador->nacimiento)?$jugador->getAgeAttribute():''}}</dd>

                </div>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <dt>Posición</dt>
                <dd>{{$jugador->tipoJugador}}</dd>


            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Altura</dt>
                <dd>{{$jugador->altura}} m.</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Peso</dt>
                <dd>{{$jugador->peso}} kg.</dd>

            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($jugador->foto)
                        <img id="original" src="{{ url('images/'.$jugador->foto) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$jugador->observaciones}}</dd>

            </div>

        </div>






    <div class="d-flex">

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>

@endsection

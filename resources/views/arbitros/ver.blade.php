@extends('layouts.appPublic')

@section('pageTitle', 'Ver arbitro')

@section('content')
    <div class="container">


        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$arbitro->persona->name}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Completo</dt>
                <dd>{{$arbitro->persona->name}} {{$arbitro->persona->apellido}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$arbitro->persona->ciudad}}</dd>

            </div>

        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                {!! ($arbitro->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!}
                <dd>{{($arbitro->persona->nacimiento)?$arbitro->persona->getAgeAttribute():''}}</dd>

            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($arbitro->persona->foto)
                        <img id="original" src="{{ url('images/'.$arbitro->persona->foto) }}" height="200">
                    @else
                        <img id="original" src="{{ url('images/sin_foto_arbitro.png') }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$arbitro->persona->observaciones}}</dd>

            </div>

        </div>






        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection

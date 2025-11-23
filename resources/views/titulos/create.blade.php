@extends('layouts.app')

@section('pageTitle', 'Nuevo título')

@section('content')
    <div class="container">
        <h1 class="display-6">Nuevo título</h1>
        <hr/>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('titulos.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-4">
                    <label>Nombre del título</label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    <label>Año</label>
                    <input type="number" name="year" class="form-control" value="{{ old('year') }}" required>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    {{Form::label('tipo', 'Tipo')}}
                    {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],'', ['class' => 'form-control']) }}
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('ambito', 'Ambito')}}
                    {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],'', ['class' => 'form-control']) }}
                </div>
            </div>
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-6">
                    <label>Equipo campeón</label>
                    <select name="equipo_id" class="form-control js-example-basic-single" required>
                        <option value="">Seleccionar...</option>
                        @foreach($equipos as $equipo)
                            <option value="{{ $equipo->id }}">{{ $equipo->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-6">
                    <label>Torneos asociados</label>
                    <select name="torneos[]" class="form-control" multiple size="8">
                        @foreach($torneos as $torneo)
                            <option value="{{ $torneo->id }}">{{ $torneo->nombre }} ({{ $torneo->year }})</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Podés seleccionar uno o varios torneos</small>
                </div>
            </div>

            <button class="btn btn-success mt-4">Guardar</button>
            <a href="{{ route('titulos.index') }}" class="btn btn-secondary mt-4">Cancelar</a>
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('pageTitle', 'Editar título')

@section('content')
    <div class="container">
        <h1 class="display-6">Editar título</h1>
        <hr/>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('titulos.update', $titulo->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-4">

                    <label>Nombre del título</label>
                    <input type="text" name="nombre" class="form-control"
                           value="{{ old('nombre', $titulo->nombre) }}" required>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    <label>Año</label>
                    <input type="number" name="year" class="form-control"
                           value="{{ old('year', $titulo->year) }}" required>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    {{Form::label('tipo', 'Tipo')}}
                    {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],$titulo->tipo, ['class' => 'form-control']) }}
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('ambito', 'Ambito')}}
                    {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],$titulo->ambito, ['class' => 'form-control']) }}
                </div>

            </div>
            <div class="row">
                <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <label>Equipo campeón</label>
                <select name="equipo_id" class="form-control" required>
                    @foreach($equipos as $equipo)
                        <option value="{{ $equipo->id }}"
                            {{ $equipo->id == $titulo->equipo_id ? 'selected' : '' }}>
                            {{ $equipo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group mt-4">
                <label>Torneos asociados</label>
                <select name="torneos[]" class="form-control" multiple size="8">
                    @foreach($torneos as $torneo)
                        <option value="{{ $torneo->id }}"
                            {{ in_array($torneo->id, $titulo->torneos->pluck('id')->toArray()) ? 'selected' : '' }}>
                            {{ $torneo->nombre }} ({{ $torneo->year }})
                        </option>
                    @endforeach
                </select>
            </div>

            <button class="btn btn-primary mt-4">Actualizar</button>
            <a href="{{ route('titulos.index') }}" class="btn btn-secondary mt-4">Cancelar</a>
        </form>
    </div>
@endsection

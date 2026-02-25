@extends('layouts.app')

@section('pageTitle', 'Reasignar grupo')

@section('content')
<div class="container">
    <div class="container">
        <h1 class="display-6">Reasignar grupo a {{$plantilla->equipo->nombre}} en {{$plantilla->rupo->torneo->nombre}} {{$plantilla->grupo->torneo->year}}</h1>
        <br>
        @if($plantilla->equipo->escudo)
            <img id="original" src="{{ url('images/'.$plantilla->equipo->escudo) }}" height="50">
        @endif

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

    <form method="POST" action="{{ route('plantillas.guardarGrupo', $plantilla->id) }}">
        @csrf

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <label>Grupo</label>
                <select name="grupo_id" class="form-control">
                    @foreach($grupos as $id => $nombre)
                        <option value="{{ $id }}"
                                @if($plantilla->grupo_id == $id) selected @endif>
                            {{ $nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <button class="btn btn-primary">Guardar</button>

        <a href="{{route('plantillas.edit', $plantilla->id)}}" class="btn btn-success m-1">Volver</a>
    </form>
</div>
@endsection

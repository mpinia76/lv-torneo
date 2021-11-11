@extends('layouts.app')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container">
    <h1 class="display-6">Ver jugador</h1>

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
                <dt>Documento</dt>
                <dd>{{$jugador->tipoDocumento}} {{$jugador->documento}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>E-mail</dt>
                <dd>{{$jugador->email}}</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Teléfono</dt>
                <dd>{{$jugador->telefono}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$jugador->ciudad}}</dd>

            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                <dd>{{($jugador->nacimiento)?$jugador->getAgeAttribute():''}}</dd>

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
                <dt>Observaciones</dt>
                <dd>{{$jugador->observaciones}}</dd>

            </div>

        </div>






    <div class="d-flex">
        <a href="{{route('jugadores.edit', $jugador->id)}}" class="btn btn-primary m-1">Editar</a>

        <form action="{{ route('jugadores.destroy', $jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <button class="btn btn-danger m-1">Eliminar</button>
        </form>
        <a href="{{ route('jugadores.index') }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>
    <script>

        function ConfirmDelete()
        {
            var x = confirm("Está seguro?");
            if (x)
                return true;
            else
                return false;
        }

    </script>
@endsection

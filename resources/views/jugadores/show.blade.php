@extends('layouts.app')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container">
    <h1 class="display-6">Ver jugador</h1>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$jugador->persona->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Apellido</dt>
                <dd>{{$jugador->persona->apellido}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Mostrar</dt>
                <dd>{{$jugador->persona->name}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>E-mail</dt>
                <dd>{{$jugador->persona->email}}</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Teléfono</dt>
                <dd>{{$jugador->persona->telefono}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$jugador->persona->ciudad}}</dd>

            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                {!! ($jugador->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!}
                <dd>{{($jugador->persona->nacimiento)?$jugador->persona->getAgeAttribute():''}}</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <dt>Posición</dt>
                <dd>{{$jugador->tipoJugador}}</dd>


            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Altura</dt>
                <dd>{{$jugador->persona->altura}} m.</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Peso</dt>
                <dd>{{$jugador->persona->peso}} kg.</dd>

            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($jugador->persona->foto)
                        <img id="original" src="{{ url('images/'.$jugador->persona->foto) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <dt>Observaciones</dt>
                <dd>{{$jugador->persona->observaciones}}</dd>

            </div>

        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <img id="original" src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}">

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

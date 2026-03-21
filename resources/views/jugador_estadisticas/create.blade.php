@extends('layouts.app')

@section('pageTitle', 'Nueva estadística manual')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto jugador" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $jugador->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $jugador->persona->nombre }} {{ $jugador->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $jugador->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $jugador->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $jugador->persona->nacimiento ? $jugador->persona->getAgeAttribute() : '' }}
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Posición</strong><br>{{ $jugador->tipoJugador }}</div>
                    <div class="col-md-3"><strong>Altura</strong><br>{{ $jugador->persona->altura }} m</div>
                    <div class="col-md-3"><strong>Peso</strong><br>{{ $jugador->persona->peso }} kg</div>
                </div>
                @if($jugador->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $jugador->persona->observaciones }}</div>
                    </div>
                @endif
            </div>
        </div>
        <hr/>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('jugador-estadisticas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- 🔒 jugador --}}
            <input type="hidden" name="jugador_id" value="{{ $jugador->id }}">

            <div class="row">

                {{-- torneo --}}
                <div class="form-group col-md-4">
                    <label>Torneo</label>
                    <input type="text" name="torneo_nombre" class="form-control"
                           value="{{ old('torneo_nombre') }}">
                </div>

                {{-- logo --}}
                <div class="form-group col-md-3">
                    <label>Logo torneo</label>

                    <input type="file" class="form-control" name="escudoTmp">
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

            <hr>


            {{-- stats base --}}
            <div class="row">
                {{-- equipo --}}
                <div class="form-group col-md-5">
                    <label>Equipo</label>
                    <select name="equipo_id" class="form-control js-example-basic-single">
                        @foreach($equipos as $id => $nombre)
                            <option value="{{ $id }}"
                                {{ old('equipo_id') == $id ? 'selected' : '' }}>
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Posición</label>
                    <input type="number" name="posicion" class="form-control" value="{{ old('posicion') }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Partidos</label>
                    <input type="number" name="partidos" class="form-control" value="{{ old('partidos') }}">
                </div>
            </div>

            <hr>

            {{-- ⚽ TIPOS DE GOLES --}}
            <h5>Goles</h5>

            <div class="row">
                <div class="form-group col-md-2">
                    <label>Cabeza</label>
                    <input type="number" name="goles_cabeza" class="form-control" value="{{ old('goles_cabeza') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Jugada</label>
                    <input type="number" name="goles_jugada" class="form-control" value="{{ old('goles_jugada') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Penal</label>
                    <input type="number" name="goles_penal" class="form-control" value="{{ old('goles_penal') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Tiro Libre</label>
                    <input type="number" name="goles_tiro_libre" class="form-control" value="{{ old('goles_tiro_libre') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>En contra</label>
                    <input type="number" name="goles_en_contra" class="form-control" value="{{ old('goles_en_contra') }}">
                </div>
            </div>



            <hr>

            <h5>Tarjetas</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>Amarillas</label>
                    <input type="number" name="amarillas" class="form-control" value="{{ old('amarillas') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Rojas</label>
                    <input type="number" name="rojas" class="form-control" value="{{ old('rojas') }}">
                </div>
            </div>

            <hr>

            <h5>Penales</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>Errados</label>
                    <input type="number" name="penales_errados" class="form-control" value="{{ old('penales_errados') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Atajados</label>
                    <input type="number" name="penales_atajados" class="form-control" value="{{ old('penales_atajados') }}">
                </div>
            </div>
            <hr>
            <h5>Arquero</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>G. Recibidos</label>
                    <input type="number" name="goles_recibidos" class="form-control" value="{{ old('goles_recibidos') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>V. invictas</label>
                    <input type="number" name="vallas_invictas" class="form-control" value="{{ old('vallas_invictas') }}">
                </div>

                <div class="form-group col-md-2">
                    <label>P. Atajados</label>
                    <input type="number" name="penales_atajo" class="form-control" value="{{ old('penales_atajo') }}">
                </div>
            </div>

            <button class="btn btn-primary">Guardar</button>

            <a href="{{ route('jugador-estadisticas.indexPorJugador', $jugador->id) }}"
               class="btn btn-success">
                Volver
            </a>

        </form>
    </div>
@endsection

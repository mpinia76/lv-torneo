@extends('layouts.app')

@section('pageTitle', 'Editar estadística manual')

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

        <form action="{{ route('jugador-estadisticas.update', $stat->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- 🔒 jugador --}}
            <input type="hidden" name="jugador_id" value="{{ $jugador->id }}">

            <div class="row">

                {{-- torneo --}}
                <div class="form-group col-md-4">
                    <label>Torneo</label>
                    <input type="text" name="torneo_nombre" class="form-control"
                           value="{{ old('torneo_nombre', $stat->torneo_nombre) }}">
                </div>

                {{-- logo --}}
                <div class="form-group col-md-3">
                    <label>Logo torneo</label>

                    @if($stat->torneo_logo)
                        <div class="mb-2">
                            <img src="{{ url('images/'.$stat->torneo_logo) }}" height="40">
                        </div>
                    @endif

                    <input type="file" class="form-control" name="escudoTmp">
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-2">
                    {{Form::label('tipo', 'Tipo')}}
                    {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],$stat->tipo, ['class' => 'form-control']) }}
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    {{Form::label('ambito', 'Ambito')}}
                    {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],$stat->ambito, ['class' => 'form-control']) }}
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
                                {{ old('equipo_id', $stat->equipo_id) == $id ? 'selected' : '' }}>
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Posición</label>
                    <input type="number" name="posicion" class="form-control" value="{{ old('posicion', $stat->posicion) }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Partidos</label>
                    <input type="number" name="partidos" class="form-control" value="{{ old('partidos', $stat->partidos) }}">
                </div>
            </div>

            <hr>

            {{-- ⚽ TIPOS DE GOLES --}}
            <h5>Goles</h5>

            <div class="row">
                @foreach([
                    'goles_jugada' => 'Jugada',
                    'goles_cabeza' => 'Cabeza',
                    'goles_penal' => 'Penal',
                    'goles_tiro_libre' => 'Tiro Libre',
                    'goles_en_contra' => 'En contra'
                ] as $field => $label)

                    <div class="form-group col-md-2">
                        <label>{{ $label }}</label>
                        <input type="number" name="{{ $field }}" class="form-control"
                               value="{{ old($field, $stat->$field) }}">
                    </div>

                @endforeach
            </div>



            <hr>

            <h5>Tarjetas</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>Amarillas</label>
                    <input type="number" name="amarillas" class="form-control" value="{{ old('amarillas', $stat->amarillas) }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Rojas</label>
                    <input type="number" name="rojas" class="form-control" value="{{ old('rojas', $stat->rojas) }}">
                </div>
            </div>

            <hr>

            <h5>Penales</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>Errados</label>
                    <input type="number" name="penales_errados" class="form-control" value="{{ old('penales_errados', $stat->penales_errados) }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Atajados</label>
                    <input type="number" name="penales_atajados" class="form-control" value="{{ old('penales_atajados', $stat->penales_atajados) }}">
                </div>
            </div>
            <hr>
            <h5>Arquero</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>G. Recibidos</label>
                    <input type="number" name="goles_recibidos" class="form-control" value="{{ old('goles_recibidos', $stat->goles_recibidos) }}">
                </div>

                <div class="form-group col-md-2">
                    <label>V. invictas</label>
                    <input type="number" name="vallas_invictas" class="form-control" value="{{ old('vallas_invictas', $stat->vallas_invictas) }}">
                </div>

                <div class="form-group col-md-2">
                    <label>P. Atajados</label>
                    <input type="number" name="penales_atajo" class="form-control" value="{{ old('penales_atajo', $stat->penales_atajo) }}">
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

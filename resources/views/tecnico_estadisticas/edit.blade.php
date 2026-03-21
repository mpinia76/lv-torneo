@extends('layouts.app')

@section('pageTitle', 'Editar estadística manual')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $tecnico->persona->foto ? url('images/'.$tecnico->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto tecnico" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $tecnico->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $tecnico->persona->nombre }} {{ $tecnico->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $tecnico->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $tecnico->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $tecnico->persona->nacimiento ? $tecnico->persona->getAgeAttribute() : '' }}
                    </div>
                </div>

                @if($tecnico->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $tecnico->persona->observaciones }}</div>
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

        <form action="{{ route('tecnico-estadisticas.update', $stat->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- 🔒 tecnico --}}
            <input type="hidden" name="tecnico_id" value="{{ $tecnico->id }}">

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
            <h5>Partidos</h5>
            {{-- stats base --}}
            <div class="row">


                <div class="form-group col-md-2">
                    <label>Ganados</label>
                    <input type="number" name="ganados" class="form-control" value="{{ old('ganados', $stat->ganados) }}">
                </div>

                <div class="form-group col-md-2">
                    <label>Empatados</label>
                    <input type="number" name="empatados" class="form-control" value="{{ old('empatados', $stat->empatados) }}">
                </div>
                <div class="form-group col-md-2">
                    <label>Perdidos</label>
                    <input type="number" name="perdidos" class="form-control" value="{{ old('perdidos', $stat->perdidos) }}">
                </div>
            </div>

            <hr>
            {{-- ⚽ TIPOS DE GOLES --}}
            <h5>Goles</h5>

            <div class="row">
                @foreach([

                    'goles_favor' => 'Favor',

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





            <button class="btn btn-primary">Guardar</button>

            <a href="{{ route('tecnico-estadisticas.indexPorTecnico', $tecnico->id) }}"
               class="btn btn-success">
                Volver
            </a>

        </form>
    </div>
@endsection

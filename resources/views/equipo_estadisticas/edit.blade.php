@extends('layouts.app')

@section('pageTitle', 'Editar estadística manual')
<style>


    /* --- Equipo info --- */
    dd img {
        margin-left: 5px;
        vertical-align: middle;
    }
</style>
@section('content')
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <dt>Nombre</dt>
                        <dd>{{$equipo->nombre}} <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Socios</dt>
                        <dd>{{$equipo->socios}}</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Estadio</dt>
                        <dd>{{$equipo->estadio}}</dd>
                    </div>
                </div>
            </div>

            <div class="col-md-4 d-flex justify-content-center align-items-center">
                @if($equipo->escudo)
                    <img src="{{ url('images/'.$equipo->escudo) }}" style="width: 200px" class="img-fluid">
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

        <form action="{{ route('equipo-estadisticas.update', $stat->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- 🔒 equipo --}}
            <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">

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

            <a href="{{ route('equipo-estadisticas.indexPorEquipo', $equipo->id) }}"
               class="btn btn-success">
                Volver
            </a>

        </form>
    </div>
@endsection

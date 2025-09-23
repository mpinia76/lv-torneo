@extends('layouts.app')

@section('pageTitle', 'Equipos Clasificados - ' . $torneo->nombre)

@section('content')
    <div class="container">
        <h1 class="display-6">Equipos Clasificados - {{ $torneo->nombre }}</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('torneos.updateClasificados', $torneo->id) }}" method="POST">
            @csrf

            @foreach($clasificaciones as $clasificacion)
                <div class="form-group mb-3">
                    <label>{{ $clasificacion->nombre }} (Máx {{ $clasificacion->cantidad }})</label>
                    <select name="clasificados[{{ $clasificacion->id }}][]" class="form-control" multiple>
                        @foreach($equipos as $equipo_id => $equipo_nombre)
                            <option value="{{ $equipo_id }}"
                                    @if(isset($equiposClasificados[$clasificacion->id]) && $equiposClasificados[$clasificacion->id]->pluck('equipo_id')->contains($equipo_id)) selected @endif
                            >
                                {{ $equipo_nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary">Guardar Clasificados</button>
            <a href="{{ route('torneos.index') }}" class="btn btn-secondary">Volver</a>
        </form>
    </div>
@endsection

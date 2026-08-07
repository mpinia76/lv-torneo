@extends('layouts.app')

@section('pageTitle', 'Resolver equipos de promiedos')

@section('content')
    <div class="container">
        <h1 class="display-6">Resolver equipos — {{ $fechaLabel }} ({{ $grupo->nombre }} de {{ $grupo->torneo->nombre }} {{ $grupo->torneo->year }})</h1>

        <hr/>

        <div class="alert alert-warning">
            Algunos equipos de promiedos no coinciden con los de tu base. Elegí a qué equipo tuyo corresponde cada uno (aparecen ordenados por parecido). Cuando estén todos elegidos, se importa la fecha.
        </div>

        {{ Form::open(['action' => 'FechaController@importprocess']) }}
        {{ Form::token() }}

        {{-- Estado para reejecutar el import con lo elegido --}}
        {{ Form::hidden('url2', $url2) }}
        {{ Form::hidden('grupoSelect_id', $grupoId) }}
        {{ Form::hidden('grupo_id', $grupoId) }}
        {{ Form::hidden('fecha_pm', $fechaInput) }}
        @if($verificado)
            {{ Form::hidden('verificado', 1) }}
        @endif

        <table class="table">
            <thead>
            <tr>
                <th>Equipo en promiedos</th>
                <th>Tu equipo</th>
            </tr>
            </thead>
            <tbody>
            @foreach($pendientes as $p)
                <tr>
                    <td>{{ $p['nombre'] }}</td>
                    <td>
                        <select name="pm_map[{{ $p['key'] }}]" class="form-control" required>
                            @foreach($p['opciones'] as $op)
                                <option value="{{ $op['id'] }}" {{ $loop->first ? 'selected' : '' }}>{{ $op['nombre'] }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ Form::submit('Importar fecha', ['class' => 'btn btn-primary']) }}
        <a href="{{ route('fechas.index', array('grupoId' => $grupoId)) }}" class="btn btn-success m-1">Cancelar</a>
        {{ Form::close() }}
    </div>
@endsection

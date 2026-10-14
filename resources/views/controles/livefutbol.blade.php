@extends('layouts.app')

@section('pageTitle', 'Partido en livefutbol')

@section('content')
    {{--
        Sale sólo cuando el botón "LF" de los controles no encontró el partido.
        No hay plan B que mande al calendario del club o a una búsqueda: igual
        que el botón TM, "LF" tiene que abrir el partido. Se pega la URL una
        vez y queda anotada (partido_livefutbol, origen manual).
    --}}
    <div class="container" style="max-width: 760px; margin-top: 30px;">
        <h2>No encontré el partido en livefutbol</h2>
        <p class="lead">
            {{ $partido->equipol->nombre ?? '?' }} {{ $partido->golesl }}-{{ $partido->golesv }} {{ $partido->equipov->nombre ?? '?' }}
            · {{ $partido->dia ? \Carbon\Carbon::parse($partido->dia)->format('d/m/Y') : 'sin fecha' }}
        </p>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($pasos)
            <p>Lo que probé:</p>
            <ul>
                @foreach($pasos as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('controles.livefutbol.guardar', $partido->id) }}" style="margin-top: 20px;">
            @csrf
            <div class="form-group">
                <label for="lf-url">URL del partido en livefutbol</label>
                <input type="text" name="url" id="lf-url" class="form-control" autofocus
                       placeholder="https://www.livefutbol.com/match-report/co132/uefa-europa-league/ma608664/...">
                <p class="help-block">Queda anotada: la próxima vez el botón LF va directo.</p>
            </div>
            <button type="submit" class="btn btn-primary">Guardar y abrir</button>
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('pageTitle', 'Partido en livefutbol')

@section('content')
    {{--
        Dos usos:
          - el botón "LF" no encontró el partido: muestra qué probó y pide la URL;
          - el link "✎" al lado de LF (?corregir=1): la URL guardada estaba mal.
        No hay plan B que mande al calendario del club o a una búsqueda: igual
        que el botón TM, "LF" tiene que abrir el partido.

        Al guardar, el controlador baja la ficha y compara la fecha. Si no
        coincide vuelve acá con el motivo y aparece "guardar igual".
    --}}
    <div class="container" style="max-width: 760px; margin-top: 30px;">
        <h2>{{ $actual ? 'Corregir el partido en livefutbol' : 'No encontré el partido en livefutbol' }}</h2>
        <p class="lead">
            {{ $partido->equipol->nombre ?? '?' }} {{ $partido->golesl }}-{{ $partido->golesv }} {{ $partido->equipov->nombre ?? '?' }}
            · {{ $partido->dia ? \Carbon\Carbon::parse($partido->dia)->format('d/m/Y') : 'sin fecha' }}
        </p>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($actual)
            <p>Ahora abre: <a href="{{ $actual }}" target="_blank" rel="noopener">{{ $actual }}</a></p>
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
                <input type="text" name="url" id="lf-url" class="form-control" autofocus value="{{ old('url') }}"
                       placeholder="https://www.livefutbol.com/match-report/co132/uefa-europa-league/ma608664/...">
                <p class="help-block">Antes de guardarla controlo que la ficha sea del mismo día que el partido.</p>
            </div>
            @if(session('lf_puede_forzar'))
                <div class="checkbox">
                    <label><input type="checkbox" name="forzar" value="1"> Guardar igual (sé que es ésta)</label>
                </div>
            @endif
            <button type="submit" class="btn btn-primary">Guardar y abrir</button>
            @if($actual)
                <button type="submit" name="accion" value="borrar" class="btn btn-default"
                        title="Borra la URL guardada: la próxima vez LF lo vuelve a buscar solo.">Borrar y que lo busque de nuevo</button>
            @endif
        </form>
    </div>
@endsection

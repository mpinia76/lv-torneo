<?php

namespace App\Http\Controllers;

use App\Titulo;
use App\Equipo;
use App\Torneo;
use Illuminate\Http\Request;

class TituloController extends Controller
{
    public function index(Request $request)
    {
        $buscar = $request->buscarpor;

        $titulos = Titulo::with('equipo')
            ->when($buscar, function($q) use ($buscar) {
                $q->where('nombre', 'like', "%$buscar%");
            })
            ->paginate(20);

        return view('titulos.index', compact('titulos'));
    }

    public function create()
    {
        $equipos = Equipo::orderBy('nombre')->get();
        $torneos = Torneo::orderBy('year', 'desc')->orderBy('nombre')->get();

        return view('titulos.create', compact('equipos', 'torneos'));
    }

    public function store(Request $request)
    {
        $titulo = Titulo::create($request->only('nombre','equipo_id','year','tipo','ambito'));

        $titulo->torneos()->sync($request->torneos);

        return redirect()->route('titulos.index')
            ->with('success', 'Título creado correctamente');
    }

    public function edit($id)
    {
        $titulo = Titulo::findOrFail($id);
        $equipos = Equipo::orderBy('nombre')->get();
        $torneos = Torneo::orderBy('year', 'desc')->orderBy('nombre')->get();

        return view('titulos.edit', compact('titulo','equipos','torneos'));
    }

    public function update(Request $request, $id)
    {
        $titulo = Titulo::findOrFail($id);

        $titulo->update($request->only('nombre','equipo_id','year','tipo','ambito'));
        $titulo->torneos()->sync($request->torneos);

        return redirect()->route('titulos.index')
            ->with('success', 'Título actualizado correctamente');
    }

    public function destroy($id)
    {
        $titulo = Titulo::findOrFail($id);
        $titulo->delete();

        return redirect()->route('titulos.index')
            ->with('success', 'Título eliminado');
    }
}

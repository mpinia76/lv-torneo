<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Soap;
use Illuminate\Http\Response;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Auth::routes();




Route::group(['prefix' => 'admin'], function()
{
    Route::get('/', function () {
        return view('/home');
    });
    Route::get('/home', 'TorneoController@index')->name('index');
    Route::resource('torneos', 'TorneoController');
    Route::resource('jugadores', 'JugadorController');
    Route::resource('equipos', 'EquipoController');
    Route::resource('fechas', 'FechaController');
    Route::resource('partidos', 'PartidoController');
    Route::resource('plantillas', 'PlantillaController');
    Route::resource('goles', 'GolController');
    Route::resource('arbitros', 'ArbitroController');
    Route::resource('tecnicos', 'TecnicoController');
    Route::resource('tarjetas', 'TarjetaController');
    Route::resource('partidoarbitros', 'PartidoArbitroController');
    Route::resource('alineaciones', 'AlineacionController');
    Route::resource('cambios', 'CambioController');

    Route::get('importarJugador', 'JugadorController@importar')->name('jugadores.importar');
    Route::post('importarJugadorProcess', 'JugadorController@importarProcess');
    Route::get('importarPartido', 'FechaController@importarPartido')->name('fechas.importarPartido');
    Route::post('importarPartidoProcess', 'FechaController@importarPartidoProcess');
    Route::get('importfechas', 'FechaController@import')->name('fechas.import');
    Route::post('importprocess', 'FechaController@importprocess');
    Route::get('importplantillas', 'PlantillaController@import')->name('plantillas.import');
    Route::post('importplantillaprocess', 'PlantillaController@importprocess');
    Route::get('controlarplantillas', 'PlantillaController@controlar')->name('plantillas.controlar');
    Route::delete('/eliminar-jugador/{id}', 'PlantillaController@eliminarJugador')->name('plantilla.destroy');
    Route::delete('/eliminar-jugadores-seleccionados', 'PlantillaController@eliminarJugadoresSeleccionados')->name('plantilla.eliminarSeleccionados');

    Route::get('controlarAlineaciones', 'PartidoController@controlarAlineaciones')->name('partidos.controlarAlineaciones');
    Route::get('controlarTarjetas', 'PartidoController@controlarTarjetas')->name('partidos.controlarTarjetas');
    Route::get('controlarGoles', 'PartidoController@controlarGoles')->name('partidos.controlarGoles');
    Route::get('controlarCambios', 'PartidoController@controlarCambios')->name('partidos.controlarCambios');
    Route::get('controlarArbitros', 'PartidoController@controlarArbitros')->name('partidos.controlarArbitros');
    Route::get('controlarTecnicos', 'PartidoController@controlarTecnicos')->name('partidos.controlarTecnicos');

    Route::get('importincidencias', 'FechaController@importincidencias')->name('fechas.importincidencias');
    Route::post('importincidenciasprocess', 'FechaController@importincidenciasprocess');

    Route::get('importincidenciasfecha', 'FechaController@importincidenciasfecha')->name('fechas.importincidenciasfecha');
    Route::get('importgolesfecha', 'FechaController@importgolesfecha')->name('fechas.importgolesfecha');
    Route::get('controlarbitrosfecha', 'FechaController@controlarbitrosfecha')->name('fechas.controlarbitrosfecha');


    Route::get('importpoll', 'PollController@importPoll')->name('polls.importPoll');
    Route::post('importpollprocess', 'PollController@importpollprocess');
    Route::get('plantillasearch', 'PlantillaController@search')
        ->name('plantilla.search');
});


Route::get('/', function () {
    return view('/portada');
});
Route::get('portada', 'PortadaController@index')->name('portada');

Route::get('posiciones', 'GrupoController@posiciones')->name('grupos.posiciones');
Route::get('tablaGoles', 'GrupoController@goleadores')->name('grupos.goleadores');
Route::get('tablaJugadores', 'GrupoController@jugadores')->name('grupos.jugadores');
Route::get('tablaTrajetas', 'GrupoController@tarjetas')->name('grupos.tarjetas');
Route::get('jueces', 'PartidoController@arbitros')->name('partidos.arbitros');
Route::get('promedios', 'TorneoController@promedios')->name('torneos.promedios');

Route::get('verTorneo', 'TorneoController@ver')->name('torneos.ver');
Route::get('tabla', 'GrupoController@posicionesPublic')->name('grupos.posicionesPublic');
Route::get('goleadores', 'GrupoController@goleadoresPublic')->name('grupos.goleadoresPublic');
Route::get('tarjetero', 'GrupoController@tarjetasPublic')->name('grupos.tarjetasPublic');
Route::get('verFechas', 'FechaController@ver')->name('fechas.ver');
Route::get('verFecha', 'FechaController@showPublic')->name('fechas.showPublic');
Route::get('detalleFecha', 'FechaController@detalle')->name('fechas.detalle');
Route::get('verJugador', 'JugadorController@ver')->name('jugadores.ver');
Route::get('jugadorJugados', 'JugadorController@jugados')->name('jugadores.jugados');
Route::get('jugadorGoles', 'JugadorController@goles')->name('jugadores.goles');
Route::get('jugadorTarjetas', 'JugadorController@tarjetas')->name('jugadores.tarjetas');
Route::get('verEquipo', 'EquipoController@ver')->name('equipos.ver');
Route::get('equipoJugados', 'EquipoController@jugados')->name('equipos.jugados');
Route::get('verTecnico', 'TecnicoController@ver')->name('tecnicos.ver');
Route::get('tecnicoJugados', 'TecnicoController@jugados')->name('tecnicos.jugados');
Route::get('verArbitro', 'ArbitroController@ver')->name('arbitros.ver');
Route::get('descensos', 'TorneoController@promediosPublic')->name('torneos.promediosPublic');
Route::get('acumulado', 'TorneoController@acumulado')->name('torneos.acumulado');
Route::get('arqueros', 'GrupoController@arqueros')->name('grupos.arqueros');
Route::get('metodo', 'GrupoController@metodo')->name('grupos.metodo');

Route::get('historiales', 'TorneoController@historiales')->name('torneos.historiales');
Route::get('goleadoresHistorico', 'TorneoController@goleadores')->name('torneos.goleadores');
Route::get('jugadoresHistorico', 'TorneoController@jugadores')->name('torneos.jugadores');
Route::get('tarjetasHistorico', 'TorneoController@tarjetas')->name('torneos.tarjetas');
Route::get('posicionesHistorico', 'TorneoController@posiciones')->name('torneos.posiciones');
Route::get('otrasEstadisticas', 'TorneoController@estadisticasOtras')->name('torneos.estadisticasOtras');
Route::get('estadisticasTorneo', 'TorneoController@estadisticasTorneo')->name('torneos.estadisticasTorneo');
Route::get('tecnicos', 'TorneoController@tecnicos')->name('torneos.tecnicos');
Route::get('arquerosHistorico', 'TorneoController@arqueros')->name('torneos.arqueros');


Route::get('logout', 'Auth\LoginController@logout');




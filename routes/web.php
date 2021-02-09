<?php

use Illuminate\Support\Facades\Route;

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
    Route::get('importfechas', 'FechaController@import')->name('fechas.import');
    Route::post('importprocess', 'FechaController@importprocess');

    Route::get('importincidencias', 'FechaController@importincidencias')->name('fechas.importincidencias');
    Route::post('importincidenciasprocess', 'FechaController@importincidenciasprocess');

    Route::get('importincidenciasfecha', 'FechaController@importincidenciasfecha')->name('fechas.importincidenciasfecha');
});


Route::get('/', function () {
    return view('/portada');
});


Route::get('posiciones', 'GrupoController@posiciones')->name('grupos.posiciones');
Route::get('tablaGoles', 'GrupoController@goleadores')->name('grupos.goleadores');
Route::get('tablaTrajetas', 'GrupoController@tarjetas')->name('grupos.tarjetas');
Route::get('jueces', 'PartidoController@arbitros')->name('partidos.arbitros');

Route::get('verTorneo', 'TorneoController@ver')->name('torneos.ver');
Route::get('tabla', 'GrupoController@posicionesPublic')->name('grupos.posicionesPublic');
Route::get('goleadores', 'GrupoController@goleadoresPublic')->name('grupos.goleadoresPublic');
Route::get('tarjetero', 'GrupoController@tarjetasPublic')->name('grupos.tarjetasPublic');
Route::get('verFechas', 'FechaController@ver')->name('fechas.ver');
Route::get('verFecha', 'FechaController@showPublic')->name('fechas.showPublic');
Route::get('detalleFecha', 'FechaController@detalle')->name('fechas.detalle');
Route::get('verJugador', 'JugadorController@ver')->name('jugadores.ver');
Route::get('verEquipo', 'EquipoController@ver')->name('equipos.ver');
Route::get('verTecnico', 'TecnicoController@ver')->name('tecnicos.ver');
Route::get('verArbitro', 'ArbitroController@ver')->name('arbitros.ver');


Route::get('logout', 'Auth\LoginController@logout');


<?php

namespace App\Http\Controllers;


use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;
use Excel;

use Response;
use File;


class PollController extends Controller
{



    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }



    public function get_csv($resultados){



        // these are the headers for the csv file.
        $headers = array(
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename=download.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        );


        //I am storing the csv file in public >> files folder. So that why I am creating files folder
        if (!File::exists(public_path()."/files")) {
            File::makeDirectory(public_path() . "/files");
        }

        //creating the download file
        $filename =  public_path("files/download.csv");
        $handle = fopen($filename, 'w');

        //adding the first row
        fputcsv($handle, [
            'Nombre', 'DNI', 'Telefono','Voto'
        ], "|");

        //adding the data from the array
        foreach ($resultados as $resultado) {
            if(count($resultado)==5){
            fputcsv($handle, [

                utf8_decode(trim($resultado[1])),
                utf8_decode(trim($resultado[2])),
                utf8_decode(trim($resultado[3])),
                utf8_decode(trim($resultado[0])),
                utf8_decode(trim($resultado[4]))
            ], "|");
            }

        }
        fclose($handle);

        //download command
        return Response::download($filename, "download.csv", $headers);
    }





    public function importPoll(Request $request)
    {


        //
        return view('polls.importPoll');
    }

    public function importpollprocess(Request $request)
    {

        set_time_limit(0);



        $file = $request->file('archivoCSV');

        // File Details
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $tempPath = $file->getRealPath();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();


        // Valid File Extensions
        $valid_extension = array("csv");

        // 2MB in Bytes
        $maxFileSize = 2097152;

        // Check file extension
        if(in_array(strtolower($extension),$valid_extension)){

            // Check file size
            //if($fileSize <= $maxFileSize){

                // File upload location
                $location = 'uploads';

                // Upload file
                $file->move($location,$filename);

                // Import CSV to Database
                $filepath = public_path($location."/".$filename);

                // Reading file
                $file = fopen($filepath,"r");

                $importData_arr = array();
                $i = 0;

                while (($filedata = fgetcsv($file, 1000, ",")) !== FALSE) {
                    $num = count($filedata );

                    // Skip first row (Remove below comment if you want to skip the first row)
                    /*if($i == 0){
                       $i++;
                       continue;
                    }*/
                    for ($c=0; $c < $num; $c++) {
                        $importData_arr[$i][] = $filedata [$c];
                    }
                    $i++;
                }
                fclose($file);
                $hojaExcel = array();
                foreach($importData_arr as $importData) {
                    Log::info('Poll name: ' . $importData[0]);
                    Log::info('Username: ' . $importData[1]);
                    Log::info('Email: ' . $importData[2]);
                    Log::info('User Type: ' . $importData[3]);
                    Log::info('IP: ' . $importData[4]);
                    Log::info('Date: ' . $importData[5]);
                    Log::info('Message: ' . $importData[6]);
                    Log::info('Vote data: ' . $importData[7]);
                    $lineaExcel = array();
                    if ($importData[6]!='success'){
                        $arrayVotacion = explode(';',$importData[7]);
                        foreach ($arrayVotacion as $voto){
                            $arrayVoto = explode(':',$voto);
                            if ($arrayVoto[0]=='Answer'){
                                Log::info('voto: ' . $arrayVoto[1]);
                                $lineaExcel[] = $arrayVoto[1];
                            }

                        }
                        if (count($lineaExcel)>0){
                            $lineaExcel[] = $importData[6];
                        }
                    }
                    if (count($lineaExcel)>0){
                        $hojaExcel[] = $lineaExcel;
                    }

                }
                //Excel::store($hojaExcel,'prueba.xls');
               $this->get_csv($hojaExcel);

                //print_r($importData_arr);
                // Insert to MySQL database

                $ok=1;

            /*}else{


                $error='Archivo demasiado grande. El archivo debe ser menor que 2MB.';
                $ok=0;

            }*/

        }else{

            $error='Extensión de archivo no válida.';
            $ok=0;

        }

        if ($ok){



            DB::commit();
            $respuestaID='success';
            $respuestaMSJ='Importación exitosa. (ver log)';
        }
        else{
            DB::rollback();
            $respuestaID='error';
            $respuestaMSJ=$error;
        }

        //
        return redirect()->route('torneos.index')->with($respuestaID,$respuestaMSJ);
    }

}

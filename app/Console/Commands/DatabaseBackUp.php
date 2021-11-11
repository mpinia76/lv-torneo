<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Http\Controllers\PhpmailerController;

class DatabaseBackUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copia de BD';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $fecha = Carbon::now()->format('Y-m-d');
        $filename = "backup-" . $fecha . ".sql";


        $command = "".env('DUMP_PATH')." --user=" . env('DB_USERNAME') . " --password=" . env('DB_PASSWORD') . " --host=" . env('DB_HOST') . " " . env('DB_DATABASE') . "  > \"" . storage_path() . "/app/backup/" . $filename."\"";

        //echo $command;
        $returnVar = NULL;
        $output = NULL;


        exec($command, $output, $returnVar);



        $zip_file ="backup-" . $fecha . ".zip"; // name of the package to download
//Initialize PHP class
        $zip = new \ZipArchive();
        $zip->open(storage_path() . "/app/backup/" .$zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

//Add file: the second parameter is the path of the file to be compressed in the compressed package
//So, it will create another path called "storage /" in the zip and put the file in the directory.
        $zip->addFile(storage_path(). "/app/backup/" .$filename);
        $zip->close();

        unlink(storage_path() . "/app/backup/" . $filename);

        $attach = array(storage_path() . "/app/backup/" .$zip_file);

        $data=array(

            'email'=>'crones.codnet@gmail.com',
            'subject'=>'BackUp lv-torneo',
            'body'=>'En el archivo adjunto se encuentra el BackUp de la BBDD lv-torneo realizado el '.$fecha,
            'attachs'=>$attach
        );

        $mailer = new PhpmailerController();

        $mailer->sendEmail($data);

        unlink(storage_path() . "/app/backup/" . $zip_file);

    }
}

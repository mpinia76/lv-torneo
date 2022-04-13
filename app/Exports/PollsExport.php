<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class PollsExport implements FromCollection
{
    public function collection()
    {
        //return Invoice::all();
    }
}


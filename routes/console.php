<?php

use Illuminate\Support\Facades\Schedule;

// Backup diario de la base. Antes vivia en app/Console/Kernel.php::schedule().
Schedule::command('database:backup')->dailyAt('23:00');

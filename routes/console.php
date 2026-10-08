<?php

// El backup diario de la base (php artisan database:backup) NO se programa acá.
// Lo dispara directo el crontab de 'deploy' en el servidor, igual que hacía el
// cron del cPanel, y no hay ningún 'schedule:run' corriendo:
//
//   0 4 * * * /usr/bin/php /var/www/lv-torneo/artisan database:backup >> /dev/null 2>&1
//
// (El servidor está en America/Argentina/Buenos_Aires.) Si algún día se pasa al
// scheduler de Laravel, agregar acá Schedule::command(...) y cambiar el crontab
// por 'schedule:run' cada minuto — no las dos cosas, o llegan dos backups.

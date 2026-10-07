<?php

return [
    App\Providers\AppServiceProvider::class,
    // View composer sobre '*': inyecta $torneos y $torneosMenu en cada vista.
    App\Providers\ComposerServiceProvider::class,
];

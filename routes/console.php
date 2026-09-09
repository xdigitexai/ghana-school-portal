<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('school:status', function () {
    $this->info('Ghana School Portal is ready.');
})->purpose('Check school portal command registration');

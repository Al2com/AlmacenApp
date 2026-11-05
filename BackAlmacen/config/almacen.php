<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Días de aviso de caducidad
    |--------------------------------------------------------------------------
    |
    | Un lote «caduca pronto» cuando le quedan estos días o menos. Es un ajuste
    | (docs/frontend.md), no un número escrito en el código.
    |
    */

    'dias_aviso_caducidad' => (int) env('ALMACEN_DIAS_AVISO_CADUCIDAD', 7),

];

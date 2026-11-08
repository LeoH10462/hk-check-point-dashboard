<?php
return ['default'=>'mysql', 'connections'=>['mysql'=>[
    'driver'=>'mysql','host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT',3306),
    'database'=>env('DB_DATABASE','borderflow'),'username'=>env('DB_USERNAME','borderflow'),
    'password'=>env('DB_PASSWORD'),'charset'=>'utf8mb4','collation'=>'utf8mb4_0900_ai_ci',
    'strict'=>true,'timezone'=>'+08:00',
]]];

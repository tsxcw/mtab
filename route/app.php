<?php

use think\facade\Route;
Route::any('/manager', 'index/index');
Route::any('/noteApp', "index/index");
Route::any("/", 'index/index');
Route::any("/favicon","index/favicon");
Route::options("[:s]", function () {
    return response('', 200);
});
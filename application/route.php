<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\Route;

/*
 * 测试
 */
Route::get('test/:params', 'api/Test/test');
Route::get('info', 'api/v1.First/info');
Route::get('get', 'api/v1.First/info');

Route::group(':version/token', function () {
    Route::post('user', 'api/:version.Token/getUserToken');
});

Route::group(':version/user', function () {
    Route::get('info', 'api/:version.User/Info');
    Route::get('get_list', 'api/:version.User/getList');
});

Route::miss('api/Miss/miss');

return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]' => [
        ':id' => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

<?php

use think\Route;

// Test
Route::rule('getToken', 'api/Test/getToken');
Route::rule('getUid', 'api/Test/getUid');

// AutoRun 
Route::rule('api/:version/auto', 'api/v1.AutoRun/index');// 定时任务
Route::rule('api/:version/auto/i', 'api/v1.AutoRun/minuteHandle');// 每分钟定期执行
Route::rule('api/:version/auto/d', 'api/v1.AutoRun/dayHandle');// 每日定期执行
Route::rule('api/:version/auto/w', 'api/v1.AutoRun/weekHandle');// 每周定期执行
Route::rule('api/:version/auto/m', 'api/v1.AutoRun/monthHandle');// 每月定期执行

// Notify
Route::rule('api/:version/notify/receive', 'api/v1.Notify/receive');// 客服消息推送
Route::rule('api/:version/notify/auth', 'api/v1.Notify/getAuth');// 

// Page 
Route::rule('api/:version/page/app', 'api/v1.Page/app');

// User
Route::rule('api/:version/user/login', 'api/v1.User/login');// 登录
Route::rule('api/:version/user/login_app', 'api/v1.User/login_app');// 登录

Route::rule('api/:version/user/saveinfo', 'api/v1.User/saveInfo');// 保存用户详细信息
Route::rule('api/:version/user/savephone', 'api/v1.User/savePhone');// 保存用户详细信息
Route::rule('api/:version/user/edit', 'api/v1.User/edit');// 修改用户头像和昵称
Route::rule('api/:version/user/info', 'api/v1.User/getInfo');// 获取用户详细信息

// Pay
Route::rule('api/:version/pay/order', 'api/v1.Payment/order');// 支付下单
Route::rule('api/:version/pay/notify/:platform', 'api/v1.Payment/notify');// 支付通知
Route::rule('api/:version/pay/goods', 'api/v1.Payment/goods');// 商品列表
Route::rule('api/:version/pay/alipaynotify', 'api/v1.Payment/alipayNotify');// 支付下单

// Task
Route::rule('api/:version/task', 'api/v1.Task/index');// 任务
Route::rule('api/:version/task/settle', 'api/v1.Task/settle');// 任务领取

Route::rule('api/:version/uploadIndex', 'api/v1.Ext/uploadIndex');// 文件上传
Route::rule('api/:version/upload', 'api/v1.Ext/upload');// 文件上传
Route::rule('api/:version/ragreement', 'api/v1.Ext/rAgreement');// 文件上传

Route::rule('api/:version/page/sendSms', 'api/v1.Page/sendSms');//使用道具

Route::rule('api/:version/ad/custom', 'api/v1.Page/customAd'); // 公益打卡信息

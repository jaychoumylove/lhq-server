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
Route::rule('api/:version/page/index', 'api/v1.Page/index');
Route::rule('api/:version/page/friend_rank', 'api/v1.Page/friendRank');
Route::rule('api/:version/page/rank', 'api/v1.Page/rank');
Route::rule('api/:version/page/user_info', 'api/v1.Page/userInfo');
Route::rule('api/:version/page/bill', 'api/v1.Page/bill');
Route::rule('api/:version/page/qrcode', 'api/v1.Page/qrCode');
Route::rule('api/:version/page/withdraw_log', 'api/v1.Page/withdrawLog');
Route::rule('api/:version/ad/custom', 'api/v1.Page/customAd');


Route::rule('api/:version/bill/lottery', 'api/v1.Bill/lottery'); // 抽奖
Route::rule('api/:version/bill/double', 'api/v1.Bill/doubleLottery'); // 抽奖埋点
Route::rule('api/:version/bill/withdraw', 'api/v1.Bill/withdraw'); // 发起提现
Route::rule('api/:version/task/settle', 'api/v1.Task/settle'); // 完成任务

// User
Route::rule('api/:version/user/login', 'api/v1.User/login');// 登录

Route::rule('api/:version/user/saveinfo', 'api/v1.User/saveInfo');// 保存用户详细信息
Route::rule('api/:version/user/savephone', 'api/v1.User/savePhone');// 保存用户详细信息
Route::rule('api/:version/user/info', 'api/v1.User/getInfo');// 获取用户详细信息

//UserRank
Route::rule('api/:version/user/pointRankInfo', 'api/v1.UserRank/pointRankInfo');// 用户积分排名信息

// Task
Route::rule('api/:version/task/settle', 'api/v1.Task/settle');// 完成任务
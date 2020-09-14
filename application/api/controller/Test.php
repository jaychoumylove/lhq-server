<?php

namespace app\api\controller;


use app\api\model\AnimalLottery;
use app\api\model\CfgAnimal;
use app\api\model\CfgAnimalLevel;
use app\base\controller\Base;
use Exception;
use think\Db;
use app\base\service\Common;
use app\api\model\CfgTaskgiftCategory;
use app\api\model\RecTaskgift;
use app\api\model\CfgTaskgift;
use app\api\model\CfgBadge;
use app\api\model\Prop;
use app\api\model\CfgHeadwear;
use app\api\model\UserStar;
use app\api\service\User as UserService;
use app\api\model\StarRank as StarRankModel;
use app\api\model\Fanclub;
use app\api\model\RecHour;
use app\api\model\PkUser;
use app\api\model\RecTask;
use app\api\model\BadgeUser;
use app\api\model\FanclubUser;
use app\api\model\RecPayOrder;
use think\Response;

class Test extends Base
{
    
    public function getToken()
    {
        echo Common::setSession(input('uid') / 1234);
    }
    
    public function getUid()
    {
        echo Common::getSession(input('token'));
    }
    
    public function index()
    {
        RecPayOrder::where('tar_user_id',0)->update(['tar_user_id'=>Db::raw('user_id')]);
    }
    
    public function reback(){
        $type = input('type',0);
        $uid = input('uid',0);
        $hot = input('flower',0);
        if(!$type || !$uid || !$hot) Common::res(['code' => 1, 'msg' => '参数错误']);
        
        Db::startTrans();
        try {
            // 用户货币减少
            if ($type == 1) $update = ['coin' => $hot, 'point' => -$hot];
            else if ($type == 2) $update = ['flower' => $hot, 'point' => -$hot];
            else if ($type == 3) $update = ['old_coin' => $hot, 'point' => -$hot];
            
            (new UserService)->change($uid, $update, '撤回打榜，+' . $hot);
            
            
            $starid = UserStar::getStarId($uid);
            BadgeUser::where(['uid' => $uid,'stype' => 2])->delete(true); //stype=2鲜花徽章
            
            // 用户贡献度增加
            UserStar::changeHandle($uid, 'pick', $starid, -$hot,  $type);
            // 赠送鲜花时：占领封面小时榜贡献增加
            //if ($type == 2) RecHour::change($uid, -$hot, $starid);
            // 团战贡献增加
            //PkUser::addHot($uid, $starid, $hot);
            // 粉丝团            
            $fid = FanclubUser::where('user_id', $uid)->value('fanclub_id');
            if ($fid != 0) {
                Fanclub::where('id', $fid)->update([
                    'week_count' => Db::raw('week_count-' . $hot),
                    'month_count' => Db::raw('month_count-' . $hot)
                ]);
                
                FanclubUser::where('user_id', $uid)->update([
                    'week_count' => Db::raw('week_count-' . $hot)
                ]);
            }
            
            // 家族贡献
            //Family::change($uid, $hot);
            
            // 宝箱
            
            //RecTask::addRec($uid, [14, 15, 16, 17, 18], -$hot);
            
            // 明星增加人气
            StarRankModel::change($starid, -$hot, $type);
            
            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }
    
    
    // 冬至日活动
    public function activeOn()
    {
        
        //$editTaskId1 = 30;//新人礼包奖励，任务ID
        $editTaskId2 = 31;//新人礼包奖励，任务ID
        //$badge1 = CfgBadge::where('id',56)->field('id,bimg as img,name')->find();
        Db::name('cfg_badge')->where('id',59)->update(['delete_time'=>NULL]);
        $badge2 = Db::name('cfg_badge')->where('id',59)->field('id,bimg as img,name')->find();
        $giftTask_startTime = '2020-01-20 00:00:00'; //新人礼包开始时间
        $giftTask_endTime = '2020-01-31 23:59:59'; //新人礼包结束时间
        $propId = [14];  //积分兑换冬至徽章开启
        
        //判断活动是否已开始
        $nowdate = date('Y-m-d H:i:s');
        $active_exist = CfgTaskgiftCategory::where('id', 3)->where('start_time', '<=', $nowdate)->where('end_time', '>=', $nowdate)->value('count(1)');
        if($active_exist) Common::res(['code' => 400,'msg' => '活动已经开始']);
        
        Db::startTrans();
        try {
            //清除历史数据
            RecTaskgift::where('cid',3)->delete();
            
            //设置礼包启动时间
            Db::name('cfg_taskgift_category')->where('id', 3)->update(['name'=>'春节礼包','start_time'=>$giftTask_startTime,'end_time'=>$giftTask_endTime,'delete_time'=>NULL]);
            
            //增加徽章奖励
            //             $awards['badge'] = $badge1;
            //             $update = ['awards'=>json_encode($awards)];
            //             CfgTaskgift::where('id', $editTaskId1)->update($update);
            $awards['badge'] = $badge2;
            $update = ['awards'=>json_encode($awards),'title'=>'累计充值500','count'=>500,'delete_time'=>NULL];
            Db::name('cfg_taskgift')->where('id', $editTaskId2)->update($update);
            CfgTaskgift::where('id','in',[28,29,30,32,33,34,35])->update(['delete_time'=>date('Y-m-d H:i:s')]);
            
            //开启积分商城冬至徽章兑换
            Db::name('prop')->where('id','in',$propId)->update(['delete_time'=>NULL]);
            
            //开启新年头饰
            CfgHeadwear::where('sort','in',[95,96])->update(['delete_time'=>date('Y-m-d H:i:s')]);
            Db::name('cfg_headwear')->where('sort','in',[94])->update(['delete_time'=>NULL]);
            
            
            Db::commit();
        }
        catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
        Common::res(['code' => 0,'msg' => '操作成功']);
    }
    
    // 冬至日活动
    public function activeOff()
    {
        
        //$editTaskId1 = 30;//新人礼包奖励，任务ID
        $editTaskId2 = 31;//新人礼包奖励，任务ID
        $propId = [14];  //积分兑换冬至徽章关闭
        
        //判断活动是否已结束
        $nowdate = date('Y-m-d H:i:s');
        $active_end = CfgTaskgiftCategory::where('id', 3)->where('end_time', '<', $nowdate)->value('count(1)');
        if(!$active_end) Common::res(['code' => 400,'msg' => '活动还未截止']);
        
        Db::startTrans();
        try {
            //取消徽章奖励
            //             $update = ['awards'=>'{"coin":100000,"stone":10,"trumpet":10}'];
            //             CfgTaskgift::where('id', $editTaskId1)->update($update);
            $update = ['awards'=>'{"coin":500000,"stone":55,"trumpet":50}','title'=>'累计充值500','count'=>500,'delete_time'=>date('Y-m-d H:i:s')];
            CfgTaskgift::where('id', $editTaskId2)->update($update);
            
            //关闭积分商城冬至徽章兑换
            Prop::where('id','in',$propId)->update(['delete_time'=>date('Y-m-d H:i:s')]);
            CfgTaskgiftCategory::where('id', 3)->update(['start_time'=>NULL,'end_time'=>NULL,'delete_time'=>date('Y-m-d H:i:s')]);
            
            Db::commit();
        }
        catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
        
        Common::res(['code' => 0,'msg' => '操作成功']);
    }

    public function reBuildAnimal()
    {
        $normal = [];
        $secret = [];
        $lock = 0;
        $lock_num = 0;
        $exchange = 0;

        $animalLeft = 8;
        $luckyLeft = 9;
        $animal = [
            'name' => '鼠',
            'image' => 'https://mmbiz.qpic.cn/mmbiz_gif/w5pLFvdua9Fic6VmPQYib2ktqATmSxJmUtvNXVsBzTEmc1fyK8O16OSuJUAicicLZA0o1hkNVmBoSqKZUj89srXPvA/0',
            'scrap_img' => 'https://mmbiz.qpic.cn/mmbiz_png/w5pLFvdua9GF0Ayowf19yN8oiaLKldV6QhT8Zws3rWRdHxribSNudmOUjMjv17TxfCTLhDwKKRCaW0VwbNRzUlQA/0',
            'empty_img' => 'https://mmbiz.qpic.cn/mmbiz_png/w5pLFvdua9GF0Ayowf19yN8oiaLKldV6QOpdWkhyqdYQ2icwwiborbFn9uXEDnyI3FsHiaHia5UwOPjFYibjVO0htb8g/0',
            'scrap_name' => '鼠碎片',
            'star_id' => null,
            'lock' => $lock,
            'lock_num' => $lock_num,
            'exchange' => $exchange
        ];

        $array = range(0, 16, 1);

        $animalArray = [];
        foreach ($array as $key => $value) {
            array_push($animalArray, $animal);
        }

        $sql = (new CfgAnimal())->fetchSql(true)->insertAll($animalArray);

        return Response::create(['sql' => $sql], 'json');
    }

    public function reBuildAnimalLevel()
    {
        $list = CfgAnimal::all();
        $steal = 0;

        $array = range(0, 9, 1);
        $normalArrayLv = [];
        $luckyArrayLv = [];

        foreach ($array as $key => $value) {
            $lvItem = [
                'level' => bcadd($value, 1),
                'steal' => $steal,
            ];

            $lvItem['number'] = bcmul($lvItem['level'], 100);
            $lvItem['output'] = bcmul($lvItem['level'], 10);
            if ($lvItem['level'] == 1) {
                $lvItem['number'] = 10;
                $lvItem['output'] = 1;
            }

            $lvItem['desc'] = sprintf('每10秒/%s金豆', $lvItem['number']);

            $luckyLvItem = $lvItem;
            $luckyLvItem['number'] = 10;
            $luckyLvItem['output'] = bcmul($luckyLvItem['level'], 100);

            array_push($normalArrayLv, $lvItem);
            array_push($luckyArrayLv, $luckyLvItem);
        }

        $cfgAnimalLevel = new CfgAnimalLevel();
        $typeMap = [
            'NORMAL' => $normalArrayLv,
            'SECRET' => $luckyArrayLv,
        ];
        $insert = [];
        foreach ($list as $key => $value) {
            $lvMap = $typeMap[$value['type']];

            foreach ($lvMap as $item) {
                $insertItem = [
                    'animal_id' => $value['id'],
                ];
                $insertItem = array_merge($insertItem, $item);
                array_push($insert, $insertItem);
            }
        }

        $sql = $cfgAnimalLevel->fetchSql(true)->insertAll($insert);

        return Response::create(['sql' => $sql], 'json');
    }

    public function reBuildLottery()
    {
        $list = CfgAnimal::where('type', 'NORMAL')->select();
        $litem = [
            'chance' => 10,
            'number' => 10,
        ];
        $insert = [];
        foreach ($list as $item) {
            $lotteryItem = array_merge(['animal' => $item['id']], $litem);
            array_push($insert, $lotteryItem);
        }

        $sql = (new AnimalLottery())->fetchSql(true)->insertAll($insert);

        return Response::create(['sql' => $sql], 'json');
    }
}

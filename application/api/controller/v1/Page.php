<?php

namespace app\api\controller\v1;

use app\api\model\AnimalLottery;
use app\api\model\Cfg_luckyDraw;
use app\api\model\CfgAnimal;
use app\api\model\CfgManorBackground;
use app\api\model\CfgNovel;
use app\api\model\CfgScrap;
use app\api\model\CfgUserLevel;
use app\api\model\NovelContent;
use app\api\model\RecLuckyDrawLog;
use app\api\model\RecUserInvite;
use app\api\model\UserAchievementHeal;
use app\api\model\UserAnimal;
use app\api\model\UserInvite;
use app\api\model\UserManor;
use app\api\model\UserManorBackground;
use app\api\model\UserManorLog;
use app\api\model\UserScrap;
use app\base\controller\Base;
use app\api\model\User;
use app\api\model\UserCurrency;
use app\api\model\UserStar;
use app\api\model\CfgShareTitle;
use app\api\model\Cfg;
use app\base\service\Common;
use app\api\model\UserRelation;
use app\api\service\Star;
use app\api\model\Star as StarModel;
use app\api\model\RecStarChart;
use app\api\model\Article;
use app\api\model\CfgAds;
use app\api\model\CfgItem;
use app\api\model\Notice;
use app\api\model\UserItem;
use Exception;
use GatewayWorker\Lib\Gateway;
use app\api\model\UserExt;
use app\api\model\Prop;
use app\api\model\UserProp;
use app\api\model\UserWxgroup;
use app\api\model\Wxgroup;
use think\Db;
use app\api\service\User as UserService;
use app\api\model\BadgeUser;
use app\api\model\GzhUser;
use app\api\model\GzhUserPush;
use app\api\model\RecPayOrder;
use app\api\model\FanclubUser;
use app\api\model\CfgShare;
use app\api\model\RecTaskfather;
use app\api\service\Sms;
use Throwable;

class Page extends Base
{

    public function app()
    {
        $this->getUser();

        $platform = input('platform', 'MP-WEIXIN');
        $rer_user_id = input('referrer', 0);
        $enterScene = $this->req('scene');// 场景
        if ($enterScene == 1001 || $enterScene == 1089) {
            // 添加到我的小程序
            UserExt::where('user_id', $this->uid)->update(['add_enter' => 1]);
        }
        if ($rer_user_id) {
            // 拉新关系
            UserRelation::saveNew($this->uid, $rer_user_id);
        }

        $res['userInfo'] = User::where([
            'id' => $this->uid
        ])->field('id,nickname,avatarurl,type,phoneNumber')->find();
        $res['userCurrency'] = UserCurrency::getCurrency($this->uid);
        $res['userExt'] = UserExt::where('user_id', $this->uid)->find();
        $res['userExt']['totalCount'] = UserStar::where('user_id', $this->uid)->max('total_count');

        $userStar = UserStar::with('Star')->where([
            'user_id' => $this->uid
        ])
            ->order('id desc')
            ->find();
        if (!$userStar) {
            $res['userStar'] = [];
        } else {
            $starInfo = $userStar['star'];
            $starInfo['captain'] = $userStar['captain'];
            $res['userStar'] = $userStar['star'];
        }

        // 获取分享信息
        $res['config'] = Cfg::getList();
        $userTotalPay = RecPayOrder::where('tar_user_id', $this->uid)->where('pay_time', 'not null')->sum('total_fee');
        if ($res['userStar'] && $res['userStar']['birthday'] == (int) date('md') && $res['userStar']['open_img']) {
            // 生日弹框条件
            $res['config']['index_open']['img'] = $res['userStar']['open_img'];
            $res['config']['isBirthday']=1;
        } else if ($userTotalPay < Cfg::getCfg('open_img_show_charge')) {
            // 充值小于n元的用户隐藏首页弹图
            $res['config']['index_open'] = null;
        } else {
            // index_open 格式 '{"img": "", "url": "", "platform":["MP-WEIXIN","MP-QQ"]}'
            if (array_key_exists ('platform', $res['config']['index_open'])) {
                if (in_array ($platform, $res['config']['index_open']['platform']) == false) {
                    $res['config']['index_open'] = null;
                }
            }
        }

        $res['config']['share_text'] = CfgShareTitle::getOne();
        $res['config']['share_cfg'] = CfgShare::column('title,imageUrl,path','id');
        if (array_key_exists('free_lottery', $res['config'])) {
            $level = CfgUserLevel::getLevel($this->uid);

            $multiple = [];
            foreach ($res['config']['free_lottery']['multiple'] as $item) {
                if ((int)$level >= $item['level']) {
                    $multiple = $item;
                }
            }

            $res['config']['free_lottery']['multiple'] = [$multiple];
        }

        //生成我的徽章数据
        BadgeUser::initBadge($this->uid);

        // 师徒每日登录
        RecTaskfather::checkIn($this->uid);

        Common::res([
            'data' => $res
        ]);
    }

    public function group()
    {
        $starid = input('starid');
        $client_id = input('client_id');
        $this->getUser();

        if (!$starid)
            Common::res([
                'code' => 100
            ]);

        $res['starInfo'] = StarModel::with('StarRank')->where([
            'id' => $starid
        ])->find();
        if (date('md') == $res['starInfo']['birthday']) {
            $res['starInfo']['isBirth'] = true;
        }

        $starService = new Star();
        $res['starInfo']['star_rank']['week_hot_rank'] = $starService->getRank($res['starInfo']['star_rank']['week_hot'], 'week_hot');

        $res['userRank'] = UserStar::getRank($starid, 'thisday_count', 1, 6);
        if (!$res['userRank']) $res['userRank'] = UserStar::getRank($starid, 'total_count', 1, 6);

        $res['captain'] = UserStar::where('user_id', $this->uid)->value('captain');

        $res['is_blessing_gifts'] = UserExt::where('user_id', $this->uid)->value('is_blessing_gifts');
        if(input('platform')!='MP-WEIXIN'){
            $res['is_blessing_gifts']=1;
        }
        if(!$res['starInfo']['chat_off']){
            // 聊天内容
            $res['chartList'] = RecStarChart::getLeastChart($starid);
            // 加入聊天室
            Gateway::joinGroup($client_id, 'star_' . $starid);
        }
        
        $res['disLeastCount'] = StarModel::disLeastCount($starid);

        // $res['mass'] = ShareMass::getMass($this->uid);

        // $res['invitList'] = [
        // 'list' => UserRelation::fixByType(1, $this->uid, 1, 10),
        // 'award' => Cfg::getCfg('invitAward'),
        // 'hasInvitcount' => UserRelation::with('User')->where(['rer_user_id' => $this->uid, 'status' => ['in', [1, 2]]])->count()
        // ];

        $res['article'] = Notice::where('1=1')->order('create_time desc,id desc')->find();
        $res['fanclub_id'] = FanclubUser::where('user_id', $this->uid)->value('fanclub_id');

        // 礼物
        // $res['itemList'] = CfgItem::where('1=1')->order('count asc')->select();
        // foreach ($res['itemList'] as &$value) {
        // $value['self'] = UserItem::where(['uid' => $this->uid, 'item_id' => $value['id']])->value('count');
        // if (!$value['self']) $value['self'] = 0;
        // }

        Common::res([
            'data' => $res
        ]);
    }

    public function giftPackage()
    {
        $this->getUser();
        $res['itemList'] = CfgItem::where('1=1')->order('count asc')->select();
        foreach ($res['itemList'] as &$value) {
            $value['self'] = UserItem::where([
                'uid' => $this->uid,
                'item_id' => $value['id']
            ])->value('count');
            if (!$value['self'])
                $value['self'] = 0;
        }

        Common::res([
            'data' => $res
        ]);
    }

    public function giftCount()
    {
        $this->getUser();
        $res = UserItem::where([
            'uid' => $this->uid
        ])->sum('count');
        Common::res([
            'data' => $res
        ]);
    }

    public function prop()
    {
        $rechargeSwitch = Cfg::getCfg('ios_switch');
        if (input('platform') == 'MP-WEIXIN' && $rechargeSwitch == 3) {
            $propList = Prop::all(function ($query) {
                $query->where('get_type', Prop::STORE)->where('id', 'not in', [1, 2])->order('point asc');
            });
        } else {
            $propList = Prop::all(function ($query) {
                $query->where('get_type', Prop::STORE)->order('point asc');
            });
        }
        // $propList = Prop::all(function ($query) {
        //     $query->order('point asc');
        // });

        Common::res(['data' => $propList]);
    }

    public function myprop()
    {
        $this->getUser();

        //触发用户PK积分转移
        $score = Db::name('pk_user_rank')->where('uid', $this->uid)->order('last_pk_time desc')->value('score');
        if ($score) {

            Db::startTrans();
            try {
                (new UserService)->change($this->uid, ['point' => $score], 'PK积分转移');
                Db::name('pk_user_rank')->where('uid', $this->uid)->update(['score' => 0]);

                Db::commit();
            } catch (Exception $e) {
                Db::rollBack();
                Common::res(['code' => 400, 'msg' => $e->getMessage()]);
            }
        }


        $res['list'] = UserProp::getList($this->uid);
        $res['currentPoint'] = UserCurrency::getCurrency($this->uid)['point'];
        $res['pointNoticeId'] = 15;
        Common::res([
            'data' => $res
        ]);
    }

    public function propExchange()
    {
        $proid = $this->req('proid', 'integer', 0);
        $count = $this->req('count', 'integer', 0);
        $this->getUser();
        $res = UserProp::exchangePoint($this->uid, $proid, $count);
        Common::res([
            'data' => $res
        ]);
    }

    public function propUse()
    {
        $userprop_id = $this->req('userprop_id', 'integer', 0);
        $this->getUser();
        $res = UserProp::useIt($this->uid, $userprop_id);
        Common::res([
            'data' => self::myprop()
        ]);
    }
    /**
     * 游戏试玩
     */
    public function game()
    {
        $type = $this->req('type', 'integer', 0);
        $w = ['platform' => input('platform')];
        if ($type == 1) {
            $w['show'] = 1;
        }
        Common::res([
            'data' => CfgAds::where($w)->order('sort asc')->select()
        ]);
    }

    /**
     * 群集结信息
     */
    public function groupMass()
    {
        $gid = $this->req('gid', 'integer');
        $star_id = $this->req('star_id', 'integer');

        UserWxgroup::massSettle();

        $res = UserWxgroup::massStatus($gid);
        // 集结成员
        if ($res['status'] != 0) {
            $res['list'] = UserWxgroup::with('User')->where('wxgroup_id', $gid)
                ->whereTime('mass_join_at', 'between', [
                    $res['massStartTime'],
                    $res['massEndTime']
                ])
                ->order('mass_join_at asc')
                ->select();
        } else {
            $res['list'] = [];
        }
        // star信息
        $res['star'] = StarModel::where('id', $star_id)->field('name,head_img_s')->find();
        Common::res([
            'data' => $res
        ]);
    }

    public function wxgroup()
    {
        $this->getUser();
        // 集结动态
        // $res['dynamic'] = array_reverse(WxgroupDynamic::where('1=1')->order('id desc')->limit(30)->select());

        // 群日贡献排名
        $res['groupList'] = Wxgroup::with('star')->order('thisday_count desc')
            ->limit(10)
            ->select();
        foreach ($res['groupList'] as &$group) {
            $group['userRank'] = UserWxgroup::with('user')->where('wxgroup_id', $group['id'])
                ->order('thisday_count desc')
                ->field('user_id,thisday_count')
                ->limit(5)
                ->select();
        }

        // 贡献奖励
        $res['reback'] = UserWxgroup::where('user_id', $this->uid)->sum('daycount_reback');

        Common::res([
            'data' => $res
        ]);
    }

    /**
     * 广场
     */
    public function square()
    {
        $page = $this->req('page', 'integer', 1);
        $size = $this->req('size', 'integer', 10);
        $star_id = $this->req('star_id', 'require');

        $this->getUser();

        // 文章列表
        $res['article'] = Article::getList($star_id, $page, $size);
        // 是否订阅
        $res['subscribe'] = UserStar::where('user_id', $this->uid)->value('article_subscribe');
        // 明星信息
        $res['starInfo'] = StarModel::with('StarRank')->where([
            'id' => $star_id
        ])
            ->field('id,head_img_s,name,square_bg_img,square_bg_color')
            ->find();

        $starService = new Star();
        $res['starInfo']['star_rank']['week_hot_rank'] = $starService->getRank($res['starInfo']['star_rank']['week_hot'], 'week_hot');
        Common::res([
            'data' => $res
        ]);
    }

    /**公众号订阅列表 */
    public function gzhSubscribe()
    {
        $this->getUser();
        $gzh_appid = 'wx3507654fa8d00974';// 服务号APPID
        $res['subscribe'] = GzhUser::where('gzh_appid', $gzh_appid)->where('user_id', $this->uid)->value('subscribe');
        if ($res['subscribe']) {
            $res['list'] = GzhUserPush::getList($this->uid);
        }

        Common::res(['data' => $res]);
    }
    
    /*
     * 发送短信
     * */
    public function sendSms()
    {

        $phoneNumber = input('phoneNumber',0);
        $this->getUser();
        
        $phoneNumber = strpos($phoneNumber,'86')!==false && strpos($phoneNumber,'86')==0 ? substr($phoneNumber, -11) : $phoneNumber;
        $hasExist = User::where('phoneNumber',$phoneNumber)->count();
        if($hasExist) Common::res(['code' => 1, 'msg' => '该号码已被占用']);
        
        $sms = json_decode(UserExt::where('user_id',$this->uid)->value('sms'),true);
        if(isset($sms['phoneNumber']) && time()-$sms['sms_time']<=24*3600 && $sms['phoneNumber']==$phoneNumber ) Common::res(['code' => 1, 'msg' => '验证码已发送，1天只能发送一次']);
        
        $content = (new Sms())->send($phoneNumber);
        UserExt::where('user_id',$this->uid)->update(['sms'=>json_encode($content)]);
        if($content['Code'] != 'OK') Common::res(['code' => 1, 'msg' => $content['Message']]);
        Common::res();
    }

    public function luckyCharge()
    {
        $this->getUser ();

        $config = Cfg::getCfg (Cfg::RECHARGE_LUCKY);
        $config['multiple_draw']['able'] = Cfg::checkMultipleDrawAble ($config['multiple_draw']);

        $forbiddenUser = array_key_exists ('forbidden_user', $config) ? $config['forbidden_user']: [];

        $rec = RecLuckyDrawLog::with(['user'])
            ->where('type', RecLuckyDrawLog::SINGLE)
            ->where('user_id', 'not in', $forbiddenUser)
            ->order('create_time', 'desc')
            ->limit (6)
            ->select ();

        // 触发更新用户碎片过期
        try {
            $currentTime = date ('Y-m-d H:i:s');

            $nbf = $currentTime < $config['scrap_time']['start_time'];
            $naf = $currentTime > $config['scrap_time']['end_time'];

            if ($naf || $nbf) {
                // 碎片是否过期
                $userExt = (new UserExt)->readMaster ()
                    ->where('user_id', $this->uid)
                    ->find ();
                if (empty($userExt['scrap_time'])) {
                    // 是否更新过
                    if ($userExt['scrap'] || $userExt['last_scrap']) {
                        // 是否需要更新
                        UserExt::where('user_id', $this->uid)->update([
                            'scrap' => 0,
                            'last_scrap' => $userExt['scrap'],
                            'scrap_time' => $currentTime
                        ]);
                    }
                }
            }
        }catch (Throwable $throwable) {}

        $scrap = CfgScrap::where('status', CfgScrap::ON)
            ->order([
                'sort' => 'desc',
                "id" => "desc"
            ])
            ->select ();
        if (is_object ($scrap)) $scrap = $scrap->toArray ();

        $scrapIds = array_column ($scrap, 'id');

        $userScrapNum = UserExt::where('user_id', $this->uid)->value ('scrap');

        $userScraps = UserScrap::where('user_id', $this->uid)
            ->where('scrap_id', 'in', $scrapIds)
            ->select ();
        if (is_object ($userScraps)) $userScraps = $userScraps->toArray ();

        $userScrapDict = array_column ($userScraps, null, 'scrap_id');
        foreach ($scrap as $index => $item) {
            $item['has_number'] = $userScrapNum;
            $item['has_exchange'] = 0;
            $item['percent'] = bcmul (bcdiv ($userScrapNum, $item['count'], 2), 100);
            if (array_key_exists ($item['id'], $userScrapDict)) {
                $item['has_exchange'] = $userScrapDict[$item['id']]['exchange'];
            }
        }

        $data[Cfg::RECHARGE_LUCKY] = $config;
        $data['lucky_log'] = $rec;
        $data['scrap_list'] = $scrap;
        Common::res (compact ('data'));
    }

    public function achievement()
    {
        $configCheck = input ('config', false);

        $type = input ('type', false);
        if (false === $type) Common::res (['code' => 1,'msg' => '请选择类别1']);

        $rankType = input ('rank_type', false);
        if (false === $rankType) Common::res (['code' => 1,'msg' => '请选择类别2']);

        $config = Cfg::getCfg (Cfg::ACHIEVEMENT);

        if (array_key_exists ($type, $config['rank_group']) == false) {
            Common::res (['code' => 1,'msg' => '请选择类别3']);
        }
        $typeField = $config['rank_group'][$type]['value'];

        if (array_key_exists ($rankType ,$config['rank_group'][$type]['btn']) == false) {
            Common::res (['code' => 1,'msg' => '请选择类别4']);
        }
        $rankTypeField = $config['rank_group'][$type]['btn'][$rankType]['value'];

        $page = input('page', 1);
        $size = input('size', 10);

        $extra = [];
        if ($rankTypeField == 'star') {
            $this->getUser ();
            $star_id = UserStar::getStarId ($this->uid);
            $extra = ['star_id' => $star_id];
        }

        $sum = bcmul ($page, $size);
        if ($sum > 100) {
            // 限制100条数据
            $list = [];
        } else {
            $list = UserAchievementHeal::getRankByTypeForAchievement ($typeField, $rankTypeField, $page, $size, $extra);
        }

        $data = $configCheck ? compact ('list', 'config'): compact ('list');

        Common::res (compact ('data'));
    }

    public function userInviteAssist()
    {
        $this->getUser ();
        $config = Cfg::getCfg (Cfg::INVITE_ASSIST);

        $config['end_time'] = strtotime ($config['time']['end']);

        $starId = UserStar::getStarId ($this->uid);
        $userInvite = UserInvite::where('user_id', $this->uid)->find ();

        if (empty($userInvite)) {
            $userInvite = [
                'user_id' => $this->uid,
                'star_id' => $starId,
                'invite_day' => 0,
                'invite_sum' => 0,
                'invite_day_settle' => []
            ];
        }

        $star = StarModel::get($starId);

        $config['idol_progress'] = $this->supportProgress ($config['idol_progress'], $star['invite_sum']);
        $config['my_progress'] = $this->supportProgress ($config['my_progress'], $userInvite['invite_day']);
        $config['idol_sum'] = $star['invite_sum'];
        $config['my_sum'] = $userInvite['invite_sum'];
        $config['my_day'] = $userInvite['invite_day'];
        $config['my_day_settle'] = $userInvite['invite_day_settle'];

        $config['rec_list'] = RecUserInvite::with(['user'])
            ->where('user_id', '>', 0)
            ->order ([
                'create_time' => 'desc'
            ])
            ->limit (10)
            ->select ();

        Common::res (['data' => $config]);
    }

    private function supportProgress($progress, $number, $key = 'value') {
        $lastValue = 0;
        $lastSum = $number;
        $weights = $progress[count ($progress) - 1][$key];
        foreach ($progress as $index => $item) {
            $value = bcsub ($item[$key], $lastValue);
            if ($lastSum > 0) {
                if ($lastSum > $value) {
                    $item['percent'] = 100;
                } else {
                    $item['percent'] = bcdiv ($lastSum, $value, 2) * 100;
                }
                $lastSum -= $value;
            } else {
                $item['percent'] = 0;
            }
            $lastValue = $item[$key];
            $item['weights'] = bcdiv ($value, $weights, 2) * 100;

            $progress[$index] = $item;
        }

        return $progress;
    }

    public function manor()
    {
        // 我的庄园信息
        $this->getUser();
        $user_id = $this->uid;

        $currentTime = time();

        $manor = UserManor::get(['user_id' => $user_id]);
        $panaceaReward = 0;
        $new = empty($manor);
        $try = [];
        if ($new) {
            $useAnimal = 1;
            $output = 1;
            $background = 1;
            $callType = 'goCall';
            $manor = UserManor::create([
                'user_id' => $user_id,
                'last_output_time' => $currentTime,
                'use_animal' => $useAnimal,
                'output' => $output,
                'background' => $background,
                'try_data' => '[]'
            ]);
            $animal = UserAnimal::create([
                "user_id" => $user_id,
                'animal_id' => $useAnimal,
                'scrap' => 0,
                'level' => 1,
            ]);
            UserManorBackground::create([
                'user_id' => $user_id,
                'background' => $background,
            ]);
            $addCount = 0;
            $autoCount = false;
            $steal_left = 1;
            $panaceaReward = UserManor::getFlowerReward($user_id);
            $boxLog = [];
        } else {
            $useAnimal = $manor['use_animal'];
            $diffTime = bcsub($currentTime, $manor['last_output_time']);
            $output = UserAnimal::getOutput($user_id, CfgAnimal::OUTPUT);
            if ((int)$output != (int) $manor['output']) {
                UserManor::where('id', $manor['id'])
                    ->where('output', $manor['output'])
                    ->update(['output' => $output]);
            }
            $steal_left = UserAnimal::getOutput($user_id, CfgAnimal::STEAL);

            $addCount = UserAnimal::getOutputNumber($user_id, $diffTime, $manor['count_left']);
            $autoCount = false;
            $background = $manor['background'];
            if (empty($background)) {
                $background = 1;
                UserManor::where('id', $manor['id'])->update(['background' => $background]);
            }

            $animalIds = CfgAnimal::where('type', 'NORMAL')->column('id');

            $normalAnimalNum = UserAnimal::where('user_id', $user_id)
                ->where('animal_id', 'in', $animalIds)
                ->count();
            $callType = $normalAnimalNum == 12 ? 'goSupple': 'goCall';

            if ($manor['try_data']) {
                foreach ($manor['try_data'] as $item) {
                    if ($item['time'] > $currentTime) {
                        $try = $item;
                    }
                }
            }
            $boxLog = UserManorLog::with(['otherUser', 'user'])
                ->where('other|user_id', $user_id)
                ->where('type', 'LOTTERY_ANIMAL_BOX')
                ->limit(6)
                ->select();
        }

        $mainAnimal = CfgAnimal::get($useAnimal);
        $nums = UserExt::where('user_id', $user_id)->value('animal_lottery');
        $config = Cfg::getCfg(Cfg::MANOR_ANIMAL);
        $maxLottery = (int)$config['lottery']['max'];
        if ($nums > $maxLottery) {
            $lotteryLeft = 0;
        } else {
            $lotteryLeft = bcsub($maxLottery, $nums);
        }

        $max_output_hours = $config['max_output_hours'];
        $limit_add_time = (int)bcmul($max_output_hours, 360);
        $normalStr = [
            "记得常来看我",
//            "庄园金豆存8小时不再生产",
//            "庄园生产金豆在线离线都一样",
            "宠物列表可换宠物",
//            "个人账户金豆每周日清零",
//            "鲜花月榜有超多大屏奖励",
//            "金豆月榜奖励5000元应援金",
//            "人气周榜福利2400元应援金",
//            "打榜送鲜花占领封面宣传爱豆",
//            "新人礼包、成长礼包记得去领",
//            "打卡7天可获得6666元应援金",
            "记得报名参加团战PK",
//            "无聊了去圈子和大家聊天吧",
//            "粉丝团集结每小时都能参与",
            "你有几个荣誉徽章呢",
//            "你还差多少人气升级呢",
            "今天做了多少任务了",
        ];

        $secretStr = [
            "付出总是有回报",
            "跟着你好有幸福感",
            "点我是因为想我吗",
            "好想抱抱你",
            "点我要轻一点哦",
            "希望明天是晴天",
            "再点我就气鼓鼓",
            "不要放弃爱和梦想",
            "我想听听你的故事",
            "不要忘记每天陪陪我",
            "总有心动的感觉",
            "想要你的抱抱",
//            "或许我们可以一起走",
        ];
        $str = $mainAnimal['type'] == 'NORMAL' ? $normalStr: $secretStr;

        $mainBackground = CfgManorBackground::get($background);
        if ($try) {
            $tryBackground = CfgManorBackground::get($try['id']);
            $tryBackground['time'] = $try['time'];
        }

        $index = rand(0, count($str) - 1);
        $word = $str[$index];

        Common::res(['data' => [
            'manor' => $manor,
            'output' => (int)$output,
            'add_count' => (int)$addCount,
            'auto_count' => $autoCount,
            'main_animal' => $mainAnimal,
            'lottery_left' => $lotteryLeft,
            'steal_left' => $steal_left,
            'limit_time' => $limit_add_time,
            'panacea_reward' => $panaceaReward,
            'word' => $word,
            'max_lottery'  => $maxLottery,
            'main_background'  => $mainBackground,
            'try_background'  => empty($tryBackground) ? null: $tryBackground,
            'call_type' => $callType,
            'box_log' => $boxLog
        ]]);
    }
    
    public function customAd()
    {
        $this->getUser();

        $list = CfgNovel::all();
        $list = collection($list)->toArray();

        $ids = array_column($list, 'id');
        $lucky = rand(0, count($ids) - 1);
        $luckyId = $ids[$lucky];
        $item = [];
        foreach ($list as $key => $value) {
            if ($value['id'] == $luckyId) {
                $item = $value;
                break;
            }
        }

        $data['banner'] = $item['img'];

        $data['content'] = $item['content'];
        $data['second'] = 15;

        Common::res(compact('data'));
    }

    public function otherManor()
    {
        $this->getUser();
        // 查看别人家的庄园
        $user_id = (int)input('user_id', 0);
        if (empty($user_id)) {
            Common::res(['code' => 1, 'msg' => '请选择拜访好友']);
        }
        $currentTime = time();

        $manor = UserManor::get(['user_id' => $user_id]);
        $selfManor = UserManor::get(['user_id' => $this->uid]);
        $panaceaReward = 0;
        $new = empty($manor);
        $try = [];
        if ($new) {
            $useAnimal = 1;
            $output = 1;
            $background = 1;
            $manor = UserManor::create([
                'user_id' => $user_id,
                'last_output_time' => $currentTime,
                'use_animal' => $useAnimal,
                'output' => $output,
                'background' => $background,
                'try_data' => '[]'
            ]);
            $animal = UserAnimal::create([
                "user_id" => $user_id,
                'animal_id' => $useAnimal,
                'scrap' => 0,
                'level' => 1,
            ]);
            UserManorBackground::create([
                'user_id' => $user_id,
                'background' => $background,
            ]);
            $panaceaReward = UserManor::getFlowerReward($user_id);
            $boxLog = [];
        } else {
            $useAnimal = $manor['use_animal'];
            $background = $manor['background'];
            if (empty($background)) {
                $background = 1;
                UserManor::where('id', $manor['id'])->update(['background' => $background]);
            }

            if ($manor['try_data']) {
                foreach ($manor['try_data'] as $item) {
                    if ($item['time'] > $currentTime) {
                        $try = $item;
                    }
                }
            }
            $boxLog = UserManorLog::with(['user'])
                ->where('other', $user_id)
                ->where('type', 'LOTTERY_ANIMAL_BOX')
                ->limit(6)
                ->select();
        }

        $mainAnimal = CfgAnimal::get($useAnimal);

        $normalStr = [
            "记得常来看我",
            //            "庄园金豆存8小时不再生产",
            //            "庄园生产金豆在线离线都一样",
            "宠物列表可换宠物",
            //            "个人账户金豆每周日清零",
            //            "鲜花月榜有超多大屏奖励",
            //            "金豆月榜奖励5000元应援金",
            //            "人气周榜福利2400元应援金",
            //            "打榜送鲜花占领封面宣传爱豆",
            //            "新人礼包、成长礼包记得去领",
            //            "打卡7天可获得6666元应援金",
            "记得报名参加团战PK",
            //            "无聊了去圈子和大家聊天吧",
            //            "粉丝团集结每小时都能参与",
            "你有几个荣誉徽章呢",
            //            "你还差多少人气升级呢",
            "今天做了多少任务了",
        ];

        $secretStr = [
            "付出总是有回报",
            "跟着你好有幸福感",
            "点我是因为想我吗",
            "好想抱抱你",
            "点我要轻一点哦",
            "希望明天是晴天",
            "再点我就气鼓鼓",
            "不要放弃爱和梦想",
            "我想听听你的故事",
            "不要忘记每天陪陪我",
            "总有心动的感觉",
            "想要你的抱抱",
            //            "或许我们可以一起走",
        ];
        $str = $mainAnimal['type'] == 'NORMAL' ? $normalStr: $secretStr;

        $mainBackground = CfgManorBackground::get($background);
        if ($try&&empty($visit)) {
            $tryBackground = CfgManorBackground::get($try['id']);
            $tryBackground['time'] = $try['time'];
        }

        $index = rand(0, count($str) - 1);
        $word = $str[$index];
        $status = 0;
        if (count($selfManor['day_lottery_box']) >= 3) $status = -1;
        if (in_array($user_id, $selfManor['day_lottery_box'])) $status = 1;

        Common::res(['data' => [
            'manor' => $manor,
            'main_animal' => $mainAnimal,
            'panacea_reward' => $panaceaReward,
            'word' => $word,
            'main_background'  => $mainBackground,
            'try_background'  => empty($tryBackground) ? null: $tryBackground,
            'box_log' => $boxLog,
            'lottery_status' => $status
        ]]);
    }
}

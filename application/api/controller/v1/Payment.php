<?php

namespace app\api\controller\v1;

use app\api\model\UserCurrency;
use app\base\service\alipay\request\AlipayTradeWapPayRequest;
use app\api\model\Cfg;
use app\base\controller\Base;
use app\base\service\AliPayApi;
use app\base\service\Common;
use app\api\model\PayGoods;
use app\api\model\RecPayOrder;
use app\base\service\WxAPI;
use app\api\model\User;
use app\api\model\UserSprite;
use app\base\service\WxPay as WxPayService;
use Exception;
use think\Db;
use think\Log;

class Payment extends Base
{
    /**商品列表 */
    public function goods()
    {
        $platform = input('platform', 'MP-WEIXIN');
        if ($platform != 'H5-OTHER') {
            $this->getUser();
            // 我的优惠
            $res['discount'] = PayGoods::getMyDiscount($this->uid);

            // 农场产量
            $res['farm_coin'] = UserSprite::where('user_id', $this->uid)->value('total_speed_coin');
            $res['farm_distance'] = 432 - $res['farm_coin'];
        } else {
            $user_id = input('user_id', false);
            if ($user_id&&(int)$user_id) {
                // 我的优惠
                $res['discount'] = PayGoods::getMyDiscount($this->uid);

                // 农场产量
                $res['farm_coin'] = UserSprite::where('user_id', $this->uid)->value('total_speed_coin');
                $res['farm_distance'] = 432 - $res['farm_coin'];
                $res['currency'] = UserCurrency::getCurrency($user_id);
            }
        }
        $res['list'] = PayGoods::all();

        Common::res(['data' => $res]);
    }

    /**
     * 下单
     */
    public function order()
    {
        $type = $this->req('type', 'require');
        $tar_user_id = $this->req('user_id', 'require',0);
        $count = $this->req('count', 'integer'); // 数目
        $payType = $this->req('pay_type', 'require', RecPayOrder::WECHAT_PAY);
        if (in_array($payType, [RecPayOrder::WECHAT_PAY, RecPayOrder::QQ_PAY])) {
            $this->getUser();
            $user_id = $this->uid;
        } else {
            $user_id = (int)$tar_user_id;
            if (empty($user_id)) {
                Common::res(['code' => 1, 'msg' => '请选择充值用户']);
            }
        }
        if(User::where('id',$user_id)->value('type')==5) Common::res(['code' => 1, 'msg' => '该账号检测不安全，不予以充值']);

        $discount = PayGoods::getMyDiscount($user_id);
        if ($type == 'stone') {
            $rate = Cfg::getCfg('recharge_rate')['stone'];
            $totalFee = $count * $rate;
            if ($count < 100) Common::res(['code' => 1, 'msg' => '数值过小，需大于100颗']);
            $count *= $discount['increase'];
        } else if ($type == 'flower') {
            $rate = Cfg::getCfg('recharge_rate')['flower'];
            $totalFee = $count * $rate;
            if ($count < 1000000) Common::res(['code' => 1, 'msg' => '数值过小，需大于100万']);
            $count *= $discount['increase'];
        }

        $minCount = strlen(substr(strrchr($totalFee, "."), 1));
        if ($minCount > 0) {
            // 有小数位数即不符合比例
            Common::res(['code' => 1, 'msg' => '数目不正确']);
        }

        // 下单
        $order = RecPayOrder::create([
            'id' => date('YmdHis') . mt_rand(1000, 9999),
            'user_id' => $user_id,
            'tar_user_id' => $tar_user_id,
            'total_fee' => $totalFee,
            'goods_info' => json_encode([$type => $count], JSON_UNESCAPED_UNICODE), // 商品信息
            'platform' => input('platform', null),
            'pay_type' => $payType
        ]);

        if ($payType == RecPayOrder::QQ_PAY) {
            // 预支付参数
            $config = [
                'body' => '充值', // 支付标题
                'orderId' => $order['id'], // 订单ID
                'totalFee' => $totalFee, // 支付金额
                'notifyUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/v1/pay/notify/' . input('platform'), // 支付成功通知url
                'tradeType' => 'MINIAPP', // 支付类型
            ];
            // APP和小程序差异
            $openidType = 'openid';

            $config['openid'] = User::where('id', $user_id)->value($openidType);
            if (!$config['openid']) Common::res(['code' => 1, 'msg' => '请先登录小程序']);

            $res = (new WxAPI())->unifiedorder($config);

            // 处理预支付数据
            (new WxPayService())->returnFront($res);
        }

        if ($payType == RecPayOrder::WECHAT_PAY) {
            // 预支付参数
            $config = [
                'body' => '充值', // 支付标题
                'orderId' => $order['id'], // 订单ID
                'totalFee' => $totalFee, // 支付金额
                'notifyUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/v1/pay/notify/' . input('platform'), // 支付成功通知url
                'tradeType' => 'JSAPI', // 支付类型
            ];
            // APP和小程序差异
            $openidType = 'openid';
            if (input('platform') == 'APP') {
                $openidType = 'openid_app';
                $config['tradeType'] = 'APP';
            } else if (input('platform') == 'MP-QQ') {
                $config['tradeType'] = 'MINIAPP';
            }

            $config['openid'] = User::where('id', $user_id)->value($openidType);
            if (!$config['openid']) Common::res(['code' => 1, 'msg' => '请先登录小程序']);

            $res = (new WxAPI())->unifiedorder($config);

            // 处理预支付数据
            (new WxPayService())->returnFront($res);
        }

        if ($payType == RecPayOrder::ALI_PAY) {
            $totalFee = number_format($totalFee, 2, '.', '');

            $aop = AliPayApi::getInstance();

            $request = new AlipayTradeWapPayRequest ();

            $data = [
                'body' => "",
                'subject' => "充值",
                'out_trade_no' => $order['id'],
                'timeout_express' => "10m", // 支付截止时间
                'total_amount' => $totalFee, //
                'product_code' => "QUICK_WAP_WAY",
            ];
            $request->setBizContent(json_encode($data));
            $notifyUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/v1/pay/alipaynotify';
            $request->setNotifyUrl($notifyUrl);
            $returnUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/#/pages/charge/charge';
            $request->setReturnUrl($returnUrl);
            $result = $aop->pageExecute($request);
//            echo $result;
            Common::res(['data' => $result]);
        }
    }

    public function alipayNotify()
    {
        $data = request()->post();

        if ($data['trade_status'] != 'TRADE_SUCCESS') {
            Log::record("交易出错", 'error');
            Log::error(json_encode($data));
            die();
        }

        $aop = AliPayApi::getInstance();
        // 验签
        $res = $aop->rsaCheckV1($data, $aop->alipayrsaPublicKey, $data['sign_type']);
        if (empty($res)) {
            Log::record("验签错误", 'error');
            Log::error(json_encode($data));
            die();
        }

        $order = RecPayOrder::get($data['out_trade_no']);
        if (empty($order)) {
            Log::record("订单号找不到", 'error');
            Log::error(json_encode($data));
            die();
        }
        if ($order['pay_time'] && $order['pay_time'] == $data['gmt_payment']) {
            Log::record("订单已处理", 'error');
            Log::error(json_encode($data));
            echo "success";
            die();
        }
        // 处理订单状态和业务
        Db::startTrans();
        try {
            // 更改订单状态
            $isDone = RecPayOrder::where(['id' => $data['out_trade_no']])->update(['pay_time' => $data['gmt_payment']]);
            if ($isDone) {
                // 支付成功 处理业务
                RecPayOrder::paySuccess($order);
                Db::commit();
            }
        } catch (Exception $e) {
            Db::rollback();
            Log::record($e->getMessage(), 'error');
            die();
        }

        echo "success";
        die();
    }

    /**支付成功的通知 */
    public function notify()
    {
        $wxPayService = new WxPayService();
        $data = $wxPayService->notifyHandle();
        $order = RecPayOrder::get($data['out_trade_no']);
        if ($data['total_fee'] == $order['total_fee'] * 100) {
            // 处理订单状态和业务
            Db::startTrans();
            try {
                // 更改订单状态
                $isDone = RecPayOrder::where(['id' => $data['out_trade_no']])->update(['pay_time' => $data['time_end']]);
                if ($isDone) {
                    // 支付成功 处理业务
                    RecPayOrder::paySuccess($order);
                    Db::commit();

                    $wxPayService->returnSuccess();
                }
            } catch (Exception $e) {
                Db::rollback();
                Log::record($e->getMessage(), 'error');
            }
        }
    }
}

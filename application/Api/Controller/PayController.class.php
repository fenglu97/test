<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/8/29
 * Time: 17:56
 */
namespace Api\Controller;
use Common\Controller\AppframeController;
class PayController extends AppframeController {
    public function _initialize(){
        parent::_initialize();
        $this->inpour = D('Common/Inpour');
    }

    public function test(){
        $this->ajaxReturn(null,"dazdad\ndadadada",0);
       
    }

    public function getS(){
        $redis = new \Redis();
        $redis->connect('127.0.0.1',6379);
        $redis->auth(C('REDIS_PASS'));
//        dump($redis->setnx(123,123));
//        $redis->expire(123,10);
//        //$redis->flushAll();
        //$redis->rPush('order','2039239340_1');

//        $data = $redis->lRange('order',0,-1);
        $data = $redis->keys('*');
        dump($data);
    }

    /**
     * 获取VIP充值配置
     */
    public function getVipOption(){

        $vip = get_site_options();
        $new = array();
        foreach ($vip['vip'] as $k=>$v){
            $v['productID'] = $k;
            $v['coin'] = $vip['vip_sign_coin'];
            $new['list'][] = $v;
        }
        $this->ajaxReturn($new,'success');
    }

    /**
     * 支付前检测
     */
    public function payReady(){
        $data = I('');
        $res = $this->inpour->payCheck($data);
        if($res['status']){
            $this->ajaxReturn(null,$res['msg']);
        }else{
            $this->ajaxReturn(null,$res['msg'],0);
        }
    }

    /**
     * 发起支付
     */
    public function payStart(){
        if($_POST['payType'] == 10) {
            // 平台币支付验证
            $PTBPayVerify = $this->inpour->checkPTBpay($_POST);
            if($PTBPayVerify['status'] == 0) {
                $this->ajaxReturn(null,$PTBPayVerify['msg'],0);
            }
        }
        if($info = $this->inpour->create()){
            // $info['money'] = 0.1;
            logs('payStart',$info);
            try{
                if($info['payMode'] == 3){
                    $table = M('inpour_ptb');
                    $id = $table->add($info);

                    $mode = 3;
                }else{
                    $table = $this->inpour;
                    $id = $table->add();
                    $mode = 1;
                }

                //如果是平台币支付直接请求游戏服务器发货，否则跳转支付
                if($_POST['payType'] == 10){
                    $url = 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=pay&a=paySuccess&money={$info['money']}&order={$info['orderID']}&time={$info['create_time']}";
                    $userPtb = M('player')->where(array('id'=>$info['uid']))->getField('platform_money');
                    $map = array(
                        'uid' => $info['uid'],
                        'type' => 2,
                        'platform_change' => -$info['platform_money'],
                        'platform_counts' => $userPtb,
                        'create_time' => time()
                    );
                    M('platform_detail_logs')->add($map);
                    $this->sendGoods($info['orderID']); //TODO 先关闭通知游戏发货
                    diylogs('coinPay',array_merge(array('suburl'=>$url),$info));
                }else{
                    $sign = md5(sha1($id).C('185KEY'));

                    // 充值进行实名认证
                    $realVerify = $this->inpour->checkRealVerify($info);
                    if($realVerify['status'] == 0) {
                        $this->ajaxReturn(null,$realVerify['msg'],0);
                    }

                    //3,4微信 1，2，11支付宝
                    if($_POST['payType'] == 3 || $_POST['payType'] == 4){
                        $res = $this->inpour->wechatpay($info);
                        if($res['return_code'] == 'SUCCESS'){
                            $url = 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=pay&a=wechatpaySubmit&id={$id}&mode={$mode}&sign={$sign}";
                        }else{
                            $this->ajaxReturn(null,'支付错误，请稍候重试',0);
                        }
                    }else{
                        $res = $this->inpour->alipay($info);
                        if($res){
                            $url = 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=pay&a=alipaySubmit&id={$id}&mode={$mode}&sign={$sign}";
                        }else{
                            $this->ajaxReturn(null,'支付错误，请稍候重试',0);
                        }
                    }
                }

                $this->ajaxReturn(array('url'=>$url,'orderID'=>$info['orderID']),'success');
            }catch (\Exception $e){
                $this->ajaxReturn(null,'系统忙，请重新发起支付',0);
            }

        }else{
            $this->ajaxReturn(null,$this->inpour->getError(),0);
        }
    }

    /**
     * 支付成功页面
     */
    public function paySuccess(){
        $data = I('');

        if($data['payMode'] == 3) {
            $table = 'inpour_ptb';
        }else {
            $table = 'inpour';
        }

        $order = M($table)->where(array('orderID'=>$data['order']))->find();
        if(!$order){
            $order = M($table)->where(array('orderID'=>$data['out_trade_no']))->find();
        }
        if(!$order){
            $order = M($table)->where(array('orderID'=>$data['out_trade_no']))->find();
        }
        if(!$order) {
            $order = M($table)->where(array('orderID'=>$data['orderId']))->find();
        }
        if(!$order) {
            $order = M($table)->where(array('orderID'=>$data['wechatOrder']))->find();
        }
        if($order['status'] != 3 && $order){
            $data['msg'] = '恭喜您，成功充值'.$order['money'].'元';
            $data['order'] = $order['orderID'];
            $data['money'] = $order['money'];
            $data['status'] = '支付成功';
	        $data['time'] = $order['create_time'];
            $data['sate'] = 1;
        }else{
            $data['msg'] = '正在支付中，请稍等';
            $data['status'] = '正在支付中，请稍等';
            $data['sate'] = 3;
            $data['time'] = time();
        }
        $this->data = $data;
        $this->display();
    }

    /**
     * VIP支付成功页
     */
    public function ptbSuccess(){
        $data = I('');
        $order = M('inpour_ptb')->where(array('orderID'=>$data['order']))->find();
 
        if($order['status'] != 3 && $order){
            $data['msg'] = '恭喜您，成功充值'.$order['money'].'元';
            $data['status'] = '支付成功';
            $data['time'] = $order['pay_to_time'];
            $data['money'] = $order['money'];
            $data['sate'] = 1;
        }else{
            $data['msg'] = '正在支付中，请稍等';
            $data['status'] = '正在支付中，请稍等';
            $data['sate'] = 3;
            $data['time'] = time();
        }
        $this->data = $data;
        $this->display();
    }

    public function wechatpaySubmit(){
        $data = I();
        if(md5(sha1($data['id']).C('185KEY')) != $data['sign']) exit('非法请求');

        if($data['mode'] == 3){
            $info = M('inpour_ptb')->where(array('id'=>$data['id']))->find();
        }else{
            $info = M('inpour')->where(array('id'=>$data['id']))->find();
        }
        $json = json_decode($info['info'],1);

        if(!$info || $info['status'] == 1){
            exit('非法订单信息');
        }else{
            if($info['payType'] == 3){

                $this->img = str_replace('\r\n','',$json['img']);
                $this->orderid = $info['orderID'];
                $this->money = $info['money'];
                $this->display('alipayQRcode');
            }else{
                $str = "<form id='sub' method='post' action=".$json['url'].">";
                $str .= '</form>正在努力打开微信...<script type="text/javascript">document.getElementById("sub").submit();</script>';
                exit($str);
            }

        }
    }

    /**
     * 原生支付宝提交页
     */
    public function alipaySubmit(){
        header("Content-type: text/html; charset=utf-8");
        diylogs('alipaySubmit',I());
	    $data = I();
        if(md5(sha1($data['id']).C('185KEY')) != $data['sign']) exit('非法请求');

        if($data['mode'] == 3){
            $info = M('inpour_ptb')->where(array('id'=>$data['id']))->find();
        }else{
            $info = M('inpour')->where(array('id'=>$data['id']))->find();
        }
        $json = json_decode($info['info'],1);

        if(!$info || $info['status'] == 1){
            exit('非法订单信息');
        }else{
            if($info['payType'] == 1){

                $this->img = str_replace('\r\n','',$json['img']);
                $this->orderid = $info['orderID'];
                $this->money = $info['money'];
                $this->display('alipayQRcode');
            }else{
                $str = "<form id='sub' method='post' action=".$json['url'].">";
                $str .= '</form>正在努力打开支付宝...<script type="text/javascript">document.getElementById("sub").submit();</script>';
                exit($str);
            }

        }
    }



    /**
     * 支付宝回调
     */
    public function aliPayReturn(){
        diylogs('alipayReturn',$_POST);
        $payMode = $_POST['payMode'];
        unset($_POST['payMode']);
        vendor('alipay.AopSdk');
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $res = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if($res && ($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED')){
            $redis = new \Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->auth(C('REDIS_PASS'));
            if($_POST['body'] == 3 || $payMode == 3){
                $table = 'inpour_ptb';
            }else{
                $table = 'inpour';
            }
            $order = M($table)->where(array('orderID'=>$_POST['out_trade_no']))->find();
            if($order['status'] == 1){
                exit('success');
            }
            //更改订单状态、参数
            if($order['payType'] == 1){
                $rate = 0.006;//0.6%
            }else{
                $rate = 0.01;//1%
            }
            $set = array(
                'jz_other' => $_POST['trade_no'],
                'status' => 2,
                'pay_to_time' => time(),
                'getmoney' => round($order['money']-($order['money'] * $rate),2)
            );
            M($table)->where(array('orderID'=>$_POST['out_trade_no']))->setField($set);

            //平台币充值不涉及游戏发货
            if($order['payMode'] != 3){
                //调统计接口
                if($order['deviceType'] == 1){
                    $extend = json_decode($order['extend'],1);
                    postback_videoads($order['cid'],$order['appid'],$extend['android_id'],$extend['imei'],3,$order['money']);
                }
                $redis->rPush('order',$order['orderID'].'_1_'.time());
            }else{
                //更新用户VIP信息
                $this->setPtbOption($order);

//                if(!$vip) logs('vip',$order);

                //任务记录
//                M('task')->add(array('uid'=>$order['uid'],'type'=>7,'create_time'=>time()));
                M($table)->where(array('orderID'=>$order['orderID']))->setField(array('status'=>1,'pay_to_time'=>time()));

            }
            //保存玩家支付信息
            $request1 = $this->savePlayerPay($order['jz_other'],$order['payMode'],$table);
            //计算分成
            $request2 = $this->channelCut($order['jz_other'],$table);
            //给好友分帐
            $friend = $this->friendsRebate($order['uid'],$order['money']);
            if(!$friend) logs('friend',$order);
            if(!$request1) logs('savePlayerPayError',$order['jz_other']);
            if(!$request2) logs('channelCutError',$order['jz_other']);

            exit('success');
        }else{
            logs('alipayReturn_error',$res);
            exit('fail');
        }
    }

    /**
     * 微信支付回调
     */
    public function wechatReturn(){
        vendor('wechatPay');
        $wechat = new \wechatPay();
        $wechat->key = C('wechat.KEY');
        $xml = file_get_contents('php://input');
        $data = $wechat->xml_to_data($xml);
        //记录日志
        diylogs('wechatReturn',$data);
        $payMode = $data['payMode'];
        //验证签名
        $postSign = $data['sign'];
        unset($data['sign']);
        unset($data['payMode']);
        $localSign = $wechat->makeSign($data);
        if($postSign == $localSign){
            $redis = new \Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->auth(C('REDIS_PASS'));
            if($_POST['body'] == 3 || $payMode == 3){
                $table = 'inpour_ptb';
            }else{
                $table = 'inpour';
            }
            $order = M($table)->where(array('orderID'=>$data['out_trade_no']))->find();
            if($order['status'] == 1){
                exit('success');
            }
            //更改订单状态、参数
            $rate = 0.01;
            
            $set = array(
                'jz_other' => $data['transaction_id'],
                'status' => 2,
                'pay_to_time' => time(),
                'getmoney' => round($order['money']-($order['money'] * $rate),2)
            );
            M($table)->where(array('orderID'=>$data['out_trade_no']))->setField($set);

            //平台币充值不涉及游戏发货
            if($order['payMode'] != 3){
                //调统计接口
                if($order['deviceType'] == 1){
                    $extend = json_decode($order['extend'],1);
                    postback_videoads($order['cid'],$order['appid'],$extend['android_id'],$extend['imei'],3,$order['money']);
                }
                $redis->rPush('order',$order['orderID'].'_1_'.time());
            }else{
                //更新用户VIP信息
                $this->setPtbOption($order);

//                if(!$vip) logs('vip',$order);

                //任务记录
//                M('task')->add(array('uid'=>$order['uid'],'type'=>7,'create_time'=>time()));
                M($table)->where(array('orderID'=>$order['orderID']))->setField(array('status'=>1,'pay_to_time'=>time()));

            }
            //保存玩家支付信息
            $request1 = $this->savePlayerPay($order['jz_other'],$order['payMode'],$table);
            //计算分成
            $request2 = $this->channelCut($order['jz_other'],$table);
            //给好友分帐
            $friend = $this->friendsRebate($order['uid'],$order['money']);
            if(!$friend) logs('friend',$order);
            if(!$request1) logs('savePlayerPayError',$order['jz_other']);
            if(!$request2) logs('channelCutError',$order['jz_other']);

            echo $wechat->data_to_xml(array('return_code'=>'SUCCESS'));
        }else{
            echo $wechat->data_to_xml(array('return_code'=>'FAIL','return_msg'=>'签名错误'));
        }
    }

    /**
     * 充值后操作
     */
    public function setPtbOption($data){
        $ptb = $data['money'] * 10;
        $set['platform_money'] = array('exp','platform_money+'.$ptb);
        $oldptb = M('player')->where(array('id'=>$data['uid']))->getField('platform_money');
        if(M('player')->where(array('id'=>$data['uid']))->setField($set) !== false){

            $map = array(
                'uid' => $data['uid'],
                'type' => 5,
                'platform_change' => $ptb,
                'platform_counts' => $oldptb + $ptb,
                'create_time' => time()
            );
            M('platform_detail_logs')->add($map);
            return true;
        }else{
            return false;
        }
    }

    /**
     * 给推荐人返利
     * @param int $uid 本次充值用户
     * @param int $money 本次充值金额
     * @return bool
     */
    public function friendsRebate($uid,$money){
        //推荐人ID
        $friend = M('player')->where(array('id'=>$uid))->getField('referee_uid');

        if($friend){
            //金币比率
            $coinRatio = get_site_options();
            $coinRatio = $coinRatio['friend_coin_ratio'];
            //单个好友封顶奖励
            $coinTop = get_site_options();
            $coinTop = $coinTop['friend_coin_top'];
            $coinLog['uid&type&link_uid'] = array($friend,3,$uid,'_multi'=>true);
            $countCoin = M('coin_log')->where($coinLog)->sum('coin_change');
            if($countCoin < $coinTop){
                //剩余可获得的金币值
                $diff = $coinTop - $countCoin;
                //本次可获得的金币值
                $getCoin = $money * $coinRatio;

                //如果本次得到的金币值大于剩余可获得金币值，则把剩余可获得金币值充入帐户
                $setCoin = $getCoin > $diff ? $diff : $getCoin;
                $totleCoin = M('player')->where(array('id'=>$friend))->getField('coin');
                $map = array(
                    'uid' => $friend,
                    'type' => 3,
                    'link_uid' => $uid,
                    'coin_change' => $setCoin,
                    'coin_counts' => $totleCoin + $setCoin,
                    'create_time' => time()
                );
                if(M('player')->where(array('id'=>$friend))->setInc('coin',$setCoin)){
                    M('coin_log')->add($map);
                    return true;
                }else{
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * 定时重发和手动重发
     */
    public function reSend(){
        if(IS_AJAX){
            $order = I('order');
            $res = $this->inpour->requestData($order);
            if($res){
                $this->ajaxReturn(null,'成功');
            }else{
                $this->ajaxReturn(null,'请求远程服务器失败',0);
            }
        }else{
            $redis = new \Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->auth(C('REDIS_PASS'));
            $data = array();
            $resend = array();
            //取出所有队列值
            while ($orderid = $redis->lpop('order')){
                $data[] = $orderid;
            }
            if(count($data) > 0){
                foreach($data as $k=>$v){
                    $info = explode('_',$v);
                    //不超过20次的做处理
                    if($info[1] <= 20){
                        //如果是第一次直接请求
                        if($info[1] == 1){
                            $resend[] = $v;
                        }else{
                            //每次重发固定+20秒，时间到了存入待处理数组
                            if((time() - $info[2]) > 20){
                                $resend[] = $v;
                            }else{
                                $redis->rPush('order',$v);
                            }
                        }
                    }
                }
                //处理重发数据
                if(count($resend) > 0){
                    foreach($resend as $k=>$v){
                        $info = explode('_',$v);
                        $res = $this->inpour->requestData($info[0]);
                        if(!$res){
                            $redis->rPush('order',$info[0].'_'.($info[1]+1).'_'.time());
                        }
                    }
                }
            }
        }
    }

    /**
     * 查询支付状态
     */
    public function payQuery(){
        $order = I('orderID') ? I('orderID') : I('get.orderID');
        $sign = I('sign') ? I('sign') : I('get.sign');
        $payMode = I('payMode') ? I('payMode') : I('get.payMode');

        if($payMode == 3) {
            $table = 'inpour_ptb';
        }else {
            $table = 'inpour';
        }
        $res = M($table)->where(array('orderID'=>$order))->find();
        $key = M('game')->where(array('id'=>$res['appid']))->getField('client_key');

        if(!$res) $this->ajaxReturn(null,'order is null',0);
        if(md5('orderID='.$res['orderID'].$key) != $sign) $this->ajaxReturn(null,'signature mismatch',0);
        $setOrder = $res['orderID'];
        $info = array(
            'order_status' => $res['status'],
            'url' => $res['status'] != 3 ? 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=pay&a=paySuccess&money={$res['money']}&order={$setOrder}&payMode={$payMode}&time={$res['create_time']}" : '',
        );
        $this->ajaxReturn($info,'success');
    }

    /**
     * 查询VIP支付状态
     */
    public function ptbQuery(){
        $order = I('orderID');

        $res = M('inpour_ptb')->where(array('orderID'=>$order))->find();

        $setOrder = $res['orderID'];
        $info = array(
            'order_status' => $res['status'],
            'url' => $res['status'] != 3 ? 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=pay&a=paySuccess&money={$res['money']}&order={$setOrder}&time={$res['create_time']}" : '',
        );
        $this->ajaxReturn($info,'success');
    }

    /**
     * GM权限后台发货
     */
    public function getGMPower($appid,$serverid,$username,$gm_gear_id){
        $res = $this->inpour->sendGmPower($appid,$serverid,$username,$gm_gear_id);
        return json_encode($res);
    }

    /**
     * 通知游戏发货
     */
    protected function sendGoods($order){
        if($order){
            $info = $this->inpour->requestData($order);
            if(!$info){
                $redis = new \Redis();
                $redis->connect('127.0.0.1',6379);
                $redis->auth(C('REDIS_PASS'));
                $redis->rPush('order',$order.'_1_'.time());
            }
        }else{
            diylogs('sendError',$order);
        }
    }


    /**
     * 保存玩家支付信息
     * @param $order
     */
    public function savePlayerPay($jzorder,$payMode,$table){
        $info = M($table)->where(array('jz_other'=>$jzorder))->find();
        $res = false;
        if(!$id = M('player_charge')->where(array('uid'=>$info['uid'],'appid'=>$info['appid']))->find()){
            $map = array(
                'uid'          => $info['uid'],
                'appid'        => $info['appid'],
                'channel'      => $info['cid'],
                'total_charge' => $info['money'],
                'first_charge' => $info['money'],
                'create_time'  => time()
            );
            if(M('player_charge')->add($map)) $res = true;
        }else{
            if(M('player_charge')->where(array('id'=>$id['id']))->setInc('total_charge',$info['money']) !== false) $res= true;
        }
        if($payMode == 2){
            $data = array(
                'appid'       => $info['appid'],
                'serverid'    => $info['serverID'],
                'username'    => $info['username'],
                'orderID'     => $info['orderID'],
                'gm_gear_id'  => $info['productID'],
                'type'        => 1,
                'create_time' => time()
            );
            if(M('user_gm')->add($data)) return true;
        }
        return $res;
    }


    /**
     * 计算分成
     * @param $jzorder
     */
    public function channelCut($jzorder,$table){
        $res = false;
        $orderinfo = M($table)->where(array('jz_other'=>$jzorder))->find();
        $money = $orderinfo['getmoney'];
        //查出顶级渠道下的下级渠道
//        $sql = "select id,parent,gain_sharing from __CHANNEL__ where if((select parent from __CHANNEL__ where id={$orderinfo['cid']}) > 0,id=(select parent from __CHANNEL__ where id={$orderinfo['cid']}) or parent=(select parent from __CHANNEL__ where id={$orderinfo['cid']}) or id={$orderinfo['cid']},id={$orderinfo['cid']})  order by id";

        $cid = M('channel')->field('id,parent,gain_sharing')->where(array('id'=>$orderinfo['cid']))->find();
        if($cid['parent'] > 0){
            $first = M('channel')->where(array('id'=>$cid['parent']))->getField('gain_sharing');
            for($i = 0;$i < 2;$i++){
                if($i == 0){
                    $money = round($money * $first,2);
                }else{
                    $money = round($money * $cid['gain_sharing'],2);
                }
                $data[$i] = array(
                    'orderid'       => $orderinfo['orderID'],
                    'orderid_other' => $orderinfo['jz_other'],
                    'cid'           => $i == 0 ? $cid['parent'] : $cid['id'],
                    'parent'        => $i == 0 ? 0 : $cid['parent'],
                    'appid'         => $orderinfo['appid'],
                    'ordermoney'    => $orderinfo['money'],
                    'actualmoney'   => $orderinfo['getmoney'],
                    'getmoney'      => $money,
                    'create_time'   => $orderinfo['create_time']
                );
            }
        }else{
            $money = round($money * $cid['gain_sharing'],2);
            $data[] = array(
                'orderid'       => $orderinfo['orderID'],
                'orderid_other' => $orderinfo['jz_other'],
                'cid'           => $cid['id'],
                'parent'        => 0,
                'appid'         => $orderinfo['appid'],
                'ordermoney'    => $orderinfo['money'],
                'actualmoney'   => $orderinfo['getmoney'],
                'getmoney'      => $money,
                'create_time'   => $orderinfo['create_time']
            );
        }
        if(M('inpour_cut')->addAll($data)) $res = true;
        return $res;
    }

    public function checkOrder(){
        $id = I('id');
        $status1 = M('inpour')->where(array('orderID'=>$id))->getField('status');
        $status2 = M('inpour_ptb')->where(array('orderID'=>$id))->getField('status');

        if(($status1 != 3 && $status1) || ($status2 != 3 && $status2)){
            $this->ajaxReturn(null,'success');
        }else{
            $this->ajaxReturn(null,'',0);
        }
    }
}
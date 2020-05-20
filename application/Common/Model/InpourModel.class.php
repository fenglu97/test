<?php
/**
 * 订单模型
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/31
 * Time: 10:13
 */
namespace Common\Model;
use Think\Model;

class InpourModel extends Model {

    protected $_map = array(
        'amount' => 'money',
        'channel' => 'cid',
        'extend' => 'extension'
    );

    protected $_validate = array(
        array('deviceType','require','system is null',1),
        array('appid','require','appid is null',1),
        array('cid','require','channel is null',1),
        array('money','require','money is null',1),
        array('money','checkMoney','金额错误，请重新启动游戏再支付',1,'callback'),
        array('uid','require','uid is null',1),
        array('app_uid','checkRequire','小号ID不能为空',1,'callback'),
        array('uid','checkUid','player is null',1,'callback'),
        array('app_uid','checkAppUid','小号ID错误',1,'callback'),
        array('serverID','require','server_id is null',1),
        array('serverNAME','require','server_name is null',1),
        array('roleID','require','role_id is null',1),
        array('roleNAME','require','role_name is null',1),
        array('productID','require','product_id is null',1),
        array('productNAME','require','product_name is null',1),
        array('extension','require','extension is null',1),
        array('payType','require','payType is null',1),
        array('payType','checkPayType','payType is error',1,'callback'),
        array('payMode','require','payMode is null',1),
//        array('payMode','checkPayMode','您已经购买该服务了',1,'callback'),
        //array('sign','require','sign is null',1),
        array('payType','checkPayCard_3','platform coin not enough',1,'callback'),
        array('payType','checkPayCard_4','该支付类型暂未开通，请使用支付宝或微信充值',1,'callback'),
        //array('sign','checkPostSign','签名失败',1,'callback'),
    );

    protected $_auto = array (
        array('cid','checkChannel',1,'callback'),
        array('orderID','orderID',1,'callback'),
        array('tag','getTag',1,'callback'),
        array('username','getUsername',1,'callback'),
        array('getmoney','0'),
        array('create_time','time',1,'function'),
        array('ip','getIp',1,'callback'),
        array('platform_money','getPt',1,'callback'),
        array('status','getStatus',1,'callback'),
        array('promoter_uid','getPromoter',1,'callback'),
        array('money','getMoney',1,'callback'),
        array('origPrice','getOrigPrice',1,'callback'),
        array('extend','getExtend',1,'callback')
    );

    protected function checkChannel(){
        $channel = M('player')->where(array('id'=>$_POST['uid']))->getField('channel');
        if(!$channel){
            $channel = C('MAIN_CHANNEL');
        }
        return $channel;
    }

    //自动完成
    protected function getUsername(){
        $name = M('player')->where(array('id'=>I('uid')))->getField('username');
        return $name;
    }

    //查询推广人
    protected function getPromoter(){
        $promoter_uid = M('player')->where(array('id'=>I('uid')))->getField('promoter_uid');
        return $promoter_uid;
    }

    //自动完成
    protected function getIp(){
        $ip = sprintf("%u",ip2long(getClientIP()));
        return $ip;
    }

    //自动完成
    protected function getPt(){
        $coin = 0;
        if($_POST['payType'] == 10){
            $coin = $_POST['amount'] * C('PLATFORM_COIN_RATIO');
        }
        return $coin;
    }

    //自动完成
    protected function getStatus(){
        $status = $_POST['payType'] == 10 ? 2 : 3;
        return $status;
    }

    //获取简写
    protected function getTag(){
        $tag = M('game')->where(array('id'=>$_POST['appid']))->getField('tag');
        return $tag;
    }

    //计算折扣
    protected function getMoney(){
        $money = $_POST['amount'];
        $discount = M('game')->where(array('id'=>$_POST['appid']))->getField('discount');
        if($_POST['payMode'] == 1 && $_POST['origPrice'] == 0 && $discount > 0){
            $money = round($money * ($discount/10),2);
        }
        return $money;
    }

    protected function getOrigPrice(){
        $origPrice = $_POST['origPrice'];
        $discount = M('game')->where(array('id'=>$_POST['appid']))->getField('discount');
        if($_POST['payMode'] == 1 && $_POST['origPrice'] == 0 && $discount > 0){
            if($discount > 0) $origPrice = $_POST['amount'];
        }
        return $origPrice ? $origPrice : 0;
    }

    //扩展字段
    protected function getExtend(){
        $json = '';
        if($_POST['android_id'] && $_POST['imei'] && $_POST['deviceType'] == 1){
            $json['android_id'] = $_POST['android_id'];
            $json['imei']= $_POST['imei'];
            $json = json_encode($json);
        }
        return $json;
    }

    //金额验证
    protected function checkMoney($money){
        // && strpos($money,'.') !== false 小数验证
        if($money < 1){
            //return false;
        }
        if($_POST['payMode'] == 2){
            $gearinfo = M('game g')
                ->field('gm.gear_info')
                ->join('left join '.C('DB_PREFIX').'gm_pri gm on g.gm_pri_id=gm.id')
                ->where(array('g.id'=>$_POST['appid']))
                ->find();
            $data = json_decode($gearinfo,1);
            foreach($data as $v){
                if($v['gear_id'] == $_POST['productID']){
                    if($v['gear_money'] != $money){
                        return false;
                    }
                }
            }
        }

        //验证折扣
        if($_POST['payMode'] == 1 && $_POST['origPrice'] > 0){
            $discount = M('game')->where(array('id'=>$_POST['appid']))->getField('discount');
            if($discount < 0 || $discount > 10){
                return false;
            }
            if($discount == 0 && $money == $_POST['origPrice']){
                return true;
            }else{
                $truemoney = round($_POST['origPrice']*($discount/10),2);
                //金额误差在0.5块钱
                $diff = $money - $truemoney;
                if($diff < 0) $diff = -$diff;
                if($diff <= 0.5 && $diff >= 0){
                    return true;
                }else{
                    return false;
                }
            }
        }
        return true;
    }

    //用户验证
    protected function checkUid($uid){
        if(!M('player')->where(array('id'=>$uid))->find()){
            return false;
        }
        return true;
    }

    protected function checkRequire($appuid){
        if($_POST['payMode'] == 3){
            return true;
        }else{
            if(empty($appuid)){
                return false;
            }else{
                return true;
            }
        }
    }
    protected function checkAppUid($appuid){
        if($_POST['payMode'] != 3){
            if(!M('app_player')->where(array('id'=>$appuid,'uid'=>$_POST['uid']))->find()){
                return false;
            }else{
                return true;
            }
        }else{
            return true;
        }
    }

    /**
     * 检测签名
     * @return bool
     */
    protected function checkPostSign(){
        $key = M('game')->where(array('id'=>$_POST['appid']))->getField('client_key');
        $arr = array(
            'deviceType' => $_POST['deviceType'],
            'appid' => $_POST['appid'],
            'channel' => $_POST['channel'],
            'uid' => $_POST['uid'],
            'app_uid' => $_POST['app_uid'],
            'serverID' => $_POST['serverID'],
            'serverNAME' => $_POST['serverNAME'],
            'roleID' => $_POST['roleID'],
            'roleNAME' => $_POST['roleNAME'],
            'productID' => $_POST['productID'],
            'productNAME' => $_POST['productNAME'],
            'payType' => $_POST['payType'],
            'payMode' => $_POST['payMode'],
            'amount' => $_POST['amount'],
            'extend' => $_POST['extend'],
            'sign' => $_POST['sign']
        );
        if(!checkSign($arr,$key)){
            return false;
        }
        return true;
    }

    /**
     * 支付方式验证
     * @param $type
     * @return bool
     */
    protected function checkPayType($type){
        $paytype = array(1,2,3,4,5,6,7,8,9,10,11);
        if(!in_array($type,$paytype)){
            return false;
        }
        return true;
    }

    /**
     * 检查订单类型
     * @param $mode
     * @return bool
     */
    protected function checkPayMode($mode){
        if($mode == 2){
            $where = array(
                'appid' => $_POST['appid'],
                'serverid' => $_POST['serverID'],
                'username' => M('player')->where(array('id'=>$_POST['uid']))->getField('username'),
                'gm_gear_id' => $_POST['productID']
            );
            if(M('user_gm')->where($where)->find()){
                return false;
            }
        }
        return true;
    }

    /**
     * 充值卡验证
     * @param $type
     * @return bool
     */
    protected function checkPayCard_4($type){

        if($type == 5 || $type == 6 || $type == 7 || $type == 8 || $type == 9){
            return false;
        }
        return true;
    }

    /**
     * 平台币验证
     * @param $type
     * @return bool
     */
    protected function checkPayCard_3($type){
        if($type == 10){
            $userCoin = M('player')->where(array('id'=>$_POST['uid']))->getField('platform_money');
            $needCoin = $_POST['amount']*C('PLATFORM_COIN_RATIO');
            if($userCoin < $needCoin){
                return false;
            }
            M('player')->where(array('id'=>$_POST['uid']))->setDec('platform_money',$needCoin);
        }
        return true;
    }

    /**
     * 生成时间规则的订单号
     * @return string
     */
    public function orderID($table = 'inpour'){
        $id = time().substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 9);
        if(M($table)->where(array('orderID'=>$id))->find()){
            $this->orderID();
        }
        return $id;
    }

    /**
     * 改变订单状态
     * @param $id
     * @param $status
     */
    public function changeStatus($order,$data){
        $this->where(array('orderID'=>$order))->setField($data);
    }

    /**
     * 支付前检测
     * @param $post
     * @return array
     */
    public function payCheck($data){

        if(empty($data['appid']) || empty($data['uid']) || empty($data['sign'])){
            return array('msg'=>'params is error','status'=>0);
        }


        $info = M('game')->field('is_pay,client_key,status,is_audit')->where(array('id'=>$data['appid']))->find();

        $arr = array(
            'appid' => $data['appid'],
            'uid' => $data['uid'],
            'sign' => $data['sign']
        );
        if(!checkSign($arr,$info['client_key'])) return array('msg'=>'sign is error','status'=>0);

        if(!$info['is_audit']) return array('msg'=>'the game failed to pass the review','status'=>0);

        if(!$info['status']) return array('msg'=>'game off','status'=>0);

        if(!$info['is_pay']) return array('msg'=>'game close pay','status'=>0);

        if(!M('player')->where(array('id'=>$data['uid']))->find()) return array('msg'=>'player is null','status'=>0);

        return array('msg'=>'success','status'=>1);
    }

    /**
     * 生成向游戏服请求的签名
     * @param $arr
     * @param $serverkey
     * @return string
     */
    public function getSign($arr,$serverkey){
        $str = '';
        foreach ($arr as $k=>$v){
            $str .= $k.'='.$v.'&';
        }
        $str = md5($str.'key='.$serverkey);
        return $str;
    }

    /**
     * 后台手动给GM权限
     * @return array
     */
    public function sendGmPower($appid,$serverid,$username,$gm_gear_id){
        if(empty($appid) || empty($serverid) || empty($username) || empty($gm_gear_id)){
            return array('status'=>0,'msg'=>'error');
        }
        $info = M('game')->field('server_key,gm_pri_id')->where(array('id'=>$appid))->find();
        $url = M('gm_pri')->where(array('id'=>$info['gm_pri_id']))->getField('open_gear_url');
        if(empty($url)){
            return array('status'=>0,'msg'=>'error');
        }
        $order = $this->orderID('user_gm');

        $data = array(
            'orderID'  => $order,
            'deviceType'   => 1,
            'username' => $username,
            'serverID' => $serverid,
            'powerID'  => $gm_gear_id,
            'type'     => 2,
            'currency' => 'BTC',
            'amount'   => 0,
            'time'     => time()
        );
        $data['sign'] = $this->getSign($data,$info['server_key']);

        $res = curl_post($url,$data);
        if(stripos($res,'SUCCESS') !== false){
            $map = array(
                'appid'       => $appid,
                'serverid'    => $serverid,
                'username'    => $username,
                'orderID'     => $order,
                'gm_gear_id'  => $gm_gear_id,
                'type'        => 2,
                'create_time' => time()
            );
            M('user_gm')->add($map);
            return array('status'=>1,'msg'=>'success');
        }else{
            return array('status'=>0,'msg'=>'request error');
        }
    }


    /**
     * 请求游戏服发货
     * @param $order
     * @return bool
     */
    public function requestData($order){

        $res = M('inpour')->where(array('orderID'=>$order))->find();
        //取出参数日志
        logs('requestData',$res);
        //如果没有原价或金额和原价相等表示不打折
        if($res['origPrice'] == 0 || $res['money'] == $res['origPrice']){
            $money = $res['money'];
        }else{
            $money = $res['origPrice'];
        }
        $field = 'server_key,gm_pri_id,';
        $field .= $res['deviceType'] == 1 ? 'android_payurl sendurl' : 'ios_payurl sendurl';
        $data = M('game')->field($field)->where(array('id'=>$res['appid']))->find();

        //1一般充值，2权限充值
        if($res['payMode'] == 1){
            $orderid = $res['jz_other'];
            if($res['payType'] == 10)  $orderid = $res['orderID'];
            $map = array(
                'orderID'    => $orderid,
                'status'     => 1,
                'deviceType' => $res['deviceType'],
                'userID'     => $res['app_uid'],
                'serverID'   => $res['serverID'],
                'roleID'     => $res['roleID'],
                'productID'  => $res['productID'],
                'currency'   => $res['payType'] == 10 ? 'BTC' : 'RMB',
                'amount'     => $money*100,//转成分
                'time'       => $res['create_time'],
                'extension'  => $res['extension']
            );
            $url = html_entity_decode($data['sendurl']);
        }else{
            $map = array(
                'orderID'  => $res['payType'] == 10 ? $res['orderID'] : $res['jz_other'],
                'deviceType'   => $res['deviceType'],
                'userID' => $res['app_uid'],
                'serverID' => $res['serverID'],
                'powerID'  => $res['productID'],
                'type'     => 1,
                'currency' => $res['payType'] == 10 ? 'BTC' : 'RMB',
                'amount'   => $money*100,//转成分
                'time'     => time()
            );
            $url = M('gm_pri')->where(array('id'=>$data['gm_pri_id']))->getField('open_gear_url');
        }

        if(!$url) return false;
        //获得签名
        $map['sign'] = $this->getSign($map,$data['server_key']);
        //组装请求链接保存进数据库
        $payto = $url.'?'.http_build_query($map);
        $this->where(array('orderID'=>$order))->setField('pay_to',$payto);
        //日志记录
        logs('reSend',$payto);
        //请求接口

        $info = curl_post($url,$map);
        if($info === false){
            $info = curl_get($payto);
        }
        //logs('sendError',$info);
        if(stripos($info,'SUCCESS') !== false){
            $change = array(
                'status' => 1,
                'call_back' => $info
            );
            if($res['payWay'] == 1 || $res['payType'] == 10) $change['pay_to_time'] = time();
            $this->changeStatus($order,$change);
            if($res['payMode'] == 2){
                $gm = array(
                    'appid'       => $res['appid'],
                    'serverid'    => $res['serverID'],
                    'userID'    => $res['app_uid'],
                    'orderID'     => $order,
                    'gm_gear_id'  => $res['productID'],
                    'type'        => 1,
                    'create_time' => time()
                );
                M('user_gm')->add($gm);
            }
            return true;
        }else{

            $this->changeStatus($order,array('call_back'=>$info));
            return false;
        }
    }

    /**
     * 支付宝支付
     */
    public function alipay($info){
        try {
            vendor('alipay.AopSdk');
            $aop = new \AopClient();
            $aop->appId = C('alipay.APPID');
            $aop->rsaPrivateKey = C('alipay.RSA_PRIVATE_KEY');
            $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->format = 'json';
            $aop->apiVersion = '1.0';
            $aop->postCharset = 'UTF-8';
            $aop->signType = 'RSA2';

            $bizcontent = array(
                'subject' => '线上支付',
                'body' => $info['payMode'],
                'out_trade_no' => $info['orderID'],
                'timeout_express' => '14m',
                'total_amount' => $info['money'],
                'store_id' => '001',
                'payMode' => $info['payMode']
            );
            //请求来源，1扫码,2H5
            if($info['payType'] == 1){
                $request = new \AlipayTradePrecreateRequest();
            }else{
                $request = new \AlipayTradeWapPayRequest();
                $bizcontent['product_code'] = 'QUICK_WAP_WAY';
            }

            $con = json_encode($bizcontent);
            if($info['payMode'] == 3){
                $table = M('inpour_ptb');
                $html = 'ptbSuccess';
            }else{
                $table = $this;
                $html = 'paySuccess';
            }
	    
            $request->setNotifyUrl('http://'.$_SERVER['HTTP_HOST']."/api/pay/aliPayReturn");
            $request->setReturnUrl('http://'.$_SERVER['HTTP_HOST']."/api/pay/".$html);
            $request->setBizContent($con);
            if($info['payType'] == 1){
                $response = $aop->execute($request);
                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode = $response->$responseNode->code;
                $res = $resultCode == 10000 ? true : false;

                $table->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>'','img'=>$response->$responseNode->qr_code)));
            }else{
                $response = $aop->pageExecute($request,'GET');
                $res = $response === false ? false : true;
                if($info['payMode'] == 3){
                    $table = M('inpour_ptb');
                }else{
                    $table = $this;
                }
                $table->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>$response,'img'=>'')));

            }

            return $res;

        }catch (\Exception $e){
            diylogs('alipay++++++',$e->getMessage());
            return false;
        }
    }

    /**
     * 微信支付
     */
    public function wechatpay($info){
        vendor('wechatPay');
        $wechat = new \wechatPay();
        $wechat->appid = C('wechat.APPID');
        $wechat->notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/api/pay/wechatReturn?payMode='.$info['payMode'];
        $wechat->key = C('wechat.KEY');
        $wechat->mch_id = C('wechat.MCH_ID');
        //请求来源，3扫码,4H5
        if($info['payType'] == 3){
            $wechat->trade_type = 'NATIVE';
        }else{
            $wechat->trade_type = 'MWEB';
        }
        $res = $wechat->unifiedorder(array(
            'body' => '支付',
            'total_fee' => $info['money']*100,//单位为分
            'orderID' => $info['orderID'],
            'time_start' => date('YmdHis'),
            'time_expire' => date('YmdHis',strtotime("+ 14 Minute")),
            'payType' => $info['payType'],
            'payMode' => $info['payMode']
        ));

        if($res['return_code'] == 'SUCCESS'){
            if($info['payMode'] == 3){
                $table = M('inpour_ptb');
                $html = 'ptbSuccess';
            }else{
                $table = $this;
                $html = 'paySuccess';
            }
            if($info['payType'] == 3){
                $table->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>'','img'=>$res['code_url'])));
            }else{
                $table->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>$res['mweb_url'],'img'=>'')));
            }
        }else {
            diylogs('wechatpay++++++++++',$res);
        }
        return $res;
    }

    public function checkRealVerify($data) {
        // 根据uid 获取用户信息
        $playerInfo = M('player_info')
            ->where(['uid' => $data['uid']])
            ->field('real_name,id_card')
            ->find();

        if(!$playerInfo || empty($playerInfo['real_name']) || empty($playerInfo['id_card'])) {
            return [
                'msg' => '没有实名验证',
                'status' => 0
            ];
        }
        // 根据身份证 获取年龄
        $age = getAgeByIdcard($playerInfo['id_card']);
        // 根据uid 获取用户本月充值的金额
        // 本月第一天
        $firstday = strtotime(date('Y-m-01 00:00:01',time()));
        $condition = [
            'status' => ['in','1,2'],
            'uid' => $data['uid'],
            'create_time' => ['between',[$firstday,time()]]
        ];
        // 获取本月充值的金额
        if($data['payMode'] == 3){
            $table = M('inpour_ptb');
        }else{
            $table = M('inpour');
        }
        $payMoney = $table
            ->field('SUM(money) as money')
            ->where($condition)
            ->find();
        if($age < 8) {
            return [
                'msg' => '您当前是8岁以下，按照有关规定，不能充值。',
                'status' => 0
            ];
        }else if($age >= 8 && $age < 16) {
            if($data['money'] > 50 ) {
                return [
                    'msg' => "您当前已满8-16岁，根据相关规定；\n单次充值不能超过50元人民币，每月累计充值不能超过200元人民币。",
                    'status' => 0,
                ];
            }
            if($payMoney['money'] >= 200 || ($data['money'] + $payMoney['money']) >= 200) {
                return [
                    'msg' => "您当前已满8-16岁，根据相关规定；\n单次充值不能超过50元人民币，每月累计充值不能超过200元人民币。",
                    'status' => 0
                ];
            }
        }else if($age >= 16 && $age < 18) {
            if($data['money'] > 100 ) {
                return [
                    'msg' => "您当前已满16-18岁，根据相关规定；\n单次充值不能超过100元人民币，每月累计充值不能超过400元人民币。",
                    'status' => 0,
                ];
            }
            if($payMoney['money'] >= 400 || ($data['money'] + $payMoney['money']) >= 400) {
                return [
                    'msg' => "您当前已满16-18岁，根据相关规定；\n单次充值不能超过100元人民币，每月累计充值不能超过400元人民币。",
                    'status' => 0
                ];
            }
        }

        return [
            'msg' => 'ok',
            'status' => 1
        ];
    }

    /**
     * 验证平台币支付
     */
    public function checkPTBpay($data)
    {
        // 因为传过来的是钱，转换为平台币 1:10
        if(!$data || empty($data['amount'])) {
            return [
                'msg' => '参数错误',
                'status' => 0
            ];
        }
        $data['platform_money'] = $data['amount']*10;
        $playerInfo = M('player_info')
            ->where(['uid' => $data['uid']])
            ->field('real_name,id_card')
            ->find();

        if(!$playerInfo || empty($playerInfo['real_name']) || empty($playerInfo['id_card'])) {
            return [
                'msg' => '没有实名验证',
                'status' => 0
            ];
        }
        // 根据身份证 获取年龄
        $age = getAgeByIdcard($playerInfo['id_card']);
        // 根据uid 获取用户本月充值的金额
        // 本月第一天
        $firstday = strtotime(date('Y-m-01 00:00:01',time()));
        $condition = [
            'uid' => $data['uid'],
            'type' => 2,
            'create_time' => ['between',[$firstday,time()]]
        ];

        $PTBNum = M('platform_detail_logs')
            ->field('SUM(platform_change) as num')
            ->where($condition)
            ->find();
        // 因为取出来的数是负数，所以转正
        $PTBNum['num'] = abs($PTBNum['num']);
        if($age < 8) {
            return [
                'msg' => "您当前是8岁以下，按照有关规定，不能充值。",
                'status' => 0
            ];
        }else if($age >= 8 && $age < 16) {
            if($data['platform_money'] > 500 ) {
                return [
                    'msg' => "您当前已满8-16岁，根据相关规定；\n单次充值不能超过50元人民币，每月累计充值不能超过200元人民币。",
                    'status' => 0,
                ];
            }
            if($PTBNum['num'] >= 2000 || ($data['platform_money'] + $PTBNum['num']) >= 2000) {
                return [
                    'msg' => "您当前已满8-16岁，根据相关规定；\n单次充值不能超过50元人民币，每月累计充值不能超过200元人民币。",
                    'status' => 0
                ];
            }
        }else if($age >= 16 && $age < 18) {
            if($data['platform_money'] > 1000 ) {
                return [
                    'msg' => "您当前已满16-18岁，根据相关规定；\n单次充值不能超过100元人民币，每月累计充值不能超过400元人民币。",
                    'status' => 0,
                ];
            }
            if($PTBNum['num'] >= 4000 || ($data['platform_money'] + $PTBNum['num']) >= 4000) {
                return [
                    'msg' => "您当前已满16-18岁，根据相关规定；\n单次充值不能超过100元人民币，每月累计充值不能超过400元人民币。",
                    'status' => 0
                ];
            }
        }

        return [
            'msg' => 'ok',
            'status' => 1
        ];
            
    }
}
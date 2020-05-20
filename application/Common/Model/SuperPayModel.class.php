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

class SuperPayModel extends Model {

    protected $kj_api = 'api.kj-pay.com';
    //腾王
    protected $kj_key = 'af51d8fe98f284c51ba852a060eebc47';
    protected $merchant_no = 2019779260;

    protected $_validate = array(
        array('udid','require','appid is null',1),
        array('udid','checkNum','支付准备中',1,'callback'),
        array('cid','require','channel is null',1),
        array('tag','require','tag is null',1),
        array('tag','checkTag','简写错误',1,'callback'),
        array('money','require','money is null',1),
        array('money','checkMoney','金额错误',1,'callback'),
        array('extend','require','extension is null',1),
        array('payType','require','payType is null',1),
        array('sign','require','sign is null',1),
        array('sign','checkPostSign','签名失败',1,'callback'),
    );

    protected $_auto = array (
        array('orderID','orderID',1,'callback'),
        array('create_time','time',1,'function'),
        array('ip','getIp',1,'callback'),
        array('appid','getAppid',1,'callback'),
        array('payWay','getPayWay',1,'callback'),
    );

    public function checkNum($val){
        $type = M('dl_device',null,C('SUPER_SIGN'))->where(array('udid'=>$val))->getField('deviceType');

        $type = strtolower($type);

        switch ($type){
            case 'iphone' : $str = 'sum(iphone) num';break;
            case 'ipad' : $str = 'sum(ipad) num';break;
            case 'ipod' : $str = 'sum(ipod) num';break;
            case 'mac' : $str = 'sum(mac) num';break;
        }
        $num = M('dl_account',null,C('SUPER_SIGN'))->getField($str);

        if($num < 10){
            return false;
        }else{
            return true;
        }
    }

    protected function getPayWay(){
        return 2;
    }

    protected function getAppid(){
        $id = M('game')->where(array('tag'=>$_POST['tag']))->getField('id');
        return $id;
    }

    public function checkTag($val){
        if(M('game')->where(array('tag'=>$val))->find()){
            return true;
        }else{
            return false;
        }
    }

    public function checkMoney($val){
        if($val == 15){
            return true;
        }else{
            return false;
        }
    }

    //自动完成
    protected function getIp(){
        $ip = sprintf("%u",ip2long(getClientIP()));
        return $ip;
    }

    /**
     * 检测签名
     * @return bool
     */
    protected function checkPostSign(){
        $key = 'd76d5431452d37109aced75b6e5f79c3';
        $arr = array(
            'udid' => $_POST['udid'],
            'cid' => $_POST['cid'],
            'tag' => $_POST['tag'],
            'money' => $_POST['money'],
            'extend' => $_POST['extend'],
            'payType' => $_POST['payType'],
            'sign' => $_POST['sign']
        );
        if(!checkSign($arr,$key)){
            return false;
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
                'out_trade_no' => $info['orderID'],
                'timeout_express' => '14m',
                'total_amount' => $info['money'],
                'store_id' => '001'
            );

            $request = new \AlipayTradeWapPayRequest();
            $bizcontent['product_code'] = 'QUICK_WAP_WAY';


            $con = json_encode($bizcontent);

            $sdkDomainUrl = C('sdk_domain_url');
            $request->setNotifyUrl("$sdkDomainUrl/api/SuperPay/aliPayReturn");
            $request->setReturnUrl("$sdkDomainUrl/api/SuperPay/paySuccess");
            $request->setBizContent($con);

            $response = $aop->pageExecute($request,'GET');
            $res = $response === false ? false : true;
            $this->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>$response,'img'=>'')));


            return $res;

        }catch (\Exception $e){
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
        $wechat->notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/api/SuperPay/wechatReturn';
        $wechat->key = C('wechat.KEY');
        $wechat->mch_id = C('wechat.MCH_ID');

        $wechat->trade_type = 'MWEB';

        $res = $wechat->unifiedorder(array(
            'body' => '支付',
            'total_fee' => $info['money']*100,//单位为分
            'orderID' => $info['orderID'],
            'time_start' => date('YmdHis'),
            'time_expire' => date('YmdHis',strtotime("+ 14 Minute")),
            'payType' => $info['payType']
        ));

        if($res['return_code'] == 'SUCCESS'){
            $this->where(array('orderID'=>$info['orderID']))->setField('info',json_encode(array('url'=>$res['mweb_url'],'img'=>'')));
        }
        return $res;
    }

    /**
     * 快接支付
     */
    public function payLink($info){
        $key = $this->kj_key;
        $merchant_no = $this->merchant_no;
        $api = $this->kj_api;

        $notify_url = C('sdk_domain_url').'/api/SuperPay/kjPayReturn';
        $return_url = C('sdk_domain_url').'/api/SuperPay/paySuccess';

        $url = "http://{$api}/wechar/wap_pay";
        $arr = array('type'=>'Wap','wap_url'=>C('sdk_domain_url'),'wap_name'=>'快接支付');
        //分账
        $share = array(
            array(
                'type' => 'PERSONAL_WECHATID',
                'account' => 'xingyungame1964',
                'name' => '冯羲',
                'amount' => substr(sprintf('%.3f',$info['money']-($info['money']*0.008)),0,-1),
                'description' => '分账给冯羲'
            )
        );
        $param = array(
            'merchant_no' => $merchant_no,
            'merchant_order_no' => $info['orderID'],
            'notify_url' => $notify_url,
            'start_time' => date('YmdHis',$info['create_time']),
            'trade_amount' => $info['money'],
            'goods_name' => '线上支付',
            'goods_desc' => '线上支付',
            'return_url' => $return_url,
            'user_ip' => getClientIP(),
            'pay_sence' => json_encode($arr),
            'accounting_type' => 1,
            'receivers' => json_encode($share),
            'sign_type' => 1,
        );

        $param['sign'] = $this->sign($param,$key);

        $res = $this->getdata($url,$param);

        $res = json_decode($res,1);
        
        if($res['status'] == 1){
            $set = array(
                'payID' => $res['data']['trade_no'],
                'info' => json_encode(array('url'=>$res['data']['pay_url'],'img'=>$res['data']['image']))
            );

            $this->where(array('orderID'=>$info['orderID']))->setField($set);

            return $res;
        }else{
            logs('kj_pay_error',array_merge($info,$res));
            return array('status'=>0,'info'=>'请求失败，请稍候重试');
        }

    }


    /**
     * 快接回调验证
     */
    public function kjReturn($data){
        $arr = $data;
        $sign = $arr['sign'];
        unset($arr['sign']);
        $key = 'af51d8fe98f284c51ba852a060eebc47';

        if($arr['status'] == 'Success' && $this->sign($arr,$key) == $sign){
            $info = M('super_pay')->where(array('orderID'=>$arr['merchant_order_no']))->find();
            if($info['money'] != $arr['amount']){
                return false;
            }
            return true;
        }else{
            return false;
        }
    }

    /**
     * 参数排序
     */
    protected function argSorts($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    /**
     * 签名验证-快接支付
     * $datas 数据数组
     * $key 密钥
     */
    protected function sign($datas = array(), $key = ""){
        $str = urldecode(http_build_query($this->argSorts($this->paraFilters($datas))));
        $sign = md5($str."&key=".$key);
        return $sign;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    protected function paraFilters($para) {
        $para_filter = array();
        while (list ($key, $val) = each ($para)) {
            if($key == "sign" || $val == "")continue;
            else	$para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }

    /**
     * 快接支付
     * $datas 数据数组
     * $key 密钥
     */
    protected function getdata($url, $param){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_TIMEOUT,6);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/8
 * Time: 15:50
 */
namespace Common\Model;
use Common\Model\CommonModel;

class AccountTradeModel extends CommonModel {

    protected $_validate = array(
        array('proid','require','参数缺少',1),
        array('proid','checkStatus','该游戏已关闭账号交易',1,'callback'),
        array('proid','checkLock','该商品状态异常,请选择其他商品',1,'callback'),
        array('buy_id','require','参数缺少',1),
        array('buy_id','checkBuyId','不能购买自己的账号',1,'callback'),
        array('type','require','参数缺少',1),
        array('sign','checkPostSign','签名失败',1,'callback'),
    );

    protected $_auto = array(
        array('orderID','orderID',1,'callback'),
        array('sell_id','getSellId',1,'callback'),
        array('appid','getAppid',1,'callback'),
        array('money','getMoney',1,'callback'),
        array('third_fee','third_fee',1,'callback'),
        array('local_fee','local_fee',1,'callback'),
        array('price','getPrice',1,'callback'),
        array('request_type','request_type',1,'callback'),//请求来源，1app,2h5
        array('ip','getIp',1,'callback'),
        array('create_time','time',1,'function'),
    );

    /**
     * 请求来源
     */
    protected function request_type(){
        if($_POST['request_type'] == 2){
            return 2;
        }else{
            return 1;
        }
    }

    /**
     * 检测订单状态
     */
    protected function checkStatus($proid){
        $status = M('products p')
                ->join('left join __GAME__ g on g.id=p.appid')
                ->where(array('p.id'=>$proid))
                ->getField('trade');
        if($status){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检测商品状态
     */
    public function checkLock($proid){
        $status = M('products')->where(array('id'=>$proid))->getField('status');
        if($status != 2){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 检测买家
     */
    protected function checkBuyId($buy_id){
        $user = M('products ')->where(array('id'=>$_POST['proid']))->getField('uid');
        if($user == $buy_id){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 检测签名
     */
    protected function checkPostSign(){
        $arr = array(
            'proid' => $_POST['proid'],
            'buy_id' => $_POST['buy_id'],
            'type' => $_POST['type'],
            'sign' => $_POST['sign']
        );
        if(!checkSign($arr,C('API_KEY'))){
            return false;
        }
        return true;
    }

    /**
     * 生成时间规则的订单号
     * @return string
     */
    protected function orderID($table = 'account_trade'){
        $id = time().substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 9);
        if(M($table)->where(array('orderID'=>$id))->find()){
            $this->orderID();
        }
        return $id;
    }

    /**
     * 获得游戏ID
     */
    protected function getAppid(){
        $id = M('products ')->where(array('id'=>$_POST['proid']))->getField('appid');
        return $id;
    }

    /**
     * 获得卖家账号
     */
    protected function getSellId(){
        $user = M('products ')->where(array('id'=>$_POST['proid']))->getField('uid');
        return $user;
    }

    /**
     * 获得商品价格
     */
    protected function getMoney(){
        $money = M('products')->where(array('id'=>$_POST['proid']))->getField('price');
        return $money;
    }

    /**
     * 获得第三方手续费率
     */
    protected function third_fee(){
        if($_POST['type'] == 1){
            $rate = get_site_options('alipay_rate') ? : 1;
        }else{
            $rate = get_site_options('wechat_rate') ? : 1;
        }
        return $rate;
    }

    /**
     * 获得本站手续费率
     */
    protected function local_fee(){
        $sdk = get_site_options('sdk_rate') ? : 4;
        return $sdk;
    }

    /**
     * 计算转账金额
     */
    protected function getPrice(){
        $money = M('products')->where(array('id'=>$_POST['proid']))->getField('price');
        if($_POST['type'] == 1){
            $rate = get_site_options('alipay_rate') ? : 1;
        }else{
            $rate = get_site_options('wechat_rate') ? : 1;
        }
        $sdk = get_site_options('sdk_rate') ? : 4;
        $rate = $rate + $sdk;
        $fee = round($money * ($rate / 100),2);
        return $money - $fee;
    }

    /**
     * 获得IP
     * @return string
     */
    protected function getIp(){
        $ip = sprintf("%u",ip2long(getClientIP()));
        return $ip;
    }
}
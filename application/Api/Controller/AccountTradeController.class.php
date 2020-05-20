<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/8
 * Time: 15:40
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class AccountTradeController extends AppframeController{
    public function _initialize(){
        parent::_initialize();
        $this->pay = D('Common/AccountTrade');
    }

    /**
     * 发起支付
     */
    public function startPayment(){
        logs('accountPayStart',$_POST);
        if($info = $this->pay->create()){
            if($info['type'] == 1){
                $payConf = $this->alipaySign($info,$info['buy_id'],$info['orderID'],$info['money'],$info['request_type']);
                if($payConf === false){
                    $this->ajaxReturn(null,'支付初始化失败',0);
                }
            }else{
                $res = $this->wechatSign($info['orderID'],$info['money'],$info['request_type']);
                if($res['result_code'] != 'SUCCESS' || $res['return_code'] != 'SUCCESS'){
                    $this->ajaxReturn(null,$res['err_code_des'],0);
                }

                if($info['request_type'] == 1){
                    $payConf = array(
                        'partnerid' => $res['mch_id'],
                        'prepayid' => $res['prepay_id'],
                        'noncestr' => $res['nonce_str'],
                        'timestamp' => time(),
                        'sign' => $res['sign']
                    );
                }else{
                    $payConf = $res['mweb_url'];
                }

            }

            if($id = $this->pay->add()){
                M('products')->where(array('id'=>$info['proid']))->setField(array('status'=>3,'lock_time'=>time()));
                $this->ajaxReturn(array('id'=>$id,'token'=>$payConf),'success');
            }else{
                $this->ajaxReturn(null,'失败',0);
            }
        }else{
            $this->ajaxReturn(null,$this->pay->getError(),0);
        }
    }

    /**
     * 支付宝支付验签
     * @param $data-订单数据
     * @param $uid-购买用户
     * @param $orderID-订单号
     * @param $money-订单金额
     * @param $request_type-请求来源，1APP,2H5
     * @return bool|string
     */
    protected function alipaySign($data,$uid,$orderID,$money,$request_type){
        try {
            vendor('alipay.AopSdk');
            $aop = new \AopClient();
            $aop->appId = C('alipay.APPID');
            $aop->rsaPrivateKey = C('alipay.RSA_PRIVATE_KEY');
            $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->format = 'JSON';
            $aop->apiVersion = '1.0';
            $aop->postCharset = 'utf-8';
            $aop->signType = 'RSA2';

            $bizcontent = array(
                'body' => array('data'=>$data,'uid'=>$uid),
                'subject' => '付款',
                'out_trade_no' => $orderID,
                'timeout_express' => '14m',
                'total_amount' => $money,
                'store_id' => '001'
            );
            //请求来源，1APP,2H5
            if($request_type == 2){
                $request = new \AlipayTradeWapPayRequest();
                $bizcontent['product_code'] = 'QUICK_WAP_WAY';
            }else{
                $request = new \AlipayTradeAppPayRequest();
                $bizcontent['product_code'] = 'QUICK_MSECURITY_PAY';
            }

            $con = json_encode($bizcontent);
            $request->setNotifyUrl("{:C('API_URL')}/api/AccountTrade/alipayReturn");
            $request->setBizContent($con);
            $response = $request_type == 1 ? $aop->sdkExecute($request) : $aop->pageExecute($request,'GET');

            if(empty($response) || $response === false){
                return false;
            }else{
                return $response;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    /**
     * 支付宝异步回调
     */
    public function alipayReturn(){
        diylogs('alipayReturn',$_POST);
        vendor('alipay.AopSdk');
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $res = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if($res && ($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED')){
            //更新订单信息
            $info = json_decode($_POST['body'],true);
            M('account_trade')->where(array('orderID'=>$_POST['out_trade_no']))->setField(array('coupon'=>$_POST['point_amount'],'status'=>1,'other_order'=>$_POST['trade_no'],'success_time'=>time()));
            M('products')->where(array('id'=>$info['data']['proid']))->setField(array('lock_time'=>0));
            //订单支付成功 发送信息队列
            $link = U('Admin/AccountTrade/orderList');
            create_admin_message(2,$info['data']['proid'],'all',$link);

            exit('success');
        }else{
            logs('alipayReturn_error',$res);
            exit('fail');
        }
    }

    /**
     * 微信支付验签
     */
    protected function wechatSign($orderID,$money,$request_type){
        vendor('wechatPay');
        $wechat = new \wechatPay();
        $wechat->appid = C('wechat.APPID');
        $wechat->key = C('wechat.KEY');
        $wechat->mch_id = C('wechat.MCH_ID');
        //请求来源，1APP,2H5
        if($request_type == 1){
            $wechat->trade_type = 'APP';
        }else{
            $wechat->trade_type = 'MWEB';
        }
        $res = $wechat->unifiedorder(array(
            'body' => '账号交易',
            'total_fee' => $money*100,//单位为分
            'orderID' => $orderID,
            'time_start' => date('YmdHis'),
            'time_expire' => date('YmdHis',strtotime("+ 14 Minute"))
        ),$request_type);

        return $res;
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
        //验证签名
        $postSign = $data['sign'];
        unset($data['sign']);
        $localSign = $wechat->makeSign($data);
        if($postSign == $localSign){
            $info = M('account_trade')->field('money,proid')->where(array('orderID'=>$data['out_trade_no']))->find();
            //验证金额
            $set['status'] = $info['money'] == $data['total_fee']/100 ? 1 : 5;
            $set['other_order'] = $data['transaction_id'];
            $set['success_time'] = strtotime($data['time_end']);
            $set['coupon'] = $data['coupon_fee'] ? : 0;
            M('account_trade')->where(array('orderID'=>$data['out_trade_no']))->setField($set);
            M('products')->where(array('id'=>$info['proid']))->setField(array('lock_time'=>0));
            //订单支付成功 发送信息队列
            $link = U('Admin/AccountTrade/orderList');
            create_admin_message(2,$info['proid'],'all',$link);
            echo $wechat->data_to_xml(array('return_code'=>'SUCCESS'));
        }else{
            echo $wechat->data_to_xml(array('return_code'=>'FAIL','return_msg'=>'签名错误'));
        }
    }

    /**
     * 取消支付
     */
    public function cancelPayment(){
        $data = array(
            'id' => I('id'),
            'sign' => I('sign')
        );
        if(!checkSign($data,C('API_KEY'))){
            $this->ajaxReturn(null,'签名错误',0);
        }
        $proid = M('account_trade')->where(array('id'=>$data['id']))->getField('proid');
        if(M('products')->where(array('id'=>$proid))->setField(array('status'=>2,'lock_time'=>0)) !== false){
            $this->ajaxReturn(null,'success');
        }else{
            $this->ajaxReturn(null,'操作失败',0);
        }
    }

    /**
     * 买家交易记录
     */
    public function buyerRecord(){
        $data = array(
            'uid' => I('uid'),
            'type' => I('type',1),
            'sign' => I('sign')
        );
        if(!checkSign($data,C('API_KEY'))){
            $this->ajaxReturn(null,'签名错误',0);
        }
        $where['a.buy_id'] = $data['uid'];
        if($data['type'] == 5){
            $where['a.status'] = array('in','1,3,4');
        }elseif($data['type'] == 4){
            $map['a.status'] = 4;
            $map['a.status|a.is_trade'] = 1;
            $map['_logic'] = 'or';
            $where['_complex'] = $map;
        }elseif($data['type'] == 1){
            $where['a.status'] = 1;
            $where['a.is_transfer'] = 0;
            $where['a.is_trade'] = 0;
        }else{
            $where['a.status'] = $data['type'];
        }
        $info = M('account_trade a')
                ->field('a.id,g.game_name,p.title,a.money,a.create_time,a.status,a.reason,a.is_trade')
                ->join('left join __PRODUCTS__ p on p.id=a.proid')
                ->join('left join __GAME__ g on g.id=p.appid')
                ->where($where)
                ->select();
        if($data['type'] != 5){
            foreach($info as &$v){
                $v['status'] = $data['type'];
            }
        }else{
            foreach($info as &$v){
                if($v['status'] == 1 && $v['is_trade'] == 1){
                    $v['status'] = 4;
                }
            }
        }
        $this->ajaxReturn($info,'success');
    }

    /**
     * 卖家交易记录
     */
    public function sellerRecord(){
        $data = array(
            'uid' => I('uid'),
            'type' => I('type'),//1支付成功，2等待打款，3取消(退款)，4交易完成，5全部
            'sign' => I('sign')
        );
        if(!checkSign($data,C('API_KEY'))){
            $this->ajaxReturn(null,'签名错误',0);
        }
        $where['a.sell_id'] = $data['uid'];
        if($data['type'] == 5){
            $where['a.status'] = array('in','1,3,4');

        }elseif($data['type'] == 1){
            $where['a.status'] = 1;
            $where['a.is_transfer'] = 0;
            $where['a.is_trade'] = 0;
        }elseif($data['type'] == 2){
            $where['a.status'] = 1;
            $where['a.is_trade'] = 1;
        }else{
            $where['a.status'] = $data['type'];
        }
        $info = M('account_trade a')
            ->field('a.id,g.game_name,p.title,a.money,a.create_time,a.status,a.reason,a.is_trade')
            ->join('left join __PRODUCTS__ p on p.id=a.proid')
            ->join('left join __GAME__ g on g.id=p.appid')
            ->where($where)
            ->select();
        if($data['type'] != 5){
            foreach($info as &$v){
                $v['status'] = $data['type'];
            }
        }else{
            foreach($info as &$v){
                if($v['status'] == 1 && $v['is_trade'] == 1){
                    $v['status'] = 2;
                }
            }
        }
        $this->ajaxReturn($info,'success');
    }

    /**
     * 检测数据库中商品在交易中时间，超过15分钟改变状态
     */
    public function checkLockTime(){
        $data = M('products')->where(array('status'=>3,'lock_time'=>array('gt',0)))->select();
        if(count($data) > 0){
            foreach($data as $k=>$v){
                if((time() - $v['lock_time']) > 900){
                    M('products')->where(array('id'=>$v['id']))->setField(array('status'=>2,'lock_time'=>0));
                }
            }
        }
    }
}
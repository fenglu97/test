<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/8/29
 * Time: 17:56
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class SuperPayController extends AppframeController {
    public function _initialize(){
        parent::_initialize();
        $this->inpour = D('Common/SuperPay');
    }


    /**
     * 发起支付
     */
    public function payStart(){
        header("Access-Control-Allow-Origin:*");
        if($info = $this->inpour->create()){
            logs('payStart',$info);
            try{
                $id = $this->inpour->add();
                $sign = md5(sha1($id).C('185KEY'));

                if($info['payType'] == 1){
                    $res = $this->inpour->alipay($info);
                    if($res){
                        $url = 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=SuperPay&a=alipaySubmit&id={$id}&sign={$sign}";
                    }else{
                        $this->ajaxReturn(null,'支付错误，请稍候重试',0);
                    }
                }else{
                    $res = $this->inpour->wechatpay($info);
                    if($res['return_code'] == 'SUCCESS'){
                        $url = 'http://'.$_SERVER['HTTP_HOST']."/index.php?g=api&m=SuperPay&a=wechatpaySubmit&id={$id}&sign={$sign}";
                    }else{
                        $this->ajaxReturn(null,'支付错误，请稍候重试',0);
                    }
                }


                $this->ajaxReturn(array('url'=>$url,'orderID'=>$info['orderID']),'success');
            }catch (\Exception $e){
//                logs('payError',$e->getMessage());
                $this->ajaxReturn(null,$e->getMessage(),0);
            }

        }else{
            $this->ajaxReturn(null,$this->inpour->getError(),0);
        }
    }

    /**
     * 支付成功页面
     */
    public function paySuccess(){
        header("Access-Control-Allow-Origin:*");
        $data = I('');
        if(isset($data['order'])){
            $data['msg'] = '恭喜您，成功充值'.$data['money'].'元';
            $data['status'] = '支付成功';
        }else{
            $data['msg'] = '正在支付中，请稍等';
            $data['status'] = '正在支付中，请稍等';
            $data['time'] = time();
        }
        $this->data = $data;
        $this->display();
    }

    /**
     * 原生支付宝提交页
     */
    public function alipaySubmit(){
        header("Access-Control-Allow-Origin:*");
        header("Content-type: text/html; charset=utf-8");
        $id = I('id');
        $sign = I('sign');
        if(md5(sha1($id).C('185KEY')) != $sign) exit('非法请求');

        $info = M('super_pay')->where(array('id'=>$id))->find();
        $json = json_decode($info['info'],1);

        if(!$info || $info['status'] == 1){
            exit('非法订单信息');
        }else{

            $str = "<form id='sub' method='post' action=".$json['url'].">";
            $str .= '</form>正在努力打开支付宝...<script type="text/javascript">document.getElementById("sub").submit();</script>';
            exit($str);


        }
    }

    public function wechatpaySubmit(){
        $data = I();
        if(md5(sha1($data['id']).C('185KEY')) != $data['sign']) exit('非法请求');

        $info = M('super_pay')->where(array('id'=>$data['id']))->find();
        $json = json_decode($info['info'],1);

        if(!$info || $info['status'] == 1){
            exit('非法订单信息');
        }else{
            $str = "<form id='sub' method='post' action=".$json['url'].">";
            $str .= '</form>正在努力打开微信...<script type="text/javascript">document.getElementById("sub").submit();</script>';
            exit($str);
        }
    }

    /**
     * 快接支付提交页
     */
    public function kjSubmit(){
        header("Access-Control-Allow-Origin:*");
        header("Content-type: text/html; charset=utf-8");
        $id = I('id');
        $sign = I('sign');
        if(md5(sha1($id).C('185KEY')) != $sign) exit('非法请求');

        $info = M('super_pay')->where(array('id'=>$id))->find();
        $json = json_decode($info['info'],1);

        if(!$info || $info['status'] == 1){
            exit('非法订单信息');
        }else{
            $msg = '微信';
            $tag = M('game')->where(array('id'=>$info['appid']))->getField('tag');

            if($info['appid'] == 1013){
                $path = 'super';
            }else{
                $path = 'download';
            }
            $boxDomainUrl = C('box_domain_url');
            $url = $json['url']."&redirect_url=".urlencode( "$boxDomainUrl/{$path}/{$info['cid']}/{$tag}/{$info['promoter_uid']}");
            $str = "<form id='sub' method='post' action=".$url.">";
            $str .= '</form>正在努力打开'.$msg.'...<script type="text/javascript">document.getElementById("sub").submit();</script>';
            exit($str);
        }

    }


    /**
     * 支付宝回调
     */
    public function aliPayReturn(){
        diylogs('alipayReturn',$_POST);
        vendor('alipay.AopSdk');
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $res = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if($res && ($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED')){

            $order = M('super_pay')->where(array('orderID'=>$_POST['out_trade_no']))->find();
            if($order['status'] == 1){
                exit('success');
            }
            //更改订单状态、参数

            $rate = 0.006;//0.6%

            $set = array(
                'payID' => $_POST['trade_no'],
                'status' => 1,
                'getmoney' => round($order['money']-($order['money'] * $rate),2)
            );
            M('super_pay')->where(array('orderID'=>$_POST['out_trade_no']))->setField($set);

            $this->payUdid($order);

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
        //验证签名
        $postSign = $data['sign'];
        unset($data['sign']);
        $localSign = $wechat->makeSign($data);
        if($postSign == $localSign){
            $order = M('super_pay')->where(array('orderID'=>$data['out_trade_no']))->find();
            if($order['status'] == 1){
                exit('success');
            }
            //更改订单状态、参数
            $rate = 0.01;

            $set = array(
                'payID' => $data['transaction_id'],
                'status' => 1,
                'getmoney' => round($order['money']-($order['money'] * $rate),2)
            );
            M('super_pay')->where(array('orderID'=>$data['out_trade_no']))->setField($set);

            $this->payUdid($order);

            echo $wechat->data_to_xml(array('return_code'=>'SUCCESS'));
        }else{
            echo $wechat->data_to_xml(array('return_code'=>'FAIL','return_msg'=>'签名错误'));
        }
    }

    /**
     * 快接回调
     */
    public function kjPayReturn(){
        $data = I('');
	    logs('kjPayReturn',$data);
        
        if($this->inpour->kjReturn($data)){

            $res = M('super_pay')->where(array('orderID'=>$data['merchant_order_no']))->find();

            //更改订单状态、参数
            $set = array(
                'status' => 1
            );
            $rate = 0.008;
            $set['getmoney'] = round($res['money']-($res['money'] * $rate),2);
            M('super_pay')->where(array('orderID'=>$data['merchant_order_no']))->setField($set);

            $this->payUdid($res);

            echo 'success';
        }
    }


    /**
     * 查询支付状态
     */
    public function payQuery(){
        header("Access-Control-Allow-Origin:*");
        $udid = I('udid');
        $tag = I('tag');
        $res = M('super_pay')->where(array('udid'=>$udid,'status'=>1))->order('id desc')->find();

        if($res){
            $info = M('game')->field('ios_super_url url,super_version_num v')->where(array('tag'=>$tag))->find();

            $this->ajaxReturn($info,'success');
        }else{
            $this->ajaxReturn(null,'fail',0);
        }
    }


    public function checkOrder(){
        $id = I('id');
        $status = M('super_pay')->where(array('orderID'=>$id))->getField('status');

        if($status != 3){
            $this->ajaxReturn(null,'success');
        }else{
            $this->ajaxReturn(null,'',0);
        }
    }

    public function checkUdid(){
        header("Access-Control-Allow-Origin:*");
        $udid = I('udid');
        if(!$udid){
            $this->ajaxReturn(null,'缺少参数',0);
        }
        $info = M('super_pay')->where(array('udid'=>$udid))->find();
        if($info['status'] == 1){
            $this->ajaxReturn(null,'已支付');
        }else{
            $this->ajaxReturn(null,'未支付',0);
        }
    }

    private function payUdid($order){
        $key = '7fc835c5764e2ebe637ae9691f330dcb';
        $url = C('i_domain_url').'/download/payudid';

        $arr = array(
            'orderID' => $order['orderID'],
            'udid' => $order['udid'],
            'amount' => $order['money'],
            'pf' => C('main_domain'),
            'time' => $order['create_time'],
        );
        $arr['sign'] = md5(http_build_query($arr).'&key='.$key);

        M('super_pay')->where(array('orderID'=>$order['orderID']))->setField('remark',$url.'?'.http_build_query($arr));
        $res = curl_post($url,$arr);
        if($res == 'SUCCESS'){
            $set['state'] = 1;
        }
        $set['call_back'] = $res;
        M('super_pay')->where(array('orderID'=>$order['orderID']))->setField($set);
        logs('payUdid',$res);
    }
}
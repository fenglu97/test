<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/21
 * Time: 16:06
 */
class wechatPay{
    //微信支付接口
    private $wechatPay = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    //微信退款接口
    private $wechatRefund = 'https://api.mch.weixin.qq.com/secapi/pay/refund';

    //通知地址
    public $notify_url;

    //交易类型
    public $trade_type;

    //应用ID
    public $appid;

    //商户号
    public $mch_id;

    //api密钥
    public $key;

    //appsecret
    public $appsecret;

    //证书路径
    private $SSLCERT_PATH;

    private $SSLKEY_PATH;

    /**
     * 统一下单
     */
    public function unifiedorder($params,$request_type = 1){
        $arr = array(
            'appid'            => $this->appid,
            'body'             => $params['body'],
            'mch_id'           => $this->mch_id,
            'nonce_str'        => $this->genRandomString(),
            'notify_url'       => $this->notify_url,
            'out_trade_no'     => $params['orderID'],
            'spbill_create_ip' => $request_type == 1 ? getClientIP() : $_SERVER['HTTP_CLIENTIP'],
            'time_expire'      => $params['time_expire'],
            'time_start'       => $params['time_start'],
            'total_fee'        => $params['total_fee'],
            'product_id'       => 1,
            'trade_type'       => $this->trade_type,
        );
        if($params['payType'] == 4){
            $arr['scene_info'] = '{"h5_info": {"type":"Wap","wap_url": '.C('DOMAIN_URL').',"wap_name": '.C('BOX_NAME').'}}';
        }

        //获取签名数据
        $sign = $this->MakeSign( $arr );
        $arr['sign'] = $sign;
        $xml = $this->data_to_xml($arr);
        $response = $this->postXmlCurl($xml, $this->wechatPay);
        if( !$response ){
            return false;
        }
        $result = $this->xml_to_data( $response );

//        if( $result['result_code'] != 'SUCCESS' || $result['return_code'] != 'SUCCESS' ){
//            $result['err_msg'] = $this->error_code( $result['err_code'] );
//        }
        return $result;
    }

    /**
     * 退款
     * @param $info
     * @param $reason
     * @return bool|mixed
     */
    public function refund($info,$reason){
        $arr = array(
            'appid'            => $this->appid,
            'mch_id'           => $this->mch_id,
            'nonce_str'        => $this->genRandomString(),
            'out_trade_no'     => $info['orderID'],
            'out_refund_no'    => orderID('account_trade'),
            'total_fee'        => $info['money'] * 100,
            'refund_fee'       => ($info['money'] - $info['coupon']) * 100,
            'refund_desc'      => $reason
        );
        //获取签名数据
        $sign = $this->MakeSign( $arr );
        $arr['sign'] = $sign;
        $xml = $this->data_to_xml($arr);
        $response = $this->postXmlCurl($xml, $this->wechatRefund,true);
        if( !$response ){
            return false;
        }
        $result = $this->xml_to_data( $response );
        return $result;
    }

    /**
     * 生成签名
     *  @return-签名
     */
    public function makeSign( $params ){
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = $this->toUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$this->key;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    /**
     * 将参数拼接为url: key=value&key=value
     * @param   $params
     * @return  string
     */
    private function toUrlParams( $params ){
        $string = '';
        if( !empty($params) ){
            $array = array();
            foreach( $params as $key => $value ){
                $array[] = $key.'='.$value;
            }
            $string = implode("&",$array);
        }
        return $string;
    }

    /**
     * 产生一个指定长度的随机字符串,并返回给用户
     * @param-type $len 产生字符串的长度
     * @return string 随机字符串
     */
    private function genRandomString($len = 32) {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        // 将数组打乱
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml  需要post的xml数据
     * @param string $url  url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second   url执行超时时间，默认30s
     * @throws WxPayException
     */
    private function postXmlCurl($xml, $url, $useCert = false, $second = 30){
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if($useCert == true){
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, SITE_PATH.'data/wechatPay/apiclient_cert.pem');
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, SITE_PATH.'data/wechatPay/apiclient_key.pem');
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $res = curl_exec($ch);
        curl_close($ch);
        //返回结果
        return $res;
    }

    /**
     * 输出xml字符
     * @param   $params-参数名称
     * return   string-返回组装的xml
     **/
    public function data_to_xml( $params ){
        if(!is_array($params)|| count($params) <= 0)
        {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key=>$val)
        {
            if ($key == 'sign'){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    /**
     * 将xml转为array
     * @param string $xml
     * return array
     */
    public function xml_to_data($xml){
        if(!$xml){
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }
}
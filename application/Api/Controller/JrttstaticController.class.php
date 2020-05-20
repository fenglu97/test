<?php
/**
 * 今日头条数据上报
 * @author qing.li
 * @date 2018-04-24
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class JrttstaticController extends AppframeController
{
    private $url = 'http://ad.toutiao.com/track/activate/';
    private $key = '3f3f2b2a-bd31-4f7d-b28d-57eb64a0d3dd';


    public function get_data()
    {
        $get_data = I('get.');
        $get_data['timestamp'] = $get_data['timestamp']/1000;

        if(M('jrtt_static')->add($get_data)!==false)
        {
            exit('success');
        }

    }


    public function report_data()
    {
        $device_id = I('device_id');
        $mac = I('mac');
        $os = I('os');
        $source = I('source');
        $event_type = I('event_type');

        $arr = array(
            'device_id'=>$device_id,
            'mac'=>$mac,
            'os'=>$os,
            'source'=>$source,
            'event_type'=>$event_type,
            'sign'=>I('sign'),
        );

        $res = checksign($arr,C('API_KEY'));
        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        if($os == 0)
        {
            $device_id = md5(strtolower($device_id));
        }

        $time = time();
        $map['imei'] = $device_id;
        $map['mac'] = md5($mac);
        $map['timestamp'] = array('egt',$time-3600*24*7);
        $info = M('jrtt_static')->where($map)->find();

        if($info)
        {
            $url = $this->url.'?callback='.$info['callback'].'&muid='.$device_id.'&os='.$os.'&source='.$source.'&conv_time='.$time.'&event_type='.$event_type;
            $sign =  base64_encode(hash_hmac("SHA1",$url,$this->key , true));
            $url = $url.'&signature='.$sign;
            $res = json_decode(curl_get($url),true);
            if($res['ret'] == 0 && $res['msg'] =='success')
            {
                $this->ajaxReturn(null,'成功');
            }
            $this->ajaxReturn(null,'失败',0);
        }

        $this->ajaxReturn(null,'匹配失败',0);
    }
}
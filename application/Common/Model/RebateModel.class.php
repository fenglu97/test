<?php
/**
 * 返利
 * User: fantasmic
 * Date: 2017/7/12
 * Time: 15:28
 */
namespace Common\Model;
use Common\Model\CommonModel;

class RebateModel extends CommonModel {

    protected $_validate = array(
        array('appid','/^[1-9]\d*$/','请选择游戏', 0,'regex'),
        array('serverID','/^[1-9]\d*$/','请选择区服', 0,'regex'),
        array('username', 'require', '请输入账号！', 0, 'regex'),
        array('amount', 'require', '请输入金额！', 0, 'regex'),
//        array('roleName', 'require', '请输入角色名！', 0, 'regex'),
    );

    protected $_auto = array (
        array('orderID','orderID',1,'callback'),
        array('founder','founder',1,'callback'),
        array('vip','vip',1,'callback'),
        array('reward','reward',1,'callback'),
        array('review','review',1,'callback'),
        array('is_review','is_review',1,'callback'),
        array('create_time','time',1,'function'),
    );

    /**
     * 生成时间规则的订单号
     */
    protected function orderID(){
        list($t1, $t2) = explode(' ', microtime());
        $num = (floatval($t1)+floatval($t2))*10000;
        if($this->where(array('orderID'=>$num))->find()){
            $this->orderID();
        }else{
            return $num;
        }
    }

    protected function founder(){
        return session('ADMIN_ID');
    }

    protected function vip(){
        $data = I('vip');
        return empty($data) ? 0 : 1;
    }

    protected function reward(){
        $data = I('reward');
        return empty($data) ? 0 : 1;
    }

    protected function review(){
        if($this->checkMoney()){
            return 2;
        }else{
            return 1;
        }
    }

    protected function is_review(){
        if($this->checkMoney()){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 检查是否属于审核订单
     * @return bool
     */
    protected function checkMoney(){
        $op = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
        $rebate = json_decode($op,true);
        $rebate['rebateSingle'] = $rebate['rebateSingle'] ? $rebate['rebateSingle'] : 1000;
        $rebate['rebateTotal'] = $rebate['rebateTotal'] ? $rebate['rebateTotal'] : 10000;
        $amount = I('amount');
        $uid = session('ADMIN_ID');
        $sql = "select sum(amount) amount from __REBATE__ where to_days(FROM_UNIXTIME(create_time, '%Y%m%d')) = to_days(now()) and founder={$uid}";
        $res = $this->query($sql);
        if($amount > $rebate['rebateSingle'] || $res[0]['amount'] > $rebate['rebateTotal']){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 请求返利接口
     * @param $id 订单ID
     */
    public function requestApi($info){
        $arr = $info;
//        $info = $this->field('orderID,appid,serverID,channel,vip,reward gift,username,roleID,roleName,amount,create_time time')->where(array('id'=>$id))->find();
        $arr['amount'] = $info['amount'] *100;
        $arr['channel'] = C('MAIN_CHANNEL');
        $arr['gift'] = $info['reward'];
        $arr['time'] = $info['create_time'];
        $game = M('game')->field('bpayurl,server_key')->where(array('id'=>$arr['appid']))->find();

        $field = "orderID={$arr['orderID']}&appid={$arr['appid']}&serverID={$arr['serverID']}&channel={$arr['channel']}&vip={$arr['vip']}&gift={$arr['gift']}&username={$arr['username']}&roleID={$arr['roleID']}&roleName={$arr['roleName']}&amount={$arr['amount']}&time={$arr['time']}";

        $url = $game['bpayurl'].'?'.$field;

        $arr['sign'] = md5($field.'&key='.$game['server_key']);

        $res = curl_post($game['bpayurl'],$arr);
        

        $set['url'] = $url.'&sign='.$arr['sign'];
	
        $set['status'] = (strpos($res,'SUCCESS')!==false && strlen($res)<=15) ? 1 : 0;
        
        $set['callback'] = trim($res,'"');
	
        $this->where(array('orderID'=>$arr['orderID']))->setField($set);
    }
}
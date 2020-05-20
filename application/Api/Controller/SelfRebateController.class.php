<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/3
 * Time: 17:04
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class SelfRebateController extends AppframeController {

    /**
     * 用户查询返利接口
     */
    public function rebateInfo(){
        $uid = I('uid');
        $sign = I('sign');
        $key = C('API_KEY');
        if(!checkSign(array('uid'=>$uid,'sign'=>$sign),$key)) $this->ajaxReturn('','sign error',0);

        $option = $this->getOption();
//
        $res = '';
        if($option){
            $data = $this->getData($uid,1,$option);

            foreach ($data as $k=>$v){
                if($info = $this->algorithm($uid,$v,time())){
                    $res[] = $info;
                }
            }

        }

        $this->ajaxReturn($res,'success');
    }

    /**
     * 申请返利
     */
    public function rebateApply(){
        $uid = I('uid');
        $appid = I('appid');
        $rolename = I('rolename');
        $roleid = I('roleid');
        $serverID = I('serverID');
        $sign = I('sign');
        $key = C('API_KEY');
        if(!checkSign(array('uid'=>$uid,'appid'=>$appid,'rolename'=>$rolename,'roleid'=>$roleid,'serverID'=>$serverID,'sign'=>$sign),$key)) $this->ajaxReturn('','sign error',0);

        $map = array(
            'appid' => $appid,
            'uid' => $uid,
            '_string' => "to_days(FROM_UNIXTIME(create_time, '%Y%m%d')) = to_days(now())"
        );
        if(M('self_rebate')->where($map)->find()){
            $this->ajaxReturn(null,'每日只能申请一次返利',0);
        }
        //没有查到符合时间的配置则返回失败
        $option = $this->getOption($appid);
        if(!$option) $this->ajaxReturn(null,'数据失效',0);

        $data = $this->getData($uid,2,$option[0],$appid);
        if(!$data['appid']) $this->ajaxReturn(null,'data error',0);
        $res = $this->algorithm($uid,$data,time());

        if(!$res) $this->ajaxReturn(null,'data error',0);
        $add = array(
            'appid' => $appid,
            'uid'   => $uid,
            'amount' => $res['amount'],
            'rolename' => $rolename,
            'roleid' => $roleid,
            'servername' => $serverID,
            'game_coin' => $res['game_coin'],
            'create_time' => time(),
        );
        if(M('self_rebate')->add($add)){
            $this->ajaxReturn(null,'success');
        }else{
            $this->ajaxReturn(null,'error',0);
        }

    }

    /**
     * 返利记录
     */
    public function rebateRecord(){
        $uid = I('uid');
        $page = I('page',0);
        $sign = I('sign');
        $key = C('API_KEY');
        $limit = 10;
        $start = ($page-1) * $limit;
        if(!checkSign(array('uid'=>$uid,'page'=>$page,'sign'=>$sign),$key)) $this->ajaxReturn('','sign error',0);

        $field = 'g.game_name gamename,s.servername,s.rolename,s.game_coin,s.create_time,s.status';
        $data = M('self_rebate s')
                ->field($field)
                ->join('left join __GAME__ g on g.id=s.appid')
                ->where(array('s.uid'=>$uid))
                ->order('s.create_time desc')
                ->limit($start,$limit)
                ->select();
        $count = M('self_rebate')->where(array('uid'=>$uid))->count();
        $res = array(
            'count' => ceil($count/$limit),
            'rebate' => $data,
        );
        $this->ajaxReturn($res);
    }

    /**
     * 返利须知
     */
    public function rebateKnow(){
        $data = get_site_options();
        if(!$data['notice']['title'][0]) $this->ajaxReturn(null,'success');
        foreach($data['notice']['title'] as $k=>$v){
            $info[$k]['title'] = $v;
            $info[$k]['content'] = $data['notice']['content'][$k];
        }
        $this->ajaxReturn($info,'success');
    }

    /**
     * 返利滚动通知
     */
    public function rebateNotice(){
        $data = S('rebateNotice');
        if(empty($data)){
            $data = M('self_rebate')
                    ->field('rolename,amount')
                    ->where(array('status'=>1))
                    ->order('create_time desc')
                    ->limit(10)
                    ->select();
            $data ? S('rebateNotice',$data,6000) : S('rebateNotice',array(),6000);
        }
        $this->ajaxReturn($data,'success');
    }

    /**
     * 获得在有效期内充值的游戏和金额
     * @param $uid
     * @param $type
     * @param $option
     * @param null $appid
     * @return mixed
     */
    protected function getData($uid,$type,$option,$appid = null,$time = null){
        if($type == 1){
            if(!$appid){
                foreach($option as $k=>$v){
                    $data = M('inpour i')
                            ->field('i.appid,sum(i.money) money,g.topup_scale,g.game_name,group_concat(i.roleNAME) rolename,group_concat(i.roleID) roleid,group_concat(i.serverID) serverID')
                            ->join('left join __GAME__ g on g.id=i.appid')
                            ->where(array('i.appid'=>$v['appid'],'i.uid'=>$uid,'i.status'=>1,'i.payType'=>array('neq',10),'i.create_time'=>array('between',array($v['start'],$v['end']))))
                            ->find();
                    $info[] = $this->setData($data,$v);
                }
            }else{
                $data = M('inpour i')
                        ->field('i.appid,sum(i.money) money,g.topup_scale,g.game_name,group_concat(i.roleNAME) rolename,group_concat(i.roleID) roleid,group_concat(i.serverID) serverID')
                        ->join('left join __GAME__ g on g.id=i.appid')
                        ->where(array('i.uid'=>$uid,'i.status'=>1,'i.payType'=>array('neq',10),'i.appid'=>$appid,'i.create_time'=>array('between',array($time,time()))))
                        ->find();
                $info = $this->setData($data,$option);
            }
        }else{
            $data = M('inpour i')
                    ->field('i.appid,sum(i.money) money,g.topup_scale,g.game_name,group_concat(i.roleNAME) rolename,group_concat(i.roleID) roleid,group_concat(i.serverID) serverID')
                    ->join('left join __GAME__ g on g.id=i.appid')
                    ->where(array('i.uid'=>$uid,'i.status'=>1,'i.payType'=>array('neq',10),'i.appid'=>$appid,'i.create_time'=>array('between',array($option['start'],$option['end']))))
                    ->find();
            $info = $this->setData($data,$option);
        }
        return $info;
    }


    /**
     * 返利计算
     * @param $uid
     * @param $data
     * @param $time
     * @return array
     */
    protected function algorithm($uid,$data){
        if(!$data) return;
        $middle = '';//符合充值居中数据
        $top = '';//符合充值封顶数据
        $last = M('self_rebate')->where(array('appid'=>$data['appid'],'uid'=>$uid))->order('create_time desc')->find();
        //把数据放在新容器
        $new = $data;
        //如果有申请记录的要判断时间，没有的直接计算返利数据
        if($last){
            //在有效期内曾申请过返利就要从申请的时间重新获得数据
            if($data['start'] <= $last['create_time'] && $data['end'] >= $last['create_time']){
                $new = $this->getData($uid,1,$data,$data['appid'],$last['create_time']);
            }
        }

        //返利配置转成数组
        $option = json_decode($new['option'],1);
        foreach ($option as $k1=>$v1){
            //得到最低和最高充值区间
            list($low,$high) = explode('-',$v1['need_money']);
            //得到在区间内的返利百分比
            if($new['money'] >= $low && $new['money'] <= $high){
                $temp[] = $v1['percent'];
                //得到高于区间的返利百分比
            }elseif($new['money'] > $high){
                $temp[] = $v1['percent'];
            }
        }

        //处理区间内数据
        if($temp){
            //得到可获得返利的最高百分比
            rsort($temp);
            $res = array(
                'appid' => $new['appid'],
                'gamename' => $new['game_name'],
                'amount' => $new['money'],
                'game_coin' => intval(($new['money'] * $new['topup_scale'] * $temp[0]) / 100),
                'user' => $new['user']
            );
        }
        return $res;
    }

    /**
     * 获得返利配置
     * @return mixed
     */
    protected function getOption($appid = null){
        $Model = new \Think\Model();
        $time = time();
        $where = $appid ? " and appid={$appid}" : '';
        $sql = "SELECT * FROM (select * from `bt_rebate_option` WHERE `status` = 1 AND `start` <= {$time} AND `end` >= {$time} {$where}  ORDER BY id desc limit 999) a GROUP BY a.appid ORDER BY a.id desc";

        $option = $Model->query($sql);
        return $option;
    }

    /**
     * 组装数据
     */
    protected function setData($arr,$option){
        $data = $arr;
        if($data['appid']){
            $data['serverID'] = explode(',',$data['serverID']);
            $data['start'] = $option['start'];
            $data['end'] = $option['end'];
            $data['option'] = $option['option'];
            $data['rolename'] = explode(',',$data['rolename']);
            $data['roleid'] = explode(',',$data['roleid']);
            if(count($data['serverID']) > 1){
                foreach($data['serverID'] as $k1=>$v1){
                    $data['user'][$k1] = array(
                        'serverID' => $v1,
                        'rolename' => $data['rolename'][$k1],
                        'roleid' => $data['roleid'][$k1],
                        'all' => $data['rolename'][$k1].'   '.$v1.'区'
                    );
                }
            }else{
                $data['user'][] = array(
                    'serverID' => $data['serverID'][0],
                    'rolename' => $data['rolename'][0],
                    'roleid' => $data['roleid'][0],
                    'all' => $data['rolename'][0].'   '.$data['serverID'][0].'区'
                );
            }
            unset($data['serverID'],$data['rolename'],$data['roleid']);
            if(count($data['user']) > 1){
                $data['user'] = array_unique_fb($data['user']);
            }
            return $data;
        }
        return '';
    }

    public function checkTime(){
        $time = time();
        M('rebate_option')->where(array('end'=>array('elt',$time)))->setField('status',2);

    }

    /**
     * 获取游戏返利信息
     */
    public function gameRebate()
    {
        $appid = I('appid');

        if(empty($appid))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $client_key = M('game')->where(array('status'=>1,'id'=>$appid))->getfield('client_key');

        if(!$client_key)
        {
            $this->ajaxReturn(null,'app不存在',0);
        }

        $arr = array(
            'appid'=>$appid,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,$client_key);

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $tag = M('game')->where(array('id'=>$appid))->getfield('tag');
        $game_model = M('game','syo_',C('185DB'));
        $game_info  = $game_model->where(array('tag'=>$tag))->field('rebate,vip')->find();
        $game_info['rebate'] = html_entity_decode($game_info['rebate']);
        $game_info['vip'] = html_entity_decode($game_info['vip']);
        if(!$game_info)
        {
            $this->ajaxReturn(null,'游戏不存在',0);
        }

        $this->ajaxReturn($game_info);
    }
}
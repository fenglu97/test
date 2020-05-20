<?php
/**
 * 后台-数据管理
 * Created by PhpStorm.
 * User: lupeng
 * Date: 2019/5/27
 * Time: 10:42
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class DataManageController extends AdminbaseController{

    /**
     *  数据汇总
     */
    public function data_summary(){

        $channel_role = session('channel_role');
        if($channel_role == 'all') {
            $default_channel = C('MAIN_CHANNEL');
        } else {
            $channel_role = explode(',',$channel_role);
            $default_channel = $channel_role[0];
        }
        $cid = I('cid',$default_channel);
        $start = I('start');
        $end = I('end');
        $type_date = I('type_date',3);
        $date_info = $this->map_date($type_date);

        //$map['id'] = $cid;
        $map['id|parent'] = $cid;    //该渠道和该渠道下的子渠道
        $map['type'] = array('neq',5);
        $map['status'] = 1;

        $field = 'id,name';

        $count = M('channel')
            ->where($map)
            ->count();
        $page = $this->page($count, 20);
        $data = M('channel')
            ->field($field)
            ->where($map)
            ->limit($page->firstRow.','.$page->listRows)
            ->select();
        $inpour = M('inpour');
        $player = M('player');
        $inpour_field = 'id,money,getmoney,platform_money,rebate,getmoney + round(platform_money/10,2) - round(rebate/10,2) income';
        $total = array();
        if(empty($start) || empty($end)){
            $map_p['regtime'] =  array(array('egt',$date_info['beginDate']),array('elt',$date_info['endDate']));
            $map_i['create_time'] =  array(array('egt',$date_info['beginDate']),array('elt',$date_info['endDate']));
        }
        else{
            $start_unix = strtotime($start);
            $end_unix = strtotime($end.' 23:59:59');
            $map_p['regtime'] =  array(array('egt',$start_unix),array('elt',$end_unix));
            $map_i['create_time'] =  array(array('egt',$start_unix),array('elt',$end_unix));
        }

        $map_p['status'] = array('neq',0);
        $map_i['status'] = array('neq',3);
        foreach($data as $k=>$v){
            $money = 0;
            $getmoney = 0;
            $platform_money = 0;
            $income = 0;
            $rebate = 0;
            $map_p['channel'] = $v['id'];
            $data[$k]['regNum'] = $player->where($map_p)->count('DISTINCT machine_code');
            $map_i['cid'] = $v['id'];
            $inpour_list = $inpour->where($map_i)->field($inpour_field)->select();
            $payNum = $inpour->where($map_i)->count('DISTINCT uid');
            $data[$k]['payNum'] = $payNum?$payNum:0;
            $data[$k]['rate'] = round($data[$k]['payNum']/$data[$k]['regNum'],4)*100;
            foreach($inpour_list as $key=>$value){
                $money += $inpour_list[$key]['money'];
                $getmoney += $inpour_list[$key]['getmoney'];
                $income += $inpour_list[$key]['income'];
                $platform_money += $inpour_list[$key]['platform_money'];
                $rebate += $inpour_list[$key]['rebate'];
            }
            $data[$k]['money'] = $money;
            $data[$k]['getmoney'] = $getmoney;
            $data[$k]['platform_money'] = $platform_money;
            $data[$k]['income'] = $income;
            $data[$k]['rebate'] = $rebate;
            $total['money'] += $money;
            $total['getmoney'] += $getmoney;
            $total['platform_money'] += $platform_money;
            $total['rebate'] += $rebate;
            $total['income'] += $income;
            $total['payNum'] += $data[$k]['payNum'];
            $total['regNum'] += $data[$k]['regNum'];
            $total['rate'] = round($total['payNum']/$total['regNum'],4)*100;
        }
        $this->cid = $cid;
        $this->type_date = $type_date;
        $this->start = $start;
        $this->end = $end;
        $this->page = $page->show('Admin');
        $this->platform = C('PLATFORM');
        $this->total = $total;
        $this->list = $data;
        $this->display();
    }

    /**
    *  注册明细
    */
    public function register_details(){

        $channel_role = session('channel_role');
        if($channel_role == 'all') {
            $default_channel = C('MAIN_CHANNEL');
        } else {
            $channel_role = explode(',',$channel_role);
            $default_channel = $channel_role[0];
        }
        $cid = I('cid',$default_channel);
        $map['p.channel'] = $cid;
        $start = I('start');
        $end = I('end');
        $type_date = I('type_date',3);
        $date_info = $this->map_date($type_date);
        $username = I('username');
        if(!empty($username)){
            $map['p.username'] = $username;
        }
        $mobile = I('mobile');
        if(!empty($mobile)){
            $map['p.mobile'] = $mobile;
        }
        $month = date('Ym',time());
        if(empty($start) || empty($end)){
            $map['p.regtime'] =  array(array('egt',$date_info['beginDate']),array('elt',$date_info['endDate']));
        }
        else{
            $start_unix = strtotime($start);
            $end_unix = strtotime($end.' 23:59:59');
            $map['p.regtime'] =  array(array('egt',$start_unix),array('elt',$end_unix));
        }
        $map['p.status'] = 1;
        $count = M('player p')
            ->join('left join (select a.id lid,a.ip,a.appid,a.uid from bt_player_login_logs'.$month.' a
                    left join (select max(id) as id,uid from bt_player_login_logs'.$month.' group by uid) b on a.uid = b.uid where a.id = b.id ) l on l.uid = p.id' )
            ->join('left join bt_channel c on p.channel = c.id')
            ->join('left join bt_game g1 on g1.id = p.appid')
            ->join('left join bt_game g2 on g2.id = l.appid')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);
        $field = 'l.lid,l.appid last_appid,p.id,p.username,p.mobile,c.name channel,p.appid,g1.game_name reg_game,g2.game_name last_login_game,p.regtime,p.last_login_time,p.machine_code,l.ip';
        //玩家表关联登录日志查表找玩家最后一次登录记录，再关联游戏表获取游戏名称
        $data = M('player p')
            ->join('left join (select a.id lid,a.ip,a.appid,a.uid from bt_player_login_logs'.$month.' a
                    left join (select max(id) as id,uid from bt_player_login_logs'.$month.' group by uid) b on a.uid = b.uid where a.id = b.id ) l on l.uid = p.id' )
            ->join('left join bt_channel c on p.channel = c.id')
            ->join('left join bt_game g1 on g1.id = p.appid')
            ->join('left join bt_game g2 on g2.id = l.appid')
            ->field($field)
            ->where($map)
            ->order('p.regtime desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select();
        foreach($data as $k=>$v){
            $data[$k]['ip'] = long2ip($data[$k]['ip']);
        }
        $this->type_date = $type_date;
        $this->cid = $cid;
        $this->username = $username;
        $this->mobile = $mobile;
        $this->start = $start;
        $this->end = $end;
        $this->page = $page->show('Admin');
        $this->list = $data;
        $this->display();

    }

    /**
     *  充值明细
     */
    public function inpour_details(){

        $channel_role = session('channel_role');
        if($channel_role == 'all') {
            $default_channel = C('MAIN_CHANNEL');
        } else {
            $channel_role = explode(',',$channel_role);
            $default_channel = $channel_role[0];
        }
        $cid = I('cid',$default_channel);
        $map['i.cid'] = $cid;
        $username = I('username');
        if(!empty($username)){
            $where['username'] = $username;
            $info = M('player')->where($where)->find();
            if($info){
                $map['i.uid'] = $info['id'];
            }
        }
        $game_platform = I('game_platform');
        if(!empty($game_platform)){
            $map['g.game_type'] = $game_platform;
        }
        $gid = I('gid');
        if(!empty($gid)){
            $map['i.appid'] = $gid;
        }
        $start = I('start');
        $end = I('end');
        $type_date = I('type_date',3);
        $date_info = $this->map_date($type_date);
        if(empty($start) || empty($end)){
            $map['i.create_time'] =  array(array('egt',$date_info['beginDate']),array('elt',$date_info['endDate']));
        }
        else{
            $start_unix = strtotime($start);
            $end_unix = strtotime($end.' 23:59:59');
            $map['i.create_time'] =  array(array('egt',$start_unix),array('elt',$end_unix));
        }
        $map['i.status'] = array('neq',3);
        $count = M('inpour i')
            ->join('left join bt_player p on p.id = i.uid')
            ->join('left join bt_channel c on c.id = i.cid')
            ->join('left join bt_game g on g.id = i.appid')
            ->where($map)
            ->count();
        $array = M('inpour i')
            ->join('left join bt_player p on p.id = i.uid')
            ->join('left join bt_channel c on c.id = i.cid')
            ->join('left join bt_game g on g.id = i.appid')
            ->field('i.id,i.money,i.getmoney,i.platform_money,i.getmoney + round(i.platform_money/10,2) - round(i.rebate/10,2) income')
            ->where($map)
            ->select();

        $total = array();
        foreach($array as $k=>$v){
            $total['money'] += $v['money'];
            $total['getmoney'] += $v['getmoney'];
            $total['platform_money'] += $v['platform_money'];
            $total['income'] += $v['income'];
        }

        $page = $this->page($count, 20);
        $field = 'i.id,p.username,c.name channel,i.payType,i.app_uid,g.game_type,g.game_name,i.serverNAME,i.roleNAME,i.money,i.getmoney,i.getmoney + round(i.platform_money/10,2) - round(i.rebate/10,2) income,i.platform_money,i.create_time,i.pay_to_time,i.status';

        $data = M('inpour i')
            ->join('left join bt_player p on p.id = i.uid')
            ->join('left join bt_channel c on c.id = i.cid')
            ->join('left join bt_game g on g.id = i.appid')
            ->field($field)
            ->where($map)
            ->order('i.create_time desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select();
        $this->type_date = $type_date;
        $this->cid = $cid;
        $this->username = $username;
        $this->game_platform = $game_platform;
        $this->gid = $gid;
        $this->start = $start;
        $this->end = $end;
        $this->page = $page->show('Admin');
        $this->total = $total;
        $this->list = $data;
        $this->platform = C('PLATFORM');
        $this->pay_type = C('PAY_TYPE_INPOUR');
        $this->display();

    }

    /**
     *  根据日期类型，返回对应时间戳区间
     *  日期类型type_date: 0 - 今日， 1 - 昨日， 2 - 本周 ，3 - 本月
     */
    public function map_date($type_date = 0){
        if($type_date == 0){
            $beginDate = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $endDate = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        }elseif($type_date == 1){
            $beginDate = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
            $endDate = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        }elseif($type_date == 2){
            $today = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $w = date('w',$today);
            $beginDate = mktime(0,0,0,date('m'),date('d')-$w+1,date('Y'));
            $endDate = mktime(0,0,0,date('m'),date('d')+(7-$w)+1,date('Y'))-1;
        }else{
            $beginDate = mktime(0,0,0,date('m'),str_pad(1,2,0,STR_PAD_LEFT),date('Y'));
            $month_days = date('t',strtotime(date('Y').'-'.(date('m')).'-'.str_pad(1,2,0,STR_PAD_LEFT)));
            $endDate = mktime(0,0,0,date('m'),$month_days+1,date('Y'))-1;
        }
        $data['beginDate'] = $beginDate;
        $data['endDate'] = $endDate;
        return $data;
    }
}
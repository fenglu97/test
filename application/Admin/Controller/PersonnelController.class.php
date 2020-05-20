<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/11/14
 * Time: 14:45
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
use Think\Exception;

class PersonnelController extends AdminbaseController{


    /**
     * 人员数据
     */
    public function userData(){

        $search = I('map');
        //用户地区权限
        $role = M('users')->where(array('id'=>session('ADMIN_ID')))->getField('channel_legion');
        $role == 'all' ? $where = '' : $where['id'] = array('in',$role);
        $area = M('channel_legion')->where($where)->select();
        $data = '';
        if(empty($search['month'])){
            $search['month'] = date('Y-m');
        }
        $parent_channels = '';
        foreach($area as $k=>$v){
            $channel = explode(",",$v['channels']);
            $parent_channels.=$v['channels'].',';
            foreach($channel as $key=>$val){
                $data[] = array(
                    'area_id' => $v['id'],
                    'area' => $v['name'],
                    'taem_id' => $val,
                    'taem' => M('channel')->where(array('id'=>$val))->getField('name'),
                );
            }
        }
        $taem = $data;
        $parent_channels = trim($parent_channels,',');

        $week_target = M('channel')->where(array('id'=>array('in',$parent_channels)))->getfield('id,lastweek_register,lastweek_money,thisweek_register,thisweek_money',true);
        $this->week_target = $week_target;


        $inpour_model = M('inpour');
        $player_model = M('player');


        if(empty($search['name']) && empty($search['area']) && empty($search['team'])){

            foreach($data as $k=>$v){
                //获得团队下各组及组员cid
                $cids = M('channel')->where(array('id|parent'=>$v['taem_id']))->getField('group_concat(id)');

                $last_monday = getLastMonday();

                //上周map
                $time_map = array(array('egt',$last_monday),array('lt',$last_monday+7*3600*24));

                //上周注册
                $week_regster = $player_model
                    ->where(array('channel'=>array('in',$cids),'first_login_time'=>$time_map))
                    ->count();

                //上周充值
                $week_pay = $inpour_model
                    ->where(array('cid'=>array('in',$cids),'create_time'=>$time_map,'status'=>1))
                    ->getfield('sum(getmoney) as money');


                $data[$k]['lastweek_register'] = $week_regster;

                $data[$k]['lastweek_pay'] = $week_pay;

                //本周map
                $time_map = array(array('egt',$last_monday+7*3600*24));

                //本周注册
                $week_regster = $player_model
                    ->where(array('channel'=>array('in',$cids),'first_login_time'=>$time_map))
                    ->count();

                //本周充值
                $week_pay = $inpour_model
                    ->where(array('cid'=>array('in',$cids),'create_time'=>$time_map,'status'=>1))
                    ->getfield('sum(getmoney) as money');


                $data[$k]['thisweek_register'] = $week_regster;

                $data[$k]['thisweek_pay'] = $week_pay;


                //目前在职
                $data[$k]['working'] = M('tg_employees')->where(array('parent_channel'=>$v['taem_id'],'departure_time'=>0))->count();

                //团队绑定的员工
                $time = $search['month'].'-'.date('t',strtotime($search['month'])).' 23:59:59';
                $employees = M('tg_employees')->field('channel,hire_date,departure_time')->where(array('parent_channel'=>$v['taem_id'],'hire_date'=>array('elt',strtotime($time))))->select();

                //本月
                if(empty($search['month']) || $search['month'] == date('Y-m')){
                    $month = strtotime(date('Y-m-01',time()));

                    //当月离职
                    $data[$k]['quit'] = M('tg_employees')->where(array('parent_channel'=>$v['taem_id'],'departure_time'=>array('egt',$month)))->count();
                    //当月目标
                    $data[$k]['target'] = M('tg_info')->where(array('parent_channel'=>$v['taem_id'],'time'=>date('Y-m',$month)))->getField('target');

                    $between = array($month, strtotime(date('Y-m-'.date('t',strtotime($search['month'])).' 23:59:59')));
                    //当月充值及人数 sdk+bi
                    $payinfo = M('inpour')->field('sum(getmoney) getmoney,count(DISTINCT uid) uid')->where(array('cid'=>array('in',$cids),'status'=>1,'create_time'=>array('between',$between)))->find();
                    //$bipayinfo = M('pay','syo_',C('DB_OLDSDK_CONFIG'))->field('sum(getmoney) getmoney,count(DISTINCT username) uid')->where(array('channel'=>array('in',$cids),'type'=>1,'status'=>1,'pay_to_time'=>array('between',$between)))->find();

                    //活跃人数
                    try{
                        $sdkuser = M('player_login_logs'.date('Ym',strtotime($search['month'])))->where(array('channel'=>array('in',$cids)))->getField('count(DISTINCT username)');

                    }catch (Exception $e){
                        $sdkuser = 0;
                    }
                    //$biuser = M('login_log',null,C('DB_OLDSDK_CONFIG'))->where(array('channel'=>array('in',$cids),'time'=>array('between',$between)))->getField('count(1)');

                    //当月注册
                    $reg = M('player')->where(array('channel'=>array('in',$cids),'status'=>1,'first_login_time'=>array('between',$between)))->count();

                    //sdk+bi充值金额
                    //$countpay = $payinfo['getmoney'] + $bipayinfo['getmoney'];
                    $countpay = $payinfo['getmoney'];
                    //sdk+bi充值人数
                    //$number = $payinfo['uid'] + $bipayinfo['uid'];
                    $number = $payinfo['uid'];
                    //sdk+bi活跃人数
                    //$active = $sdkuser + $biuser;
                    $active = $sdkuser;
                    $amount = isset($countpay) ? $countpay : 0;


                    $data[$k]['pay'] = $countpay;
                    //完成率
                    $data[$k]['complete'] = $data[$k]['pay'] ? round($data[$k]['pay']/$data[$k]['target'],3)*100 : 0;

                    $data[$k]['reg'] = $reg;

                    //充值人数
                    $data[$k]['pay_number'] = $number;
//                    //活跃ARPU
//                    $data[$k]['active_arpu'] = round($amount/$active,2);
//                    //付费ARPU
//                    $data[$k]['pay_arpu'] = round($amount/$number,2);
                }else{
                    $info = M('tg_info')->where(array('parent_channel'=>$v['taem_id'],'time'=>$search['month']))->find();

                    $arr = array(
                        'working' => M('tg_employees')->where(array('parent_channel'=>$v['taem_id'],'departure_time'=>0))->count(),
                        'quit' => $info['turnover'],
                        'target' => $info['target'],
                        'pay' => $info['money'],
                        'complete' => round($info['money']/$info['target'],3)*100,
                        'pay_number' => $info['pay_numbers'],
                        'reg' => $info['registers'],
                        'active_arpu' => round($info['money']/$info['active_user'],2),
                        'pay_arpu' => round($info['money']/$info['pay_numbers'],2)
                    );
                    $data[$k] = array_merge($data[$k],$arr);
                }
            }



            $this->data = arraySort($data,'pay','SORT_DESC');
            $this->map = $search;
            $this->area = $area;
            $this->taem = $taem;
            $this->display();
        }else{
            $this->userInfo($search);
        }

    }

    /**
     * 人员详情
     */
    public function userInfo(){
        $map = I('map');

        if(empty($map['month'])){
            $map['month'] = date('Y-m');
        }

        $where['channel_legion'] = $map['area'];
        if($map['taem']) $where['parent_channel'] = $map['taem'];
        if($map['name']) $where['channel|departure_channel'] = $map['name'];
        //入职时间在查询时间之前的员工
        $where['hire_date'] = array('elt',strtotime($map['month'].'-'.date('t',strtotime($map['month']).' 23:59:59')));

        $count = M('tg_employees')->where($where)->count();
        $page = $this->page($count, 20);
        $data = M('tg_employees')->field('id,name,parent_channel,channel,hire_date,departure_time,departure_reason quit')->where($where)->limit($page->firstRow,$page->listRows)->order('hire_date')->select();

        if($data) {
            foreach($data as $k=>$v) {
                //时间段
                $start = date('Y-m',$v['hire_date']) == $map['month'] ? $v['hire_date'] : strtotime($map['month'] . '-01');
//                $end = date('Y-m',$v['departure_time']) == $map['month'] ? $v['departure_time'] : strtotime($map['month'] .'-'. date('t', strtotime($map['month'])) . ' 23:59:59');
                if(date('Y-m',$v['departure_time']) == $map['month']){
                    $end = $v['departure_time'];
                }elseif($v['departure_time'] == 0 || date('Y-m',$v['departure_time']) > $map['month']){
                    $end = strtotime($map['month'] .'-'. date('t', strtotime($map['month'])) . ' 23:59:59');
                }elseif($v['departure_time'] != 0 && date('Y-m',$v['departure_time']) < $map['month']){
                    $end = $start;
                }
                $between = array($start, $end);

                //员工信息
                $info = M('tg_evaluation')->field('sum(score) score,sum(deduct_marks) reduce,group_concat(evaluation separator "#") assess,group_concat(deduct_reason separator "#") reason')->where(array('tg_employee_id' => $v['id'], '_string' => "from_unixtime(period,'%Y-%m')='{$map['month']}'"))->find();
                $arr = explode('#',$info['assess']);
                $info['assess'] = $arr[count($arr)-1];   //获取最后一个值
                $data[$k]['taem_name'] = M('channel')->where(array('id' => $v['parent_channel']))->getField('name');
                //入职天数
                $end_time = $v['departure_time'] > 0 ? $v['departure_time'] : time();
                $data[$k]['work_day'] = floor(($end_time - $v['hire_date']) / 86400);

                $data[$k]['working'] = $v['departure_time'] > 0 ? date('y年m月d日',$v['departure_time']).'离职' : '在职';
                $data[$k]['new_user'] = M('player')->where(array('channel' => $v['channel'], 'status' => 1, 'first_login_time' => array('between',$between)))->count();
                //当月数据
                $payinfo = M('inpour')->field('sum(getmoney) getmoney,count(DISTINCT uid) uid')->where(array('cid' => $v['channel'], 'status' => 1, 'create_time' => array('between',$between)))->find();
                //$bipayinfo = M('pay','syo_',C('DB_OLDSDK_CONFIG'))->field('sum(getmoney) getmoney,count(DISTINCT username) uid')->where(array('channel'=>$v['channel'],'type'=>1,'status'=>1,'pay_to_time'=>array('between',$between)))->find();
                //充值金额
                //$data[$k]['pay'] = $payinfo['getmoney'] + $bipayinfo['getmoney'];
                $data[$k]['pay'] = $payinfo['getmoney'];
                $amount = isset($data[$k]['pay']) ? $data[$k]['pay'] : 0;
                //充值人数
                //$number = $payinfo['uid'] + $bipayinfo['uid'];
                $number = $payinfo['uid'];
                /*arpu*/
                //活跃人数
                try{
                    $sdkuser = M('player_login_logs'.date('Ym',strtotime($map['month'])))->where(array('channel'=>$val['channel']))->getField('count(DISTINCT username)');

                }catch (Exception $e){
                    $sdkuser = 0;
                }
                //$biuser = M('login_log',null,C('DB_OLDSDK_CONFIG'))->where(array('channel'=>$val['channel'],'time'=>array('between',$between)))->getField('count(1)');
                //$active_user = $sdkuser + $biuser;
                $active_user = $sdkuser;

                //活跃ARPU
                $data[$k]['active_arpu'] = round($amount / $active_user, 2);
                //付费ARPU
                $data[$k]['pay_arpu'] = round($amount / $number, 2);
                $data[$k] = array_merge($data[$k], $info);

                if($data[$k]['work_day'] < 7){
                    $data[$k]['color']  = '#ef8d8a';
                }elseif($data[$k]['work_day'] >= 7 && $data[$k]['work_day'] < 30){
                    $data[$k]['color']  = '#a2a52d';
                }elseif($data[$k]['work_day'] >= 30 && $data[$k]['work_day'] < 60){
                    $data[$k]['color']  = '#755c8e';
                }elseif($data[$k]['work_day'] >= 60 && $data[$k]['work_day'] < 90){
                    $data[$k]['color']  = '#6ea8da';
                }elseif($data[$k]['work_day'] > 90){
                    $data[$k]['color'] = '#80b180';
                }

            }

        }

        //团队
        $cids = M('channel_legion')->where(array('id'=>$map['area']))->getField('channels');
        $taem = M('channel')->field('id,name')->where(array('id'=>array('in',$cids)))->select();

        $this->page = $page->show('Admin');
//        $this->data = arraySort($data,'pay','SORT_DESC');
        $this->data = $data;
        $this->map = $map;
        $this->taem = $taem;
        $this->channel = M('tg_employees')->field('if(channel=0,departure_channel,channel) id,name')->where(array('parent_channel'=>$map['taem']))->select();
        $this->display('userInfo');
    }

    public function getData(){
        $id = I('id');
        $type = I('type');
        if($type == 1){
            $cids = M('channel_legion')->where(array('id'=>$id))->getField('channels');
            $data = M('channel')->field('id,name')->where(array('id'=>array('in',$cids)))->select();
        }else{

            $data = M('tg_employees')->field('if(channel=0,departure_channel,channel) id,name')->where(array('parent_channel'=>$id))->select();
        }
        $this->success($data);
    }
}
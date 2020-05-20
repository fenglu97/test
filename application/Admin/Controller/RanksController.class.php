<?php
/**
 * 军衔
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/7/10
 * Time: 17:13
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class RanksController extends AdminbaseController{

    public function index(){
        $channel = M('channel')->where(array('admin_id'=>SESSION('ADMIN_ID')))->find();
        if($channel['parent'] == 0){
            $channel['up'] = '无';
        }else{
            $channel['up'] = M('channel')->where(array('id'=>$channel['parent']))->getField('name');
        }

        $data = get_tg_data($channel['id']);
        if($info = M('channel_legion')->where(array('_string'=>"FIND_IN_SET('{$channel['parent']}',channels)"))->find()){

            $channel_legion_target = M('tg_info')->where(array('channel_legion'=>$info['id'],'time'=>date('Y-m')))->getfield('sum(target)');


            $parent_target = M('tg_info')->where(array('parent_channel'=>$channel['parent'],'time'=>date('Y-m')))->getfield('sum(target)');


            $group['name'] = $info['name'];
            $start = date('Y-m-01');
            $end = strtotime(date('Y-m-d', strtotime("$start +1 month -1 day")).' 23:59:59');
            $cids = M('channel')->where(array('id|parent'=>array('in',$info['channels'])))->getField('id',true);

            //sdk统计金额
            $sdkmoney = M('inpour')->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array('between',array(strtotime($start),$end))))->getField('sum(getmoney)');
            //bi统计金额
            //$bimoney = M('pay','syo_',C('DB_OLDSDK_CONFIG'))->where(array('channel'=>array('in',implode(',',$cids)),'status'=>1,'type'=>1,'created'=>array('between',array(strtotime($start),$end))))->getField('sum(getmoney)');
            //$already = $sdkmoney + $bimoney;
            $already = $sdkmoney;
            $already = $already ? : 0;



            //您所在的部门（军团）本月绩效为XXXX元，已完成绩效XXXX元，还差XXXX元
            if($already >= $channel_legion_target){
                $ext = '';
            }else{
                $diff = $channel_legion_target - $already;
                $ext = "，还差<b style='color:red;margin:0'>{$diff}</b>元";
            }

            //所在团队数据
            $cids = M('channel')->where(array('id|parent'=>$channel['parent']))->getField('id',true);

            //sdk统计金额
            $sdkmoney = M('inpour')->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array('between',array(strtotime($start),$end))))->getField('sum(getmoney)');
            //bi统计金额
            //$bimoney = M('pay','syo_',C('DB_OLDSDK_CONFIG'))->where(array('channel'=>array('in',implode(',',$cids)),'status'=>1,'type'=>1,'created'=>array('between',array(strtotime($start),$end))))->getField('sum(getmoney)');
            //$already1 = $sdkmoney + $bimoney;
            $already1 = $sdkmoney;
            $already1 = $already1 ? : 0;

            //您所在的小组 月绩效为XXXX元，已完成绩效XXXX元，还差XXXX元
            if($already1 >= $parent_target){
                $ext1 = '';
            }else{
                $diff1 = $parent_target - $already1;
                $ext1 = "，还差<b style='color:red;margin:0'>{$diff1}</b>元";
            }

            $start = getLastMonday()+3600*24*7;
            $end = $start+3600*24*7-1;
            //sdk统计金额
            $sdkmoney = M('inpour')->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array('between',array($start,$end))))->getField('sum(getmoney)');

            //bi统计金额
            //$bimoney = M('pay','syo_',C('DB_OLDSDK_CONFIG'))->where(array('channel'=>array('in',implode(',',$cids)),'status'=>1,'type'=>1,'created'=>array('between',array($start,$end))))->getField('sum(getmoney)');
            //$already2 = $sdkmoney + $bimoney;
            $already2 = $sdkmoney;
            $already2 = $already2 ? : 0;

            //您所在的小组 周绩效为XXXX元，已完成绩效XXXX元，还差XXXX元
            if($already2 >= $channel['thisweek_money']){
                $ext2 = '';
            }else{
                $diff2 = $channel['thisweek_money'] - $already2;
                $ext2 = "，还差<b style='color:red;margin:0'>{$diff2}</b>元";
            }


            $group['data'] = "您所在的军团本月绩效目标为<b style='color:red;margin:0'>{$channel_legion_target}</b>元，已完成绩效<b style='color:red;margin:0'>{$already}</b>元".$ext;
            $group['data'].= "；您所在的小组本月绩效目标为<b style='color:red;margin:0'>{$parent_target}</b>元，已完成绩效<b style='color:red;margin:0'>{$already1}</b>元".$ext1;
            $group['data'].= "；您所在的小组本周绩效目标为<b style='color:red;margin:0'>{$channel['thisweek_money']}</b>元，已完成绩效<b style='color:red;margin:0'>{$already2}</b>元".$ext2;
        }else{
            $group['name'] = '无';
            $group['data'] = '未加入军团，无法统计绩效';
        }

        $this->data = $data;
        $this->group = $group;
        $this->channel = $channel;
        $this->conf = C('V_PLAN');
        $this->display();
    }
}
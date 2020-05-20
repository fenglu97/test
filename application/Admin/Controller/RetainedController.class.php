<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/22
 * Time: 15:44
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
/**
 * 留存率控制器
 */
class RetainedController extends AdminbaseController{

    public function index(){
        $start = strtotime(date('Y-m-d',strtotime('-6 day')));
        $end = strtotime(date('Y-m-d'));
        $map['first_time'] = array('between',array($start,$end));
        $data = M('retained_day','syo_','185DB')->where($map)->order('first_time')->select();
        $this->data = $data;
        $this->display();
    }

    public function load_data(){
        $start = I('start');
        $end = I('end');
        $type = I('type');

        switch ($type){
            case 'daily':
                $res = $this->day_data($start,$end);
                break;
            case 'weekly':
                $res = $this->week_data($start,$end);
                break;
//            case 'monthly':
//                $res = $this->month_data($start,$end);
//                break;
            default :
                $res = $this->day_data($start,$end);
                break;
        }
        $data['status'] = 1;
        $data['data'] = $res;
        $this->ajaxReturn($data);
    }

    /**
     * 返回日留存
     * @param $start
     * @param $end
     * @return mixed
     */
    protected function day_data($start,$end){
        $start = strtotime($start);
        $end = strtotime($end);
        $map['first_time'] = array('between',array($start,$end));
        $data = M('retained_day','syo_','185DB')->where($map)->order('first_time')->select();
        foreach($data as $k=>$v){
            $data[$k]['first_time'] = date('Y-m-d',$v['first_time']);
            if($v['one_day']){
                $data[$k]['one_day'] = substr($v['one_day']/$v['installs'],0,strpos($v['one_day']/$v['installs'],'.')+4)*100;
            }
            if($v['two_day']){
                $data[$k]['two_day'] = substr($v['two_day']/$v['installs'],0,strpos($v['two_day']/$v['installs'],'.')+4)*100;
            }
            if($v['three_day']){
                $data[$k]['three_day'] = substr($v['three_day']/$v['installs'],0,strpos($v['three_day']/$v['installs'],'.')+4)*100;
            }
            if($v['four_day']){
                $data[$k]['four_day'] = substr($v['four_day']/$v['installs'],0,strpos($v['four_day']/$v['installs'],'.')+4)*100;
            }
            if($v['five_day']){
                $data[$k]['five_day'] = substr($v['five_day']/$v['installs'],0,strpos($v['five_day']/$v['installs'],'.')+4)*100;
            }
            if($v['six_day']){
                $data[$k]['six_day'] = substr($v['six_day']/$v['installs'],0,strpos($v['six_day']/$v['installs'],'.')+4)*100;
            }
            if($v['seven_day']){
                $data[$k]['seven_day'] = substr($v['seven_day']/$v['installs'],0,strpos($v['seven_day']/$v['installs'],'.')+4)*100;
            }
            if($v['fourteen_day']){
                $data[$k]['fourteen_day'] = substr($v['fourteen_day']/$v['installs'],0,strpos($v['fourteen_day']/$v['installs'],'.')+4)*100;
            }
            if($v['thirty_day']){
                $data[$k]['thirty_day'] = substr($v['thirty_day']/$v['installs'],0,strpos($v['thirty_day']/$v['installs'],'.')+4)*100;
            }
        }
        return $data;
    }

    public function week_data($start,$end){
        $start = strtotime($start);
        $end = strtotime($end);
        $map['start_time'] = array('egt',$start);
        $map['end_time'] = array('elt',$end);
        $data = M('retained_week','syo_','185DB')->where($map)->order('id')->select();

        foreach($data as $k=>$v){
            $data[$k]['start_time'] = date('m-d',$v['start_time']);
            $data[$k]['end_time'] = date('m-d',$v['end_time']-1);
            if($v['one_week']){
                $data[$k]['one_week'] = substr($v['one_week']/$v['installs'],0,strpos($v['one_week']/$v['installs'],'.')+4)*100;
            }
            if($v['two_week']){
                $data[$k]['two_week'] = substr($v['two_week']/$v['installs'],0,strpos($v['two_week']/$v['installs'],'.')+4)*100;
            }
            if($v['three_week']){
                $data[$k]['three_week'] = substr($v['three_week']/$v['installs'],0,strpos($v['three_week']/$v['installs'],'.')+4)*100;
            }
            if($v['four_week']){
                $data[$k]['four_week'] = substr($v['four_week']/$v['installs'],0,strpos($v['four_week']/$v['installs'],'.')+4)*100;
            }
            if($v['five_week']){
                $data[$k]['five_week'] = substr($v['five_week']/$v['installs'],0,strpos($v['five_week']/$v['installs'],'.')+4)*100;
            }
            if($v['six_week']){
                $data[$k]['six_week'] = substr($v['six_week']/$v['installs'],0,strpos($v['six_week']/$v['installs'],'.')+4)*100;
            }
            if($v['seven_week']){
                $data[$k]['seven_week'] = substr($v['seven_week']/$v['installs'],0,strpos($v['seven_week']/$v['installs'],'.')+4)*100;
            }
            if($v['eight_week']){
                $data[$k]['eight_week'] = substr($v['eight_week']/$v['installs'],0,strpos($v['eight_week']/$v['installs'],'.')+4)*100;
            }
            if($v['nine_week']){
                $data[$k]['nine_week'] = substr($v['nine_week']/$v['installs'],0,strpos($v['nine_week']/$v['installs'],'.')+4)*100;
            }
        }
        return $data;
    }
}
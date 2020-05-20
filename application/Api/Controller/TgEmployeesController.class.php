<?php
/**
 * 推广员工接口
 * @author qing.li
 * @date 2017-12-6
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class TgEmployeesController extends AppframeController
{
    public function _initialize()
    {
        if(ACTION_NAME != 'sync_data')
        {
            $admin_id = get_current_admin_id();
            if(!$admin_id)
            {
                $this->ajaxReturn(null,'请先登陆',0);
            }
        }

    }

    public function add()
    {
        $post_data = I('post.');

        if(empty($post_data['name']) || empty($post_data['sex']) || empty($post_data['nation']) || empty($post_data['birth']) || empty($post_data['marriage'])||
            empty($post_data['graduate_school']) || empty($post_data['graduate_time']) || empty($post_data['education']) || empty($post_data['major']) ||
            empty($post_data['channel_legion']) || empty($post_data['parent_channel']) || empty($post_data['channel']) || empty($post_data['hire_date']))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        if(!empty($post_data['mobile']) && !preg_match("/^1\d{10}$/", $post_data['mobile']))
        {
            $this->ajaxReturn(null,'手机格式不正确',0);
        }

        if(!empty($post_data['id_card']) && !validateIDCard($post_data['id_card']))
        {
            $this->ajaxReturn(null,'身份证号不合法',0);
        }

        if(!empty($post_data['qq']) && !preg_match("/^[1-9]\d{4,10}$/i",$post_data['qq']))
        {
            $this->ajaxReturn(null,'qq号格式不正确',0);
        }

        if(!empty($post_data['weixin']) && strlen($post_data['weixin'])>20)
        {
            $this->ajaxReturn(null,'微信格式不正确',0);
        }

        if(!empty($post_data['email']) && !preg_match('/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/',$post_data['email']))
        {
            $this->ajaxReturn(null,'email格式不正确',0);
        }


        if(strlen($post_data['birth'])>0) $post_data['birth'] = strtotime($post_data['birth']); 
        if(strlen($post_data['graduate_time'])>0) $post_data['graduate_time'] = strtotime($post_data['graduate_time']);
        if(strlen($post_data['hire_date'])>0) $post_data['hire_date'] = strtotime($post_data['hire_date']);
        if(strlen($post_data['regular_time'])>0) $post_data['regular_time'] = strtotime($post_data['regular_time']);
        if(strlen($post_data['contract_expiration'])>0) $post_data['contract_expiration'] = strtotime($post_data['contract_expiration']);
        $post_data['create_time'] = time();

        if($_FILES['img']['name'])
        {
            $upload = new \Think\Upload();

            $upload->autoSub  = false;
            $upload->rootPath = "www.sy217.com/assets/";
            $upload->savePath = "tg_employees/";


            $fileName=$_FILES["img"]["name"];
            $fileName=explode('.',$fileName);
            $serverFileName=$fileName[0]."_".time();

            $upload->saveName=$serverFileName;//设置在服务器保存的文件名

            $info = $upload->uploadOne($_FILES['img']);

            if(!$info)
            {// 上传错误提示错误信息
                $this->ajaxReturn(null,$upload->getError(),0);
            }
            else
            {// 上传成功
                $file_name = trim($info['fullpath'],'.');
                $post_data['img'] = str_replace('www.sy217.com','',$file_name);
            }

        }

        if(M('tg_employees')->add($post_data)!==false)
        {
            //添加成功后 将渠道管理员启用

            $admin_id = M('channel')->where(array('id'=>$post_data['channel']))->getfield('admin_id');
            M('users')->where(array("id"=>$admin_id,"user_type"=>1))->setField(array('effective'=>time(),'user_status'=>1));

            $this->ajaxReturn(null,'添加成功');
        }
        else
        {
            $this->ajaxReturn(null,'添加失败',0);
        }


    }

    public function info()
    {

        $id = I('id');
        $info = M('tg_employees')->where(array('id'=>$id))->find();

        $info['img'] =!empty($info['img'])?C('FTP_URL').$info['img']:'';

        if(!$info)
        {
            $this->ajaxReturn(null,'员工不存在',0);
        }
        $info['channel_legion'] = M('channel_legion')->where(array('id'=>$info['channel_legion']))->getfield('name');

        $channel_names = M('channel')->where(array('id'=>array('in',$info['parent_channel'].','.$info['channel'])))->getfield('id,name',true);
        $info['parent_channel'] = $channel_names[$info['parent_channel']];
        $info['channel'] = $channel_names[$info['channel']];


        //查询该员工入职以来的流水 注册 以及评价
        $data = array();
        $data['info'] = $info;


        if (date('l',time()) == 'Monday') $lastmonday = strtotime('last monday');

        $lastmonday = strtotime('-1 week last monday');

        //获取上周人事上周的评价
        $lastweek_evaluation = M('tg_evaluation')->field('score,deduct_marks,evaluation,deduct_reason,period')->where(array('period'=>$lastmonday,'tg_employee_id'=>$id))->find();

        if($lastweek_evaluation['period']) $lastweek_evaluation['period'] =  date('Y-m-d',$lastweek_evaluation['period']).'至'.date('Y-m-d',strtotime('+6 days',$lastweek_evaluation['period']));

        $data['lastweek_evaluation'] = $lastweek_evaluation?$lastweek_evaluation:array();


        $data['money_static'] = $this->_money_static($id,1);
        $data['register_static'] = $this->_register_static($id,1);
        $data['evaluation_static'] = $this->_evaluation_static($id,2);

        //查询最近三个月的人事评价（待续）
        $evaluation_list = M('tg_evaluation')
            ->field('id,create_time')
            ->where(array('tg_employee_id'=>$id,'period'=>array('egt',strtotime('-3 months'))))
            ->order('period desc')
            ->select();



        $data['evaluation_list'] =$evaluation_list?$evaluation_list:array();

        $this->ajaxReturn($data);
    }


    public function channel_legion_list()
    {
        $channel_legion = M('channel_legion')
            ->field('id,name,channels')
            ->select();

        $channel_model = M('channel');
        foreach($channel_legion as $k=>$v)
        {
            $parent_channels = $channel_model->field('id,name')->where(array('id'=>array('in',$v['channels'])))->select();
            unset($channel_legion[$k]['channels']);

            foreach($parent_channels as $p_k=>$p_v)
            {
                $sql = "select id,name from `bt_channel` where parent = {$p_v['id']} and id not in (select channel from `bt_tg_employees`)";
                $channel_lists = $channel_model->query($sql);
                $parent_channels[$p_k]['channel'] = $channel_lists;
            }
            $channel_legion[$k]['parent_channel'] = $parent_channels;

        }

        $this->ajaxReturn($channel_legion);
    }


    public function money_static()
    {
        $id = I('id');
        $type = I('type')?I('type'):1;

        if(empty($id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $result = $this->_money_static($id,$type);

        $this->ajaxReturn($result);
    }

    public function register_static()
    {
        $id = I('id');
        $type = I('type')?I('type'):1;

        if(empty($id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $result = $this->_register_static($id,$type);

        $this->ajaxReturn($result);
    }

    public function evaluation_static()
    {
        $id = I('id');
        $type = I('type')?I('type'):2;

        if(empty($id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $result = $this->_evaluation_static($id,$type);

        $this->ajaxReturn($result);
    }

    private function _money_static($id,$type)
    {

        $id = I('id');
        $type = I('type')?I('type'):1;

        $employee_info = M('tg_employees')->field('channel,hire_date,departure_time,departure_channel')->where(array('id'=>$id))->find();

        if(!$employee_info)
        {
            $this->ajaxReturn(null,'该员工不存在',0);
        }

        if($type == 2)
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d"),DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%u")';

            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y',$time_end).'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y',$time_end).'-01-01 00:00:00');
            }
            else
            {
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y').'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y').'-01-01 00:00:00');
            }

        }
        elseif($type == 3)
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m")';

            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
            }
            $time_start = $employee_info['hire_date'];
        }
        else
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d")';

            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y-m',$time_end).'-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y-m',$time_end).'-01 00:00:00');
            }
            else
            {
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y-m').'-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y-m').'-01 00:00:00');
            }

        }


        $map = array();
        $time_map[] = array('egt',$time_start);
        if($time_end) $time_map[] = array('elt',$time_end);

        $map['create_time'] = $time_map;

        $map['status'] = 1;
        if($employee_info['departure_channel'] > 0)
        {
            $channel = $employee_info['departure_channel'];
        }
        else
        {
            $channel = $employee_info['channel'];
        }

        $map['cid'] = $channel;


        $pay_new = M('inpour')
            ->where($map)
            ->group('time')
            ->cache(true)
            ->getfield($time_field.' as time,sum(getmoney)');

        $map = array();
        $map['type'] = 1;
        $map['status'] = 1;
        $map['created'] = $time_map;
        $map['channel'] = $channel;


        $times = M('inpour')
            ->where(array('status'=>1,'cid'=>C('MAIN_CHANNEL'),'create_time'=>$time_map))
            ->group('time')
            ->cache(true)
            ->getfield($time_field.' as time',true);

        $times = array_keys($times);
        $result = array();
        foreach($times as $v)
        {
            $result[$v] = $pay_new[$v]['sum(getmoney)'];
        }

        return $result;

    }

    private function _register_static($id,$type)
    {
        $employee_info = M('tg_employees')->field('channel,hire_date,departure_time,departure_channel')->where(array('id'=>$id))->find();

        if(!$employee_info)
        {
            $this->ajaxReturn(null,'该员工不存在',0);
        }


        if($type == 2)
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(first_login_time),"%Y-%m-%d"),DATE_FORMAT(FROM_UNIXTIME(first_login_time),"%Y-%u")';
            $time2_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d"),DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%u")';

            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y',$time_end).'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y',$time_end).'-01-01 00:00:00');
            }
            else
            {
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y').'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y').'-01-01 00:00:00');
            }

        }
        elseif($type == 3)
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(first_login_time),"%Y-%m")';
            $time2_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m")';
            $time_start = $employee_info['hire_date'];
            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
            }
        }
        else
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(first_login_time),"%Y-%m-%d")';
            $time2_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d")';
            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y-m',$time_end).'-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y-m',$time_end).'-01 00:00:00');
            }
            else
            {
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y-m').'-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y-m').'-01 00:00:00');
            }

        }

        $time_map[] = array('egt',$time_start);
        if($time_end) $time_map[] = array('elt',$time_end);

        if($employee_info['departure_channel'] > 0)
        {
            $channel = $employee_info['departure_channel'];
        }
        else
        {
            $channel = $employee_info['channel'];
        }

        $new_registers = M('player')
            ->where(array('first_login_time'=>$time_map,'channel'=>$channel))
            ->group('time')
            ->cache(true)
            ->getfield($time_field.' as time,count(*)');


        $times = M('inpour')
            ->where(array('status'=>1,'cid'=>C('MAIN_CHANNEL'),'create_time'=>$time_map))
            ->group('time')
            ->cache(true)
            ->getfield($time2_field.' as time',true);

        $times = array_keys($times);
        $result = array();

        foreach($times as $v)
        {
            $result[$v] = $new_registers[$v]['count(*)'];
        }

        return $result;

    }

    private function _evaluation_static($id,$type)
    {
        $employee_info = M('tg_employees')->field('hire_date,departure_time')->where(array('id'=>$id))->find();


        if(!$employee_info)
        {
            $this->ajaxReturn(null,'该员工不存在',0);
        }


        if($type == 2)
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(period),"%Y-%m-%d"),DATE_FORMAT(FROM_UNIXTIME(period),"%Y-%u")';
            $time1_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d"),DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%u")';

            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y',$time_end).'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y',$time_end).'-01-01 00:00:00');
            }
            else
            {
                $time_start = ($employee_info['hire_date'] > strtotime(date('Y').'-01-01 00:00:00'))?$employee_info['hire_date']:strtotime(date('Y').'-01-01 00:00:00');
            }

        }
        else
        {
            $time_field = 'DATE_FORMAT(FROM_UNIXTIME(period),"%Y-%m")';
            $time1_field = 'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m")';
            $time_start = $employee_info['hire_date'];
            if($employee_info['departure_time'] > 0)
            {
                $time_end = $employee_info['departure_time'];
            }
        }

        $time_map[] = array('egt',$time_start);
        if($time_end) $time_map[] = array('elt',$time_end);

        $evaluations = M('tg_evaluation')->where(array('tg_employee_id'=>$id,'period'=>$time_map))
            ->group('time')
            ->cache(true)
            ->getfield($time_field.' as time,avg(score)',true);


        $times = M('inpour')
            ->where(array('status'=>1,'cid'=>C('MAIN_CHANNEL'),'create_time'=>$time_map))
            ->group('time')
            ->cache(true)
            ->getfield($time1_field.' as time',true);

        $times = array_keys($times);
        $result = array();

        foreach($times as $v)
        {
            $result[$v] = (Int)$evaluations[$v]['avg(score)'];
        }

        return $result;
    }

    public function evaluation_info()
    {
        $id = I('id');
        $time = I('time');

        if(empty($id) || empty($time))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $time = strtotime($time) - ((date('w',strtotime($time)) == 0 ? 7 : date('w',strtotime($time))) - 1) * 24 * 3600;

        $info = M('tg_evaluation')->field('score,deduct_marks,evaluation,deduct_reason,period,create_time')->where(array('tg_employee_id'=>$id,'period'=>$time))->find();


        if($info['period'])$info['period'] = date('Y-m-d',$info['period']).'至'.date('Y-m-d',strtotime('+6 days',$info['period']));


        $this->ajaxReturn($info);
    }

    public function evaluation_list()
    {
        $id = I('id');

        if(empty($id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        //获取最近三个月的评价
        $list = M('tg_evaluation')
            ->field('score,deduct_marks,evaluation,deduct_reason,period,create_time')
            ->where(array('tg_employee_id'=>$id,'period'=>array('egt',strtotime('-3 months'))))
            ->order('period desc')
            ->select();

        foreach($list as $k=>$v)
        {
            $list[$k]['period'] = date('Y-m-d',$v['period']).'至'.date('Y-m-d',strtotime('+6 days',$v['period']));
        }

        $this->ajaxReturn($list);
    }

    public function edit_evaluation()
    {
        $id = I('id');
        $time = I('time');
        $score = I('score');
        $deduct_marks = I('deduct_marks');
        $evaluation = I('evaluation');
        $deduct_reason = I('deduct_reason');

        if(empty($id) || empty($time))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        if($score) $data['score'] = $score;
        if($deduct_marks) $data['deduct_marks'] = $deduct_marks;
        if($evaluation) $data['evaluation'] = $evaluation;
        if($deduct_reason) $data['deduct_reason'] = $deduct_reason;


        if(!empty($data))
        {

            $time = strtotime($time) - ((date('w',strtotime($time)) == 0 ? 7 : date('w',strtotime($time))) - 1) * 24 * 3600;
            //查询是否用评价记录
            $evaluation_id =  M('tg_evaluation')->where(array('tg_employee_id'=>$id,'period'=>$time))->getfield('id');


            if($evaluation_id)
            {
                $res = M('tg_evaluation')->where(array('id'=>$evaluation_id))->save($data);
            }
            else
            {
                $data['admin_id'] = session('ADMIN_ID');
                $data['tg_employee_id'] = $id;
                $data['period'] = $time;
                $data['create_time'] = time();

                $res = M('tg_evaluation')->add($data);
            }

            if($res)
            {
                $this->ajaxReturn(null,'操作成功');
            }
            else
            {
                $this->ajaxReturn(null,'操作失败',0);
            }
        }
        else
        {
            $this->ajaxReturn(null,'操作成功');
        }


    }

    /**
     * 同步数据
     */
    public function sync_data()
    {
        set_time_limit(0);

        $channel_legion = M('channel_legion')->field('id,channels')->select();
        $tg_employees_model = M('tg_employees');

        $pay_by_day_model = M('pay_by_day');
        $tg_info_model = M('tg_info');
        $inpour_model = M('inpour');

        $player_login_logs_model = M('player_login_logs'.date('Ym',strtotime('-1 month')));


        $parent_channels = '';
        foreach($channel_legion as $v)
        {
            $parent_channels.=$v['channels'].',';
        }
        $parent_channels = trim($parent_channels,',');

        $child_channels = M('channel')->where(array('parent'=>array('in',$parent_channels)))->group('parent')->getfield('parent,GROUP_CONCAT(id)',true);


        $last_month = date('Y-m',strtotime('-1 month'));


        $last_month_timstamp = strtotime($last_month);

        $month_timestamp = strtotime(date('Y-m').'-01 00:00:00');

        foreach($channel_legion as $v)
        {
            $channels = explode(',',$v['channels']);
            foreach($channels as $channel)
            {
                $data = array();
                //上个月离职人数
                $data['turnover'] = $tg_employees_model
                    ->where(array('parent_channel'=>$channel,'departure_time'=>array(array('egt',$last_month_timstamp),array('lt',$month_timestamp))))
                    ->count();
		    
	

                //上个月sdk充值、注册、充值人数、活跃人数
                $new_regster = $pay_by_day_model
                    ->where(array('cid'=>array('in',$child_channels[$channel].','.$channel),'time'=>array('like',$last_month.'%')))
                    ->getfield('sum(new_user) new_user');

                $new_pay = $inpour_model
                    ->where(array('cid'=>array('in',$child_channels[$channel].','.$channel),'create_time'=>array(array('egt',$last_month_timstamp),array('lt',$month_timestamp)),'status'=>1))
                    ->field('sum(getmoney) as money,count(distinct(username)) as pay_number')
                    ->find();

                $new_active = $player_login_logs_model
                    ->where(array('channel'=>array('in',$child_channels[$channel].','.$channel)))
                    ->getfield('count(distinct(uid))');


                $data['money'] = $new_pay['money'];

                $data['registers'] = $new_regster;

                $data['pay_numbers'] = $new_pay['pay_number'];

                $data['active_user'] = $new_active;

                if($id = $tg_info_model->where(array('parent_channel'=>$channel,'time'=>$last_month))->getfield('id'))
                {
                    $tg_info_model->where(array('id'=>$id))->save($data);
                }
                else
                {
                    $data['channel_legion'] = $v['id'];
                    $data['parent_channel'] = $channel;
                    $data['time'] = $last_month;
                    $data['create_time'] = time();

                    $tg_info_model->add($data);
                }


            }

        }
        exit('success');

    }

    public function edit()
    {
        $post_data = I('post.');
        if(!$post_data['id'])
        {
            $this->ajaxReturn(null,'员工ID不能为空',0);
        }

        $info = M('tg_employees')->where(array('id'=>$post_data['id']))->find();

        if(!$info)
        {
            $this->ajaxReturn(null,'该员工不存在',0);
        }

        if($info['departure_time'] > 0)
        {
            $this->ajaxReturn(null,'该员工已离职',0);
        }


        if(!empty($post_data['mobile']) && !preg_match("/^1\d{10}$/", $post_data['mobile']))
        {
            $this->ajaxReturn(null,'手机格式不正确',0);
        }

        if(!empty($post_data['id_card']) && !validateIDCard($post_data['id_card']))
        {
            $this->ajaxReturn(null,'身份证号不合法',0);
        }

        if(!empty($post_data['qq']) && !preg_match("/^[1-9]\d{4,10}$/i",$post_data['qq']))
        {
            $this->ajaxReturn(null,'qq号格式不正确',0);
        }

        if(!empty($post_data['weixin']) && strlen($post_data['weixin'])>20)
        {
            $this->ajaxReturn(null,'微信格式不正确',0);
        }

        if(!empty($post_data['email']) && !preg_match('/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/',$post_data['email']))
        {
            $this->ajaxReturn(null,'email格式不正确',0);
        }

        if(strlen($post_data['birth'])>0) $post_data['birth'] = strtotime($post_data['birth']);

        if(strlen($post_data['graduate_time'])>0) $post_data['graduate_time'] = strtotime($post_data['graduate_time']);
        if(strlen($post_data['regular_time'])>0) $post_data['regular_time'] = strtotime($post_data['regular_time']);
        if(strlen($post_data['contract_expiration'])>0) $post_data['contract_expiration'] = strtotime($post_data['contract_expiration']);
        if(strlen($post_data['hire_date'])>0) $post_data['hire_date'] = strtotime($post_data['hire_date']);

        if($_FILES['img']['name'])
        {
            $upload = new \Think\Upload();

            $upload->autoSub  = false;
            $upload->rootPath = "www.sy217.com/assets/";
            $upload->savePath = "tg_employees/";


            $fileName=$_FILES["img"]["name"];
            $fileName=explode('.',$fileName);
            $serverFileName=$fileName[0]."_".time();

            $upload->saveName=$serverFileName;//设置在服务器保存的文件名

            $info = $upload->uploadOne($_FILES['img']);

            if(!$info)
            {// 上传错误提示错误信息
                $this->ajaxReturn(null,$upload->getError(),0);
            }
            else
            {// 上传成功
                $file_name = trim($info['fullpath'],'.');
                $post_data['img'] = str_replace('www.sy217.com','',$file_name);
            }

        }


        if(M('tg_employees')->save($post_data)!==false)
        {
            //修改人事评价
            if($post_data['time'])
            {
                $data = array();
                if($post_data['score']) $data['score'] = $post_data['score'];
                if($post_data['deduct_marks']) $data['deduct_marks'] = $post_data['deduct_marks'];
                if($post_data['evaluation']) $data['evaluation'] = $post_data['evaluation'];
                if($post_data['deduct_reason']) $data['deduct_reason'] = $post_data['deduct_reason'];
                if(!empty($data))
                {
                    $time = strtotime($post_data['time']) - ((date('w',strtotime($post_data['time'])) == 0 ? 7 : date('w',strtotime($post_data['time']))) - 1) * 24 * 3600;
                    //查询是否用评价记录
//                    $evaluation_id =  M('tg_evaluation')->where(array('tg_employee_id'=>$post_data['id'],'period'=>$time))->getfield('id');
//
//                    if($evaluation_id)
//                    {
//                        $res = M('tg_evaluation')->where(array('id'=>$evaluation_id))->save($data);
//                    }
//                    else
//                    {
//                        $data['admin_id'] = session('ADMIN_ID');
//                        $data['tg_employee_id'] = $post_data['id'];
//                        $data['period'] = $time;
//                        $data['create_time'] = time();
//
//                        $res = M('tg_evaluation')->add($data);
//                    }
                    $data['admin_id'] = session('ADMIN_ID');
                    $data['tg_employee_id'] = $post_data['id'];
                    $data['period'] = $time;
                    $data['create_time'] = time();
                    $res = M('tg_evaluation')->add($data);
                    if($res!==false)
                    {
                        $this->ajaxReturn(null,'编辑成功');
                    }
                    else
                    {
                        $this->ajaxReturn(null,'编辑失败',0);
                    }
                }
            }
            $this->ajaxReturn(null,'编辑成功');

        }
        else
        {
            $this->ajaxReturn(null,'编辑失败',0);
        }




    }

    public function turnover()
    {
        $id = I('id');
        $departure_time = I('departure_time');
        $departure_reason = I('departure_reason');
        $transfer_channel = I('transfer_channel');

        if(empty($id) || empty($departure_time))
        {
            $this->ajaxReturn(null,'员工ID或者离职时间不能为空',0);
        }

        $emloyee_info = M('tg_employees')->where(array('id'=>$id))->find();

        if(!$emloyee_info)
        {
            $this->ajaxReturn(null,'员工不存在',0);
        }

        if($emloyee_info['departure_time'] > 0)
        {
            $this->ajaxReturn(null,'员工已办理离职，请不要重复提交',0);
        }

        $save['departure_time'] = strtotime($departure_time);
        $save['departure_reason'] = $departure_reason;
        $save['departure_channel'] = $emloyee_info['channel'];
        $save['channel'] = 0;

        if(M('tg_employees')->where(array('id'=>$id))->save($save)!==false)
        {
            //离职成功 转移渠道数据以及拉黑渠道管理员账号
            if($transfer_channel > 0)
            {
                if($channel_info = M('channel')->where(array('id'=>$transfer_channel))->find())
                {
                               M('player')->where(array('channel'=>$emloyee_info['channel']))->save(array('channel'=>$transfer_channel));
                               M('syo_member',null,C('DB_OLDSDK_CONFIG'))->where(array('channel'=>$emloyee_info['channel']))->save(array('channel'=>$transfer_channel));
                    M('transfer_channel')->add(array('new_channel'=>$transfer_channel,'original_channel'=>$emloyee_info['channel'],'admin_id'=>session('ADMIN_ID'),'create_time'=>time()));
                }
            }

            $admin_id = M('channel')->where(array('id'=>$emloyee_info['channel']))->getfield('admin_id');

            if($admin_id) M('users')->where(array("id"=>$admin_id,"user_type"=>1))->setField('user_status','0');

            $this->ajaxReturn(null,'操作成功');
        }
        else
        {
            $this->ajaxReturn(null,'操作失败',0);
        }

    }

    public function transfer_channel_list()
    {
        $id = I('id');
        if(empty($id))
        {
            $this->ajaxReturn(null,'员工ID不能为空',0);
        }
        $channel_list = M('tg_employees')->where(array('id'=>array('neq',$id),'channel'=>array('neq',0)))->getfield('channel',true);

        $list = array();
        if(!empty($channel_list)) $list= M('channel')->where(array('id'=>array('in',implode(',',$channel_list))))->field('id,name')->select();

        $this->ajaxReturn($list);

    }


}
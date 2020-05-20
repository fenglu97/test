<?php
/**
 * 联运统计
 * @author qing.li
 * @date 2018-04-02
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class RhStatisticsController extends AdminbaseController
{

    private $uchannel_model;
    private $uchannelmaster_model;
    private $uorder_model;
    private $uuserlog_model;
    private $tchannelsummary_model;

    public function _initialize()
    {
        parent::_initialize();

        $this->uchannel_model = M('uchannel',null,C('RH_DB_CONFIG'));
        $this->uchannelmaster_model = M('uchannelmaster',null,C('RH_DB_CONFIG'));
        $this->uorder_model = M('uorder',null,C('RH_DB_CONFIG'));
        $this->uuserlog_model = M('uuserlog'.date('Ymd'),null,C('RH_DB_CONFIG'));
        $this->tchannelsummary_model = M('tchannelsummary',null,C('RH_DB_CONFIG'));
        $role_id = M('role_user')->where(array('user_id'=>session('ADMIN_ID')))->Getfield('role_id');
        $this->display_channel = ($role_id == 5)?0:1;
        $this->role_id = $role_id;

    }

    /**
     *联运今日渠道排行
     */
    public function today_pay_by_channel()
    {
        $map = $this->_do_params(I('appID'),I('channelID'),session('game_role'),1);

        $map1['state'] = array('in','2,3');
        $map1['completeTime'] = array('like',date('Y-m-d').'%');
        $map1 = array_merge($map,$map1);



        $this->_make_channel_list($map,$map1,1);
    }

    /**
     *联运今日游戏排行
     */
    public function today_pay_by_game()
    {
        $map = $this->_do_params(I('appID'),I('channelID'),session('game_role'),2);

        $map1['state'] = array('in','2,3');
        $map1['completeTime'] = array('like',date('Y-m-d').'%');


        $map1 = array_merge($map,$map1);


        $this->_make_game_list($map,$map1,1);

    }

    /**
     * 联运充值统计渠道排行
     */
    public function pay_by_channel()
    {
        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = I('start_time')?I('start_time'):date('Y-m-d',strtotime('-1 week'));
        $end_time = I('end_time')?I('end_time'):$max;

        if(strtotime($end_time) - strtotime($start_time) >= 180*3600*24)
        {
            $this->error('不能查询超过180天以上');
        }


        $map = $this->_do_params(I('appID'),null,session('game_role'),1,I('masterID'));
        $map['currTime'] = array(
            array('lt',date('Y-m-d',strtotime($end_time)+3600*24)),
            array('egt',$start_time));

        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('max',$max);

        $this->_make_channel_list(null,$map,2);
    }

    /**
     * 联运充值统计游戏排行
     */
    public function pay_by_game()
    {
        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = I('start_time')?I('start_time'):date('Y-m-d',strtotime('-1 week'));
        $end_time = I('end_time')?I('end_time'):$max;

        if(strtotime($end_time) - strtotime($start_time) >= 180*3600*24)
        {
            $this->error('不能查询超过180天以上');
        }

        $map = $this->_do_params(I('appID'),null,session('game_role'),2,I('masterID'));
        $map['currTime'] = array(
            array('lt',date('Y-m-d',strtotime($end_time)+3600*24)),
            array('egt',$start_time));


        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('max',$max);

        $this->_make_game_list(null,$map,2);
    }

    public function pay_by_day()
    {
        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = I('start_time')?I('start_time'):date('Y-m-d',strtotime('-1 week'));
        $end_time = I('end_time')?I('end_time'):$max;

        if(strtotime($end_time) - strtotime($start_time) >= 180*3600*24)
        {
            $this->error('不能查询超过180天以上');
        }

        $map = $this->_do_params(I('appID'),null,session('game_role'),3,I('masterID'));

        $count =(strtotime($end_time)-strtotime($start_time))/(3600*24)+1;
        $page = $this->page($count, 20);

        $p = I('get.p')?I('get.p'):1;

        $datearr = array ();

        if(I('action') == 1)
        {
            $end_time_conf = strtotime($end_time.' 23:59:59');
            $start_time_conf = strtotime($start_time.' 00:00:00');
            if(($end_time_conf-$start_time_conf) > 31*3600*24)
            {
                $this->error('最大能导出31天的数据');
            }
        }
        else
        {
            $end_time_conf = strtotime($end_time.' 23:59:59')-($p-1)*24*3600*20;
            $start_time_conf =$end_time_conf-24*3600*20;
        }

        if($start_time_conf <= strtotime($start_time.' 00:00:00'))
        {
            $start_time_conf = strtotime($start_time.' 00:00:00');
            $map['currTime'] = array(array('egt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
        }
        else
        {
            $map['currTime'] = array(array('gt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
        }

        while ( $start_time_conf < $end_time_conf )
        {
            $datearr [] = date ( 'Y-m-d', $end_time_conf);
            $end_time_conf = $end_time_conf - 3600 * 24;
        }


        $pay_info = $this->tchannelsummary_model
            ->where($map)
            ->group('currTime')
            ->order('currTime desc')
            ->cache(true)
            ->getfield('currTime,sum(money) money,sum(newPayUserNum) newPayUserNum, sum(userNum) userNum,sum(payUserNum) as payUserNum,sum(newPayMoney) as newPayMoney',true);

        $map['currTime'] = array(array('egt',$start_time),array('elt',$end_time));

        $heji = $this->tchannelsummary_model
            ->field('sum(money) money,sum(newPayUserNum) newPayUserNum, sum(userNum) userNum,sum(payUserNum) as payUserNum,sum(newPayMoney) as newPayMoney')
            ->where($map)
            ->cache(true)
            ->find();

        $heji['money'] = $heji['money']/100;
        $heji['newPayMoney'] = $heji['newPayMoney']/100;

        $list =array();
        foreach($datearr as $v)
        {
            $item = array();
            $item[] = $v;
            $v = $v.' 00:00:00';

            $item[] = isset($pay_info[$v]['payUserNum'])?$pay_info[$v]['payUserNum']:0;
            $item[] = isset($pay_info[$v]['money'])?$pay_info[$v]['money']/100:0;
            $item[] = isset($pay_info[$v]['userNum'])?$pay_info[$v]['userNum']:0;
            $item[] = isset($pay_info[$v]['newPayUserNum'])?$pay_info[$v]['newPayUserNum']:0;
            $item[] = isset($pay_info[$v]['newPayMoney'])?$pay_info[$v]['newPayMoney']/100:0;


            $item[] = round($item[2] / $item[1], 2);
            $item[] = round($item[5] / $item[3], 2);

            $list[] = $item;

        }


        if(I('action') == 1)
        {

            $game_name = M('game')->where(array('id'=>I('appID')))->getfield('game_name');

            $channel_name = $this->uchannelmaster_model->where(array('masterID'=>I('masterID')))->getfield('masterName');

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis').'订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('日期','游戏名称','渠道名称','充值人数','总充值金额','新增用户','新增付费用户数','新增用户充值金额','付费Arpu','新增Arpu');

            $cellNum = count($expCellName);
            $heji_item = array('合计',$heji['payUserNum'],$heji['money'],$heji['userNum'],$heji['newPayUserNum'],$heji['newPayMoney'],round($heji['money']/$heji['payUserNum'],2),round($heji['newPayMoney']/$heji['userNum'],2));
            array_unshift($list,$heji_item);
            $dataNum = count($list);


            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for($i=0;$i<$cellNum;$i++){
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            for($i=0;$i<$dataNum;$i++){
                for($j=0;$j<$cellNum;$j++){
                    if($j ==0)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $list[$i][$j]);
                    }
                    elseif($j ==1)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $game_name?$game_name:'--');
                    }
                    elseif($j == 2)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $channel_name?$channel_name:'--');
                    }
                    else
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $list[$i][$j-2]);
                    }

                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
        else
        {
            $this->assign('p',$p);
            $this->assign('page',$page->show('Admin'));
            $this->assign('start_time',$start_time);
            $this->assign('end_time',$end_time);
            $this->assign('max',$max);
            //获取U8所有渠道商
            if($this->role_id == 24)
            {
                $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('masterID,masterName',true);
            }
            else if($this->role_id == 31)
            {
                $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_6533_CHANNEL'))))->getfield('masterID,masterName',true);
            }
            else
            {
                $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('not in',C('RH_CHANNEL_ID'))))->getfield('masterID,masterName',true);
            }

            $this->assign('u8_channel',$u8_channel);
            $this->assign('masterID',I('masterID'));
            $this->assign('game_list',get_game_list(I('appID'),1,'all'));
            $this->assign('list',$list);
            $this->assign('heji',$heji);
            $this->display();
        }


    }

    /**
     * 处理入参
     * @param $appid
     * @param $channelid
     * @param $game_role
     * @param $type 1为渠道排行 2为游戏排行 3天统计
     * @param $masterid
     * @return mixed
     */
    private function  _do_params($appid,$channelid,$game_role,$type,$masterid='')
    {
        $map = array();
        if(!empty($appid))
        {
            $map['appID'] = $appid;
            if($type == 1 || $type ==3)
            {
                $game_name = M('game')->where(array('id'=>$appid))->getfield('game_name');

                $this->assign('game_name',$game_name?$game_name:'--');
            }
        }
        else
        {
            if($game_role !='all')
            {
                $map['appID'] = $game_role;
            }
        }
        if(!empty($channelid))
        {
            if($type == 2)
            {
                $channel_name = $this->uchannelmaster_model->where(array('masterID'=>$channelid))->getfield('masterName');

                $this->assign('channel_name',$channel_name?$channel_name:'--');
            }

            $map['channelID'] = $channelid;
        }
        else
        {
            if(!($channelid === null))
            {
                if($this->role_id == 24)
                {
                    $map['channelID'] = array('in',C('RH_SWITCHPAY_SHOWCHANNEL'));
                }
                elseif($this->role_id == 31)
                {
                    $map['channelID'] = array('in',C('RH_6533_CHANNEL'));
                }
                else
                {
                    $map['channelID'] = array('not in',C('RH_CHANNEL_ID'));
                }

            }
        }
        if(!empty($masterid))
        {
            $channel_name = $this->uchannelmaster_model->where(array('masterID'=>$masterid))->getfield('masterName');

            $this->assign('channel_name',$channel_name?$channel_name:'--');
            $map['masterID'] = $masterid;
        }
        else
        {
            if($channelid === null)
            {
                if($this->role_id == 24)
                {
                    $map['masterID'] = array('in',C('RH_SWITCHPAY_SHOWCHANNEL'));
                }
                elseif($this->role_id == 31)
                {
                    $map['masterID'] = array('in',C('RH_6533_CHANNEL'));
                }
                else
                {
                    $map['masterID'] = array('not in',C('RH_CHANNEL_ID'));
                }

            }
        }



        if($map['appID'])
        {
            $map['appID'] = $this->uchannel_model
                ->where(array('masterID'=>array('in',C('RH_185CHANNEL_ID')),'cpAppID'=>array('in',$map['appID'])))
                ->getfield('appID',true);
            $map['appID'] = array('in',implode(',',$map['appID']));
        }

        if($map['channelID'])
        {
            if($map['appID'])
            {
                $map['channelID'] = $this->uchannel_model
                    ->where(array('masterID'=>$map['channelID'],'appID'=>$map['appID']))
                    ->getfield('channelID',true);

            }
            else
            {
                $map['channelID'] = $this->uchannel_model
                    ->where(array('masterID'=>$map['channelID']))
                    ->getfield('channelID',true);

            }


            $map['channelID'] = array('in',implode(',',$map['channelID']));

        }
        return $map;
    }

    /**
     * 组装渠道排行列表
     * @param $map
     * @param $map1
     * @param $type 1 日排行 2 区间排行
     */
    private function _make_channel_list($map,$map1,$type)
    {
        if($type == 1)
        {

            //获取当天充值人数 充值次数 充值总金额
            $pay_info =  $this->uorder_model
                ->where($map1)
                ->group('channelID')
                ->cache(true)
                ->getfield('channelID,count(orderID) as pay_counts,count(distinct(userID)) as uid_counts,sum(realMoney) as money',true);

            foreach($map1 as $k=>$v)
            {
                $map_newpay['t1.'.$k] = $v;
            }
            $map_newpay['t2.createTime'] = array('like',date('Y-m-d').'%');

            //获取新增用户充值
            $newpay_info = $this->uorder_model
                ->alias('t1')
                ->join('uuser t2 on t1.userID = t2.userID')
                ->where($map_newpay)
                ->group('t1.channelID')
                ->cache(true)
                ->getfield('t1.channelID,sum(realMoney) as money',true);


            //获取用户新增
            $user_info = $this->uuserlog_model
                ->where(array_merge($map,array('opType'=>6)))
                ->group('channelID')
                ->cache(true)
                ->getfield('channelID,count(distinct(userID)) as count',true);


            //获取活跃用户
            $active_info = $this->uuserlog_model
                ->where(array_merge($map,array('opType'=>array('in','6,7'))))
                ->group('channelID')
                ->cache(true)
                ->getfield('channelID,count(distinct(userID)) as count',true);

        }
        else
        {
            $pay_info = $this->tchannelsummary_model
                ->where($map1)
                ->group('masterID')
                ->cache(true)
                ->getfield('masterID,sum(money) money,sum(newPayMoney) newPayMoney,sum(newPayUserNum) newPayUserNum, sum(userNum) userNum,sum(payUserNum) as payUserNum',true);
        }




        if(I('channelID') || I('masterID'))
        {
            $channel_map['masterID'] = I('channelID')?I('channelID'):I('masterID');
        }
        else
        {
            if($this->role_id == 24)
            {
                $channel_map['masterID'] = array('in',C('RH_SWITCHPAY_SHOWCHANNEL'));
            }
            elseif($this->role_id == 31)
            {
                $channel_map['masterID'] = array('in',C('RH_6533_CHANNEL'));
            }
            else
            {
                $channel_map['masterID'] = array('not in',C('RH_CHANNEL_ID'));
            }

        }
        $umaster_channels = $this->uchannel_model->where($channel_map)->group('masterID')->getfield('masterID,group_concat(channelID)',true);

        $list = array();
        $heji = array();
        foreach($umaster_channels as $k=>$v) {
            $v = explode(',', $v);
            $item = array();
            $item['channelID'] = $k;

            if($type == 1)
            {
                foreach ($v as $value)
                {
                    $item['new_user'] += isset($user_info[$value]) ? $user_info[$value] : 0;
                    $item['active_user'] += isset($active_info[$value]) ? $active_info[$value] : 0;
                    $item['pay_counts'] += isset($pay_info[$value]['pay_counts']) ? $pay_info[$value]['pay_counts'] : 0;
                    $item['pay_number'] += isset($pay_info[$value]['uid_counts']) ? $pay_info[$value]['uid_counts'] : 0;
                    $item['pay_amount'] += isset($pay_info[$value]['money']) ? $pay_info[$value]['money'] / 100 : 0;
                    $item['newPayMoney'] += isset($newpay_info[$value]) ?  $newpay_info[$value]/100:0;
                }

                if (!($item['active_user'] == 0 && $item['new_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0) || count($umaster_channels) == 1)
                {
                    $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
                    $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);
                    $item['newuser_arpu'] = round($item['newPayMoney']/$item['new_user'] ,2);
                    $list[] = $item;
                }

                $heji['active_user'] += $item['active_user'];
                $heji['new_user'] += $item['new_user'];
                $heji['pay_counts'] += $item['pay_counts'];
                $heji['pay_number'] += $item['pay_number'];
                $heji['pay_amount'] += $item['pay_amount'];
                $heji['newPayMoney'] += $item['newPayMoney'];
            }
            else
            {
                $item['pay_amount'] = isset($pay_info[$k]['money'])?$pay_info[$k]['money']/100:0;
                $item['newPayUserNum'] = isset($pay_info[$k]['newPayUserNum'])?$pay_info[$k]['newPayUserNum']:0;
                $item['userNum'] = isset($pay_info[$k]['userNum'])?$pay_info[$k]['userNum']:0;
                $item['payUserNum'] = isset($pay_info[$k]['payUserNum'])?$pay_info[$k]['payUserNum']:0;
                $item['newPayMoney'] = isset($pay_info[$k]['newPayMoney'])?$pay_info[$k]['newPayMoney']/100:0;


                if (!($item['pay_amount'] == 0 && $item['newPayUserNum'] == 0 && $item['userNum'] == 0 && $item['payUserNum'] == 0) || count($umaster_channels) == 1)
                {
                    $item['pay_arpu'] = round($item['pay_amount'] / $item['payUserNum'], 2);
                    $item['newuser_arpu'] = round($item['newPayMoney'] / $item['userNum'], 2);
                    $list[] = $item;
                }

                $heji['pay_amount'] +=$item['pay_amount'];
                $heji['newPayUserNum'] +=$item['newPayUserNum'];
                $heji['userNum'] +=$item['userNum'];
                $heji['payUserNum'] +=$item['payUserNum'];
                $heji['newPayMoney'] +=$item['newPayMoney'];
            }


        }

        foreach($list as $k=>$v)
        {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        //获取U8所有渠道商
        if($this->role_id == 24)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('masterID,masterName',true);
        }
        elseif($this->role_id == 31)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_6533_CHANNEL'))))->getfield('masterID,masterName',true);
        }
        else
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('not in',C('RH_CHANNEL_ID'))))->getfield('masterID,masterName',true);
        }

        $this->assign('list',$list);
        $this->assign('heji',$heji);
        $this->assign('u8_channel',$u8_channel);
        $this->assign('channelid',I('channelID'));
        $this->assign('masterID',I('masterID'));
        $this->assign('game_list',get_game_list(I('appID'),1,'all'));
        $this->display();
    }

    /**
     * 组装游戏排行列表
     * @param $map
     * @param $map1
     * @param $type 1 日排行 2 区间排行
     */
    private function _make_game_list($map,$map1,$type)
    {
        if($type == 1)
        {
            //获取当天充值人数 充值次数 充值总金额
            $pay_info =  $this->uorder_model
                ->where($map1)
                ->group('appID')
                ->cache(true)
                ->getfield('appID,count(orderID) as pay_counts,count(distinct(userID)) as uid_counts,sum(realMoney) as money',true);

            foreach($map1 as $k=>$v)
            {
                $map_newpay['t1.'.$k] = $v;
            }
            $map_newpay['t2.createTime'] = array('like',date('Y-m-d').'%');

            //获取新增用户充值
            $newpay_info = $this->uorder_model
                ->alias('t1')
                ->join('uuser t2 on t1.userID = t2.userID')
                ->where($map_newpay)
                ->group('t1.appID')
                ->cache(true)
                ->getfield('t1.appID,sum(realMoney) as money',true);



            //获取用户新增
            $user_info =  $this->uuserlog_model
                ->where(array_merge($map,array('opType'=>6)))
                ->group('appID')
                ->cache(true)
                ->getfield('appID,count(distinct(userID)) as count',true);


            //获取活跃用户
            $active_info = $this->uuserlog_model
                ->where(array_merge($map,array('opType'=>array('in','6,7'))))
                ->group('appID')
                ->cache(true)
                ->getfield('appID,count(distinct(userID)) as count',true);


        }
        else
        {
            $pay_info = $this->tchannelsummary_model
                ->where($map1)
                ->group('appID')
                ->cache(true)
                ->getfield('appID,sum(money) money,sum(newPayMoney) newPayMoney,sum(newPayUserNum) newPayUserNum, sum(userNum) userNum,sum(payUserNum) as payUserNum',true);
        }


        if($map['appID'])
        {
            $game_map['appID'] = $map['appID'];
        }

        $games = M('ugame',null,C('RH_DB_CONFIG'))->where($game_map)->field('appID,name')->select();


        $list = array();
        $heji = array();
        foreach($games as $game)
        {
            $item = array();
            $item['game_name'] = $game['name'];
            if($type == 1)
            {
                $item['active_user'] = isset($active_info[$game['appID']])?$active_info[$game['appID']]:0;
                $item['new_user'] = isset($user_info[$game['appID']])?$user_info[$game['appID']]:0;
                $item['pay_counts'] = isset($pay_info[$game['appID']]['pay_counts'])?$pay_info[$game['appID']]['pay_counts']:0;
                $item['pay_number'] = isset($pay_info[$game['appID']]['uid_counts'])?$pay_info[$game['appID']]['uid_counts']:0;
                $item['pay_amount'] = isset($pay_info[$game['appID']]['money'])?$pay_info[$game['appID']]['money']/100:0;
                $item['newPayMoney'] = isset($newpay_info[$game['appID']])?$newpay_info[$game['appID']]/100:0;

                if(!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0) || count($games)==1)
                {
                    $item['active_arpu'] = round($item['pay_amount']/$item['active_user'],2);
                    $item['pay_arpu'] = round($item['pay_amount']/$item['pay_number'],2);
                    $item['newuser_arpu'] = round($item['newPayMoney']/$item['new_user'],2);
                    $list[] = $item;
                }


                $heji['new_user'] += $item['new_user'];
                $heji['active_user'] += $item['active_user'];
                $heji['pay_counts'] += $item['pay_counts'];
                $heji['pay_number'] += $item['pay_number'];
                $heji['pay_amount'] += $item['pay_amount'];
                $heji['newPayMoney'] += $item['newPayMoney'];
            }
            else
            {
                $item['pay_amount'] = isset($pay_info[$game['appID']]['money'])?$pay_info[$game['appID']]['money']/100:0;
                $item['newPayUserNum'] = isset($pay_info[$game['appID']]['newPayUserNum'])?$pay_info[$game['appID']]['newPayUserNum']:0;
                $item['userNum'] = isset($pay_info[$game['appID']]['userNum'])?$pay_info[$game['appID']]['userNum']:0;
                $item['payUserNum'] = isset($pay_info[$game['appID']]['payUserNum'])?$pay_info[$game['appID']]['payUserNum']:0;
                $item['newPayMoney'] = isset($pay_info[$game['appID']]['newPayMoney'])?$pay_info[$game['appID']]['newPayMoney']/100:0;

                if(!($item['pay_amount'] == 0 && $item['newPayUserNum'] == 0 && $item['userNum'] == 0 && $item['payUserNum'] == 0) || count($games)==1)
                {
                    $item['pay_arpu'] = round($item['pay_amount'] / $item['payUserNum'], 2);
                    $item['newuser_arpu'] = round($item['newPayMoney'] / $item['userNum'], 2);
                    $list[] = $item;
                }

                $heji['pay_amount'] +=$item['pay_amount'];
                $heji['newPayUserNum'] +=$item['newPayUserNum'];
                $heji['userNum'] +=$item['userNum'];
                $heji['payUserNum'] +=$item['payUserNum'];
                $heji['newPayMoney'] +=$item['newPayMoney'];

            }

        }

        foreach($list as $k=>$v)
        {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        //获取U8所有渠道商
        if($this->role_id == 24)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('masterID,masterName',true);
        }
        elseif($this->role_id == 31)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('in',C('RH_6533_CHANNEL'))))->getfield('masterID,masterName',true);
        }
        else
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID'=>array('not in',C('RH_CHANNEL_ID'))))->getfield('masterID,masterName',true);
        }

        $this->assign('u8_channel',$u8_channel);
        $this->assign('channelid',I('channelID'));
        $this->assign('masterID',I('masterID'));
        $this->assign('game_list',get_game_list(I('appID'),1,'all'));
        $this->assign('list',$list);
        $this->assign('heji',$heji);
        $this->display();
    }

    public function financial_counting()
    {
        $action = I('action') ? I('action') : 0;
        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);
        $start_time = I('start_time') ? I('start_time') : date('Y-m-d', strtotime('-1 week'));
        $end_time = I('end_time') ? I('end_time') : $max;
        $masterID = I('masterID');
        $appid = I('appid');
        $map = array();

        if ($start_time) $map['currTime'][] = array('egt', $start_time . ' 00:00:00');
        if ($end_time) $map['currTime'][] = array('elt', $end_time . ' 00:00:00');
        if ($masterID) {
            $map['masterID'] = $masterID;
        } else {
            if($this->role_id == 24)
            {
                $map['masterID'] = array('in', C('RH_SWITCHPAY_SHOWCHANNEL'));
            }
            elseif($this->role_id == 31)
            {
                $map['masterID'] = array('in', C('RH_6533_CHANNEL'));
            }
            else
            {
                $map['masterID'] = array('not in', C('RH_CHANNEL_ID'));
            }

        }

        $game_role = session('game_role');

        if ($appid) {
            $appid = $appid;
        } else {
            if ($game_role != 'all') {
                $appid = $game_role;
            }
        }


        if ($appid) {
            $appid = $this->uchannel_model
                ->where(array('masterID' => array('in', C('RH_185CHANNEL_ID')), 'cpAppID' => array('in', $appid)))
                ->getfield('appID', true);
            $map['appID'] = array('in', implode(',', $appid));
        }
        $heji = $this->tchannelsummary_model->where($map)->getfield('sum(money)');


        $list_all = $this->tchannelsummary_model
            ->where($map)
            ->field('appID,masterID,sum(money) money')
            ->group('masterID,appID')
            ->having('money > 0')
            ->order('masterID asc')
            ->select();

        $page = $this->page(count($list_all), 20);

        $list = array_slice($list_all, $page->firstRow, $page->listRows);

        $game_map = array();
        if ($map['appID']) $game_map['appID'] = $map['appID'];
        $games = M('ugame', null, C('RH_DB_CONFIG'))->where($game_map)->getfield('appID,name', true);

        //获取U8所有渠道商
        if($this->role_id == 24)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID' => array('in', C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('masterID,masterName', true);
        }
        elseif($this->role_id == 31)
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID' => array('in', C('RH_6533_CHANNEL'))))->getfield('masterID,masterName', true);
        }
        else
        {
            $u8_channel = $this->uchannelmaster_model->where(array('masterID' => array('not in', C('RH_CHANNEL_ID'))))->getfield('masterID,masterName', true);
        }



        if ($action == 0)
        {
            $this->assign('max',$max);
            $this->assign('start_time',$start_time);
            $this->assign('end_time',$end_time);
            $this->assign('masterID',$masterID);
            $this->assign('games',$games);
            $this->assign('u8_channel',$u8_channel);
            $this->assign('list',$list);
            $this->assign('heji',$heji);
            $this->assign('page',$page->show('Admin'));
            $this->display();
        }
        else
        {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '渠道对账');//文件名称
            $fileName =  '渠道对账';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('渠道名称', '游戏名称', '充值收入');

            $heji_item = array('合计','',$heji);
            array_unshift($list_all,$heji_item);
            $cellNum = count($expCellName);

            $dataNum = count($list_all);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if($i > 0)
                    {
                        if($j == 0)
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $u8_channel[$list_all[$i]['masterID']]);
                        }
                        elseif($j==1)
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $games[$list_all[$i]['appID']]);
                        }
                        else
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), sprintf("%.2f",$list_all[$i]['money']/100));
                        }
                    }
                    else
                    {
                        if($j == 2)
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), sprintf("%.2f",$list_all[$i][$j]/100));
                        }
                        else
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2),$list_all[$i][$j]);
                        }
                    }
                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }

    }






}
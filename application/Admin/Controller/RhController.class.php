<?php
/**
 * 融合控制器
 * @author qing.li
 * @date 2018-11-21
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class RhController extends AdminbaseController
{
    public function index()
    {
        $appid = I('appid');
        $masterID = I('masterID');
        $map = array();
        $role_id = M('role_user')->where(array('user_id'=>session('ADMIN_ID')))->Getfield('role_id');

        if($role_id == 24)
        {
            $map['t1.masterID'] = array('in','165');
        }
        else
        {
            $map['t1.masterID'] = array('not in',C('RH_SWITCHPAY_CHANNEL'));
        }

        $uchannel_model = M('uchannel',null,C('RH_DB_CONFIG'));
        if($appid)
        {
           $map['t1.appID'] = $uchannel_model->where(array('cpAppID'=>$appid,'masterID'=>array('in',C('RH_185CHANNEL_ID'))))->getfield('appID');
        }
        else
        {
            $game_role = session('game_role');
            if($game_role !='all')
            {
                $appids = $uchannel_model->where(array('cpAppID'=>array('in',$game_role),'masterID'=>array('in',C('RH_185CHANNEL_ID'))))->getfield('appID',true);
                $map['t1.appID'] = array('in',implode(',',$appids));
            }
        }

        if($masterID)
        {
            $map['t1.masterID'] = $masterID;
        }



        $count =  $uchannel_model->alias('t1')->where($map)->count();

        $page = $this->page($count, 20);

        $list = $uchannel_model
            ->field('t1.channelID,t2.masterName,t3.name,t1.openRegisterFlag,t1.openPayFlag,t1.openSwitchPayFlag')
            ->alias('t1')
            ->join('left join uchannelmaster as t2 on t1.masterID = t2.masterID')
            ->join('left join ugame as t3 on t1.appID = t3.appID')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();


        //获取U8所有渠道商
        if($role_id == 24)
        {
            $u8_channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in','165')))->getfield('masterID,masterName',true);
        }
        else
        {
            $u8_channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('not in',C('RH_SWITCHPAY_CHANNEL'))))->getfield('masterID,masterName',true);
        }

        $this->assign('u8_channel',$u8_channel);
        $this->assign('masterID',$masterID);
        $this->assign('page',$page->show('Admin'));
        $this->assign('appid',$appid);
        $this->assign('list',$list);
        $this->display();

    }

    public function edit()
    {
        if(IS_POST)
        {
            $post_data = I('post.');
            $data = array();
            if(is_array($post_data['packageName']))
            {
                foreach($post_data['packageName'] as $k=>$v)
                {
                    if($post_data['packageName'][$k] && $post_data['startTime'][$k] && $post_data['endTime'][$k])
                    {
                        $data[$k]['packageName'] = trim($v);
                        $data[$k]['loopType'] = $post_data['loopType'][$k];
                        $data[$k]['title'] = $post_data['title'][$k]?$post_data['title'][$k]:'';
                        $data[$k]['amount'] = $post_data['amount'][$k]?$post_data['amount'][$k]:0;
                        if($post_data['loopType'][$k] == 0)
                        {
                            $data[$k]['startTime'] = date('Y-m-d H:i:s',strtotime($post_data['startTime'][$k]));
                            $data[$k]['endTime'] = date('Y-m-d H:i:s',strtotime($post_data['endTime'][$k]));
                        }
                        else
                        {
                            $data[$k]['startTime'] = date('H:i:s',strtotime($post_data['startTime'][$k]));
                            $data[$k]['endTime'] = date('H:i:s',strtotime($post_data['endTime'][$k]));
                        }
                    }

                }
            }

            $post['channelid'] = $post_data['channelID'];
            $post['data'] = base64_encode(json_encode($data));
            $post['sign'] = md5("channelid={$post['channelid']}&data={$post['data']}".C('RH_KEY'));

            $res = curl_post(C('RH_EDITPACKAGE_URL'),$post);
            $res = json_decode($res,true);
            if($res['state'] == 1)
            {
                $this->success('修改成功',U('index'));
                exit();
            }
            else
            {
                $this->error('修改失败');
                exit();
            }

        }
        $channelID = I('channelID');
        $list = M('upackagename',null,C('RH_DB_CONFIG'))->where(array('channelID'=>$channelID))->select();

        $this->assign('channelID',$channelID);
        $this->assign('list',$list);
        $this->display();
    }

    public function pay_list()
    {
        $appid = I('appid');
        $start_time = I('start_time',date('Y-m-d'));
        $end_time = I('end_time',date('Y-m-d'));
        $state = I('state',3);
        $orderID = I('orderID');
        $channelOrderID = I('channelOrderID');
        $userID = I('userID');
        $masterID = I('masterID');
        $roleName = I('roleName');

        $map = array();
        $uchannel_model = M('uchannel',null,C('RH_DB_CONFIG'));
        $uorder_model = M('uorder',null,C('RH_DB_CONFIG'));

        if($appid)
        {
            $map['t1.appID'] = $uchannel_model->where(array('cpAppID'=>$appid,'masterID'=>array('in',C('RH_185CHANNEL_ID'))))->getfield('appID');
        }
        else
        {
            $game_role = session('game_role');
            if($game_role != 'all')
            {
                $appids = $uchannel_model->where(array('cpAppID'=>array('in',$game_role),'masterID'=>array('in',C('RH_185CHANNEL_ID'))))->getfield('appID',true);
                $map['t1.appID'] = array('in',implode(',',$appids));
            }
        }

        $role_id = M('role_user')->where(array('user_id'=>session('ADMIN_ID')))->Getfield('role_id');


        if($state) $map['t1.state'] = $state;

        if($start_time) $map['t1.createdTime'][] = array('egt',$start_time.' 00:00:00');
        if($end_time) $map['t1.createdTime'][] = array('elt',$end_time.' 23:59:59');
        if($orderID) $map['t1.orderID'] = array('like',$orderID.'%');
        if($channelOrderID) $map['t1.channelOrderID'] = array('like',$channelOrderID.'%');
        if($userID) $map['t1.userID'] = array('like',$userID.'%');
        if($roleName) $map['t1.roleName'] = array('like',$roleName.'%');
        if($masterID) {
            //$map['t3.masterID'] = $masterID;
            $channelIDs = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>$masterID))->getfield('channelID',true);

            $map['t1.channelID'] = array('in',implode(',',$channelIDs));
        }
        else
        {
            if($role_id ==24)
            {
              //  $map['t3.masterID'] = array('in',C('RH_SWITCHPAY_SHOWCHANNEL'));
                $channelIDs = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('channelID',true);
                $map['t1.channelID'] = array('in',implode(',',$channelIDs));
            }
            else
            {
               // $map['t3.masterID'] = array('not in',C('RH_CHANNEL_ID'));
                $channelIDs = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('not in',C('RH_CHANNEL_ID'))))->getfield('channelID',true);

                $map['t1.channelID'] = array('in',implode(',',$channelIDs));
            }
        }

        $count = $uorder_model
            ->alias('t1')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);

        $list = $uorder_model
            ->alias('t1')
            ->join('left join `ugame` as  t2 on t1.appID = t2.appID')
            ->join('left join `uchannel` as t3 on t1.channelID = t3.channelID')
            ->field('t1.*,t2.name as appName,t3.masterID')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t1.createdTime desc')
            ->select();



        $map['t1.state'] = array('in','3,2');
        $map['t1.completeTime'] = $map['t1.createdTime'];
        unset($map['t1.createdTime']);
        $total = $uorder_model
            ->alias('t1')
            ->where($map)
            ->field('sum(money) as money,sum(realMoney) as realMoney')
            ->find();

        //获取U8所有渠道商
        if($role_id == 24)
        {
            $u8_channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('masterID,masterName',true);
        }
        else
        {
            $u8_channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('not in',C('RH_CHANNEL_ID'))))->getfield('masterID,masterName',true);
        }


        $this->assign('roleName',$roleName);
        $masterNames = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
        $this->assign('masterNames',$masterNames);
        $this->assign('u8_channel',$u8_channel);
        $this->assign('masterID',$masterID);
        $this->assign('userID',$userID);
        $this->assign('channelOrderID',$channelOrderID);
        $this->assign('orderID',$orderID);
        $this->assign('total',$total);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('state',$state);
        $this->assign('appid',$appid);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();

    }

    public function edit_channel()
    {
        $channelID = I('channelID');
        $status  = I('status');
        $type = I('type');

        //获取游戏SYSDK的tag

        $uchannel_info = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('channelID'=>$channelID))->find();

        $appid = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in',C('RH_185CHANNEL_ID')),'appID'=>$uchannel_info['appID']))->getfield('cpAppID');

        $tag = M('game')->where(array('id'=>$appid))->getfield('tag');

        $post['channelid'] = $channelID;
        $post['tag'] = $tag;
        if($type == 1)
        {
            $post['openRegisterFlag'] = $status;
            $post['openPayFlag'] = $uchannel_info['openPayFlag'];
            $post['openSwitchPayFlag'] = $uchannel_info['openSwitchPayFlag'];
        }
        elseif($type == 2)
        {
            $post['openRegisterFlag'] = $uchannel_info['openRegisterFlag'];
            $post['openPayFlag'] = $status;
            $post['openSwitchPayFlag'] = $uchannel_info['openSwitchPayFlag'];
        }
        else
        {
            $post['openRegisterFlag'] = $uchannel_info['openRegisterFlag'];
            $post['openPayFlag'] = $uchannel_info['openPayFlag'];
            $post['openSwitchPayFlag'] = $status;
        }

        $sign = '';
        foreach($post as $k=>$v)
        {
            $sign.= "$k=$v&";
        }
        $sign = trim($sign,'&');
        $sign.=C('RH_KEY');




        $sign = md5($sign);
        $post['sign'] = $sign;
        $res =  curl_post(C('RH_EDITCHANNEL_URL'),$post);
        $res = json_decode($res,true);
        if($res['state'] == 1)
        {
            $this->success('修改成功');
        }
        else
        {
            $this->error('修改失败');
        }
        exit();


    }

    public function resend()
    {
        $channelID = I('channelID');
        $channelOrderID = I('channelOrderID');

        if(empty($channelID) || empty($channelOrderID))
        {
            $this->error('参数不能为空');
        }

        $post['channelID'] = $channelID;
        $post['channelOrderID'] = $channelOrderID;
        $post['sign'] = md5("channelID={$channelID}&channelOrderID={$channelOrderID}".C('RH_KEY'));


        $res = curl_post(C('RH_RESEND_URL'),$post);

        $res = json_decode($res,true);

        if($res['state'] == 1)
        {
            $this->success('重发成功');
        }
        else
        {
            $this->error('重发失败');
        }
    }

    public function pay_info()
    {
        $orderID = i('orderID');

        $data = M('uorder',null,C('RH_DB_CONFIG'))
            ->alias('t1')
            ->where(array('t1.orderID'=>$orderID))
            ->join('left join `ugame` t2 on t1.appID = t2.appID')
            ->field('t1.*,t2.name as appName')
            ->find();

        $this->assign('data',$data);
        $this->display();
    }

    public function pay_list_v2()
    {
        $appid = I('appid');
        $start_time = I('start_time',date('Y-m-d'));
        $end_time = I('end_time',date('Y-m-d'));
        $state = I('state',3);
        $orderID = I('orderID');
        $channelOrderID = I('channelOrderID');
        $userID = I('userID');
        $packageName = I('packageName');
        $payType = I('payType');
        $roleName = I('roleName');

        $map = array();
        $uchannel_model = M('uchannel',null,C('RH_DB_CONFIG'));
        $uorder_model = M('uorder',null,C('RH_DB_CONFIG'));

        if($appid)
        {
            $map['t1.appID'] = $appid;
        }


        if($state) $map['t1.state'] = $state;

        if($start_time) $map['t1.createdTime'][] = array('egt',$start_time.' 00:00:00');
        if($end_time) $map['t1.createdTime'][] = array('elt',$end_time.' 23:59:59');
        if($orderID) $map['t1.orderID'] = array('like',$orderID.'%');
        if($channelOrderID) $map['t1.channelOrderID'] = array('like',$channelOrderID.'%');
        if($userID) $map['t1.userID'] = array('like',$userID.'%');
        if($packageName) $map['t4.packageName'] = $packageName;
        if($payType == 1)
        {
            $map['t1.payType'] = 0 ;
        }
        elseif($payType == 2)
        {
            $map['t1.payType'] = array('gt',0);
        }
        if($roleName) $map['t1.roleName'] = array('like',$roleName.'%');

     //   $map['t3.masterID'] = array('in',C('RH_SWITCHPAY_SHOWCHANNEL'));

        $channelIDs = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in',C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('channelID',true);

        $map['t1.channelID'] = array('in',implode(',',$channelIDs));

        $count = $uorder_model
            ->alias('t1')
            ->join('left join `upackagename` as t4 on (t1.channelID = t4.channelID and t1.packageName = t4.packageName) or (t1.subChannelID = t4.channelID and t1.packageName = t4.packageName)')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);

        $list = $uorder_model
            ->alias('t1')
            ->join('left join `ugame` as  t2 on t1.appID = t2.appID')
            ->join('left join `upackagename` as t4 on (t1.channelID = t4.channelID and t1.packageName = t4.packageName) or (t1.subChannelID = t4.channelID and t1.packageName = t4.packageName)')
            ->field('t1.*,t2.name as appName,t4.title')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t1.createdTime desc')
            ->select();

        if(is_array($list))
        {
            $userIDs = '';
            foreach($list as $v)
            {
                $userIDs.=$v['userID'].',';
            }
            $userIDs = trim($userIDs,',');

            $switchPays = M('uuser',null,C('RH_DB_CONFIG'))->where(array('userID'=>array('in',$userIDs)))->getfield('userID,switchPay',true);

            $this->switchPays = $switchPays;
        }

        $map['t1.state'] = 3;
        $map['t1.completeTime'] = $map['t1.createdTime'];
        unset($map['t1.createdTime']);
        $total = $uorder_model
            ->alias('t1')
            ->join('left join `upackagename` as t4 on (t1.channelID = t4.channelID and t1.packageName = t4.packageName) or (t1.subChannelID = t4.channelID and t1.packageName = t4.packageName)')
            ->where($map)
            ->field('sum(money) as money,sum(realMoney) as realMoney')
            ->find();

        $upakage_list = M('upackagename',null,C('RH_DB_CONFIG'))
            ->alias('t1')
            ->join('left join `uchannel` as t2 on t1.channelID = t2.channelID ')
            ->where(array('t2.masterID'=>165))->getfield('t1.packageName,t1.title',true);
        $ugames = M('ugame',null,C('RH_DB_CONFIG'))
            ->alias('t1')
            ->join('left join `uchannel` as t2 on t1.appID = t2.appID')
            ->where(array('t2.masterID'=>165))
            ->getfield('t1.appID,t1.name',true);


        $this->assign('ugames',$ugames);
        $this->assign('roleName',$roleName);
        $this->assign('payType',$payType);
        $this->assign('upakage_list',$upakage_list);
        $this->assign('packageName',$packageName);
        $this->assign('userID',$userID);
        $this->assign('channelOrderID',$channelOrderID);
        $this->assign('orderID',$orderID);
        $this->assign('total',$total);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('state',$state);
        $this->assign('appid',$appid);
        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    /**
     * 玩家数据上报(联运)
     */
    public function report_data_list()
    {
        $userID = I('userID');
        $appID = I('appID');
        $time = I('time')?I('time'):date('Y-m-d');
        $deviceID = I('deviceID');
        $roleName = I('roleName');
        $opType = I('opType');

        $uchannel_model = M('uchannel',null,C('RH_DB_CONFIG'));
        $uuserlog_model = M('uuserlog'.date('Ymd',strtotime($time)),null,C('RH_DB_CONFIG'));
        $map = array();


        if($appID)
        {
            $map['appID'] = $appID;
        }

        if($deviceID)
        {
            $map['deviceID'] = $deviceID;
        }

        if($roleName)
        {
            $map['roleName'] = array('like',$roleName.'%');
        }

        if($opType)
        {
            $map['opType'] = $opType;
        }

        if($userID)
        {
            $map['userID'] = $userID;
        }

       $channelIDs = $uchannel_model->where(array('masterID'=>C('RH_6533_CHANNEL')))->getfield('channelID',true);

        $map['channelID'] = array('in',implode(',',$channelIDs));

        $count = $uuserlog_model->where($map)->count();

        $page = $this->page($count, 20);

        $list = $uuserlog_model
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('opTime desc')
            ->select();



        if(is_array($list))
        {
            $userIDs = '';
            foreach($list as $v)
            {
                $userIDs.=$v['userID'].',';
            }
            $userIDs = trim($userIDs,',');

            $switchPays = M('uuser',null,C('RH_DB_CONFIG'))->where(array('userID'=>array('in',$userIDs)))->getfield('userID,switchPay',true);

            $this->switchPays = $switchPays;
        }


        $this->opType_list = array(
            1=>'选择服务器',
            2=>'创建角色',
            3=>'进入游戏',
            4=>'等级提升',
            5=>'登出游戏',
            6=>'注册新增',
            7=>'登录游戏',
        );


        $this->gamenames = M('ugame',null,C('RH_DB_CONFIG'))
            ->alias('t1')
            ->join('left join `uchannel` as t2 on t1.appID = t2.appID')
            ->where(array('t2.masterID'=>C('RH_6533_CHANNEL')))
            ->getfield('t1.appID,t1.name',true);

        $this->max = date('Y-m-d');
        $this->min = '2018-05-01';
        $this->userID = $userID;
        $this->appID = $appID;
        $this->time = $time;
        $this->deviceID = $deviceID;
        $this->roleName = $roleName;
        $this->opType = $opType;
        $this->page = $page->show('Admin');
        $this->list = $list;
        $this->display();

    }

    /**
     * 修改融合玩家信息
     */
    public function edit_userID()
    {
        $userID = I('userID');
        $status = I('status');

        $user_info = M('uuser',null,C('RH_DB_CONFIG'))->where(array('userID'=>$userID))->find();

        $post['userID'] = $userID;
        $post['appID'] = $user_info['appID'];
        $post['channelID'] = $user_info['channelID'];

        $appid = M('uchannel',null,C('RH_DB_CONFIG'))->where(array('masterID'=>array('in',C('RH_185CHANNEL_ID')),'appID'=>$user_info['appID']))->getfield('cpAppID');

        $tag = M('game')->where(array('id'=>$appid))->getfield('tag');

        $post['tag'] = $tag;
        $post['switchPay'] = ($status == 1)?'true':'false';

        $sign = '';
        foreach($post as $k=>$v)
        {
            $sign.= "$k=$v&";
        }
        $sign = trim($sign,'&');
        $sign.=C('RH_KEY');


        $sign = md5($sign);
        $post['sign'] = $sign;

        $res =  curl_post(C('RH_EDIT_USERSWITCHPAY'),$post);
        $res = json_decode($res,true);
        if($res['state'] == 1)
        {
            $this->success('修改成功');
        }
        else
        {
            $this->error('修改失败');
        }
        exit();

    }


}
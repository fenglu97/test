<?php
/**
 * 自主结算
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/12/7
 * Time: 16:26
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SettlementController extends AdminbaseController{

    public function index(){
        $uid = session('ADMIN_ID');

        $count = M('withdraw_cash')->where(array('uid'=>$_SESSION['ADMIN_ID']))->count();
        $page = $this->page($count, 20);
        $data = M('withdraw_cash')->where(array('uid'=>$_SESSION['ADMIN_ID']))->order('create_time desc')->select();
        //获取配置
        $option = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
        $option = json_decode($option,true);

        //计算可提现金额
        $channel = M('channel')->field('id,parent,cash_time,gain_sharing')->where(array('admin_id'=>$uid))->find();
        if($channel['parent'] == 0){
            if($channel['cash_time'] > 0){
                $money = M('users')->where(array('id'=>$uid))->getField('withdraw_cash');
                $cash = M('withdraw_cash')->where(array('uid'=>$uid,'status'=>array('eq',2)))->sum('money');
                $money = round($money - $cash,2);
            }else{
                $money = 0;
            }
        }else{
            $money = 0;
        }
        $is_contract = M('users')->where(array('id'=>$uid))->getField('is_contract');
        $this->is_contract = $is_contract;
        $this->page = $page->show('Admin');
        $this->time = $channel['cash_time'];
        $this->fc = $channel['gain_sharing'];
        $this->money = $money;
        $this->withdraw_cash = $option['withdraw_cash'];
        $this->data = $data;
        $this->display();
    }

    /**
     * 明细
     */
    public function details(){
//        $uid = session('ADMIN_ID');
        $cid = I('cid');
        $type=  I('type');
        $start = I('start');
        $end = I('end');

        if($cid) $where['l.cid'] = $cid;
        if($type) $where['l.type'] = $type;
        if($start){
            $where['l.create_time'] = array('gt',strtotime($start));
        }else{
            $start = date('Y-m-d',strtotime('-7 day'));
            $where['l.create_time'] = array('gt',strtotime($start));
        }
        if($end){
            $where['l.create_time'] = array('lt',strtotime($end.' 23:59:59'));
        } else{
            $end = date('Y-m-d');
            $where['l.create_time'] = array('lt',strtotime($end.' 23:59:59'));
        }

        $count = M('withdraw_log l')->where($where)->count();
        $page = $this->page($count, 20);
        $data = M('withdraw_log l')
            ->field('l.*,c.name')
            ->join('left join __CHANNEL__ c on c.id=l.cid')
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->order('create_time desc')->select();
        foreach($data as $k=>$v){
            if($v['childcid'] > 0){
                $data[$k]['childname'] = M('channel')->where(array('id'=>$v['childcid']))->getField('name');
            }
        }

        $this->channel_list = get_channel_list($cid);
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->cid = $cid;
        $this->type = $type;
        $this->start = $start;
        $this->end = $end;
        $this->display();
    }

    /**
     * 执行提现
     */
    public function withdrawCash(){

        $uid = $_SESSION['ADMIN_ID'];
        $cash = I('cash');
        $code = I('code');

        $user = M('users')->where(array('id'=>$uid))->find();
        //验证码
        $smscode = M('smscode')->where(array('mobile'=>$user['mobile']))->order('id desc')->find();
        if($code != $smscode['code']){
            $this->error('验证码错误');
        }
        if(time() - strtotime($smscode['create_time']) > 300 ){
            $this->error('验证码已过期');
        }
        //获取配置
        $option = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
        $option = json_decode($option,true);

        //金额检查
        $channel = M('channel')->field('id,cash_time,gain_sharing')->where(array('admin_id'=>$uid))->find();

        $cash_money = M('withdraw_cash')->where(array('uid'=>$uid,'status'=>array('eq',2)))->sum('money');
        $money = round($user['withdraw_cash'] - $cash_money,2);

        if($cash > $money || empty($cash)){
            $this->error('提现金额非法');
        }
        if($cash < $option['withdraw_cash']){
            $this->error("提现金额要求{$option['withdraw_cash']}元以上");
        }
        M('smscode')->where(array('id'=>$smscode['id']))->setField('status',0);
        $add = array(
            'orderID' => orderID(),
            'uid' => $uid,
            'cid' => $channel['id'],
            'name' => I('name'),
            'alipay' => I('alipay'),
            'money' => $cash,
            'create_time' => time()
        );
        if(M("withdraw_cash")->add($add)){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 发送短信
     */
    public function sendSms(){
        $mobile = I('mobile');
        $uid = I('uid');
        $msg = I('msg');
        if(empty($mobile) || empty($uid)){
            $this->error('发送失败');
        }

        if(M('users')->where(array('id'=>$uid,'mobile'=>$mobile))->getField('user_status')){
            $num = createSMSCode();
            if(sendSms($mobile,$num)){
                M('smscode')->add(array('mobile'=>$mobile,'code'=>$num,'create_time'=>date('Y-m-d H:i:s'),'update_time'=>date('Y-m-d H:i:s')));
            }
            $this->success();
        }else{
            $this->error('账户已被禁用');
        }

    }

    /**
     * 计算可申请金额
     * @param $monthFirstDay
     * @param $applyMoney
     * @return int
     */
    public function getUsableMoney(){
        $log = M('withdraw_log')->where(array('uid'=>$_SESSION['ADMIN_ID'],'type'=>1))->order('create_time desc')->limit(1)->find();
        if($log){
            //相差多少个月
            $datetime1 = new \DateTime(date('Y-m-d',$log['create_time']));
            $datetime2 = new \DateTime(date('Y-m-d'));
            $interval = $datetime1->diff($datetime2);
            $month = $interval->format('%m');
            $month = $month - 1;
            if($month > 0){
                $sdkMoney = M('inpour')
                    ->field('sum(money) money,FROM_UNIXTIME(create_time,"%Y-%m") month')
                    ->where(array('payType' => array('neq',10), 'status' => 1, 'promoter_uid' => $_SESSION['ADMIN_ID'],'create_time'=>array('gt',$log['create_time'])))
                    ->order('month desc')
                    ->group('month')
                    ->select();
                $syoMoney = M('syo_inpour',null,C('185DB'))
                    ->field('sum(order_amount) money,FROM_UNIXTIME(`create`,"%Y-%m") month')
                    ->where(array('status'=>1,'promoter_uid'=>$_SESSION['ADMIN_ID'],'create_time'=>array('gt',$log['create_time'])))
                    ->order('month desc')
                    ->group('month')
                    ->select();

                $this->fillArr($month,$sdkMoney,$syoMoney);
            }

        }else{
            $sdkMoney = M('inpour')
                ->field('sum(money) money,FROM_UNIXTIME(create_time,"%Y-%m") month')
                ->where(array('payType' => array('neq',10), 'status' => 1, 'promoter_uid' => $_SESSION['ADMIN_ID']))
                ->order('month desc')
                ->group('month')
                ->select();
            $syoMoney = M('syo_inpour',null,C('185DB'))
                ->field('sum(order_amount) money,FROM_UNIXTIME(`create`,"%Y-%m") month')
                ->where(array('status'=>1,'promoter_uid'=>$_SESSION['ADMIN_ID']))
                ->order('month desc')
                ->group('month')
                ->select();

            //相差多少个月,数据从11月开始计算
            $datetime1 = new \DateTime('2017-11-01');
            $datetime2 = new \DateTime(date('Y-m-d'));
            $interval = $datetime1->diff($datetime2);
            $month = $interval->format('%m');
            //把相差的月份和初始金额填充到新数组
            if($month > 0){
                $this->fillArr($month,$sdkMoney,$syoMoney);
            }

        }
    }

    /**
     * 填充新数据
     * @param $month
     * @param $sdkMoney
     * @param $syoMoney
     */
    protected function fillArr($month,$sdkMoney,$syoMoney){
        $last = date('Y-m');
        $newMonth = $month;
        for($i = 1;$i <= $month;$i++){
            $num = $newMonth--;
            $new[$i - 1]['month'] = date('Y-m',strtotime("-$num month $last"));
            $new[$i - 1]['money'] = 0;
        }
        //获取配置
        $option = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
        $option = json_decode($option,true);
        $settlement = array_values($option['settlement']);


        $usableMoney = M('personalinfo')->where(array('uid'=>$_SESSION['ADMIN_ID']))->getField('usableMoney');

        //遍历新数组对比月份，累加充值金额
        $totalMoney = 0;


        foreach($new as $k=>$v){
            $inRange = false;
            $sum = 0;
            if(count($sdkMoney) > 0){
                foreach($sdkMoney as $k1=>$sdk){
                    if($v['month'] == $sdk['month']){
                        $sum += $sdk['money'];
                    }
                }
            }
            if(count($syoMoney) > 0){
                foreach($syoMoney as $k2=>$syo){
                    if($v['month'] == $syo['month']){
                        $sum += $syo['money'];
                    }
                }
            }

            //如果没有配置直接用写死的值
            if(count($settlement) < 1){
                $sum = sprintf("%.2f",($sum * 50)/100);
            }else{
                //充值金额计算
                if($sum > 0){
                    //如果在范围内乘以对应百分比，超出最大金额按最大金额的百分比计算
                    foreach($settlement as $k3=>$vo){
                        list($min,$max) = explode('-',$vo['range']);
                        if($sum > $min && $sum <= $max){
                            $inRange = true;
                            $sum = sprintf("%.2f",($sum * $vo['percent'])/100);
                        }else{
                            $percent[] = $vo['percent'];
                        }
                    }
                    if(!$inRange){
                        rsort($percent);
                        $sum = sprintf("%.2f",($sum * $percent[0])/100);
                    }
                }
            }

            $new[$k]['money'] = $sum;
            $new[$k]['uid'] = $_SESSION['ADMIN_ID'];
            $new[$k]['type'] = 1;
            $new[$k]['create_time'] = strtotime($v['month']);
            $totalMoney += $new[$k]['money'];
            $usableMoney += $new[$k]['money'];
            $new[$k]['money_count'] = $usableMoney;
        }
        if(M('withdraw_log')->addAll($new)){
            M('personalinfo')->where(array('uid'=>$_SESSION['ADMIN_ID']))->setInc('usableMoney',$totalMoney);
        }
    }



    /**
     * 用户信息检测
     */
    public function checkInfo(){
        $uid = I('uid');
        $data = M('users')->where(array('id'=>$uid))->find();
        $channel = M('channel')->where(array('admin_id'=>$uid))->getField('gain_sharing');

        $status = true;
        $msg = '';
        if(empty($data['user_truename']) || empty($data['alipay_account'])){
            $status = false;
            $msg = '请完善提现信息';
        }

        if($channel <= 0 || $channel > 100){
            $status = false;
            $msg = '请正确设置渠道分成比例';
        }

        if($status){
            $this->success($data);
        }else{
            $this->error($msg);
        }
    }

    /**
     * 提现申请列表
     */
    public function withdrawApply(){
        $cid = I('cid');
        $orderID = I('orderID');
        $uname = I('uname');
        $status = I('status',9);
        $pay_status = I('pay_status',9);
        $start = I('start');
        $end = I('end');

        $where = '';
        if($cid) $where['cid'] = $cid;
        if($orderID) $where['w.orderID'] = $orderID;
        if($uname) $where['u.user_login'] = $uname;
        if($status != 9) $where['w.status'] = $status;
        if($pay_status != 9) $where['w.pay_status'] = $pay_status;
        if($start) $where['w.create_time'][] = array('gt',strtotime($start));
        if($end) $where['w.create_time'][] = array('lt',strtotime($end.' 23:59:59'));

        $count = M('withdraw_cash w')
            ->join('left join __CHANNEL__ c on c.id=w.cid')
            ->join('left join __USERS__ u on u.id=w.uid')
            ->where($where)
            ->order('w.create_time desc')
            ->count();

        $page = $this->page($count, 20);

        $data = M('withdraw_cash w')
            ->field('w.*,c.name channel,u.user_login,u.user_truename')
            ->join('left join __CHANNEL__ c on c.id=w.cid')
            ->join('left join __USERS__ u on u.id=w.uid')
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->order('w.create_time desc')
            ->select();

        $this->page = $page->show('Admin');
        $this->channel_list = get_channel_list($cid);
        $this->data = $data;
        $this->uname = $uname;
        $this->orderID = $orderID;
        $this->status = $status;
        $this->pay_status = $pay_status;
        $this->start = $start;
        $this->end = $end;
        $this->display();
    }

    /**
     * 审核
     */
    public function reviewSingle(){
        $id = I('id');
        $state = I('state');

        M('withdraw_cash')->where(array('id'=>$id))->setField(array('status'=>$state,'modify_time'=>time()));
        $this->success('操作成功');

    }

    /**
     * 打款给用户
     */
    public function payUser(){
        $id = I('id');
        $info = M('withdraw_cash')->where(array('id'=>$id))->find();
        if($info['pay_status'] == 1){
            $this->success('操作成功');
        }

        M('withdraw_cash')->where(array('id'=>$id))->setField(array('pay_time'=>time()));

        vendor('alipay.AopSdk');
        $aop = new \AopClient();
        $aop->appId = C('alipay.APPID');
        $aop->rsaPrivateKey = C('alipay.RSA_PRIVATE_KEY');
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayFundTransToaccountTransferRequest();
        $bizContent = array(
            'out_biz_no' => $info['orderID'],
            'payee_type' => 'ALIPAY_LOGONID',
            'payee_account' => $info['alipay'],
            'amount' => $info['money'],
            'payer_show_name' => '渠道提现',
            'remark' => '渠道提现'
        );
        //如果有真实姓名，支付宝验证姓名
        if(!empty($info['name'])){
            $bizContent['payee_real_name'] = $info['name'];
        }
        //加锁
        $res = M('withdraw_cash')->where(array('id'=>$id,'version'=>$info['version']))->setField('version',array('exp','version+1'));
        if(!$res){
            $this->error('其他管理员正在操作');
        }

        $request->setBizContent(json_encode($bizContent));
        try{
            $result = $aop->execute ($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            diylogs('withdraw_cash',$result);
            if(!empty($resultCode) && $resultCode == 10000){
                $set = array(
                    'pay_status' => 1,
                    'alipay_order' => $result->$responseNode->order_id
                );
                $user_cash = M('users')->where(array('id'=>$info['uid']))->getField('withdraw_cash');
                M('withdraw_cash')->where(array('id'=>$id))->setField($set);
                M('users')->where(array('id'=>$info['uid']))->setDec('withdraw_cash',$info['money']);
                M('withdraw_log')->add(array('uid'=>$info['uid'],'cid'=>$info['cid'],'order'=>$info['orderID'],'type'=>2,'money'=>$info['money'],'cash'=>$user_cash-$info['money'],'create_time'=>time()));
                $this->success('操作成功');

            } else {
                $set['remark'] = $result->$responseNode->sub_msg;
                $set['modify_time'] = time();
                M('withdraw_cash')->where(array('id'=>$id))->setField($set);
                $this->error($result->$responseNode->sub_msg);
            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * 余额明细
     */
    public function detail(){
        $uname = I('uname');
        $type = I('type',-1);
        $start = I('start');
        $end = I('end');

        $uid = $_SESSION['ADMIN_ID'];
        $role_id = M('role_user')->where(array('user_id'=>$uid))->getField('role_id');
        $where = '';

        if($type != -1) $where['w.type'] = $type;
        if($start) $where[]['w.create_time'] = array('gt',strtotime($start));
        if($end) $where[]['w.create_time'] = array('lt',strtotime($end.' 23:59:59'));
        if($role_id == C('SPREAD_ID')){
            $where['uid'] = $uid;
        }else{
            if($uname) $where['u.user_login'] = $uname;
        }
        $count = M('withdraw_log w')
            ->field('w.*,u.user_login,u.user_truename')
            ->join('left join __USERS__ u on u.id=w.uid')
            ->where($where)
            ->order('w.create_time desc')
            ->count();

        $page = $this->page($count, 20);

        $data = M('withdraw_log w')
            ->field('w.*,u.user_login,u.user_truename')
            ->join('left join __USERS__ u on u.id=w.uid')
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->order('w.create_time desc')
            ->select();

        $this->role_id = $role_id;
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->uname = $uname;
        $this->type = $type;
        $this->start = $start;
        $this->end = $end;
        $this->display();
    }

    /**
     * 推广玩家充值
     */
    public function pay(){
        $date = I('date');
        if(!$date) $date = date('Y-m');
        $start = strtotime($date);
        $end = strtotime($date.'-'.date('t').' 23:59:59');
        $uname = I('uname');
        $role_id = M('role_user')->where(array('user_id'=>$_SESSION['ADMIN_ID']))->getField('role_id');
        if($uname){
            if($role_id == C('SPREAD_ID')){
                $uid = $_SESSION['ADMIN_ID'];
            }else{
                $uid = M('users')->where(array('user_login'=>$uname))->getField('id');
            }
        }else{
            $uid = $_SESSION['ADMIN_ID'];
        }

        //获取配置
        $option = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
        $option = json_decode($option,true);
        $settlement = array_values($option['settlement']);

        $sdkinfo = M('inpour i')
            ->field('i.jz_other jzorder,g.game_name gname,i.username,i.money,i.create_time')
            ->join('left join __GAME__ g on g.id=i.appid')
            ->where(array('i.payType' => array('neq',10),'i.status' => 1, 'i.promoter_uid' => $uid,'i.create_time'=>array('between',array($start,$end))))
            ->order('i.create_time desc')
            ->select();

        $syoinfo = M('syo_inpour as i',null,C('185DB'))
            ->field('i.jz_order jzorder,g.gamename gname,i.username,i.order_amount money,i.create create_time')
            ->join('left join syo_game g on g.id=i.gid')
            ->where(array('i.status'=>1,'i.promoter_uid'=>$uid,'i.create'=>array('between',array($start,$end))))
            ->order('i.create desc')
            ->select();

        if($sdkinfo && $syoinfo){
            $data = array_merge($sdkinfo,$syoinfo);
        }elseif($sdkinfo){
            $data = $sdkinfo;
        }else{
            $data = $syoinfo;
        }

        $sdkMoney = 0;//新SDK统计充值
        $syoMoney = 0;//老BI统计充值
        $trueMoney = 0;//经过计算后的提现金额
        $showpercent = 0;//当前提现档位
        $inRange = false;//是否在配置范围
        $res = '';
        foreach($sdkinfo as $v){
            $sdkMoney += $v['money'];
        }
        foreach($syoinfo as $v){
            $syoMoney += $v['order_amount'];
        }
        $totalMoney = $sdkMoney + $syoMoney;
        //如果没有配置分成，提现金额使用默认值5%计算
        if(count($settlement) < 1){
            $trueMoney = sprintf("%.2f",($totalMoney * 5)/100);
        }else{
            //充值金额计算
            if($totalMoney > 0){
                //如果在范围内乘以对应百分比，超出最大金额按最大金额的百分比计算
                foreach($settlement as $k3=>$vo){
                    list($min,$max) = explode('-',$vo['range']);
                    if($totalMoney > $min && $totalMoney <= $max){
                        $inRange = true;
                        $trueMoney = sprintf("%.2f",($totalMoney * $vo['percent'])/100);
                        $showpercent = $vo['percent'];
                    }else{
                        $percent[] = $vo['percent'];
                    }
                }
                //如果高于最高分成档位则使用最高档位计算
                if(!$inRange){
                    rsort($percent);
                    $trueMoney = sprintf("%.2f",($totalMoney * $percent[0])/100);
                    $showpercent = $percent[0];
                }
            }

        }
        //当月则显示还需充值多少元进入下一分成档位
        if($date == date('Y-m')){
            if($totalMoney == 0){
                $info = $settlement[1];
                list($min,$max) = explode('-',$info['range']);
                $res['money'] = $min;
                $res['percent'] = $info['percent'];
                $res['max'] = false;
            }else{
                foreach($settlement as $k=>$v){
                    if($v['percent'] == $showpercent){
                        if(count($settlement) == ($k + 1)){
                            $res['money'] = 0;
                            $res['percent'] = $v['percent'];
                            $res['max'] = true;
                        }else{
                            list($min,$max) = explode('-',$settlement[$k+1]['range']);
                            $res['money'] = $min - $totalMoney;
                            $res['percent'] = $settlement[$k+1]['percent'];
                            $res['max'] = false;
                        }
                    }
                }
            }

        }
        $this->role_id = $role_id;
        $this->uname = $uname;
        $this->res = $res;
        $this->date = $date;
        $this->data = $data;
        $this->totalMoney = $totalMoney;
        $this->money = $trueMoney;
        $this->showpercent = $showpercent;
        $this->display();
    }

    /**
     * 提现信息
     */
    public function accountInfo(){
        if(IS_POST){
            $id = I('id');
            $alipay = trim(I('alipay'));
            $name = trim(I('name'));
            $mobile = I('mobile');
            $code = I('code');
            $id_card = I('id_card');
//            $is_contract = I('is_contract') ? 1 : 0;


            if($id_card){
                if(!validateIDCard($id_card)){
                    $this->error('身份证格式错误');
                }else{
                    $set['id_card'] = $id_card;
                }
            }else{
                $this->error('身份证不能为空');
            }
            $user = M('users')->where(array('id'=>$id))->find();

            if($user['mobile'] == ''){
                $set['mobile'] = $mobile;
            }else{
                if($mobile != ''){
                    if($code == ''){
                        $this->error('验证码不能为空');
                    }
                    $sms = M('smscode')->where(array('mobile'=>$user['mobile']))->order('id desc')->find();
                    if($code != $sms['code']){
                        $this->error('验证码错误');
                    }
                    if(time() - strtotime($sms['create_time']) > 300 || $sms['status'] != 1){
                        $this->error('验证码已过期');
                    }
                    M('smscode')->where(array('id'=>$sms['id']))->setField('status',0);
                    $set['mobile'] = $mobile;
                }
            }


            if(empty($alipay) || empty($name)){
                $this->error('提现信息不能为空');
            }


            if(!$user['is_contract']){
                $set['is_contract'] = 1;
                $set['contract_time'] = time();
            }

            $set['alipay_account'] = $alipay;
            $set['user_truename'] = $name;

            if(M('users')->where(array('id'=>$id))->setField($set) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $uid = session('ADMIN_ID');
            $info = M('users')->where(array('id'=>$uid))->find();
            $data = M('options')->where(array('option_name'=>'parttimeContract'))->getField('option_value');
            $this->data = html_entity_decode($data);
            $this->info = $info;
            $this->display();
        }
    }

    /**
     * 用户信息
     */
    public function userInfo(){
        $cid = I('cid');
        $start = I('start');
        $end = I('end');

        if($cid) $where['c.id'] = $cid;
        if($start){
            $map['pay_time'][] = array('gt',strtotime($start));
        } else{
            $start = date('Y-m-d');
            $map['pay_time'][] = array('gt',strtotime($start));
        }
        if($end){
            $map['pay_time'][] = array('lt',strtotime($end.' 23:59:59'));
        } else{
            $end = date('Y-m-d');
            $map['pay_time'][] = array('lt',strtotime($end.' 23:59:59'));
        }



        $where['cash_time'] = array('gt',0);

        $count = M('channel c')->join('left join __USERS__ u on c.admin_id=u.id')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('channel c')
            ->field('c.id cid,c.name,c.cash_time,c.create_time,u.withdraw_cash,u.alipay_account,u.user_truename,u.id_card,u.mobile,u.is_contract,u.id uid')
            ->join('left join __USERS__ u on c.admin_id=u.id')
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->select();


        foreach($data as $k=>$v){
            $map['uid'] = $v['uid'];
            $map['cid'] = $v['cid'];
            $data[$k]['getMoney'] = M('withdraw_cash')->where($map)->sum('money');

        }
        $Contract= M('options')->where(array('option_name'=>'parttimeContract'))->getField('option_value');
        $this->contract = html_entity_decode($Contract);
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->start = $start;
        $this->end = $end;
        $this->channel_list = get_channel_list($cid);
        $this->display();
    }

    public function export(){
        $cid = I('cid');
        $start = I('start');
        $end = I('end');

        if($cid) $where['c.id'] = $cid;
        if($start){
            $map['pay_time'][] = array('gt',strtotime($start));
        } else{
            $start = date('Y-m-d');
            $map['pay_time'][] = array('gt',strtotime($start));
        }
        if($end){
            $map['pay_time'][] = array('lt',strtotime($end.' 23:59:59'));
        } else{
            $end = date('Y-m-d');
            $map['pay_time'][] = array('lt',strtotime($end.' 23:59:59'));
        }

        $where['cash_time'] = array('gt',0);

        $data = M('channel c')
            ->field('u.id uid,c.id cid,c.name,u.withdraw_cash,u.alipay_account,u.user_truename,u.mobile,u.id_card,c.create_time,c.cash_time,u.is_contract')
            ->join('left join __USERS__ u on c.admin_id=u.id')
            ->where($where)
            ->select();


        foreach($data as $k=>$v){
            $data[$k]['is_contract'] = $v['is_contract'] == 1 ? '已签约' : '未签约';
            $data[$k]['cash_time'] = $v['cash_time'] > 0 ? date('Y-m-d H:i:s',$v['cash_time']) : '';
            $data[$k]['create_time'] = $v['create_time'] > 0 ? date('Y-m-d H:i:s',$v['create_time']) : '';
            $map['uid'] = $v['uid'];
            $map['cid'] = $v['cid'];
            $getmoney['getMoney'] = M('withdraw_cash')->where($map)->sum('money');
            $this->array_insert($data[$k],4,$getmoney);
            unset($data[$k]['uid']);
        }

        vendor('PHPExcel.PHPExcel');
        $excel = new \PHPExcel();
        $excel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objActSheet = $excel->getActiveSheet();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
        $expCellName = array('渠道ID','渠道名','账户余额','提现金额','支付宝账号','真实姓名','手机号','身份证','注册时间','结算开通时间','合同');
        for($i = 0;$i < 11;$i++) {
            $objActSheet->setCellValue("$cellName[$i]1","$expCellName[$i]");
        }
        foreach($data as $k=>$v){
            $j = $k+2;
            $objActSheet->getRowDimension($j)->setRowHeight(20);

            for($i=0;$i < count($v);$i++){
                $val = array_values($v);
                $excel->setActiveSheetIndex(0)->setCellValue("$cellName[$i]".$j, $val[$i].' ');
            }
        }

        $write = new \PHPExcel_Writer_Excel5($excel);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition: attachment;filename="用户信息.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }

    public function applyExport(){
        $cid = I('cid');
        $orderID = I('orderID');
        $uname = I('uname');
        $status = I('status');
        $pay_status = I('pay_status');
        $start = I('start');
        $end = I('end');

        $where = '';
        if($cid) $where['cid'] = $cid;
        if($orderID) $where['w.orderID'] = $orderID;
        if($uname) $where['u.user_login'] = $uname;
        if($status < 9) $where['w.status'] = $status;
        if($pay_status < 9) $where['w.pay_status'] = $pay_status;
        if($start) $where['w.create_time'][] = array('gt',strtotime($start));
        if($end) $where['w.create_time'][] = array('lt',strtotime($end.' 23:59:59'));


        $data = M('withdraw_cash w')
            ->field('u.user_truename,u.id_card,w.money,from_unixtime(w.pay_time,"%Y-%m-%d %H:%i") pay_time')
            ->join('left join __USERS__ u on u.id=w.uid')
            ->where($where)
            ->order('w.create_time desc')
            ->select();

        vendor('PHPExcel.PHPExcel');
        $excel = new \PHPExcel();
        $excel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objActSheet = $excel->getActiveSheet();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
        $expCellName = array('姓名','身份证','结算金额','结算日期');
        for($i = 0;$i < 11;$i++) {
            $objActSheet->setCellValue("$cellName[$i]1","$expCellName[$i]");
        }
        foreach($data as $k=>$v){
            $data[$k]['pay_time'] = date('Y-m-d H:i',$v['pay_time']);
            $j = $k+2;
            $objActSheet->getRowDimension($j)->setRowHeight(20);

            for($i=0;$i < count($v);$i++){
                $val = array_values($v);
                $excel->setActiveSheetIndex(0)->setCellValue("$cellName[$i]".$j, $val[$i].' ');
            }
        }

        $write = new \PHPExcel_Writer_Excel5($excel);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition: attachment;filename="提现记录.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }

    private function array_insert (&$array, $position, $insert_array) {
        $first_array = array_splice ($array, 0, $position);
        $array = array_merge ($first_array, $insert_array, $array);
    }

}
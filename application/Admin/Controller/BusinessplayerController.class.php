<?php
/**
 *交易账号控制器
 * @author qing.li
 * @date 2018-06-08
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class BusinessplayerController extends AdminbaseController
{
    /**
     * 账号列表
     */
    public function index()
    {
        $username = I('username');
        $mobile = I('mobile');
        $start_time = I('start_time');
        $end_time = I('end_time');
        $account = I('account');

        $map = array();
        if($username){
            $uid = M('player')->where(array('username'=>$username))->getField('id');
            if($uid){
                $map['a.uid'] = $uid;
            }
        }
        if($mobile){
            $uid = M('player')->where(array('mobile'=>$mobile))->getField('id');
            if($uid){
                $map['a.uid'] = $uid;
            }
        }
        if($account){
            $map['a.id'] = $account;
        }
        if($start_time)
        {
            $map['a.create_time'][] = array('egt',strtotime($start_time));
        }

        if($end_time)
        {
            $map['a.create_time'][] = array('lt',strtotime($end_time)+3600*24);
        }
        $map['pro.status'] = array('in','1,2,3,4');
        $count = M('app_player a')
                ->join('left join __PLAYER__ p on p.id = a.uid')
                ->join('left join __PRODUCTS__ pro on pro.account = a.id')
                ->where($map)
                ->count();

        $page = $this->page($count, 20);

        $list =  M('app_player a')
            ->field('a.id app_uid,a.uid,p.username,p.mobile,p.alipay_account,p.regip,p.system,p.maker,p.mobile_model,p.machine_code,p.system_version,a.create_time,a.last_login_time')
            ->join('left join __PLAYER__ p on p.id = a.uid')
            ->join('left join __PRODUCTS__ pro on pro.account = a.id')
            ->where($map)
            ->select();


        $this->assign('page',$page->show('Admin'));
        $this->assign('end_time',$end_time);
        $this->assign('start_time',$start_time);
        $this->assign('mobile',$mobile);
        $this->assign('username',$username);
        $this->assign('account',$account);
        $this->assign('list',$list);
        $this->display();
    }

    /**
     * 账号列表
     */
    public function index_old()
    {
        $username = I('username');
        $mobile = I('mobile');
        $start_time = I('start_time');
        $end_time = I('end_time');
        $account = I('account');

        $map = array();
        if($username) $map['username'] = $username;
        if($mobile) $map['mobile'] = $mobile;
        if($start_time)
        {
            $map['create_time'][] = array('egt',strtotime($start_time));
        }

        if($end_time)
        {
            $map['create_time'][] = array('lt',strtotime($end_time)+3600*24);
        }

        if($account)
        {
            $busi_uid = M('busi_sdk')->where(array('sdk_username'=>$account))->getfield('busi_uid');
            $map['id'] = $busi_uid;
        }

        $count = M('business_player')->where($map)->count();

        $page = $this->page($count, 20);

        $list = M('business_player')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('create_time desc')
            ->select();


        $this->assign('page',$page->show('Admin'));
        $this->assign('end_time',$end_time);
        $this->assign('start_time',$start_time);
        $this->assign('mobile',$mobile);
        $this->assign('username',$username);
        $this->assign('account',$account);
        $this->assign('list',$list);
        $this->display();
    }

    /**
     * 商品列表
     */
    public function products_by_user()
    {
        $uid = I('uid');
        $appid = I('appid');
        $status = I('status');

        $map['t1.uid'] = $uid;
        if($appid) $map['t1.appid'] = $appid;
        if($status) $map['t1.status'] = $status;

        $count = M('products')
            ->alias('t1')
            ->join('bt_game t2 on t1.appid =t2.id')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);

        $list = M('products')
            ->field('t1.*,t2.game_name')
            ->alias('t1')
            ->join('bt_game t2 on t1.appid =t2.id')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t1.create_time desc')
            ->select();


        $this->assign('products_status',C('PRODUCTS_STATUS'));
        $this->assign('page',$page->show('Admin'));
        $this->assign('appid',$appid);
        $this->assign('status',$status);
        $this->assign('list',$list);
        $this->assign('uid',$uid);
        $this->display();

    }

    /**
     * 卖家订单
     */
    public function order_by_seller()
    {
        $uid = I('uid');
        $appid = I('appid');
        $status = I('status');

        $map['t1.sell_id'] = $uid;

        if($appid) $map['t2.appid'] = $appid;
        if($status) $map['t1.status'] = $status;

        $count = M('account_trade')
            ->alias('t1')
            ->join('bt_products as t2 on t1.proid = t2.id')
            ->join('bt_game as t3 on t2.appid= t3.id')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);

        $list = M('account_trade')
            ->field('t1.*,t2.account,t3.game_name')
            ->alias('t1')
            ->join('bt_products as t2 on t1.proid = t2.id')
            ->join('bt_game as t3  on t2.appid= t3.id')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t1.create_time desc')
            ->select();

        $busi_uids = '';

        foreach($list as $v)
        {
            $busi_uids.=$v['buy_id'].',';
            $busi_uids.=$v['sell_id'].',';
        }
        $busi_uids = trim($busi_uids,',');

        $bsp_usernames = M('business_player')->where(array('id'=>array('in',$busi_uids)))->getfield('id,username',true);

        $this->assign('bsp_usernames',$bsp_usernames);

        $this->assign('trade_status',array(1=>'支付成功',2=>'未支付',3=>'退款','4'=>'交易完成','5'=>'异常订单','6'=>'转账成功','7'=>'重置成功'));
        $this->assign('page',$page->show('Admin'));
        $this->assign('appid',$appid);
        $this->assign('status',$status);
        $this->assign('list',$list);
        $this->assign('uid',$uid);
        $this->display();

    }

    /**
     * 买家订单
     */
    public function order_by_buyer()
    {
        $uid = I('uid');
        $appid = I('appid');
        $status = I('status');

        $map['t1.buy_id'] = $uid;

        if($appid) $map['t2.appid'] = $appid;
        if($status) $map['t1.status'] = $status;

        $count = M('account_trade')
            ->alias('t1')
            ->join('bt_products as t2 on t1.proid = t2.id')
            ->join('bt_game as t3 on t2.appid= t3.id')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);

        $list = M('account_trade')
            ->field('t1.*,t2.account,t3.game_name')
            ->alias('t1')
            ->join('bt_products as t2 on t1.proid = t2.id')
            ->join('bt_game as t3  on t2.appid= t3.id')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t1.create_time desc')
            ->select();

        $busi_uids = '';

        foreach($list as $v)
        {
            $busi_uids.=$v['buy_id'].',';
            $busi_uids.=$v['sell_id'].',';
        }
        $busi_uids = trim($busi_uids,',');

        $bsp_usernames = M('business_player')->where(array('id'=>array('in',$busi_uids)))->getfield('id,username',true);


        $this->assign('bsp_usernames',$bsp_usernames);

        $this->assign('trade_status',array(1=>'支付成功',2=>'未支付',3=>'退款','4'=>'交易完成','5'=>'异常订单','6'=>'转账成功','7'=>'重置成功'));
        $this->assign('page',$page->show('Admin'));
        $this->assign('appid',$appid);
        $this->assign('status',$status);
        $this->assign('list',$list);
        $this->assign('uid',$uid);
        $this->display();
    }

    /**
     * 关联账号
     */
    public function sdkuser_list()
    {
        $uid = I('uid');
        $count = M('busi_sdk')->where(array('busi_uid'=>$uid))->count();
        $page = $this->page($count, 20);
        $list = M('busi_sdk')
            ->field('t1.*,t2.username')
            ->alias('t1')
            ->join('bt_business_player t2 on t1.busi_uid = t2.id')
            ->where(array('busi_uid'=>$uid))
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('create_time desc')
            ->select();


        $sdk_usernames = '';
        foreach($list as $v)
        {
            $sdk_usernames.=$v['sdk_username'].',';
        }
        $sdk_usernames = trim($sdk_usernames,',');

        $products = M('products')->where(array('account'=>array('in',$sdk_usernames),'status'=>array('in','1,2,3')))->getfield('account',true);


        foreach($list as $k=>$v)
        {
            $list[$k]['selling'] = in_array($v['sdk_username'],$products)?1:0;
        }

        $this->assign('page',$page->show('Admin'));
        $this->assign('list',$list);
        $this->assign('uid',$uid);
        $this->display();
    }

    /**
     * 取消关联
     */
    public function unbind_sdkuser()
    {
        $uid = I('uid');
        $sdk_username = I('sdk_username');

        $info = M('busi_sdk')->where(array('busi_uid'=>$uid,'sdk_username'=>$sdk_username))->find();

        if(!$info)
        {
            $this->error('不存在绑定关系');
        }

        $product_info = M('products')->where(array('uid'=>$uid,'account'=>$sdk_username,'status'=>array('in','1,2,3')))->find();

        if($product_info)
        {
            $this->error('该账号正在交易中');
        }

        if(M('busi_sdk')->where(array('busi_uid'=>$uid,'sdk_username'=>$sdk_username))->delete()!==false)
        {
            //解绑成功 删除该账号相关的商品
            M('products')->where(array('uid'=>$uid,'account'=>$sdk_username))->delete();
            $this->success('解绑成功');
        }
        $this->error('解绑失败');
    }

}
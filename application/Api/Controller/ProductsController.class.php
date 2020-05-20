<?php
/**
 * 商品控制器
 * @author qing.li
 * @date 2018-06-12
 *
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class ProductsController extends AppframeController
{
    private $products_model;
    private $page_size = 20;


    public function _initialize()
    {
        $this->products_model = M('products');

        if(!is_dir(SITE_PATH."data/log/185sy/".date('Y-m-d',time())))
        {
            mkdir(SITE_PATH."data/log/185sy/".date('Y-m-d',time()),0777);
        }

        $file_name = SITE_PATH."data/log/185sy/".date('Y-m-d',time())."/products.log";

        $log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n\r\n";

        file_put_contents($file_name,$log,FILE_APPEND);
    }

    /**
     * 账号可交易游戏
     */
    public function game_by_sdkuser()
    {
        $uid = I('uid');

        if (empty($uid)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $user_info = M('player')->where(array('id'=>$uid))->find();

        //获取所有有充值记录的游戏
        $appids = M('inpour')->where(array('uid' => $uid, 'status' => array('in','1,2')))->group('appid')->getfield('appid', true);


        if(!empty($appids)) {
            $app_infos = M('game')->field('id,game_name,tag,double_platform')->where(array('id' => array('in', implode(',', $appids)), 'trade' => 1))->select();

            $tags = '';

            foreach ($app_infos as $v) {
                $tags .= $v['tag'] . ',';
            }
            $tags = trim($tags, ',');



            $game_info = M('game', 'syo_', C('185DB'))->where(array('tag' => array('in', $tags)))->getfield('tag,gamename,system,logo', true);


            $list = array();
            $pro = M('products');
            foreach ($app_infos as $v) {
                $item['appid'] = $v['id'];
                $item['game_name'] = $v['game_name'];
                $item['logo'] = $game_info[$v['tag']]['logo'];
                $item['logo'] = $item['logo'] ? C('CDN_URL') . $item['logo'] : '';
                $sdk_infos = M('app_player p')
                    ->field('p.id app_uid,p.nick_name,i.deviceType')
                    ->join('left join __INPOUR__ i on i.app_uid = p.id')
                    ->where(array('p.uid'=>$uid,'p.appid'=>$v['id'],'i.status'=>array('in','1,2')))
                    ->group('app_uid')
                    ->select();
                $sdk_item = array();
                $item['sdk_list'] = array();
                foreach($sdk_infos as $val){
                    $exist = $pro->where(array('appid'=>$v['id'],'account'=>$val['app_uid'],'status'=>array('in','1,2,3')))->getField('id');
                    $sdk_item['app_uid'] = $val['app_uid'];
                    $sdk_item['nick_name'] = $val['nick_name'];
                    $sdk_item['selling'] = 0;
                    if($exist){
                        $sdk_item['selling'] = 1;
                    }
                    if ($v['double_platform'] == 1) {
                        $sdk_item['system'] = 3;
                    } elseif ($val['deviceType'] == 2) {
                        $sdk_item['system'] = 2;
                    } else {
                        $sdk_item['system'] = 1;
                    }
                    $item['sdk_list'][] = $sdk_item;
                }
                //$item['sdk_list'] = $sdk_infos;
                $list[] = $item;
            }
        }
        $data['list'] = $list;
        $data['alipay_account'] = $user_info['alipay_account'];
        $this->ajaxReturn($data);
       

    }

    /**
     * 卖商品
     */
    public function sell_product()
    {
        $uid = I('uid');
        $appid = I('appid');
        $title = I('title');
        $app_uid = I('app_uid');
        $price = I('price');
        $desc = I('desc');
        $system = I('system');
        $server_name = I('server_name');
        $end_time = I('end_time');


        $arr = array(
            'uid' => $uid,
            'appid' => $appid,
            'title' => $title,
            'app_uid' => $app_uid,
            'price' => $price,
            'desc' => $desc,
            'system' => $system,
            'server_name' => $server_name,
            'end_time' => $end_time,
            'sign' => I('sign'),
        );
        logs('products',$arr);
        if (empty($uid) || empty($appid) || empty($title) || empty($app_uid) || empty($desc) ||
            empty($price) || empty($system) || empty($server_name) || empty($end_time)
        ) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $products_config = C('ACCOUNT_PRODUCTS');

        if ($price < $products_config['price_limit']) {
            $this->ajaxReturn(null, '价格不能低于' . $products_config['price_limit'], 0);
        }

        if ($end_time <= date('Y-m-d', time())) {
            $this->ajaxReturn(null, '时间期限不合法', 0);
        }



        $res = checksign($arr, C('API_KEY'));

        if (!$res)
        {
            $this->ajaxReturn(null, '签名错误', 0);
        }


        $user_info = M('player')->where(array('id' => $uid))->find();

        if (!$user_info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        if(empty($user_info['alipay_account']))
        {
            $this->ajaxReturn(null, '用户需要绑定支付宝账号才能进行交易', 0);
        }

        $app_info = M('game')->where(array('id' => $appid))->count();

        if (!$app_info) {
            $this->ajaxReturn(null, '游戏不存在', 0);
        }

        //查询商品账号是否已经存在
        $account_exists = $this->products_model->where(array('account' => $app_uid, 'status' => array('in', '1,2,3')))->count();

        if ($account_exists > 0) {
            $this->ajaxReturn(null,'该账号已被出售，不能重复提交',0);
        }

        $imgs_files = $_FILES;
        unset($imgs_files['trade_imgs']);
        $trade_imgs_files = $_FILES;
        unset($trade_imgs_files['imgs']);

        if ($_FILES['imgs']['name'][0]) {

            $savepath = date('Ymd') . '/';
            //上传处理类
            $config = array(
                'rootPath' => './' . C("UPLOADPATH"),
                'savePath' => $savepath,
                'maxSize' => 10485760,
                'exts' => array('jpg', 'png', 'jpeg'),
                'autoSub' => false,
            );
            $upload = new \Think\Upload($config);
            $info = $upload->upload($imgs_files);
            if (!$info) {
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {
                foreach ($info as $v) {
                    $file_name = trim($v['fullpath'], '.');
                    $src[] = str_replace('/www.sy217.com', '', $file_name);
                }
                $arr['imgs'] = json_encode($src);
            }
        }
        
        if($_FILES['trade_imgs']['name'][0])
        {
            $savepath = date('Ymd') . '/';
            //上传处理类
            $config = array(
                'rootPath' => './' . C("UPLOADPATH"),
                'savePath' => $savepath,
                'maxSize' => 10485760,
                'exts' => array('jpg', 'png', 'jpeg'),
                'autoSub' => false,
            );
            $upload = new \Think\Upload($config);
            $info = $upload->upload($trade_imgs_files);
            $src = array();
            if (!$info) {
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {
                foreach ($info as $v) {
                    $file_name = trim($v['fullpath'], '.');
                    $src[] = str_replace('/www.sy217.com', '', $file_name);
                }
                $arr['trade_imgs'] = json_encode($src);
            }
        }

        $arr['create_time'] = time();
        $arr['account'] = $app_uid;
        $arr['end_time'] = strtotime($arr['end_time'])+(3600*24)-1; //截至到当天晚上23：59分
        unset($arr['sign']);

        if (($product_id = $this->products_model->add($arr)) !== false) {
            //提交商品成功后即进入审核状态，锁定该账号（只有下架或者交易成功才能解锁）
            $player_closed_data = array(
                'uid' => $app_uid,
                'type' => 1,
                'end_time' => $arr['end_time'],
                'create_time' => time(),
                'remark'=>'账号正在交易中，不能登陆游戏',

            );
            M('player_closed')->add($player_closed_data);

            //商品上架审核 发送信息队列
            $link = U('Admin/AccountTrade/goodsIndex');
            //create_admin_message(2,$product_id,'all',$link);

            $this->ajaxReturn(null, '添加成功');
        }

        $this->ajaxReturn(null, '添加失败', 0);

    }

    /**
     * 商品列表
     */
    public function get_product_list()
    {
        $game_name = I('game_name');
        $system = I('system');
        $order = I('order');
        $order_type = I('order_type');
        $page = I('page');

        $arr = array(
            'game_name' => $game_name,
            'system' => $system,
            'order' => $order,
            'order_type' => $order_type,
            'page' => $page,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res)
        {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $now_time = time();
        $map = array();
        $map['a.status'] = 2;
        $map['a.end_time'] = array('egt', $now_time);
        if ($system) $map['a.system'] = $system;

        if ($game_name)
        {
            $appid = M('game')->where(array('source' => 1, 'game_name' => array('like',  '%'.$game_name . '%')))->getfield('id', true);
            if($appid)
            {
                $map['a.appid'] = array('in', implode(',',$appid));
            }
            else
            {
                $map['a.appid'] = array('in', '');
            }
        }

        $order_type = ($order_type == 2) ? 'asc' : 'desc';
        $order = ($order == 2) ? 'a.price' : 'a.publish_time';

        $order = $order . ' ' . $order_type;

        $count = $this->products_model->alias('a')->where($map)->count();

        $page = ($page > 0) ? $page : 1;
        $list = $this->products_model
            ->alias('a')
            ->join('bt_game as b on a.appid =b.id')
            ->field('a.id,b.game_name,a.title,a.price,a.system,a.server_name,a.publish_time,a.imgs')
            ->where($map)
            ->limit(($page - 1) * $this->page_size, $this->page_size)
            ->order($order)
            ->select();


        foreach ($list as $k => $v) {
            $imgs = json_decode($v['imgs'], true);
            $list[$k]['imgs'] = $imgs[0] ? C('FTP_URL') . $imgs[0] : '';
        }

        //主动将过期的商品改为下架并解锁SDK账号
        $accounts = $this->products_model->where(array('status' => 2, 'end_time' => array('lt', $now_time)))->getfield('account', true);

        if($accounts)
        {

            if ($this->products_model->where(array('status' => 2, 'end_time' => array('lt', $now_time)))->save(array('status' => 5, 'off_reason' => '商品过期')) !== false)
            {
                M('player_closed')->where(array('uid' => array('in', implode(',', $accounts)),'type' => 1, 'end_time' => array('egt', $now_time)))->delete();
            }
        }

        $data = array(
            'count' => ceil($count / $this->page_size),
            'list' => $list,
        );

        $this->ajaxReturn($data);

    }



    /**
     * 下架商品（出售中）
     */
    public function withdraw_product()
    {
        $uid = I('uid');
        $product_id = I('product_id');

        if(empty($uid) || empty($product_id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid'=>$uid,
            'product_id'=>$product_id,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $product_info = $this->products_model->where(array('id'=>$product_id))->find();

        if(!$product_info)
        {
            $this->ajaxReturn(null,'商品不存在',0);
        }

        if($product_info['status'] != 2)
        {
            $this->ajaxReturn(null,'商品不能下架',0);
        }

        if($product_info['uid'] !=$uid)
        {
            $this->ajaxReturn(null,'商品不属于用户',0);
        }

        //下架操作 先操作下架成功后将SDK账号锁定解除
        if($this->products_model->where(array('id'=>$product_id))->save(array('status'=>5,'off_reason'=>'用户手动下架'))!==false)
        {

            M('player_closed')->where(array('uid' =>$product_info['account'],'type' => 1 , 'end_time' => array('egt', time())))->delete();
            $this->ajaxReturn(null,'操作成功');
        }

        $this->ajaxReturn(null,'操作失败',0);

    }



    /**
     * 删除商品（下架或者交易成功）
     */
    public function delete_product()
    {
        $uid = I('uid');
        $product_id = I('product_id');

        if(empty($uid) || empty($product_id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid'=>$uid,
            'product_id'=>$product_id,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $product_info = $this->products_model->where(array('id'=>$product_id))->find();

        if(!$product_info)
        {
            $this->ajaxReturn(null,'商品不存在',0);
        }

        if($product_info['status'] != 5)
        {
            $this->ajaxReturn(null,'商品只有下架才能删除',0);
        }

        if($product_info['uid'] !=$uid)
        {
            $this->ajaxReturn(null,'商品不属于用户',0);
        }

        if($this->products_model->where(array('id'=>$product_id))->delete()!==false)
        {
            $this->ajaxReturn(null,'操作成功');
        }
        $this->ajaxReturn(null,'操作失败',0);

    }

    /**
     * 申请上架（下架情况下）
     */
    public function apply_onsale()
    {
        $uid = I('post.uid');
        $product_id = I('post.product_id');
        $title = I('post.title');
        $price = I('post.price');
        $desc = I('post.desc');
        $system = I('post.system');
        $server_name = I('post.server_name');
        $end_time = I('post.end_time');

        if(empty($uid) || empty($product_id) || empty($title) || empty($price) || empty($desc) || empty($system) || empty($server_name) || empty($end_time))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid'=>$uid,
            'product_id'=>$product_id,
            'title'=>$title,
            'price'=>$price,
            'desc'=>$desc,
            'system'=>$system,
            'server_name'=>$server_name,
            'end_time'=>$end_time,
            'sign'=>I('sign')
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $product_info = $this->products_model->where(array('id'=>$product_id))->find();

        if(!$product_info)
        {
            $this->ajaxReturn(null,'商品不存在',0);
        }

        if($product_info['status'] != 5)
        {
            $this->ajaxReturn(null,'商品不能上架',0);
        }

        if($product_info['uid'] !=$uid)
        {
            $this->ajaxReturn(null,'商品不属于用户',0);
        }

        $products_config = C('ACCOUNT_PRODUCTS');

        if ($price < $products_config['price_limit']) {
            $this->ajaxReturn(null, '价格不能低于' . $products_config['price_limit'], 0);
        }

        if ($end_time <= date('Y-m-d', time())) {
            $this->ajaxReturn(null, '时间期限不合法', 0);
        }


        $user_info = M('player')->where(array('id' => $uid))->find();

        if (!$user_info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        if(empty($user_info['alipay_account']))
        {
            $this->ajaxReturn(null, '用户需要绑定支付宝账号才能进行交易', 0);
        }

        //查询商品账号是否已经存在
        $account_exists = $this->products_model->where(array('account' => $product_info['account'], 'status' => array('in', '1,2,3')))->count();

        if ($account_exists > 0) {
            $this->ajaxReturn(null,'该账号已被出售，不能重复提交',0);
        }


        $arr['end_time'] = strtotime($end_time)+(3600*24)-1;


        $imgs_files = $_FILES;
        unset($imgs_files['trade_imgs']);
        $trade_imgs_files = $_FILES;
        unset($trade_imgs_files['imgs']);

        if ($_FILES['imgs']['name'][0]) {
            $savepath = date('Ymd') . '/';
            //上传处理类
            $config = array(
                'rootPath' => './' . C("UPLOADPATH"),
                'savePath' => $savepath,
                'maxSize' => 10485760,
                'exts' => array('jpg', 'png', 'jpeg'),
                'autoSub' => false,
            );
            $upload = new \Think\Upload($config);
            $info = $upload->upload($imgs_files);
            if (!$info) {
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {
                foreach ($info as $v) {
                    $file_name = trim($v['fullpath'], '.');
                    $src[] = str_replace('/www.sy217.com', '', $file_name);
                }
                $arr['imgs'] = json_encode($src);
            }
        }

        if ($_FILES['trade_imgs']['name'][0]) {
            $savepath = date('Ymd') . '/';
            //上传处理类
            $config = array(
                'rootPath' => './' . C("UPLOADPATH"),
                'savePath' => $savepath,
                'maxSize' => 10485760,
                'exts' => array('jpg', 'png', 'jpeg'),
                'autoSub' => false,
            );
            $upload = new \Think\Upload($config);
            $info = $upload->upload($trade_imgs_files);
            $src = array();
            if (!$info) {
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {
                foreach ($info as $v) {
                    $file_name = trim($v['fullpath'], '.');
                    $src[] = str_replace('/www.sy217.com', '', $file_name);
                }
                $arr['trade_imgs'] = json_encode($src);
            }
        }

        $arr['status'] = 1;
        $arr['off_reason'] = '';

        //提交审核锁定账号
        if($this->products_model->where(array('id'=>$product_id))->save($arr)!==false)
        {
            //提交商品成功后即进入审核状态，锁定该账号（只有下架或者交易成功才能解锁）
            $player_closed_data = array(
                'uid' => $product_info['account'],
                'type' => 1,
                'end_time' => $arr['end_time'],
                'create_time' => time(),
                'remark'=>'账号正在交易中，不能登陆游戏',

            );
            M('player_closed')->add($player_closed_data);

            //商品重新上架审核 发送信息队列
            $link = U('Admin/AccountTrade/goodsIndex');
            create_admin_message(2,$product_id,'all',$link);
            $this->ajaxReturn(null, '操作成功');
        }
        $this->ajaxReturn(null,'操作失败',0);

    }

    /**
     * 商品详情
     */
    public function product_info()
    {
        $product_id = I('product_id');
        $system = I('system');
        $uid = I('uid');


        if(empty($product_id) || empty($system))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr =array(
            'product_id'=>$product_id,
            'system'=>$system,
            'uid'=>$uid,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $field = 'title,account,appid,server_name,system,desc,imgs,price,end_time';

        $where= array('id'=>$product_id);
        if($uid>0)
        {
            $field.=',trade_imgs';
            $where['uid'] = $uid;
        }

        $product_info = $this->products_model->field($field)->where($where)->find();


        if(!$product_info)
        {
            $this->ajaxReturn(null,'商品不存在',0);
        }

        $now_time = time();
        if($product_info['end_time'] < $now_time && $product_info['status'] == 2)
        {
            //解锁下架
            if ($this->products_model->where(array('id'=>$product_id))->save(array('status' => 5, 'off_reason' => '商品过期')) !== false)
            {
                $sdk_uid = M('player')->where(array('username'=>$product_info['account']))->getfield('id');
                M('player_closed')->where(array('uid' => $sdk_uid, 'end_time' => array('egt', $now_time)))->delete();
            }
            $this->ajaxReturn(null,'商品已过期',0);
        }

        $appinfo = M('game')->where(array('id'=>$product_info['appid']))->field('tag,game_name,android_package_name,android_url,ios_package_name,ios_url,double_platform,h5')->find();
        //获取游戏的logo,id，大小
        $where = array();
        $where['tag'] = $appinfo['tag'];

        $game_info  = M('game','syo_',C('185DB'))->field('id,logo,size')->where($where)->find();

        $player_info = M('app_player')->where(array('id'=>$product_info['account']))->field('id,nick_name,create_time')->find();


        $pay_money = M('inpour'
        )->where(array('create_time'=>array('elt',time()),'status'=>array('in','1,2'),'app_uid'=>$product_info['account'],'appid'=>$product_info['appid']))
            ->getfield('sum(money) money');



        $product_info['sdk_uid'] = $product_info['account'];
        $product_info['nick_name'] = $player_info['nick_name'];
        $product_info['box_gameid'] = $game_info['id'];
        $product_info['game_name'] = $appinfo['game_name'];
        $product_info['game_logo'] = C('CDN_URL').$game_info['logo'];
        $product_info['size'] = $game_info['size'];
        $product_info['account_cretime'] = $player_info['create_time'];
        $product_info['pay_money'] = $pay_money;
        $product_info['imgs'] = json_decode($product_info['imgs'],true);


        foreach($product_info['imgs'] as $k=>$v)
        {
            $product_info['imgs'][$k] = C('FTP_URL').$v;
        }
        unset($product_info['appid']);

        if($uid > 0)
        {
            if(($appinfo['android_package_name'] &&$appinfo['android_url'] &&$appinfo['ios_package_name'] &&$appinfo['ios_url']&& $appinfo['double_platform'])
            ||strlen(trim($appinfo['h5']))>0)
            {
                $product_info['system_enabled'][] = 3;
            }
            else
            {
                if($appinfo['android_package_name'] &&$appinfo['android_url']) $product_info['system_enabled'][] = 1;
                if($appinfo['ios_package_name'] &&$appinfo['ios_url']) $product_info['system_enabled'][] = 2;
            }


            $product_info['trade_imgs'] = json_decode($product_info['trade_imgs'],true);
            foreach($product_info['trade_imgs'] as $k=>$v)
            {
                $product_info['trade_imgs'][$k] = C('FTP_URL').$v;
            }

        }



        $this->ajaxReturn($product_info);

    }


    /**
     * 我的商品
     */
    public function get_product_by_user()
    {
        $uid = I('uid');
        $status = I('status');
        $page = I('page');

        if(empty($uid))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid'=>$uid,
            'status'=>$status,
            'page'=>$page,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $map = array('a.uid'=>$uid);

        if($status) $map['a.status'] = $status;

        $count = $this->products_model->alias('a')->where($map)->count();

        $page = ($page>0)?$page:1;
        $list = $this->products_model
            ->alias('a')
            ->join('bt_game as b on a.appid =b.id')
            ->field('a.id,b.game_name,a.title,a.price,a.system,a.server_name,a.create_time,a.imgs,a.status,a.off_reason,a.account')
            ->where($map)
            ->limit(($page - 1) * $this->page_size, $this->page_size)
            ->select();


        $off_product_accounts = '';
        foreach($list as $v)
        {
            if($v['status'] == 5)
            {
                $off_product_accounts.=$v['account'].',';
            }
        }
        $off_product_accounts = trim($off_product_accounts,',');

        if($off_product_accounts)
        {
            $onsale_enabled = $this->products_model->where(array('account'=>array('in',$off_product_accounts),'status'=>array('in','1,2,3')))->getfield('account',true);
        }


        foreach($list as $k=>$v)
        {
            if($v['status'] == 5)
            {
                $list[$k]['onsale_enabled'] = in_array($v['account'],$onsale_enabled)?0:1;
            }
            else
            {
                $list[$k]['onsale_enabled'] = 0;
            }
        }

        foreach($list as $k=>$v)
        {
            $imgs = json_decode($v['imgs'], true);
            $list[$k]['imgs'] = $imgs[0]?C('FTP_URL').$imgs[0]:'';
        }

        $this->ajaxReturn(array('count'=>ceil($count/$this->page_size),'list'=>$list));
    }

    /**
     *客服信息
     */
    public function customer()
    {
        $product_customerqq = trim(trim(get_site_options('product_customerqq'),','));
        $this->ajaxReturn(explode(',',$product_customerqq));
    }

    /**
     * 交易须知
     */
    public function trade_notes()
    {
        $site_options = get_site_options();
        $trade_notes = array();
        $trade_notes['buyer_notes'] = $site_options['buyer_notes'];
        $trade_notes['seller_notes'] = $site_options['seller_notes'];
        $trade_notes['business_notice'] = $site_options['business_notice'];
        $account_products = C('ACCOUNT_PRODUCTS');
        $trade_notes['product_price_limit'] = $account_products['price_limit'];
        $this->ajaxReturn($trade_notes);
    }






}








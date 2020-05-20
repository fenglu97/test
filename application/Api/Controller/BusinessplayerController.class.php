<?php
/**
 * 交易账号
 * @author qing.li
 * @date 2018-06-05
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class BusinessplayerController extends AppframeController
{
    private $business_player_model;
    private $token_valid_time = 300;

    public function _initialize()
    {
        $this->business_player_model = M('business_player');

        if(!is_dir(SITE_PATH."data/log/185sy/".date('Y-m-d',time())))
        {
            mkdir(SITE_PATH."data/log/185sy/".date('Y-m-d',time()),0777);
        }

        $file_name = SITE_PATH."data/log/185sy/".date('Y-m-d',time())."/businessplayer.log";

        $log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n\r\n";

        file_put_contents($file_name,$log,FILE_APPEND);
    }

    /**
     * 注册
     */
    public function register()
    {
        $post_data = I('post.');
        $code = $post_data['code'];
        $mobile = $post_data['mobile'];
        $password = $post_data['password'];
        $system = $post_data['system'];
        $maker = $post_data['maker'];
        $mobile_model = $post_data['mobile_model'];
        $machine_code = $post_data['machine_code'];
        $system_version = $post_data['system_version'];
        $client = $post_data['client']; //默认1为手机端 2为web端


        if (empty($code) || empty($mobile) || empty($password) || empty($system) || empty($client))
        {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $pass_len = strlen($password);

        if($pass_len < 6 || $pass_len > 16)
        {
            $this->ajaxReturn(null,'密码长度为6-16位!',0);
        }

        //验证手机号
        if (!preg_match("/^1\d{10}$/", $mobile))
        {
            $this->ajaxReturn(null, '手机号码格式有误', 0);
        }

        $arr = array(
            'code' => $code,
            'mobile' => $mobile,
            'password' => $password,
            'system' => $system,
            'maker' => $maker,
            'mobile_model' => $mobile_model,
            'machine_code' => $machine_code,
            'system_version' => $system_version,
            'client' => $client,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //验证手机验证码
        $res = checkSMSCode($mobile, $code);

        if ($res == 1)
        {
            $this->ajaxReturn(null, '验证码过期', 0);
        }
        elseif ($res == 2)
        {
            $this->ajaxReturn(null, '验证码错误', 0);
        }

        $count = $this->business_player_model->where(array('mobile' => $mobile))->count();

        if ($count > 0) {
            $this->ajaxReturn(null, '手机号已存在', 0);
        }

        //自动生成账号，以BM开头
        $max_id = $this->business_player_model->field('max(id)')->find();
        $username = 'BM' . str_pad($max_id['max(id)'] + 1, 5, "0", STR_PAD_LEFT);

        $time = time();
        $arr['username'] = $username;
        $arr['salt'] = $salt = getRandomString(6);
        $arr['password'] = sp_password_by_player($password,$arr['salt']);
        $arr['regip'] = ip2long(get_client_ip(0,true));
        $arr['create_time'] = $time;

        $rst = $this->business_player_model->add($arr);

        if ($rst !== false) {
            $data = array(
                'uid' => $rst,
                'username' => $username,
                'mobile' => $mobile,
                'icon_url' => get_avatar_url(''),
            );

            if($client == 2)
            {
                $_SESSION['business_player']['user_id'] = $rst;
                $_SESSION['business_player']['username'] = $username;
                $_SESSION['business_player']['mobile'] = $mobile;
                $_SESSION['business_player']['icon_url'] = get_avatar_url('');
            }

            $this->ajaxReturn($data, '注册成功');
        } else {
            $this->ajaxReturn(null, '注册失败', 0);
        }


    }

    /**
     * 登陆
     */
    public function login()
    {
        $post_data = I('post.');
        $username = $post_data['username'];
        $password = $post_data['password'];
        $system = $post_data['system'];
        $machine_code = $post_data['machine_code']?$post_data['machine_code']:'';
        $client = $post_data['client'];//默认1为手机端 2为web端

        if (empty($username) || empty($password) || empty($system) || empty($client))
        {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $pass_len = strlen($password);
        if($pass_len < 6 || $pass_len > 16)
        {
            $this->ajaxReturn(null,'密码长度为6-16位!',0);
        }

        $arr = array(
            'username' => $username,
            'password' => $password,
            'system' => $system,
            'machine_code' => $machine_code,
            'client' => $client,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $user_info = $this->business_player_model->where(array('username' => $username, 'mobile' => $username, '_logic' => 'or'))->find();

        if (!$user_info)
        {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        if($user_info['state'] == 2)
        {
            $this->ajaxReturn(null, '用户已被禁号', 0);
        }

        if (sp_password_by_player($password, $user_info['salt']) == $user_info['password']) {

            $this->business_player_model->where(array('id'=>$user_info['id']))->save(array('last_login_time'=>time()));

            $data = array(
                'uid' => $user_info['id'],
                'username' => $user_info['username'],
                'mobile' => $user_info['mobile'],
                'icon_url' => get_avatar_url($user_info['icon_url']),
            );

            if($client == 2)
            {
                $_SESSION['business_player']['user_id'] = $user_info['id'];
                $_SESSION['business_player']['username'] = $user_info['username'];
                $_SESSION['business_player']['mobile'] = $user_info['mobile'];
                $_SESSION['business_player']['icon_url'] = get_avatar_url($user_info['icon_url']);
            }

            $this->ajaxReturn($data, '登陆成功');
        }
        else
        {
            $this->ajaxReturn(null, '密码错误', 0);
        }

    }

    /**
     * 修改密码
     */
    public function modify_password()
    {
        $post_data = I('post.');
        $uid = $post_data['uid'];
        $password = $post_data['password'];
        $newpassword = $post_data['newpassword'];

        if (empty($uid) || empty($password) || empty($newpassword)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $pass_len_new = strlen($newpassword);
        if($pass_len_new < 6 || $pass_len_new > 16)
        {
            $this->ajaxReturn(null,'新密码长度为6-16位!',0);
        }

        $arr = array(
            'uid' => $uid,
            'password' => $password,
            'newpassword' => $newpassword,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $info = $this->business_player_model->where(Array('id' => $uid))->find();

        if (!$info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        if ($info['password'] != sp_password_by_player($password, $info['salt'])) {
            $this->ajaxReturn(null, '原密码错误', 0);
        }

        $save['password'] = sp_password_by_player($newpassword, $info['salt']);
        $save['modify_time'] = time();

        if ($this->business_player_model->where(array('id' => $uid))->save($save) !== false) {
            $this->ajaxReturn(null, '修改成功');
        } else {
            $this->ajaxReturn(null, '修改失败', 0);
        }
    }

    /**
     * 忘记密码
     */
    public function forget_password()
    {
        $post_data = I('post.');
        $mobile = $post_data['mobile'];
        $code = $post_data['code'];
        $password = $post_data['password'];

        if(empty($mobile) || empty($code) || empty($password))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $pass_len = strlen($password);
        if($pass_len < 6 || $pass_len > 16)
        {
            $this->ajaxReturn(null,'密码长度为6-16位!',0);
        }

        $arr = array(
            'mobile'=>$mobile,
            'code'=>$code,
            'password'=>$password,
            'sign'=>I('sign')
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $info = $this->business_player_model->where(array('mobile'=>$mobile))->find();

        if(!$info)
        {
            $this->ajaxReturn(null,'用户不存在',0);
        }

        $res = checkSMSCode($mobile,$code);
        if($res == 1)
        {
            $this->ajaxReturn(null,'验证码过期',0);
        }
        elseif($res == 2)
        {
            $this->ajaxReturn(null,'验证码错误',0);
        }

        $save['password'] = sp_password_by_player($password,$info['salt']);
        $save['modify_time'] = time();

        if($this->business_player_model->where(array('mobile'=>$mobile))->save($save) !== false)
        {
            $this->ajaxReturn(null,'修改成功');
        }
        else
        {
            $this->ajaxReturn(null,'修改失败',0);
        }
    }


    /**
     * 查看用户信息
     */
    public function user_info()
    {
        $uid = I('uid');

        if (empty($uid))
        {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));
        if (!$res)
        {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $info = M('player')
            ->field('mobile,alipay_account,real_name,icon_url')
            ->where(array('id'=> $uid))->find();

        if (!$info)
        {
            $this->ajaxReturn(null, '用户不存在', 0);
        } else
        {
            $info['qq'] = '';
            $info['icon_url'] = get_avatar_url($info['icon_url']);
            $this->ajaxReturn($info);
        }
    }

    /**
     * 修改用户信息
     */
    public function edit_user()
    {
        $uid = I('uid');
        $qq = I('qq');
        $alipay_account = I('alipay_account');
        $real_name = I('real_name');


        if (empty($uid)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }


        if (!empty($qq) && !preg_match("/^[1-9]\d{4,10}$/i",$qq)) {
            $this->ajaxReturn(null, 'qq号码有误', 0);
        }

        if(!empty($alipay_account) && !preg_match("/^1\d{10}$/", $alipay_account) && !filter_var($alipay_account, FILTER_VALIDATE_EMAIL))
        {
            $this->ajaxReturn(null,'请输入正确的支付宝账号',0);
        }

        if(!empty($real_name) && !isChineseName($real_name))
        {
            $this->ajaxReturn(null,'中文名不合法',0);
        }


        $arr = array(
            'uid' => $uid,
            'qq' => $qq,
            'alipay_account' => $alipay_account,
            'sign' => I('sign'),
        );
        $res = checkSign($arr, C('API_KEY'));
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //$info = $this->business_player_model->where(array('id' => $uid))->find();
        $info = M('player')->where(array('id' => $uid))->find();
        if (!$info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $data = array();


        //if ($qq) $data['qq'] = $qq;
        if ($alipay_account) $data['alipay_account'] = $alipay_account;
        if ($real_name) $data['real_name'] = $real_name;



        if ($_FILES['icon_url']['name']) {
            $savepath = date('Ymd') . '/';
            //上传处理类
            $config = array(
                'rootPath' => './' . C("UPLOADPATH"),
                'savePath' => $savepath,
                'maxSize' => 2097152,
                'saveName' => array('time', ''),
                'exts' => array('jpg', 'png', 'jpeg'),
                'autoSub' => false,
            );
            $upload = new \Think\Upload($config);
            $info = $upload->upload();

            if (!$info) {// 上传错误提示错误信息
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {// 上传成功
                $file_name = trim($info['icon_url']['fullpath'], '.');
                $data['icon_url'] = str_replace('/www.sy217.com', '', $file_name);
            }

        }

        if (M('player')->where(array('id' => $uid))->save($data) !== false) {
            $file_name = ($data['icon_url']) ? C('FTP_URL') . $data['icon_url'] : '';
            $this->ajaxReturn($file_name, '修改成功');
            //$this->ajaxReturn(null, '修改成功');
        }

        $this->ajaxReturn(null, '修改失败', 0);

    }

    /**
     * 查看信息是否完整
     */
    public function is_complete_info()
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
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $info = M('player')->where(array('id' => $uid))->find();

        if (!$info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        if (empty($info['mobile']) || empty($info['alipay_account'])) {
            $this->ajaxReturn(null, '用户信息不完整', 0);
        } else {
            $this->ajaxReturn(null, '用户信息完整');
        }

    }

    /**
     * 发短信
     */
    public function send_message()
    {

        $mobile = I('mobile');
        $type = I('type');
        $client = I('client'); //默认1为手机端 2为web端


        if(empty($mobile) || empty($type) || empty($client))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        if($type == 1)
        {
            $word = '注册';
        }
        elseif($type == 2)
        {
            $word = '找回密码';
        }
        elseif($type == 3)
        {
            $word = '绑定';
        }
        else
        {
            $word = '解绑';
        }


        $arr = array(
            'mobile'=>$mobile,
            'type'=>$type,
            'client'=>$client,
            'sign'=>I('sign'),
        );
        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        if(!preg_match("/^1\d{10}$/", $mobile))
        {
            $this->ajaxReturn(null,'手机号码格式有误',0);
        }

        $business_player = $this->business_player_model
            ->where(array('mobile'=>$mobile))
            ->count();

        if($type == 2 || $type == 4)
        {
            if(!$business_player)
            {
                $this->ajaxReturn(null,'手机号不存在',0);
            }
        }
        else
        {
            if($business_player)
            {
                $this->ajaxReturn(null,'手机号已存在',0);
            }
        }

        $num = createSMSCode();
//        $content = '用户您好，您'.$word.'的验证码是'.$num.'，5分钟输入有效。【'.C('BOX_NAME').'】';
        $result = sendSms($mobile,$num);

        if(!$result)
        {
            $this->ajaxReturn(null,'发送失败',0);
        }
        //            echo '发送失败返回值为:'.$line.'。请查看webservice返回值对照表';
        else
        {

            $data['mobile'] = $mobile;
            $data['code'] = $num;
            $smscode = M('smscode');
            $smscodeObj = $smscode->where(array('mobile'=>$mobile))->find();
            if($smscodeObj)
            {
                $data['update_time'] = date('Y-m-d H:i:s');
                $success = $smscode->where(array('mobile'=>$mobile))->save($data);
                if($success !== false)
                {
                    //web端不传验证码给客户端进行验证
                    if($client == 1)
                    {
                        $this->ajaxReturn(null,'发送成功');
                    }
                    else
                    {
                        $this->ajaxReturn(null,'发送成功');
                    }
                }
            }
            else
            {
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['update_time'] = $data['create_time'];
                if($smscode->create($data))
                {
                    $id = $smscode->add();
                    if($id)
                    {
                        //手机端传验证码给客户端进行验证
                        if($client==1)
                        {
                            $this->ajaxReturn(null,'发送成功');
                        }
                        else
                        {
                            $this->ajaxReturn(null,'发送成功');
                        }
                    }
                }
            }

            $this->ajaxReturn(null,'发送失败',0);
        }
    }

    /**
     * 关联SDK账号
     */
    public function bind_sdkuser()
    {
        $postdata = I('post.');
        $uid = $postdata['uid'];
        $sdk_username = $postdata['sdk_username'];
        $sdk_password = $postdata['sdk_password'];

        if(empty($uid) || empty($sdk_username) || empty($sdk_password))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr= array(
            'uid'=>$uid,
            'sdk_username'=>$sdk_username,
            'sdk_password'=>$sdk_password,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $business_player = $this->business_player_model->where(array('id'=>$uid))->find();

        if(!$business_player)
        {
            $this->ajaxReturn(null,'交易账号不存在',0);
        }

        $sdkuser_info = M('player')->where(array('username'=>$sdk_username))->find();

        if(!$sdkuser_info)
        {
            $this->ajaxReturn(null,'sdk账号不存在',0);
        }

        if ($sdkuser_info['password'] != sp_password_by_player($sdk_password, $sdkuser_info['salt'])) {
            $this->ajaxReturn(null, '密码错误', 0);
        }

        $info = M('busi_sdk')->where(array('sdk_username'=>$sdk_username))->find();

        if($info)
        {
            $this->ajaxReturn(null,'账号已被绑定',0);
        }

        $product_info = M('products')->where(array('account'=>$sdk_username,'status'=>array('in','1,2,3')))->find();

        if($product_info)
        {
            $this->ajaxReturn(null,'该账号正在交易中',0);
        }

        $data = array(
            'busi_uid'=>$uid,
            'sdk_uid'=>$sdkuser_info['id'],
            'sdk_username'=>$sdk_username,
            'create_time'=>time(),
        );

        if(M('busi_sdk')->add($data)!==false)
        {
            $this->ajaxReturn(null,'操作成功');
        }

        $this->ajaxRetur(null,'操作失败',0);


    }

    /**
     * 解除关联SDK账号（删除相关商品）
     */
    public function unbind_sdkuser()
    {
        $uid = I('post.uid');
        $sdk_username = I('post.sdk_username');

        if(empty($uid) || empty($sdk_username))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr= array(
            'uid'=>$uid,
            'sdk_username'=>$sdk_username,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }


        $info = M('busi_sdk')->where(array('busi_uid'=>$uid,'sdk_username'=>$sdk_username))->find();

        if(!$info)
        {
            $this->ajaxReturn(null,'不存在绑定关系',0);
        }

        $product_info = M('products')->where(array('uid'=>$uid,'account'=>$sdk_username,'status'=>array('in','1,2,3')))->find();

        if($product_info)
        {
            $this->ajaxReturn(null,'该账号正在交易中',0);
        }

        if(M('busi_sdk')->where(array('busi_uid'=>$uid,'sdk_username'=>$sdk_username))->delete()!==false)
        {
            //解绑成功 删除该账号相关的商品
            M('products')->where(array('uid'=>$uid,'account'=>$sdk_username))->delete();
            $this->ajaxReturn(null,'操作成功');
        }
        $this->ajaxReturn(null,'操作失败',0);
    }

    /**
     * 关联账号列表
     */
    public function sdkuser_list()
    {
        $uid = I('uid');

        if(empty($uid))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid'=>$uid,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $list = M('busi_sdk')->field('sdk_username')->where(array('busi_uid'=>$uid))->select();

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
            $list[$k]['game_list'] = array();

            //获取所有有充值记录的游戏
            $appids = M('inpour')->where(array('username' => $v['sdk_username'], 'status' => 1,))->group('appid')->getfield('appid', true);

            if($appids)
            {
                $app_infos = M('game')->field('id,game_name,tag,android_package_name,android_url,ios_package_name,ios_url,double_platform,h5')->where(array('id' => array('in', implode(',', $appids)),'trade' => 1))->select();

                $tags = '';

                foreach ($app_infos as $app_info) {
                    $tags .= $app_info['tag'] . ',';
                }
                $tags = trim($tags, ',');

                $android_game_info = M('game', 'syo_', C('185DB'))->where(array('android_pack_tag' => array('in', $tags)))->getfield('android_pack_tag,gamename,logo');


                $ios_game_info = M('game', 'syo_', C('185DB'))->where(array('ios_tag' => array('in', $tags)))->getfield('ios_tag,gamename,logo', true);


                $game_list = array();

                foreach ($app_infos as $app_info) {
                    $item = array();
                    $item['appid'] = $app_info['id'];
                    $item['game_name'] = $app_info['game_name'];
                    $item['logo'] = $android_game_info[$app_info['tag']]['logo'] ? $android_game_info[$app_info['tag']]['logo'] : $ios_game_info[$app_info['tag']]['logo'];
                    $item['logo'] = $item['logo'] ? C('185SY_URL') . $item['logo'] : '';

                    if(($app_info['android_package_name'] && $app_info['android_url'] && $app_info['ios_package_name'] && $app_info['ios_url'] && $app_info['double_platform'])||
                    strlen(trim($app_info['h5']))>0)
                    {
                        $item['system'][] = 3;
                    }
                    else
                    {
                        if($app_info['android_package_name'] &&$app_info['android_url']) $item['system'][] = 1;
                        if($app_info['ios_package_name'] &&$app_info['ios_url']) $item['system'][] = 2;
                    }

                    $game_list[] = $item;
                }
                $list[$k]['game_list'] = $game_list;
            }
        }

        $data = array('list'=>$list);
        $data['alipay_acount'] = $this->business_player_model->where(array('id'=>$uid))->getfield('alipay_account');

        $this->ajaxReturn($data);
    }

    public function get_user_info()
    {
        $this->ajaxReturn($_SESSION['business_player']);
    }

    public function log_out()
    {
        $_SESSION['business_player'] = null;
        $this->ajaxReturn(null,'退出成功');
    }

}
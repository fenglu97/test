<?php

namespace User\Controller;

use Common\Controller\AppframeController;
use Think\Log;

class GatewayController extends AppframeController
{

    /**
     * 用户登录
     */
    public function login()
    {

        $player = M("player");
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('username', 'require', '手机号/邮箱/用户名不能为空！', 1),
            array('password', 'require', '密码不能为空！', 1),
            array('username', '6,16', '用户名长度为6-16个字符！', 3, 'length'),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),

        );
        if ($player->validate($rules)->create() === false) {

            $this->ajaxReturn("", $player->getError(), 0);
        }

        $this->_do_user_login();

    }

    /**
     * 用户登录 -用户名 邮箱 手机号
     */
    private function _do_user_login()
    {

        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        $ip = trim(I('post.ip'));
        $ucenter = C("UCENTER_ENABLED");
        $time = time();
        $sign = trim(I('post.sign'));

        $channel = 1;
        $system = 1;
        $type = 1;

        // 校验签名部分
        $signData = array(
            'username' => $username,
            'password' => $password,
            'channel' => $channel,
            'system' => $system,
            'type' => $type,
            'sign' => $sign,
        );

        $status = checkSign($signData, C('API_KEY'));

        if (!$status) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        if (!$ip) {
            $ip = get_client_ip();
        }

        if ($ucenter) {

            $player = M('player');
            $player_action = M('player_action');
            if (preg_match("/^1[3456789]{1}\d{9}$/", $username)) {
                $where['mobile'] = $username;
            } else if (preg_match("/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i", $username)) {
                $where['email'] = $username;
            } else {
                $where['username'] = $username;
            }

//            $result = $player->where($where)->field('id,username,password,icon_url,email,status,salt,count,vip,vipfirst_login_time,last_login_time')->find();
            $result = $player->where($where)->find();

            if ($result) {

                if ($result['status'] == 1) {//正常


                    if ($result['last_login_time'] + 30 > $time) {

                        $this->ajaxReturn("", "操作过于频繁，请稍后再试!", 0);

                    }

                    if (sp_password_by_player($password, $result['salt']) == $result['password']) {

                        //记录于登录日志
                        if ($result['first_login_time'] == 0) {
                            $player->where(array('id' => $result["id"]))->save(array('first_login_time' => $time, 'last_login_time' => $time, 'count' => $result['count'] + 1));
                        } else {
                            $player->where(array('id' => $result["id"]))->save(array('last_login_time' => $time, 'count' => $result['count'] + 1));
                        }

                        //记录行为分析
                        $action_data = array(
                            'uid' => $result['id'],
                            'type' => 1,
                            'action' => "登录",
                            'ip' => ip2long($ip),
                            'status' => 1,
                            'extra' => "{}",
                            'create_time' => $time,
                        );

                        $player_action->add($action_data);

                        $data = array(
                            'user_id' => $result['id'],
                            'username' => $result['username'],
                            'nick_name' => $result['nick_name'],
                            'email' => $result['email'],
                            'coin' => $result['coin'],
                            'money' => $result['platform_money'],
                            'last_login_time' => $result['last_login_time'],
                            'timestamp' => $time,
                        );

                        $this->ajaxReturn($data, "success", 1);

                    } else {

                        //记录行为分析
                        $action_data = array(
                            'uid' => $result['id'],
                            'type' => 1,
                            'action' => "登录",
                            'ip' => ip2long($ip),
                            'status' => 0,
                            'extra' => "{}",
                            'create_time' => $time,
                        );

                        $player_action->add($action_data);

                        $this->ajaxReturn(null, '密码错误', 0);
                    }

                } else if ($result['status'] == 2) {//禁号
                    $this->ajaxReturn("", "账户限制登录", 0);
                } else {//删除
                    $this->ajaxReturn("", "用户不存在", 0);
                }

            } else {
                $this->ajaxReturn("", "用户不存在", 0);
            }

        } else {
            $this->ajaxReturn("", "服务器维护！", 2);
        }

    }

    /**
     * 用户登录 -邮箱
     */
    private function _do_email_login()
    {
        $this->ajaxReturn("", "暂未开放该登录方式！", 0);
    }

    /**
     * 用户登录 - 手机
     */
    private function _do_mobile_login()
    {
        $this->ajaxReturn("", "暂未开放该登录方式！", 0);
    }

    /**
     * 行为
     */
    private function action()
    {
        $this->ajaxReturn("", "暂未开放该登录方式！", 0);
    }

    /**
     * 用户注册
     */
    public function register()
    {

        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('username', 'require', '用户名不能为空！', 1),
            array('username', '6,16', '用户名长度为6-16个字符！', 3, 'length'),
            array('password', 'require', '密码不能为空！', 1),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),
            array('email', 'require', '邮箱不能为空！', 1 ),
            array('email','email','邮箱格式不正确！',1), // 验证email字段格式是否正确
        );

        $player = M("player");
        if ($player->validate($rules)->create() === false) {
            $this->ajaxReturn("", $player->getError(), 0);
        }

        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        $email = trim(I('post.email'));
        $ip = trim(I('post.ip'));
        $sign = trim(I('post.sign'));
        $channel = 1;
        $system = 1;
        $type = 1;
        $time = time();
        $salt = getRandomString(6);

        // 校验签名部分
        $signData = array(
            'username' => $username,
            'password' => $password,
            'channel' => $channel,
            'system' => $system,
            'type' => $type,
            'sign' => $sign,
        );

        $status = checkSign($signData, C('API_KEY'));

        if (!$status) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        if (!$ip) {
            $ip = get_client_ip();
        }

        //用户名需过滤的字符的正则
        $stripChar = '?<*.>\'"';
        if (preg_match('/[' . $stripChar . ']/is', $username) == 1) {
            $this->ajaxReturn(null, '用户名中包含' . $stripChar . '等非法字符！', 1);
        }

        //用户名需过滤的中文字符的正则
        if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $username, $match)) {
            $this->ajaxReturn(null, '用户名中包含中文字符！', 1);
        }

        $result = $player->where(array('regip' => ip2long($ip)))->field('create_time')->order('id desc')->find();

        if ($result['create_time'] + 30 > $time) {

            $this->ajaxReturn("", "操作过于频繁，请稍后再试!", 0);

        }

        if ($player->where(array('username' => $username))->field('id')->find()) {
            $this->ajaxReturn(null, '用户名已被注册', 0);
        }

        if ($player->where(array('email' => $email))->field('id')->find()) {
            $this->ajaxReturn(null, '邮箱已被注册', 0);
        }

        $udata = array(
            'username' => $username,
            'password' => sp_password_by_player($password, $salt),
            'mobile' => '',
            'email' => $email,
            'salt' => $salt,
            'regip' => ip2long($ip),
            'regtime' => $time,
            'appid' => 0,
            'channel' => $channel,
            'system' => 0,
            'source' => "platform",
            'create_time' => $time,
        );

        $rst = $player->add($udata);

        if ($rst) {
            $data = array(
                'id' => $rst,
                'username' => $username,
                'mobile' => '',
                'nick_name' => $username,
                'icon_url' => get_avatar_url(''),
                'coin' => 0,
                'platform_money' => 0
            );

            $this->ajaxReturn($data, '注册成功', 1);

        } else {

            $this->ajaxReturn(null, '注册失败', 0);

        }

    }

    /**
     * 用户注册 - 邮箱
     */
    public function doregister()
    {
        $this->ajaxReturn("", "暂未开放该注册方式！", 0);
    }

    /**
     * 用户注册 - 激活
     */
    public function doactive()
    {
        $this->ajaxReturn("", "暂未开放该激活方式！", 0);
    }

    /**
     * 用户找回密码 手机
     */
    public function do_mobile_forgot_password()
    {
        $this->ajaxReturn("", "暂未开放该找回密码方式！", 0);
    }

    /**
     * 用户找回密码 - 邮箱
     */
    public function do_forgot_password()
    {
        $this->ajaxReturn("", "暂未开放该找回密码方式！", 0);
    }

    /**
     * 重置密码
     */
    public function password_reset()
    {

        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        $ip = trim(I('post.ip'));
        $time = time();
        $sign = trim(I('post.sign'));

        $type = 2;

        // 校验签名部分
        $signData = array(
            'username' => $username,
            'password' => $password,
            'type' => $type,
            'sign' => $sign,
        );

        $status = checkSign($signData, C('API_KEY'));

        if (!$status) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        if (!$ip) {
            $ip = get_client_ip();
        }

        $player = M('player');
        $player_action = M('player_action');
        $salt = getRandomString(6);

        if (preg_match("/^1[3456789]{1}\d{9}$/", $username)) {
            $where['mobile'] = $username;
        } else if (preg_match("/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i", $username)) {
            $where['email'] = $username;
        } else {
            $where['username'] = $username;
        }

        $result = $player->where($where)->field('id,username,status')->find();

        if ($result) {

            if ($result['status'] == 1) {//正常

                $new_password = sp_password_by_player($password, $salt);

                //记录行为分析
                $action_data = array(
                    'uid' => $result['id'],
                    'type' => 2,
                    'action' => "重置密码",
                    'ip' => ip2long($ip),
                    'status' => 1,
                    'extra' => "{}",
                    'create_time' => $time,
                );

                $player_action->add($action_data);

                $player->where(array('id' => $result["id"]))->save(array('password' => $new_password, 'salt' => $salt));

                $this->ajaxReturn("", "success", 1);
            } else {
                $this->ajaxReturn("", "fail", 0);
            }
        }

    }

    /**
     * 修改密码
     */
    public function edit_password()
    {

        $uid = trim(I('post.uid'));
        $oldpassword = trim(I('post.oldpassword'));
        $password = trim(I('post.password'));
        $repassword = trim(I('post.repassword'));
        $ip = trim(I('post.ip'));
        $time = time();
        $sign = trim(I('post.sign'));

        $type = 1;

        // 校验签名部分
        $signData = array(
            'uid' => $uid,
            'oldpassword' => $oldpassword,
            'password' => $password,
            'type' => $type,
            'sign' => $sign,
        );

        $status = checkSign($signData, C('API_KEY'));

        if (!$status) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        if ($password != $repassword) {
            $this->ajaxReturn(null, '密码不一致', 0);
        }

        if (!$ip) {
            $ip = get_client_ip();
        }

        $player = M('player');
        $player_action = M('player_action');
        $salt = getRandomString(6);

        $result = $player->where(array('id' => $uid))->field('id,username,password,status,salt')->find();

        if ($result) {

            if ($result['status'] == 1) {//正常

                if (sp_password_by_player($password, $result['salt']) == $result['password']) {

                    $this->ajaxReturn("", "修改密码与原密码一致", 0);

                }

                if (sp_password_by_player($oldpassword, $result['salt']) == $result['password']) {

                    $new_password = sp_password_by_player($password, $salt);

                    //记录行为分析
                    $action_data = array(
                        'uid' => $result['id'],
                        'type' => 2,
                        'action' => "修改密码",
                        'ip' => ip2long($ip),
                        'status' => 1,
                        'extra' => "{}",
                        'create_time' => $time,
                    );

                    $player_action->add($action_data);

                    $player->where(array('id' => $result["id"]))->save(array('password' => $new_password, 'salt' => $salt));
//                    $player->where(array('id' => $result["id"]))->save(array('email' => $email, 'password' => $new_password, 'salt' => $salt));

                    $this->ajaxReturn("", "success", 1);

                } else {

                    $this->ajaxReturn("", "账户限制修改密码", 0);

                }

            } else {
                $this->ajaxReturn("", "fail", 0);
            }

        }

    }

    /**
     * 发送重置短信
     */
    protected function send_message()
    {
        $this->ajaxReturn("", "暂未开放该重置方式！", 0);
    }

    /**
     * 发送重置邮件
     */
    protected function send_to_resetpass()
    {
        $this->ajaxReturn("", "暂未开放该重置方式！", 0);
    }

    /**
     * 账号申诉
     */
    public function appeal()
    {
        $this->ajaxReturn("", "暂未开放该方式！", 0);
    }

    /**
     * 账号申诉 - 结果
     */
    public function appeal_result()
    {
        $this->ajaxReturn("", "暂未开放该方式！", 0);
    }

    /**
     * 用户登录 - 日志
     */
    public function login_log()
    {

        $username = trim(I('post.username'));
        $uid = trim(I('post.uid'));
        $time = time();
        $sign = trim(I('post.sign'));

        // 校验签名部分
        $signData = array(
            'username' => $username,
            'uid' => $uid,
            'type' => 2,
            'sign' => $sign,
        );

        $status = checkSign($signData, C('API_KEY'));

        if (!$status) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $player_action = M('player_action');

        $result = $player_action->where(array("uid" => $uid))->field('uid,type,ip,status,create_time')->select();

        $this->ajaxReturn($result, "success", 1);

    }

    /**
     * 用户详细信息
     */
    public function user_info()
    {

    }

    /**
     * 账号充值列表
     */
    public function pay_list()
    {

    }

    /**
     * 账号充值详情
     */
    public function pay_info()
    {

    }

    /**
     * 数据同步
     */
    public function sync()
    {

    }

    /**
     * 账户行为 - 适用于申诉 核对资料
     */
    public function analysis()
    {

    }


}
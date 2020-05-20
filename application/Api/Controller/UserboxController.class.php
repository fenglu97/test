<?php
/**
 * 用户中心接口
 * @author qing.li
 * @date 2017-08-30
 */

namespace Api\Controller;

use Common\Controller\AppframeController;
use Think\Log;

class UserboxController extends AppframeController
{
    private $msg_valid_time = 300;
    private $token_valid_time = 300;
    private $follow_page_size = 20;

    public function _initialize()
    {
        parent::_initialize();

        if (!is_dir(SITE_PATH . "data/log/185sy/" . date('Y-m-d', time()))) {
            mkdir(SITE_PATH . "data/log/185sy/" . date('Y-m-d', time()), 0777);
        }

        $file_name = SITE_PATH . "data/log/185sy/" . date('Y-m-d', time()) . "/user.log";

        $log = date('Y-m-d H:i:s', time()) . "\r\n" . ACTION_NAME . "\r\n" . urldecode(http_build_query($_REQUEST)) . "\r\n\r\n";

        file_put_contents($file_name, $log, FILE_APPEND);

        $this->player_model = M('player');
        $this->player_info_model = M('player_info');
        $this->player_login_logs_model = M('player_login_logs' . date('Ym', time()));
    }

    public function login()
    {

        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('username', 'require', '用户名不能为空！', 1),
            array('username', '6,16', '用户名长度为6-16个字符！', 3, 'length'),
            array('password', 'require', '密码不能为空！', 1),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),
            array('channel', 'require', '渠道ID不能为空', 1),
            array('system', 'require', '系统不能为空', 1),
        );

        if ($_POST['is_web'] == 0) {
            $rules[] = array('machine_code', 'require', '设备号不能为空', 1);
        }

        if ($this->player_model->validate($rules)->create() === false) {
            $this->ajaxReturn(null, $this->player_model->getError(), 0);
        }

        extract($_POST);


        $time = time();
        $arr = array(
            'username' => $username,
            'password' => $password,
            'channel' => $channel,
            'system' => $system,
            'machine_code' => $machine_code,
            'sign' => $sign
        );

        $res = checkSign($arr, C('API_KEY'));
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $appid = C('BOX_APP_ID');

        $channel_info = M('channel')->where(array('id' => $channel))->find();

        if (!$channel_info) {
            $this->ajaxReturn(null, '渠道不存在', 0);
        }

        $where['username'] = $username;


        $result = $this->player_model->where($where)->find();

        if (!$result && preg_match("/^1\d{10}$/", $username)) {
            //如果通过账号没有查询到，登录账号符号手机正则
            $result1 = $this->player_model->where(array('mobile' => $username))
                ->find();
            $result = $result1;
        }

        if ($result != null) {
            //如果用户存在 查询用户ID和machine_code是否被封号

            if ($is_web == 0) {
                $player_machine = M('player_machine')->
                where(array('machine_code' => $machine_code, 'end_time' => array('egt', $time)))->
                find();

                if ($player_machine) {
                    $this->ajaxReturn(null, $player_machine['remark'], 0);
                }
            }

            $player_closed = M('player_closed')->
            where(array('uid' => $result['id'], 'end_time' => array('egt', $time)))->
            find();

            if ($player_closed) {
                $this->ajaxReturn(null, $player_closed['remark'], 0);
            }

            if ((sp_password_by_player($password, $result['salt']) == $result['password'])) {

                //记录于登录日志
                if ($result['first_login_time'] == 0) {
                    $this->player_model->where(array('id' => $result["id"]))->save(array('first_login_time' => $time));
                }

                $log_data = array(
                    'uid' => $result['id'],
                    'username' => $result['username'],
                    'appid' => $appid,
                    'channel' => $result['channel'],
                    'system' => $system,
                    'ip' => ip2long(get_client_ip(0, true)),
                    'machine_code' => $machine_code,
                    'create_time' => $time,
                );

                $this->player_login_logs_model->add($log_data);

                $data = array(
                    'last_login_time' => $time,
                    'count' => $result['count'] + 1,
                );

                $this->player_model->where("id=" . $result["id"])->save($data);

                $player_other_info = $this->player_info_model->where(array('uid' => $result['id']))->find();

                $recom_bonus = M('coin_log')->where(array('uid' => $result['id'], 'type' => 3))->field('sum(coin_change) as coin_change')->find();

                $data = array(
                    'id' => $result['id'],
                    'username' => $result['username'],
                    'mobile' => $result['mobile'] ? $result['mobile'] : '',
                    'platform_money' => $result['platform_money'] ? $result['platform_money'] : 0,
                    'coin' => $result['coin'] ? $result['coin'] : 0,
                    'icon_url' => get_avatar_url($player_other_info['icon_url']),
                    'nick_name' => $player_other_info['nick_name'] ? $player_other_info['nick_name'] : $result['username'],
                    'recom_bonus' => isset($recom_bonus['coin_change']) ? $recom_bonus['coin_change'] : 0,
                    'is_vip' => (int)checkVip($result['id']),
                );


                if ($is_web == 1) {
                    //存入session
                    $_SESSION['webapp']['user_id'] = $result['id'];
                    $_SESSION['webapp']['nick_name'] = $data['nick_name'];
                    $_SESSION['webapp']['icon_url'] = $data['icon_url'];
                    $_SESSION['webapp']['username'] = $data['username'];
                }

                $this->ajaxReturn($data, '登陆成功');

            } else {

                $this->ajaxReturn(null, '密码错误', 0);
            }

        } else {

            $this->ajaxReturn(null, '用户不存在', 0);

        }

    }

    public function modify_password()
    {
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('id', 'require', '用户ID不能为空', 1),
            array('password', 'require', '原密码不能为空！', 1),
            array('newpassword', 'require', '新密码不能为空！', 1),
            array('newpassword', '6,16', '密码长度为6-16位！', 6, 'length'),
        );

        if ($this->player_model->validate($rules)->create() === false) {
            $this->ajaxReturn(null, $this->player_model->getError(), 0);
        }
        extract($_POST);

        $arr = array(
            'id' => $id,
            'password' => $password,
            'newpassword' => $newpassword,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $info = $this->player_model->where(array('id' => $id, 'status' => 1))->find();

        if ($info['password'] != sp_password_by_player($password, $info['salt'])) {

            $this->ajaxReturn(null, '原密码错误', 0);
        }

        $save['password'] = sp_password_by_player($newpassword, $info['salt']);
        $save['modify_time'] = time();
        if ($this->player_model->where(array('id' => $id))->save($save) !== false) {
            $this->ajaxReturn(null, '修改成功');
        } else {
            $this->ajaxReturn(null, '修改失败', 0);
        }
    }

    /**
     * 发送短信
     *
     */
    function send_message()
    {

        $mobile = I('mobile');
        $type = I('type');
        $client = I('client') ? I('client') : 0; //默认0为手机端 1为pc端
        $username = I('username');

        if (empty($mobile) || empty($type)) {
            $this->ajaxReturn(null, '参数错误', 0);
        }

        if ($type == 1) {
            $word = '注册';
        } elseif ($type == 2) {
            $word = '找回密码';
        } elseif ($type == 3) {
            $word = '绑定';
        } elseif ($type == 4) {
            $word = '解绑';
        } else {
            $word = '';
        }


        //PC端不进行签名
        if ($client != 1) {
            $arr = array(
                'mobile' => $mobile,
                'type' => $type,
                'sign' => I('sign'),
            );
            $res = checkSign($arr, C('API_KEY'));

            if (!$res) {
                $this->ajaxReturn(null, '签名错误', 0);
            }
        }


        if (!preg_match("/^1\d{10}$/", $mobile)) {
            $this->ajaxReturn(null, '手机号码格式有误', 0);
        }

        if ($client != 1) {
            $player = $this->player_model
                ->field('id')
                ->where(array('mobile' => $mobile))
                ->find();
            if ($type == 2 || $type == 4) {
                if (!$player) {
                    $this->ajaxReturn(null, '手机号未绑定', 0);
                }
            } else {
                if ($player) {
                    $this->ajaxReturn(null, '手机号已存在', 0);
                }
            }
        } else {
            $player = M('users')->where(array('user_login' => $username))->find();

            if (!$player) {
                $this->ajaxReturn(null, '账号不存在', 0);
            }

            if (empty($player['mobile'])) {
                $this->ajaxReturn(null, '该账号未绑定手机号，请联系管理员进行绑定', 0);
            }

            if ($player['mobile'] != $mobile) {
                $this->ajaxReturn(null, '手机号码与账号不存在绑定关系', 0);
            }
        }


        $num = createSMSCode();
//        $content = '用户您好，您' . $word . '的验证码是' . $num . '，5分钟输入有效。【'.C('BOX_NAME').'】';
        if(!sendSms($mobile,$num)) {
            $this->ajaxReturn(null,'发送失败',0);
        }else {

            $data['mobile'] = $mobile;
            $data['code'] = $num;
            $smscode = M('smscode');
            $smscodeObj = $smscode->where(array('mobile' => $mobile))->find();
            if ($smscodeObj) {
                $data['update_time'] = date('Y-m-d H:i:s');
                $success = $smscode->where(array('mobile' => $mobile))->save($data);
                if ($success !== false) {
                    //PC端不传验证码给客户端进行验证
                    if ($client != 1) {
                        $this->ajaxReturn(null, '发送成功');
                    } else {
                        $this->ajaxReturn(null, '发送成功');
                    }
                }
            } else {
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['update_time'] = $data['create_time'];
                if ($smscode->create($data)) {
                    $id = $smscode->add();
                    if ($id) {
                        //PC端不传验证码给客户端进行验证
                        if ($client != 1) {
                            $this->ajaxReturn(null, '发送成功');
                        } else {
                            $this->ajaxReturn(null, '发送成功');
                        }
                    }
                }
            }

            $this->ajaxReturn(null, '发送失败', 0);
        }
    }

    /**
     * 验证短信(接口层 用于找回密码)
     */
    public function check_smscode()
    {
        $mobile = I('mobile');
        $code = I('code');

        if (empty($mobile) || empty($code)) {
            $this->ajaxReturn(null, '参数错误', 0);
        }

        if (!preg_match("/^1\d{10}$/", $mobile)) {
            $this->ajaxReturn(null, '手机号码格式有误', 15);
        }

        $arr = array(
            'mobile' => $mobile,
            'code' => $code,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //先验证是否存在该手机
        $id = $this->player_model->where(array('mobile' => $mobile, 'status' => 1))->getfield('id');
        if (!$id) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $res = checkSMSCode($mobile, $code);
        if ($res == 1) {
            $this->ajaxReturn(null, '验证码过期', 0);
        } elseif ($res == 2) {
            $this->ajaxReturn(null, '验证码错误', 0);
        }
        //生成一个md5 ,把md5和有效期入库
        $md5 = md5($mobile . $code . uniqid());
        $save = array(
            'token' => $md5,
            'token_time' => date('Y-m-d H:i:s')
        );
        if (M('smscode')->where(array('mobile' => $mobile))->save($save) !== false) {
            $data = array(
                'id' => $id,
                'token' => $md5
            );
            $this->ajaxReturn($data, '验证成功');
        }

        $this->ajaxReturn(null, '验证失败', 0);
    }


    /**
     * 找回密码
     */
    public function forget_password()
    {
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('id', 'require', '用户ID不能为空', 1),
            array('password', 'require', '新密码不能为空！', 1),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),
            array('token', 'require', 'token不能为空', 1)
        );

        if ($this->player_model->validate($rules)->create() === false) {
            $this->ajaxReturn(null, $this->player_model->getError(), 0);

        }
        extract($_POST);

        $arr = array(
            'id' => $id,
            'password' => $password,
            'token' => $token,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $info = $this->player_model->where(array('id' => $id, 'status' => 1))->find();
        if (!$info) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        //验证token是否过期或者不正确
        $smsobj = M('smscode');
        $token_info = $smsobj->field('token,token_time')->where(array('mobile' => $info['mobile']))->find();
        if (time() - strtotime($token_info['token_time']) > $this->token_valid_time) {
            $this->ajaxReturn(null, 'token过期', 0);
        }

        if ($token_info['token'] != $token) {
            $this->ajaxReturn(null, 'token错误', 0);
        }

        $save['password'] = sp_password_by_player($password, $info['salt']);
        $save['modify_time'] = time();

        if ($this->player_model->where(array('id' => (int)$id))->save($save) !== false) {

            //密码修改成功后 将token有效期设置为无效
            $smsobj->where(array('mobile' => $info['mobile']))->save(array('token_time' => '1970-01-01 00:00:00'));
            $this->ajaxReturn(null, '修改成功');
        } else {
            $this->ajaxReturn(null, '修改失败', 0);
        }
    }

    /**
     * 注册
     */
    public function register()
    {
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('password', 'require', '密码不能为空！', 1),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),
            array('channel', 'require', '渠道ID不能为空！', 1),
            array('system', 'require', '系统不能为空', 1),
        );

        if (I('type') == 1) {
            $rules[] = array('username', 'require', '账号不能为空！', 1);
            $rules[] = array('username', '/^[A-Za-z]{1}\w*$/', '账号格式错误！', 1, 'regex', 1);
            $rules[] = array('username', '6,16', '账号长度为6-16位！', 6, 'length');
            $rules[] = array('username', '', '用户名已经存在！', 0, 'unique', 1);
        } else {
            $rules[] = array('mobile', 'require', '手机号码不能为空', 1);
            $rules[] = array('mobile', '/^1\d{10}$/', '手机格式错误', 1, 'regex', 1);
            $rules[] = array('mobile', '', '手机号已经存在！', 0, 'unique', 1);
            $rules[] = array('code', 'require', '手机验证码不能为空', 1);
        }

        if ($_POST['is_web'] == 0) {
            $rules[] = array('machine_code', 'require', '设备号不能为空！', 1);
        }

        if ($this->player_model->validate($rules)->create() === false) {
            $this->ajaxReturn(null, $this->player_model->getError(), 0);
        }

        extract($_POST);

        $register_enabled = M('channel')->where(array('id' => $channel))->getfield('register_enabled');

        if (!$register_enabled) {
            $this->ajaxReturn(null, '请使用推广页面注册账号', 0);
        }

        $arr = array(
            'username' => $username,
            'code' => $code,
            'mobile' => $mobile,
            'password' => $password,
            'channel' => $channel,
            'system' => $system,
            'maker' => $maker,
            'mobile_model' => $mobile_model,
            'machine_code' => $machine_code,
            'system_version' => $system_version,
            'type' => $type,
            'sign' => $sign,
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }


        $channel_info = M('channel')->where(array('id' => $channel))->find();

        if (!$channel_info) {
            $this->ajaxReturn(null, '渠道不存在', 0);
        }

        if ($type == 2) {
            //验证手机验证码
            $res = checkSMSCode($mobile, $code);

            if ($res == 1) {
                $this->ajaxReturn(null, '验证码过期', 0);
            } elseif ($res == 2) {
                $this->ajaxReturn(null, '验证码错误', 0);
            }
            //自动生成账号，以JS开头
            $max_id = M('player')->field('max(id)')->find();
            $username = 'JS' . str_pad($max_id['max(id)'] + 1, 5, "0", STR_PAD_LEFT);
        } else {
            $username = trim($username);
        }

        $mobile = trim($mobile);
        $password = trim($password);

        //用户名需过滤的字符的正则
        $stripChar = '?<*.>\'"';
        if (preg_match('/[' . $stripChar . ']/is', $username) == 1) {
            $this->ajaxReturn(null, '用户名中包含' . $stripChar . '等非法字符！', 1);
        }


        $salt = getRandomString(6);
        $time = time();

        $udata = array(
            'username' => $username,
            'password' => sp_password_by_player($password, $salt),
            'mobile' => $mobile,
            'salt' => $salt,
            'regip' => ip2long(get_client_ip(0, true)),
            'regtime' => $time,
            'appid' => C('BOX_APP_ID'),
            'channel' => $channel,
            'system' => $system,
            'maker' => $maker,
            'mobile_model' => $mobile_model,
            'machine_code' => $machine_code,
            'system_version' => $system_version,
            'create_time' => $time,
        );

        $rst = $this->player_model->add($udata);
        if ($rst) {
            $data = array(
                'id' => $rst,
                'username' => $username,
                'mobile' => $mobile,
                'nick_name' => $username,
                'icon_url' => get_avatar_url(''),
                'coin' => 0,
                'platform_money' => 0
            );
            $this->ajaxReturn($data, '注册成功');
        } else {
            $this->ajaxReturn(null, '注册失败', 0);
        }


    }

    /**
     * 注册
     */
    public function doregister()
    {

        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('password', 'require', '密码不能为空！', 1),
            array('password', '6,16', '密码长度为6-16位！', 6, 'length'),
            array('channel', 'require', '渠道ID不能为空！', 1),
            array('system', 'require', '系统不能为空', 1),
        );

        if (I('type') == 1) {
            $rules[] = array('username', 'require', '账号不能为空！', 1);
            $rules[] = array('username', '/^[A-Za-z]{1}\w*$/', '账号格式错误！', 1, 'regex', 1);
            $rules[] = array('username', '6,16', '账号长度为6-16位！', 6, 'length');
            $rules[] = array('username', '', '用户名已经存在！', 0, 'unique', 1);
        } else {
            $rules[] = array('mobile', 'require', '手机号码不能为空', 1);
            $rules[] = array('mobile', '/^1\d{10}$/', '手机格式错误', 1, 'regex', 1);
            $rules[] = array('mobile', '', '手机号已经存在！', 0, 'unique', 1);
            $rules[] = array('code', 'require', '手机验证码不能为空', 1);
        }

        if ($this->player_model->validate($rules)->create() === false) {
            $this->ajaxReturn(null, $this->player_model->getError(), 0);
        }

        extract($_POST);

        $register_enabled = M('channel')->where(array('id' => $channel))->getfield('register_enabled');

        if (!$register_enabled) {
            $this->ajaxReturn(null, '请使用推广页面注册账号', 0);
        }

        $arr = array(
            'username' => $username,
            'code' => $code,
            'mobile' => $mobile,
            'password' => $password,
            'channel' => $channel,
            'system' => $system,
            'type' => $type,
            'sign' => $sign,
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }


        $channel_info = M('channel')->where(array('id' => $channel))->find();

        if (!$channel_info) {
            $this->ajaxReturn(null, '渠道不存在', 0);
        }

        if ($type == 2) {
            //验证手机验证码
            $res = checkSMSCode($mobile, $code);

            if ($res == 1) {
                $this->ajaxReturn(null, '验证码过期', 0);
            } elseif ($res == 2) {
                $this->ajaxReturn(null, '验证码错误', 0);
            }
            //自动生成账号，以JS开头
            $max_id = M('player')->field('max(id)')->find();
            $username = 'JS' . str_pad($max_id['max(id)'] + 1, 5, "0", STR_PAD_LEFT);
        } else {
            $username = trim($username);
        }

        $mobile = trim($mobile);
        $password = trim($password);

        //用户名需过滤的字符的正则
        $stripChar = '?<*.>\'"';
        if (preg_match('/[' . $stripChar . ']/is', $username) == 1) {
            $this->ajaxReturn(null, '用户名中包含' . $stripChar . '等非法字符！', 1);
        }


        $salt = getRandomString(6);
        $time = time();

        $udata = array(
            'username' => $username,
            'password' => sp_password_by_player($password, $salt),
            'mobile' => $mobile,
            'salt' => $salt,
            'regip' => ip2long($ip),
            'regtime' => $time,
            'appid' => C('BOX_APP_ID'),
            'channel' => $channel,
            'system' => $system,
            'source' => "平台",
            'create_time' => $time,
        );

        $rst = $this->player_model->add($udata);
        if ($rst) {
            $data = array(
                'id' => $rst,
                'username' => $username,
                'mobile' => $mobile,
                'nick_name' => $username,
                'icon_url' => get_avatar_url(''),
                'coin' => 0,
                'platform_money' => 0
            );
            $this->ajaxReturn($data, '注册成功');
        } else {
            $this->ajaxReturn(null, '注册失败', 0);
        }


    }

    public function upload_portrait()
    {
        $id = I('post.id');

        if ($_FILES['img']['name']) {
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
            $upload = new \Think\Upload($config);//
            $info = $upload->upload();

            if (!$info) {// 上传错误提示错误信息
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {// 上传成功

                $file_name = trim($info['img']['fullpath'], '.');

                $file_name = str_replace('/www.sy217.com', '', $file_name);


                $player_info = $this->player_info_model->where(array('uid' => $id))->count();
                if ($player_info) {
                    $res = $this->player_info_model->where(array('uid' => $id))->save(array('icon_url' => $file_name));
                } else {
                    $res = $this->player_info_model->add(array('uid' => $id, 'icon_url' => $file_name));
                }

                if ($res > 0) {
                    $this->ajaxReturn(C('FTP_URL') . $file_name, '上传成功');
                } else {
                    $this->ajaxReturn(null, '上传失败', 0);
                }
            }

        }
        $this->ajaxReturn(null, '上传文件不能为空', 0);

    }

    public function modify_nickname()
    {
        $id = I('id');
        $nick_name = I('nick_name');

        if (empty($id) || empty($nick_name)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        if (mb_strlen($nick_name, 'utf-8') > 12 || mb_strlen($nick_name, 'utf-8') < 1) {
            $this->ajaxReturn(null, '昵称不能为空或超过12位', 0);
        }

        $file = trie_filter_load(SITE_PATH . 'words.dic');
        $is_sentive = trie_filter_search_all($file, $nick_name);

        if (!empty($is_sentive)) {
            $this->ajaxReturn(null, '昵称中含义非法字符，请更换昵称重试', 0);
        }

        $is_exists = $this->player_model
            ->where(array('id' => $id))
            ->count();

        if (!$is_exists) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $player_info = $this->player_info_model->where(array('uid' => $id))->count();

        if ($player_info) {
            $res = $this->player_info_model->where(array('uid' => $id))->save(array('nick_name' => $nick_name));
        } else {
            $res = $this->player_info_model->add(array('uid' => $id, 'nick_name' => $nick_name));
        }

        if ($res !== false) {
            $_SESSION['webapp']['nick_name'] = $nick_name;
            $this->ajaxReturn(null, '修改成功');
        } else {
            $this->ajaxReturn(null, '修改失败', 0);
        }

    }

    public function friend_recom_info()
    {
        $uid = I('uid');
        $channel = I('channel');

        if (empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }
        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $recom_bonus = M('coin_log')->where(array('uid' => $uid, 'type' => 3))->field('sum(coin_change) as coin_change')->find();

        $recom_counts = 0;
        if ($uid != 0) {
            $recom_counts = $this->player_model->where(array('referee_uid' => $uid))->count();
        }

        $site_options = get_site_options();

        $data = array(
            'recom_bonus' => isset($recom_bonus['coin_change']) ? $recom_bonus['coin_change'] : 0,
            'recom_counts' => $recom_counts,
            'one_get_coin' => $site_options['friend_coin_ratio'] ? $site_options['friend_coin_ratio'] : 10,
            'recom_top' => $site_options['friend_coin_top'] ? $site_options['friend_coin_top'] : 2000,
            'one_register_coin' => $site_options['onefriend_register_coin'] ? $site_options['onefriend_register_coin'] : 10,
        );

        $this->ajaxReturn($data);

    }

    /**
     * sdk用户中心
     */
    public function user_center_sdk()
    {
        $uid = I('uid');
        $appid = I('appid');
        if(empty($uid))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'uid' => $uid,
            'sign' => I('sign'),
        );
        if($appid){
            $arr = array(
                'uid'=>$uid,
                'appid'=>$appid,
                'sign'=>I('sign'),
            );
        }

        //logs('userCenterSdk',$arr);
        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $player = $this->player_model->where(array('id' => $uid))->find();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $data = array();

        $data['username'] = $player['username'];
        $data['mobile'] = $player['mobile'];
        //$data['nick_name'] = C('DEFAULT_NAME');
        $data['nick_name'] = $player['nick_name'] ? $player['nick_name'] : '';
        $data['platform_money'] = $player['platform_money'];
        //$data['membership'] = $player['membership'];
        $data['icon_url'] = get_avatar_url($player['icon_url']);
        //$data['money'] = $player['money'];
        $data['pay_platform_money'] = 0;
        if($appid){
            $pay_platform_money = M('game')->where(array('id'=>$appid))->getField('pay_platform_money');
            $data['pay_platform_money'] = (int)$pay_platform_money;
        }

        $inpour = M('inpour');
        $map['uid'] = $uid;
        $inpour_list = $inpour->where($map)->field('id,appid,uid,money,productID,create_time,status')->select();
        $data['inpour_list'] = $inpour_list;

        $channel = $player['channel'];
        $channelData = M("Channel")->where('id',$channel)->find();
        $data['box_download_enabled'] = $channelData['box_download_enabled'];
        $this->ajaxReturn($data);

    }

    /**
     * 盒子用户中心
     */
    public function user_center_box()
    {
        $uid = I('uid');
        $channel = I('channel');

        if (empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }
        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            //$this->ajaxReturn(null,'签名错误',0);
        }

        $idcard_info = [];
        if ($uid != 0) {
            $player_info = $this->player_model->where(array('id' => $uid))->field('platform_money,coin,mobile')->find();
            
            // 获取player_info 表里面的实名认证信息
            $idcardInfo = M('player_info')->where(array('uid' => $uid))
                ->field('real_name,id_card')
                ->find();
            
            // 判断改用户是否需要实名认证
            if (!$player_info || empty($idcardInfo['real_name']) || empty($idcardInfo['id_card'])) {  // 判断表中是否有该用户  或者 有用户必须要有 real_name  和 id_card
                // 没有进行实名认证
                $idcard_info['idcard_verify'] = 0;
            }else{
                $idcard_info['idcard_verify'] = 1;
            }
            $idcard_info['idcard'] = $idcardInfo['id_card'];
            $idcard_info['real_name'] = $idcardInfo['real_name'];

            $recom_bonus = M('coin_log')->where(array('uid' => $uid, 'type' => 3))->field('sum(coin_change) as coin_change')->find();

            $is_vip = (int)checkVip($uid);

        }

        $sign_config = C('SIGN_CONFIG');

        $site_options = get_site_options();

        /*统计狂人排名*/
        //开车狂
        $drive = M('dynamics')->where(array('uid' => $uid, 'audit' => 1, 'status' => 0))->count();

        //点评狂
        $comment = M('comment')->where(array('uid' => $uid, 'status' => 1, 'comment_type' => 2, 'order' => 1))->count();

        //助人狂
        $help = M('consult_info')->where(array('uid' => $uid, 'audit' => 1))->count();

        //签到狂
        $signin = M('sign_log')->where(array('uid' => $uid))->count();

        $data = array(
            'platform_money' => isset($player_info['platform_money']) ? $player_info['platform_money'] : 0,
            'coin' => isset($player_info['coin']) ? $player_info['coin'] : 0,
            'recom_bonus' => isset($recom_bonus['coin_change']) ? $recom_bonus['coin_change'] : 0,
            'is_vip' => $is_vip,
            'mobile' => empty($player_info['mobile']) ? '' : $player_info['mobile'],
            'recom_top' => $site_options['friend_coin_top'] ? $site_options['friend_coin_top'] : 2000,
            'sign_day_bonus' => $sign_config['DAY_BONUS'],
            'platform_coin_ratio' => $site_options['platform_coin_ratio'] ? $site_options['platform_coin_ratio'] : 10,
            'pl_coin' => $site_options['pl_coin'],
            'deplete_coin' => $site_options['deplete_coin'],
            'rank_recom_top' => $site_options['rank'][0] ? $site_options['rank'][0] : 1000,
            'follow' => M('follow')->where(array('uid' => $uid))->count(),
            'fans' => M('follow')->where(array('buid' => $uid))->count(),
            'driveLevel' => crazy_level($drive, 4),
            'commentLevel' => crazy_level($comment, 3),
            'helpLevel' => crazy_level($help, 2),
            'signLevel' => crazy_level($signin, 1),
            'idcard_info' => $idcard_info,
        );

        $this->ajaxReturn($data);

    }


    public function get_patch()
    {
        $version = I('version');


        $data['patch_url'] = '';
        $this->ajaxReturn($data);

    }

    public function follow_list()
    {
        $uid = I('uid');
        $visit_uid = I('visit_uid');
        $channel = I('channel');
        $type = I('type'); //1 关注 2 粉丝
        $page = I('page');


        if (empty($uid) || empty($visit_uid) || empty($channel) || empty($type) || empty($page)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'visit_uid' => $visit_uid,
            'channel' => $channel,
            'type' => $type,
            'page' => $page,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //先查询该用户是否存在
        $player_model = M('player');

        $player = $player_model->where(array('id' => $uid))->count();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $follow_model = M('follow');

        //关注列表
        $follow_list = $follow_model->where(array('uid' => $uid))->order('create_time desc')->getfield('buid', true);
        //粉丝列表
        $fans_list = $follow_model->where(array('buid' => $uid))->order('create_time desc')->getfield('uid', true);

        $list = ($type == 1) ? $follow_list : $fans_list;

        if ($uid == $visit_uid) {
            //获取所有相互关注
            $follow_eachother_list = array_intersect($follow_list, $fans_list);
        } else {
            $uids_sql = '';

            foreach ($list as $v) {
                $uids_sql .= $v . ',';
            }
            $uids_sql = trim($uids_sql, ',');
            $visit_follow_list = $follow_model->where(array('buid' => array('in', $uids_sql), 'uid' => $visit_uid))->getfield('buid', true);

            $visit_fans_list = $follow_model->where(array('buid' => $visit_uid))->getfield('uid', true);

            $follow_eachother_list = array_intersect($visit_follow_list, $visit_fans_list);

        }

        $page = $page ? $page : 1;

        $count = count($list);
        $list = array_slice($list, ($page - 1) * $this->follow_page_size, $this->follow_page_size);

        $result = array();

        if (!empty($list)) {
            $uids_sql = '';
            foreach ($list as $v) {
                $uids_sql .= $v . ',';
            }
            $uids_sql = trim($uids_sql, ',');

            $player_info = $this->_get_user_info($uids_sql);

            $fans_counts = $follow_model->where(array('buid' => array('in', $uids_sql)))->group('buid')->getfield('buid,count(uid) as count', true);

            foreach ($list as $v) {
                $item['uid'] = $v;
                $item['nickname'] = $player_info['player_info'][$v]['nick_name'] ? $player_info['player_info'][$v]['nick_name'] : $player_info['player'][$v];
                $item['icon_url'] = get_avatar_url($player_info['player_info'][$v]['icon_url']);
                $item['vip'] = (in_array($v, $player_info['vip_players'])) ? 1 : 0;
                $item['sex'] = isset($player_info['player_info'][$v]['sex']) ? $player_info['player_info'][$v]['sex'] : 0;
                if ($uid == $visit_uid) {
                    $item['follow_status'] = in_array($v, $follow_eachother_list) ? 2 : (($type == 1) ? 1 : 0);
                } else {
                    $item['follow_status'] = in_array($v, $follow_eachother_list) ? 2 : (in_array($v, $visit_follow_list) ? 1 : 0);
                }
                $item['fans_counts'] = $fans_counts[$v] ? $fans_counts[$v] : 0;
                $result[] = $item;
            }

        }

        $data = array(
            'count' => $count,
            'list' => $result ? $result : array()
        );

        $this->ajaxReturn($data);


    }

    public function user_desc()
    {
        $uid = I('uid');
        $visit_uid = I('visit_uid');
        $channel = I('channel');
        $field_type = I('field_type');

        if (empty($uid) || empty($channel) || empty($field_type) || empty($visit_uid)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'visit_uid' => $visit_uid,
            'channel' => $channel,
            'field_type' => $field_type,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //先查询该用户是否存在
        $player = $this->player_model->where(array('id' => $uid))->count();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $player = $this->player_model->where(array('id' => $uid))->field('username,create_time')->find();

        $player_info = $this->player_info_model->where(array('uid' => $uid))->field('nick_name,email,icon_url,sex,address,qq,desc,birth')->find();

        $field_type = $field_type ? $field_type : 1;

        $data = array();
        $data['nick_name'] = $player_info['nick_name'] ? $player_info['nick_name'] : $player['username'];
        $data['desc'] = $player_info['desc'] ? $player_info['desc'] : '';
        $data['icon_url'] = get_avatar_url($player_info['icon_url']);
        $data['driver_level'] = user_driver_level($uid);
        $data['vip'] = (int)checkVip($uid);

        $data['drive_counts'] = (int)M('dynamics')->where(array('uid' => $uid, 'status' => 0, 'audit' => 1))->count();
        $data['fan_counts'] = (int)M('follow')->where(array('buid' => $uid))->count();
        $data['follow_counts'] = (int)M('follow')->where(array('uid' => $uid))->count();

        if ($field_type == 2) {
            $data['username'] = $player['username'];
            $data['sex'] = $player_info['sex'] ? $player_info['sex'] : 0;
            $data['address'] = $player_info['address'] ? $player_info['address'] : '';
            $data['birth'] = $player_info['birth'] ? $player_info['birth'] : '';
            $data['email'] = $player_info['email'] ? $player_info['email'] : '';
            $data['qq'] = $player_info['qq'] ? $player_info['qq'] : '';
            $data['reg_time'] = $player['create_time'];
        }
        //查询访问人是否关注此用户
        $is_follow = M('follow')->where(array('uid' => $visit_uid, 'buid' => $uid))->count();

        $data['is_follow'] = ($is_follow > 0) ? 1 : 0;

        $this->ajaxReturn($data);
    }

    public function edit_desc()
    {
        $uid = I('uid');
        $channel = I('channel');
        $nick_name = I('nick_name');
        $sex = I('sex');
        $address = I('address');
        $desc = I('desc');
        $birth = I('birth');
        $qq = I('qq');
        $email = I('email');

        if (empty($uid) || empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'nick_name' => $nick_name,
            'sex' => $sex,
            'address' => $address,
            'desc' => $desc,
            'birth' => $birth,
            'qq' => $qq,
            'email' => $email,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //先查询该用户是否存在

        $player = $this->player_model->where(array('id' => $uid))->count();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

//        if($nick_name)
//        {
//            $file = trie_filter_load(SITE_PATH.'words.dic');
//            $is_sentive = trie_filter_search_all($file, $nick_name);
//
//            if(!empty($is_sentive))
//            {
//                $this->ajaxReturn(null,'昵称中含义非法字符，请更换昵称重试',0);
//            }
//        }

        $data = array();
        if ($nick_name) $data['nick_name'] = $nick_name;
        if ($sex) $data['sex'] = $sex;
        if ($address) $data['address'] = $address;
        if ($desc) $data['desc'] = $desc;
        if ($birth) $data['birth'] = $birth;
        if ($qq) $data['qq'] = $qq;
        if ($email) $data['email'] = $email;

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
            $upload = new \Think\Upload($config);//
            $info = $upload->upload();

            if (!$info) {// 上传错误提示错误信息
                $this->ajaxReturn(null, $upload->getError(), 0);
            } else {// 上传成功
                $file_name = trim($info['icon_url']['fullpath'],'.');
                $data['icon_url'] = $file_name;
            }

        }

        $player_info = $this->player_info_model->where(array('uid' => $uid))->count();
        $time = time();
        if ($player_info) {
            $data['modifiy_time'] = $time;
            $res = $this->player_info_model->where(array('uid' => $uid))->save($data);
        } else {
            $data['uid'] = $uid;
            $data['create_time'] = $time;
            $res = $this->player_info_model->add($data);
        }

        $file_name = ($data['icon_url'])?'http://'.$_SERVER['HTTP_HOST'].'/'.$data['icon_url']:'';
        if($res !==false)
        {
            $this->ajaxReturn($file_name,'修改成功');
        }
        else
        {
            $this->ajaxReturn(null,'修改失败',0);
        }
    }

    public function new_up_counts()
    {
        $uid = I('uid');
        $channel = I('channel');

        if (empty($uid) || empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        //先查询该用户是否存在

        $player = $this->player_model->where(array('id' => $uid))->count();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }


        $map1 = array(
            'd.uid' => $uid,
            'c.to_uid' => $uid,
            '_logic' => 'or',
        );

        $map = array(
            'c.is_read' => 0,
            'c.uid' => array('neq', $uid),
            '_complex' => $map1,
            '_logic' => 'and',
            'c.status' => 1,
            'c.comment_type' => 1,
            'd.status' => 0,
        );

        //获取未读评论数量
        $unread_comment_count = M('comment')->
        alias('c')->
        join('left join __DYNAMICS__ d on c.dynamics_id=d.id')->
        where($map)->
        count();

        if ($unread_comment_count > 0) {
            $unread_comment_newtime = M('comment')
                ->alias('c')
                ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
                ->where($map)
                ->order('c.create_time desc')
                ->limit(1)
                ->getfield('c.create_time');
        }


        $map = array(
            'cl.is_read' => 0,
            'c.uid' => $uid,
            'cl.uid' => array('neq', $uid),
            'c.status' => 1,
            'c.comment_type' => 1,
            'd.status' => 0,
        );

        //获取未读评论赞数
        $unread_comment_like_count = M('comment_like_info')
            ->alias('cl')
            ->join('left join __COMMENT__ c on cl.comment_id = c.id')
            ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
            ->where($map)
            ->count();


        if ($unread_comment_like_count > 0) {
            $unread_comment_like_newtime = M('comment_like_info')
                ->alias('cl')
                ->join('left join __COMMENT__ c on cl.comment_id = c.id')
                ->where($map)
                ->order('cl.create_time desc')
                ->limit(1)
                ->getfield('cl.create_time');
        }


        $map = array(
            'dl.is_read' => 0,
            'd.uid' => $uid,
            'dl.uid' => array('neq', $uid),
            'd.status' => 0,
        );

        //获取未读动态赞数
        $unread_dynamics_like_count = M('dynamics_like_info')
            ->alias('dl')
            ->join('left join __DYNAMICS__ d on dl.dynamics_id = d.id')
            ->where($map)
            ->count();

        if ($unread_dynamics_like_count > 0) {
            $unread_dynamics_like_newtime = M('dynamics_like_info')
                ->alias('dl')
                ->join('left join __DYNAMICS__ d on dl.dynamics_id = d.id')
                ->where($map)
                ->order('dl.create_time desc')
                ->limit(1)
                ->getfield('dl.create_time');
        }


        $count = $unread_comment_count + $unread_comment_like_count + $unread_dynamics_like_count;

        if ($count == 0) {
            $type = 1;
        } else {
            $type = ($unread_comment_newtime > $unread_comment_like_newtime) ?
                (($unread_comment_newtime > $unread_dynamics_like_newtime) ? 1 : 3) : (($unread_comment_like_newtime > $unread_dynamics_like_newtime) ? 2 : 3);
        }

        $this->ajaxReturn(array('count' => $count, 'type' => $type));

    }

    public function my_comment_zan()
    {
        $uid = I('uid');
        $channel = I('channel');
        $type = I('type'); //1评论 2被赞的评论 3被赞得微博
        $page = I('page');

        if (empty($uid) || empty($channel) || empty($type) || empty($page)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'type' => $type,
            'page' => $page,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }
        //先查询该用户是否存在

        $player = $this->player_model->where(array('id' => $uid))->count();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }


        $page = $page ? $page : 1;

        $list = array();
        $count = 0;

        if ($type == 1) {
            $comment_model = M('comment');
            $map1 = array(
                'd.uid' => $uid,
                'c.to_uid' => $uid,
                '_logic' => 'or',
            );

            $map = array(
                'c.uid' => array('neq', $uid),
                '_complex' => $map1,
                '_logic' => 'and',
                'c.status' => 1,
                'c.comment_type' => 1,
                'd.status' => 0,
            );

            $result = $comment_model
                ->alias('c')
                ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
                ->where($map)
                ->order('c.create_time desc')
                ->limit(($page - 1) * $this->follow_page_size . ',' . $this->follow_page_size)
                ->field('c.id,c.uid,c.to_uid,c.dynamics_id,c.content,c.create_time,d.uid d_uid,d.content d_content,d.imgs')
                ->select();


            $count = $comment_model
                ->alias('c')
                ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
                ->where($map)
                ->count();

            $uids_sql = '';
            $comment_ids = '';


            if (is_array($list)) {
                foreach ($result as $v) {
                    $uids_sql .= $v['uid'] . ',';
                    $uids_sql .= $v['to_uid'] . ',';
                    $uids_sql .= $v['d_uid'] . ',';
                    $comment_ids .= $v['id'] . ',';
                }
                $uids_sql = trim($uids_sql, ',');

                $comment_ids = trim($comment_ids, ',');
                $player_info = $this->_get_user_info($uids_sql);
                $comment_model->where(array('id' => array('in', $comment_ids)))->save(array('is_read' => 1));

                foreach ($result as $v) {
                    $item['comment_id'] = $v['id'];
                    $item['c_uid'] = $v['uid'];
                    $item['c_uid_iconurl'] = get_avatar_url($player_info['player_info'][$v['uid']]['icon_url']);
                    $item['c_uid_vip'] = (in_array($v['uid'], $player_info['vip_players'])) ? 1 : 0;

                    $item['c_uid_nickname'] = $player_info['player_info'][$v['uid']]['nick_name']
                        ? $player_info['player_info'][$v['uid']]['nick_name'] :
                        $player_info['player'][$v['uid']];

                    $item['c_touid'] = $v['to_uid'];
                    $item['c_touid_nickname'] = $player_info['player_info'][$v['to_uid']]['nick_name']
                        ? $player_info['player_info'][$v['to_uid']]['nick_name'] :
                        $player_info['player'][$v['to_uid']];

                    $item['c_content'] = $v['content'];
                    $item['create_time'] = $v['create_time'];

                    $item['dynamics_id'] = $v['dynamics_id'];
                    $item['d_uid'] = $v['d_uid'];
                    $item['d_uid_nickname'] = $player_info['player_info'][$v['d_uid']]['nick_name']
                        ? $player_info['player_info'][$v['d_uid']]['nick_name'] :
                        $player_info['player'][$v['d_uid']];

                    $item['d_content'] = $v['d_content'];


                    $imgs = json_decode($v['imgs'], true);
                    $item['d_img'] = $imgs[0] ? (C('FTP_URL') . '/' . $imgs[0]) : '';

                    $list[] = $item;

                }
            }

        } elseif ($type == 2) {
            $map = array(
                'c.uid' => $uid,
                'cl.uid' => array('neq', $uid),
                'c.status' => 1,
                'c.comment_type' => 1,
                'd.status' => 0,
            );

            $comment_like_model = M('comment_like_info');

            $result = $comment_like_model
                ->alias('cl')
                ->join('left join __COMMENT__ c on cl.comment_id = c.id')
                ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
                ->where($map)
                ->order('cl.create_time desc')
                ->limit(($page - 1) * $this->follow_page_size . ',' . $this->follow_page_size)
                ->field('cl.id,cl.comment_id,cl.uid,cl.type,cl.create_time,c.uid c_uid,c.to_uid c_touid,c.content c_content,c.dynamics_id')
                ->select();


            $count = $comment_like_model
                ->alias('cl')
                ->join('left join __COMMENT__ c on cl.comment_id = c.id')
                ->join('left join __DYNAMICS__ d on c.dynamics_id=d.id')
                ->where($map)
                ->count();


            $comment_like_ids = '';
            $uids_sql = '';
            $dynamics_ids = '';
            if (is_array($result)) {
                foreach ($result as $v) {
                    $uids_sql .= $v['uid'] . ',';
                    $uids_sql .= $v['c_uid'] . ',';
                    $uids_sql .= $v['c_touid'] . ',';
                    $comment_like_ids .= $v['id'] . ',';
                    $dynamics_ids .= $v['dynamics_id'] . ',';
                }
                $uids_sql = trim($uids_sql, ',');
                $comment_like_ids = trim($comment_like_ids, ',');
                $dynamics_ids = trim($dynamics_ids, ',');

                $dynamics_info = M('dynamics')->where(array('id' => array('in', $dynamics_ids)))->getfield('id,uid,content,imgs');

                foreach ($dynamics_info as $v) {
                    $uids_sql .= $v['uid'] . ',';
                }

                $uids_sql = trim($uids_sql, ',');


                $player_info = $this->_get_user_info($uids_sql);


                //设置为已读
                $comment_like_model->where(array('id' => array('in', $comment_like_ids)))->save(array('is_read' => 1));

                foreach ($result as $v) {

                    $item['type'] = $v['type'];
                    $item['cl_uid'] = $v['uid'];

                    $item['cl_uid_iconurl'] = get_avatar_url($player_info['player_info'][$v['uid']]['icon_url']);
                    $item['cl_uid_vip'] = (in_array($v['uid'], $player_info['vip_players'])) ? 1 : 0;

                    $item['cl_uid_nickname'] = $player_info['player_info'][$v['uid']]['nick_name']
                        ? $player_info['player_info'][$v['uid']]['nick_name'] :
                        $player_info['player'][$v['uid']];

                    $item['comment_id'] = $v['comment_id'];
                    $item['c_uid'] = $v['c_uid'];
                    $item['c_uid_nickname'] = $player_info['player_info'][$v['c_uid']]['nick_name']
                        ? $player_info['player_info'][$v['c_uid']]['nick_name'] :
                        $player_info['player'][$v['c_uid']];


                    $item['c_touid'] = $v['c_touid'];
                    $item['c_touid_nickname'] = $player_info['player_info'][$v['c_touid']]['nick_name']
                        ? $player_info['player_info'][$v['c_touid']]['nick_name'] :
                        $player_info['player'][$v['c_touid']];
                    $item['c_content'] = $v['c_content'];
                    $item['create_time'] = $v['create_time'];

                    $item['dynamics_id'] = $v['dynamics_id'];
                    $item['d_uid'] = $dynamics_info[$v['dynamics_id']]['uid'];
                    $item['d_uid_nickname'] = $player_info['player_info'][$dynamics_info[$v['dynamics_id']]['uid']]['nick_name']
                        ? $player_info['player_info'][$dynamics_info[$v['dynamics_id']]['uid']]['nick_name'] :
                        $player_info['player'][$dynamics_info[$v['dynamics_id']]['uid']];

                    $item['d_content'] = $dynamics_info[$v['dynamics_id']]['content'];
                    $imgs = json_decode($dynamics_info[$v['dynamics_id']]['imgs'], true);
                    $item['d_img'] = $imgs[0] ? (C('FTP_URL') . '/' . $imgs[0]) : '';

                    $list[] = $item;

                }

            }

        } else {
            $dynamics_like_model = M('dynamics_like_info');
            $map = array(
                'd.uid' => $uid,
                'dl.uid' => array('neq', $uid),
                'd.status' => 0,
            );

            $result = $dynamics_like_model
                ->alias('dl')
                ->join('left join __DYNAMICS__ d on dl.dynamics_id = d.id')
                ->where($map)
                ->order('dl.create_time desc')
                ->limit(($page - 1) * $this->follow_page_size . ',' . $this->follow_page_size)
                ->field('dl.id,dl.dynamics_id,dl.uid,dl.type,dl.create_time,d.uid d_uid,d.content d_content,d.imgs')
                ->select();

            $count = $dynamics_like_model
                ->alias('dl')
                ->join('left join __DYNAMICS__ d on dl.dynamics_id = d.id')
                ->where($map)
                ->count();

            $dynamics_like_ids = '';
            $uids_sql = '';

            if (is_array($result)) {
                foreach ($result as $v) {
                    $uids_sql .= $v['uid'] . ',';
                    $uids_sql .= $v['d_uid'] . ',';
                    $dynamics_like_ids .= $v['id'] . ',';
                }
                $uids_sql = trim($uids_sql, ',');
                $dynamics_like_ids = trim($dynamics_like_ids, ',');

                $player_info = $this->_get_user_info($uids_sql);
                //设置为已读
                $dynamics_like_model->where(array('id' => array('in', $dynamics_like_ids)))->save(array('is_read' => 1));
                foreach ($result as $v) {
                    $item['type'] = $v['type'];

                    $item['dl_uid'] = $v['uid'];
                    $item['dl_uid_iconurl'] = get_avatar_url($player_info['player_info'][$v['uid']]['icon_url']);
                    $item['dl_uid_vip'] = (in_array($v['uid'], $player_info['vip_players'])) ? 1 : 0;

                    $item['dl_uid_nickname'] = $player_info['player_info'][$v['uid']]['nick_name']
                        ? $player_info['player_info'][$v['uid']]['nick_name'] :
                        $player_info['player'][$v['uid']];

                    $item['create_time'] = $v['create_time'];

                    $item['dynamics_id'] = $v['dynamics_id'];
                    $item['d_uid'] = $v['d_uid'];
                    $item['d_uid_nickname'] = $player_info['player_info'][$v['d_uid']]['nick_name']
                        ? $player_info['player_info'][$v['d_uid']]['nick_name'] :
                        $player_info['player'][$v['d_uid']];

                    $item['d_content'] = $v['d_content'];
                    $imgs = json_decode($v['imgs'], true);
                    $item['d_img'] = $imgs[0] ? (C('FTP_URL') . '/' . $imgs[0]) : '';

                    $list[] = $item;

                }

            }

        }


        $data = array(
            'count' => ceil($count / $this->follow_page_size),
            'list' => $list ? $list : array()
        );

        $this->ajaxReturn($data);

    }

    private function _get_user_info($uids_sql)
    {
        $player_model = M('player');
        $player_info = M('player_info')->where(array('uid' => array('in', $uids_sql)))->getfield('uid,nick_name,icon_url,sex', true);
        $player = $player_model->where(array('id' => array('in', $uids_sql)))->getfield('id,username', true);

        //查询用户是否是VIP
        $vip_players = $player_model->where(array('id' => array('in', $uids_sql)))->group('id')->having('vip_end>' . time())->getfield('id,vip_end', true);

        $vip_players = array_keys($vip_players);
        return array(
            'player_info' => $player_info,
            'player' => $player,
            'vip_players' => $vip_players,
        );
    }

    public function do_init()
    {
        $channel = I('channel');
        $system = I('system');

        if (empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'channel' => $channel,
        );

        if (!empty($system)) {
            $arr['system'] = $system;
        }

        $arr['sign'] = I('sign');

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $box_static = M('channel')->where(array('id' => $channel))->getfield('box_static');

        $discount_field = ($system == 2) ? 'ios_discount_enabled' : 'android_discount_enabled';

        $discount_enabled = (int)M('channel')->where(array('id' => $channel))->getfield($discount_field);


        $data = array(
            'box_static' => (int)$box_static,
            'discount_enabled' => $discount_enabled,
        );

        if ($channel == C('MAIN_CHANNEL')) {
            $data['qq_zixun'] = 'http://q.url.cn/abe9qf?_type=wpa&qidian=true';
        }

        $this->ajaxReturn($data);
    }

    public function get_user_info()
    {
        $this->ajaxReturn($_SESSION['webapp']);
    }

    public function log_out()
    {
        $_SESSION['webapp'] = null;
        $this->ajaxReturn(null, '退出成功');
    }

    public function notice()
    {
        $data['cid'] = I('cid');
        $data['sign'] = I('sign');
        if (!$data['cid']) $this->ajaxReturn('', 'channel is null', 0);
        if (!checkSign($data, C('API_KEY'))) $this->ajaxReturn('', 'sign error', 0);
        unset($data['sign']);
        $data['appid'] = 1013;
        $data['status'] = 1;
        $field = 'title,content,add_time';
        $info = M('notice')->field($field)->where($data)->order('id desc')->find();
        if (!$info) {
            $info = M('notice')->field($field)->where(array('appid' => 1013, 'cid' => 0, 'status' => 1))->order('id desc')->find();
        }
        $this->ajaxReturn($info, 'success');
    }

    /**
     * 邀请好友排行
     */
    public function rankingList()
    {
        $setting = M('options')->where(array('option_name' => 'site_options'))->find();
        $setting = json_decode($setting['option_value'], true);
        $data['today'] = $this->getRankingData($setting['rank'], array(
            'p1.referee_uid' => array('gt', 0),
            '_string' => 'to_days(FROM_UNIXTIME(p1.create_time,"%Y-%m-%d")) = to_days("' . date('Y-m-d') . '")'
        ));
        $data['yesterday'] = $this->getRankingData($setting['rank'], array(
            'p1.referee_uid' => array('gt', 0),
            '_string' => 'to_days("' . date('Y-m-d') . '") - to_days(FROM_UNIXTIME(p1.create_time,"%Y-%m-%d")) = 1'
        ));
        $this->ajaxReturn($data, 'success');
    }

    /**
     * 领取排名奖励
     */
    public function receiveReward()
    {
        $data = array(
            'uid' => I('uid'),
            'sign' => I('sign')
        );
        $coin = false;
        if (!checkSign($data, C('API_KEY'))) {
            $this->ajaxReturn(null, '签名错误', 0);
        }
        //是否领取过奖励
        $rank = M('coin_log')->where(array('type' => 10, 'uid' => $data['uid'], '_string' => 'to_days("' . date('Y-m-d') . '") = to_days(FROM_UNIXTIME(create_time,"%Y-%m-%d"))'))->find();
        if ($rank) {
            $this->ajaxReturn(null, '已经领取过奖励', 0);
        }
        //获取配置排名奖励
        $setting = M('options')->where(array('option_name' => 'site_options'))->find();
        $setting = json_decode($setting['option_value'], true);
        //查询排名
        $info = $this->getRankingData($setting['rank'], array(
            'p1.referee_uid' => array('gt', 0),
            '_string' => 'to_days("' . date('Y-m-d') . '") - to_days(FROM_UNIXTIME(p1.create_time,"%Y-%m-%d")) = 1'
        ));
        foreach ($info as $k => $v) {
            if ($v['uid'] == $data['uid']) {
                $coin = $v['coin'];
            }
        }
        if (!$coin) {
            $this->ajaxReturn(null, '非法用户', 0);
        }
        if (M('player')->where(array('id' => $data['uid']))->setInc('coin', $coin)) {
            $ucoin = M('player')->where(array('id' => $data['uid']))->getField('coin');
            M('coin_log')->add(array('uid' => $data['uid'], 'type' => 10, 'coin_change' => $coin, 'coin_counts' => $ucoin, 'create_time' => time()));
            M('task')->add(array('uid' => $data['uid'], 'type' => 8, 'create_time' => time()));
            $this->ajaxReturn(null, '领取成功');
        } else {
            $this->ajaxReturn(null, '领取失败', 0);
        }
    }

    /**
     * 用户自己邀请排行
     */
    public function userRanking()
    {
        $data = array(
            'uid' => I('uid', 0),
            'type' => I('type', 1),//1今日2昨日
            'sign' => I('sign')
        );
        if (!checkSign($data, C('API_KEY'))) {
            $this->ajaxReturn(null, '签名错误', 0);
        }
        if ($data['uid'] == '' || $data['uid'] <= 0) {
            $this->ajaxReturn(0, 'success');
        }
        $where['referee_uid'] = $data['uid'];
        if ($data['type'] == 1) {
            $where['_string'] = 'to_days(FROM_UNIXTIME(create_time,"%Y-%m-%d")) = to_days("' . date('Y-m-d') . '")';
        } else {
            $where['_string'] = 'to_days("' . date('Y-m-d') . '") - to_days(FROM_UNIXTIME(create_time,"%Y-%m-%d")) = 1';
        }
        $count = M('player')->where($where)->count();
        $this->ajaxReturn($count, 'success');
    }

    /**
     * 排行奖励须知
     */
    public function rankNotice()
    {
        $data = get_site_options();
        if (!$data['rankNotice']['title'][0]) $this->ajaxReturn(null, 'success');
        foreach ($data['rankNotice']['title'] as $k => $v) {
            $info[$k]['title'] = $v;
            $info[$k]['content'] = $data['rankNotice']['content'][$k];
        }
        $this->ajaxReturn($info, 'success');
    }

    /**
     * @param $setting
     * @param $where
     * @return mixed
     */
    protected function getRankingData($setting, $where)
    {
        $data = M('player p1')
            ->field('p.id uid,p.username,count(*) count,i.icon_url,p.vip,i.sex')
            ->join('join __PLAYER__ p on p1.referee_uid=p.id')
            ->join('left join __PLAYER_INFO__ i on i.uid=p.id')
            ->where($where)
            ->group('p1.referee_uid')
            ->order('count desc,p1.create_time')
            ->limit(10)
            ->select();
        if ($data) {
            foreach ($data as $k => $v) {
                $rank = M('coin_log')->where(array('type' => 10, 'uid' => $v['uid'], '_string' => 'to_days("' . date('Y-m-d') . '") = to_days(FROM_UNIXTIME(create_time,"%Y-%m-%d"))'))->find();
                $data[$k]['icon_url'] = get_avatar_url($v['icon_url']);
                $data[$k]['ranking'] = $k + 1;
                $data[$k]['coin'] = $setting[$k];
                $data[$k]['got'] = $rank ? 1 : 0;
                $data[$k]['sex'] = $v['sex'] != null ? $v['sex'] : '3';
            }
        }
        return $data;
    }

    /**
     * 用户协议
     */
    public function userAgreement()
    {
        $setting = M('options')->where(array('option_name' => 'agreement'))->find();
        $this->data = html_entity_decode($setting['option_value']);
        $this->display('agreement');
    }

    /**
     * 任务中心
     */

    public function task_center()
    {
        $uid = I('uid');
        $channel = I('channel');

        if (empty($channel) || empty($uid)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }
        $arr = array(
            'uid' => $uid,
            'channel' => $channel,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }


        $player_info = $this->player_model->where(array('id' => $uid))->count();


        if ($player_info < 1) {
            $this->ajaxReturn(null, '用户不存在', 0);
        }

        $task_info = M('task')->where(array('uid' => $uid, 'create_time' => array('egt', strtotime(date('Y-m-d')))))->group('type')->getfield('type,count(*) count', true);

        $task = array(
            1 => isset($task_info[1]) ? 1 : 0,
            2 => isset($task_info[2]) ? 1 : 0,
            3 => isset($task_info[3]) ? 1 : 0,
            4 => isset($task_info[4]) ? 1 : 0,
            5 => isset($task_info[5]) ? 1 : 0,
            6 => isset($task_info[6]) ? 1 : 0,
            7 => isset($task_info[7]) ? 1 : 0,
            8 => isset($task_info[8]) ? 1 : 0,
            9 => isset($task_info[9]) ? (int)$task_info[9] : 0,
        );

        $drive_bonus = C('DRIVE_BONUS');

        $drive_bonus = $drive_bonus[3]['bonus'] . '-' . $drive_bonus[0]['bonus'];

        $sign_config = C('SIGN_CONFIG');

        $site_options = get_site_options();

        $data = array(
            'recom_top' => $site_options['friend_coin_top'] ? $site_options['friend_coin_top'] : 2000,
            'sign_day_bonus' => $sign_config['DAY_BONUS'],
            'platform_coin_ratio' => $site_options['platform_coin_ratio'] ? $site_options['platform_coin_ratio'] : 10,
            'pl_coin' => $site_options['pl_coin'],
            'lottery_bonus' => 500,
            'rank_recom_top' => $site_options['rank'][0] ? $site_options['rank'][0] : 1000,
            'drive_bonus' => $drive_bonus,
            'task' => $task,
        );

        $this->ajaxReturn($data);
    }

    public function app_promise()
    {
        $channel = I('channel');

        if (empty($channel)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'channel' => $channel,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $site_options = get_site_options();

        $data['list'] = array_values($site_options['app_promise']);
        $data['pic'] = sp_get_asset_upload_path($site_options['promise_pic']);


        $this->ajaxReturn($data);

    }

    public function do_init_v2()
    {
        $system = I('system');
        $version = I('version');
        $channel = I('channel');
        $maker = I('maker');
        $machine_code = I('machine_code');
        $mobile_model = I('mobile_model');
        $system_version = I('system_version');
        $mac = I('mac');
        $is_first_boot = I('is_first_boot');
        $ext = I('ext');
        $uid = I('uid');
        if (empty($system) || empty($version) || strlen($is_first_boot) < 1) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }


        $arr = array(
            'system' => $system,
            'version' => $version,
            'channel' => $channel,
            'maker' => $maker,
            'machine_code' => $machine_code,
            'mobile_model' => $mobile_model,
            'system_version' => $system_version,
            'mac' => $mac,
            'is_first_boot' => $is_first_boot,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }


        if (empty($ext)) {
            $channel = C('MAIN_CHANNEL');
        } else {
            $ext = explode('-', $ext);
            $channel = $ext[0];
        }


        if (M('channel')->where(array('id' => $channel))->count() == 0) {
            $channel = C('MAIN_CHANNEL');
        }


        $now_time = time();

        $box_install_info_model = M('box_install_info', 'syo_', C('185DB'));
        $box_boot_info_model = M('box_boot_info', 'syo_', C('185DB'));

        if (!empty($maker) && !empty($machine_code) && !empty($mobile_model) && !empty($system_version)) {
            if ($is_first_boot == 1) {
                $data = array(
                    'system' => $system,
                    'version' => $version,
                    'channel' => $channel,
                    'maker' => $maker,
                    'machine_code' => $machine_code,
                    'mobile_model' => $mobile_model,
                    'system_version' => $system_version,
                    'ip' => get_client_ip(1, true),
                    'mac' => $mac,
                    'create_time' => $now_time
                );
                //如果是首次登陆进行安装上报统计
                if (!$box_install_info_model->where(array('machine_code' => $data['machine_code']))->find()) {
                    if (!$box_install_info_model->add($data)) {
                        $this->ajaxReturn('', 'error', 0);
                    } else {
                        M('options', 'syo_', C('185DB'))->where(array('option_name' => 'app_total'))->setInc('option_value', 1);
                    }
                }
            }

            //盒子启动统计
            $data = array(
                'system' => $system,
                'version' => $version,
                'channel' => $channel,
                'machine_code' => $machine_code,
                'system_version' => $system_version,
                'ip' => get_client_ip(1, true),
                'create_time' => $now_time
            );

            if ($box_boot_info_model->add($data)) {
                $info = $box_install_info_model->where(array('machine_code' => $data['machine_code']))->find();
                if ($info) {
                    if (!$info['ip'] && $data['ip']) {
                        $save['ip'] = $data['ip'];
                    }
                    $save['modified_time'] = $now_time;
                    $save['count'] = array('exp', 'count+1');
                    $box_install_info_model->where(array('machine_code' => $data['machine_code']))->setField($save);
                }
            } else {
                $this->ajaxReturn('', 'error', 0);
            }
        }


        //客服端检查更新
        $option = M('options', 'syo_', C('185DB'))->where(array('option_name' => 'site_options'))->find();
        $info = json_decode($option['option_value'], true);

        if ($system == 1) {
            if ($channel == C('MAIN_CHANNEL')) {
                if ($version < $info['site_android_version']) {
                    $url = $info['site_android_download'];
                }
            } else {
                if ($version < $info['site_android_version']) {
                    $info['channel_android_root'] = rtrim($info['channel_android_root'], '/') . '/';
                    $url = $info['channel_android_root'] . $channel . '/androidapp_' . $channel . '_' . $info['channel_android_version'] . '.apk';
                }
            }

        } else {
            if ($channel == C('MAIN_CHANNEL')) {
                if ($version < $info['site_ios_version']) {
                    $url = $info['site_ios_download'];
                }
            } else {
                if ($version < $info['site_ios_version']) {
                    $url = $info['channel_ios_root'] . 'ios_app_' . $channel . '_' . $info['channel_ios_version'];
                }
            }

        }
        $result['update_url'] = $url;

        //获取启动页广告图
        $res = M('appstart')->where(array('channel' => $channel))->find();

        $path = '';
        if ($res) {
            if ($system == 1) {
                $path = C('FTP_URL') . ((strpos($res['android_img'], '/assets/pic') !== false) ? '' : '/assets/pic/') . $res['android_img'];
                $link = $res['android_link'];
            } else {
                $path = C('FTP_URL') . ((strpos($res['ios_img'], '/assets/pic') !== false) ? '' : '/assets/pic/') . $res['ios_img'];
                $link = $res['ios_link'];
            }
        }
        $result['start_page'] = $path;
        $result['start_page_link'] = $link;

        //获取公告
        $data = array();
        $data['cid'] = $channel;

        $data['appid'] = C('BOX_APP_ID');
        $data['status'] = 1;
        $field = 'title,content,add_time';
        $info = M('notice')->field($field)->where($data)->order('id desc')->find();


        if (!$info) {
            $info = M('notice')->field($field)->where(array('appid' => C('BOX_APP_ID'), 'cid' => 0, 'status' => 1))->order('id desc')->find();
        }
        $result['app_notice'] = $info;

        //消息附件通知
        $result['msg_info'] = '';
        if ($uid) {
            $result['msg_info'] = $this->message_notice($uid);
        }


        //获取初始化数据
        $channel_info = M('channel')->where(array('id' => $channel))->find();
        $result['box_static'] = (int)$channel_info['box_static'];
        $discount_field = ($system == 2) ? 'ios_discount_enabled' : 'android_discount_enabled';
        $result['discount_enabled'] = (int)$channel_info[$discount_field];
        $result['business_enbaled'] = (int)$channel_info['business_enbaled'];

        if ($channel == C('MAIN_CHANNEL')) {
            $result['qq_zixun'] = 'http://q.url.cn/abe9qf?_type=wpa&qidian=true';
        }

        //获取所有游戏名和游戏包名（安卓用）
        if ($system == 1) {
            if ($channel != C('MAIN_CHANNEL')) $where['display_channel'] = 1;
            if ($system == 1) {

                $sql_system = "(find_in_set('a',system) or find_in_set('a,i',system) or find_in_set('a,y',system))";
                $field = 'id,version,tag,android_pack,concat("' . C('185SY_URL') . '",logo) as logo';
            } else {
                $sql_system = "(find_in_set('i',system) or find_in_set('y',system) or find_in_set('i,y',system))";
                $field = 'id,version,tag,ios_pack,concat("' . C('185SY_URL') . '",logo) as logo';
            }
            $where['_string'] = $sql_system;
            $where['status'] = 0;
            $where['isdisplay'] = 1;

            $result['game_names'] = M('game', 'syo_', C('185DB'))->where($where)->getfield('gamename', true);

            $where['platform'] = array('neq', 3);
            $result['game_packs'] = M('game', 'syo_', C('185DB'))->field($field)->where($where)->select();

        }

        //用户行为统计开关
        $result['actstatic_enabled'] = (int)$channel_info['actstatic_enabled'];

        //问答须知
        $consult_info = get_site_options('consult');

        $result['consult'] = $consult_info;

        $day_consult_bonus = C('DAY_CONSULT_BONUS');
        $result['consult']['task_bonus'] = $day_consult_bonus['bonus'];

        //精品开关
        $product_field = ($system == 2) ? 'ios_product_enabled' : 'android_product_enabled';
        $result['good_product_enabled'] = (int)$channel_info[$product_field];
        $result['channel'] = $channel;


        $this->ajaxReturn($result);
    }

    public function crazy_label_instruct()
    {
        $this->type = I('type');
        $this->uid = I('uid');
        $this->display();
    }

    public function getCrazyInfo()
    {
        $type = I('type');//1签到狂，2助人狂，3点评狂，4开车狂
        $uid = I('uid');
        if (empty($type) || empty($uid)) {
            $this->ajaxReturn('', '参数不能为空', 0);
        }

        $where['uid'] = $uid;
        switch ($type) {
            case 1:
                $conf = C('CRAZY_MAN.signIn');
                $fix = 'sign';
                $model = M('sign_log');
                break;
            case 2:
                $conf = C('CRAZY_MAN.help');
                $fix = 'help';
                $model = M('consult_info');
                $where['audit'] = 1;
                break;
            case 3:
                $conf = C('CRAZY_MAN.comment');
                $fix = 'comment';
                $model = M('comment');
                $where['status'] = 1;
                $where['comment_type'] = 2;
                $where['order'] = 1;
                break;
            case 4:
                $conf = C('CRAZY_MAN.drive');
                $fix = 'car';
                $model = M('dynamics');
                $where['audit'] = 1;
                $where['status'] = 0;
                break;
            default:
                $this->ajaxReturn('', 'error', 0);
        }
        $count = $model->where($where)->count();
        $res['user']['userLevel'] = crazy_level($count, $type);


        foreach ($conf as $k => $v) {
            $img = '';
            if ($k >= 1 && $k <= 3) {
                $img = "/public/images/{$fix}1.png";
            } elseif ($k > 3 && $k <= 6) {
                $img = "/public/images/{$fix}2.png";
            } else {
                $img = "/public/images/{$fix}3.png";
            }
            $res['list'][$k]['icon'] = $img;
            $res['list'][$k]['condition'] = $v;
        }

        if ($res['user']['userLevel'] > 0) {
            $res['user']['icon'] = $res['list'][$res['user']['userLevel']]['icon'];
        } else {
            $res['user']['icon'] = "/public/images/{$fix}0.png";
        }
        $this->ajaxReturn($res);
    }


    /**
     * 狂人排行
     */
    public function crazyManTop()
    {
        $post = array(
            'type' => I('type'),
            'uid' => I('uid', 0),
            'sign' => I('sign')
        );
        if (empty($post['type'])) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        if (!checkSign($post, C('API_KEY'))) {
            $this->ajaxReturn(null, '签名错误', 0);
        }
        $res = '';
        $res['userLevel'] = 0;
        //1金币榜  2签到榜  3开车榜  4点评榜
        switch ($post['type']) {
            case 1:
                $data = M('coin_log c')->field('p.id uid,i.nick_name,p.username,p.vip_end,sum(c.coin_change) count')->join('left join __PLAYER__ p on p.id=c.uid')->join('left join __PLAYER_INFO__ i on i.uid=p.id')->where('c.coin_change > 0')->group('c.uid')->order('count desc')->limit(10)->select();
                break;
            case 2:
                $data = M('sign_log s')->field('p.id uid,i.nick_name,p.username,p.vip_end,count(s.id) count')->join('left join __PLAYER__ p on p.id=s.uid')->join('left join __PLAYER_INFO__ i on i.uid=p.id')->group('s.uid')->order('count desc')->limit(10)->select();
                break;
            case 3:
                $data = M('dynamics d')->field('p.id uid,i.nick_name,p.username,p.vip_end,count(d.id) count')->join('left join __PLAYER__ p on p.id=d.uid')->join('left join __PLAYER_INFO__ i on i.uid=p.id')->where(array('d.audit' => 1, 'd.status' => 0))->group('d.uid')->order('count desc')->limit(10)->select();
                break;
            case 4:
                $data = M('comment c')->field('p.id uid,i.nick_name,p.username,p.vip_end,count(c.id) count')->join('left join __PLAYER__ p on p.id=c.uid')->join('left join __PLAYER_INFO__ i on i.uid=p.id')->where(array('c.status' => 1, 'c.comment_type' => 2))->group('c.uid')->order('count desc')->limit(10)->select();

                break;
        }

        foreach ($data as $k => $v) {
            //是否进前10
            if ($post['uid'] > 0 && $v['uid'] == $post['uid']) {
                $res['userLevel'] = $k + 1;
            }

            if ($v['nick_name']) {
                $data[$k]['username'] = cutStr($v['nick_name'], 'uname');
            } else {
                $data[$k]['username'] = cutStr($v['username'], 'uname');
            }

            $data[$k]['is_vip'] = $v['vip_end'] > time() ? 1 : 0;

            $pic = M('player_info')->where(array('uid' => $v['uid']))->getField('icon_url');
            $data[$k]['headpic'] = $pic ? C('FTP_URL') . $pic : '';
            //开车狂
            $drive = M('dynamics')->where(array('uid' => $v['uid'], 'audit' => 1, 'status' => 0))->count();
            //点评狂
            $comment = M('comment')->where(array('uid' => $v['uid'], 'status' => 1, 'comment_type' => 2))->count();
            //助人狂
            $help = M('consult_info')->where(array('uid' => $v['uid'], 'audit' => 1))->count();
            //签到狂
            $signin = M('sign_log')->where(array('uid' => $v['uid']))->count();

            $data[$k]['driveLevel'] = crazy_level($drive, 4);
            $data[$k]['commentLevel'] = crazy_level($comment, 3);
            $data[$k]['helpLevel'] = crazy_level($help, 2);
            $data[$k]['signLevel'] = crazy_level($signin, 1);
            unset($data[$k]['vip_end'], $data[$k]['nick_name']);
        }
        $res['list'] = $data;

        $this->ajaxReturn($res);

    }

    /**
     * 消息附件通知-新 盒子
     */
    private function message_notice($uid)
    {

        $map['type'] = 4;
        $map['message_type'] = 4;
        $map['end_time'] = array('egt', time());
        $map['_string'] = "`uids` = '' or find_in_set({$uid},`uids`)";
        $msg = M('message')->where($map)->order('id desc')->find();

        if ($msg) {
            $map_exist = array('message_id' => $msg['id'], 'type' => 4, 'uid' => $uid);
            $map_msg = $map_exist;
            $exist = M('user_message')->where($map_exist)->count();
            if (empty($exist)) {
                $map_exist['create_time'] = time();
                M('user_message')->add($map_exist);
            }
            $map_msg['is_read'] = 0;
            $user_msg = M('user_message')->where($map_msg)->find();
            if ($user_msg) {
                $data['title'] = $msg['title'];
                $data['desc'] = $msg['desc'];
                $data['action'] = $msg['action'];
                $data['system'] = $msg['system'];
                $data['api_url'] = $msg['api_url'];
                $data['attach_type'] = $msg['attach_type'];
                $data['attach_count'] = $msg['attach_count'];
                $data['end_time'] = $msg['end_time'];
                $data['user_message_id'] = $user_msg['id'];
                $data['is_read'] = $user_msg['is_read'];
                $data['is_get'] = $user_msg['is_get'];
                return $data;
            }

        }
        return '';
    }
}

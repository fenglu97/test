<?php
/*
 * @Descripttion: 
 * @version: 
 * @Author: hecheng
 * @Date: 2019-12-24 13:39:13
 * @LastEditors: hecheng
 * @LastEditTime: 2020-03-11 15:56:16
 */
/**
 * H5sdk登录界面
 * @author qing.li
 * @date 2019-04-25
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class H5sdkController extends AppframeController
{

    // 账号登录
    public function login_account()
    {
        $this->display();
    }
    // 手机号登录
    public function login_mobile()
    {
        $this->display();
    }
    // 用户名注册
    public function register_user()
    {
        $this->display();
    }
    // 一键注册
    public function register_trial()
    {
        $this->display();
    }
    // 手机号注册
    public function register_mobile()
    {
        $this->display();
    }
    // 账号列表
    public function account_list()
    {
        $this->display();
    }
    // 找回密码
    public function retrieve_password_check()
    {
        $this->display();
    }
    // 找回密码
    public function retrieve_password()
    {
        $this->display();
    }
    // 用户协议
    public function user_agreement()
    {
        $setting = M('options')->where(array('option_name' => 'agreement'))->find();
        $data = html_entity_decode($setting['option_value']);
        $this->assign('data',$data);
        $this->display();
    }
    // 隐私协议
    public function user_hide()
    {
        $setting = M('options')->where(array('option_name' => 'hide'))->find();
        $data = html_entity_decode($setting['option_value']);
        $this->assign('data',$data);
        $this->display();
    }
    // 支付
    public function pay()
    {
        $this->display();
    }
    // 实名认证
    public function real_name_verify()
    {
        $this->display();
    }
    
    /**
     * 绑定信息
     * 请求参数
     * @param uid
     * @param sign   md(uid+visitor)
     * 请求方式 GET
     */
    public function visitor_info()
    {
        if(IS_AJAX)  {
            $data = I('post.');
            if(empty($data['uid']) || empty($data['username']) || empty($data['password']) || empty($data['real_name']) || empty($data['id_card'])) {
                $this->ajaxReturn(null,'参数不能为空',0);
            }
            // 获取用户信息
            $player = M('player')->find($data['uid']);
            if(!$player) {
                $this->ajaxReturn(null,'用户不存在',0);
            }
            
            if (!isChineseName($data['real_name'])) {
                $this->ajaxReturn(null, '中文名不合法', 11);
            }
    
            if (!validateIDCard($data['id_card'])) {
                $this->ajaxReturn(null, '身份证号不合法', 16);
            }
            
            if($player['is_visitor']) {
                // 游客
                $testUsername = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/';
                if(!preg_match($testUsername,$data['username'])) {
                    $this->ajaxReturn(null,'用户名由6-12位字母或字母+数字组成',0);
                }
                //用户名需过滤的字符的正则
                $stripChar = '?<*.>\'"';
                if (preg_match('/[' . $stripChar . ']/is', $data['username']) == 1) {
                    $this->ajaxReturn(null, '用户名中包含' . $stripChar . '等非法字符！', 24);
                }
                //用戶名不能以大寫JS開頭
                if (preg_match('/^'.C('USER_NAME_PREFIX').'/', $data['username']) == 1) {
                    $this->ajaxReturn(null, '用戶名不能以'.C('USER_NAME_PREFIX').'开头', 24);
                }
                $banned_usernames = explode(",", sp_get_cmf_settings("banned_usernames"));
                if (in_array($data['username'], $banned_usernames)) {
                    $this->ajaxReturn(null, '此用户名禁止使用！', 11);
                }
                if(M('player')->where(['username' => $data['username']])->find()) {
                    $this->ajaxReturn(null,'该用户已经被注册',0);
                }

                // 游客模式的用户 需要修改用户名 密码 和 实名认证信息
                M()->startTrans();
                $updatePlayer = [
                    'username' => $data['username'],
                    'password' => sp_password_by_player($data['password'], $player['salt']),
                ];
                $res = M('player')->where(['id' => $data['uid']])->save($updatePlayer);
                $res1 = true;
                if(!empty($res)) {
                    $updatePlayerInfo = [
                        'real_name' => $data['real_name'],
                        'id_card' => $data['id_card']
                    ];
                    $res1 = M('player_info')->where(['uid' => $data['uid']])->save($updatePlayerInfo);
                }
                if(!empty($res) && !empty($res1)) {
                    M()->commit();
                    $this->ajaxReturn(['username' => $data['username'],'password' => $data['password']],'绑定成功',1);
                }else{
                    M()->rollback();
                    $this->ajaxReturn(null,'系统繁忙，请稍后再试',0);
                }
            }else{
                // 注册用户
                if($player['username'] != $data['username'] || $player['password'] != $data['password']) {
                    $this->ajaxReturn(null,'请求错误',0);
                }
                // 查询player_info里面是否有该用户数据
                $player_info = M('player_info')->where(['uid' => $data['uid']])->find();
                if($player_info) {
                    // 有数据则修改
                    $update = [
                        'real_name' => $data['real_name'],
                        'id_card' => $data['id_card']
                    ];
                    $res = M('player_info')->where(['uid' => $data['uid']])->save($update);
                    if(!empty($res)) {
                        $this->ajaxReturn(['username' => $data['username'],'password' => $data['password']],'绑定成功',1);
                    }else{
                        $this->ajaxReturn(null,'系统繁忙，请稍后再试',0);
                    }
                }else{
                    // 没有数据则新增
                    $add = [
                        'uid' => $data['uid'],
                        'real_name' => $data['real_name'],
                        'id_card' => $data['id_card'],
                        'create_time' => time(),
                        'modifiy_time' => time(),
                    ];
                    $res = M('player')->add($add);
                    if(!empty($res)) {
                        $this->ajaxReturn(['username' => $data['username'],'password' => $data['password']],'绑定成功',1);
                    }else{
                        $this->ajaxReturn(null,'系统繁忙，请稍后再试',0);
                    }
                }
            }
        }else{
            $data =  I('get.');
            if(empty($data['uid']) || empty($data['sign'])) {
                exit('系统繁忙，请稍后再试');
            }
            if($data['sign'] != md5($data['uid'].'+visitor')) {
                exit('系统繁忙，请稍后再试');
            }
            // 根据UID 获取用户信息
            $player = M('player')
                ->alias('a')
                ->join('bt_player_info as b ON a.id = b.uid')
                ->field('a.id,a.username,a.password,a.is_visitor,b.real_name,b.id_card')
                ->where(['a.id' => $data['uid']])
                ->find();
            if(!$player) {
                $this->ajaxReturn(null,'用户不存在',0);
            }
            // 如果用户是注册用户  则不允许用户修改账号信息，只能实名认证
            // 如果用户是游客用户 则需要都修改
            if($player['is_visitor']) {
                // 游客模式
                $player['username'] = '';
                $player['password'] = '';
            }
            $this->assign('data',$player);
            $this->display();
        }
        
    }

}

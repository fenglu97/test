<?php
/**
 * 个人中心
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/12/11
 * Time: 10:44
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class PersonalCenterController extends AdminbaseController{

    public function index(){
        if(IS_POST){
            $post = I('');
            if(isset($post['user_truename'])) $add['user_truename'] = $post['user_truename'];
            if(isset($post['qq'])) $add['qq'] = $post['qq'];
            if(isset($post['user_email'])) $add['user_email'] = $post['user_email'];
            if(M('users')->where(array('id'=>$_SESSION['ADMIN_ID']))->save($add) !== false){
                $person['idCard'] = $post['idCard'];
                $person['bankCard'] = $post['bankCard'];
                $person['openBank'] = $post['openBank'];
                $person['alipay'] = $post['alipay'];
                $person['uid'] = $_SESSION['ADMIN_ID'];
                $person['create_time'] = time();
                $info = M('personalinfo')->where(array('uid'=>$_SESSION['ADMIN_ID']))->find();
                if(!$info){
                    M('personalinfo')->add($person);
                }elseif(!$info['idCard'] && !$info['bankCard'] && !$info['openBank'] && !$info['alipay']){
                    $uid = $person['uid'];
                    unset($person['uid'],$person['create_time']);
                    M('personalinfo')->where(array('uid'=>$uid))->save($person);
                }

                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $role_id = M('role_user')->where(array('user_id'=>$_SESSION['ADMIN_ID']))->getField('role_id');
            if($role_id != C('SPREAD_ID')){
                $this->error('无权限进入');
            }
            $data = M('users')->field('user_truename,user_email,mobile,qq')->where(array('id'=>$_SESSION['ADMIN_ID']))->find();
            $personal = M('personalinfo')->field('idCard,bankCard,openBank,alipay')->where(array('uid'=>$_SESSION['ADMIN_ID']))->find();

            $data = $personal ? array_merge($data,$personal) : $data;

            foreach($data as $k=>&$v){
                if($k == 'mobile') $v = cutStr($v,'mobile');
                if($k == 'idCard') $v = cutStr($v,'idCard');
                if($k == 'bankCard') $v = cutStr($v,'bankCard');
                if($k == 'alipay') $v = cutStr($v,'alipay');
                if($k == 'user_truename') $v = cutStr($v,'uname');
            }
            $this->data = $data;
            $this->display();
        }
    }
}
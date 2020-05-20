<?php
/**
 * 研发游戏管理
 * User: fantasmic
 * Date: 2017/6/15
 * Time: 16:30
 */
namespace Common\Model;
use Common\Model\CommonModel;

class GameModel extends CommonModel {

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('name', 'require', '请输入游戏名！', 0, 'regex', 2),
        array('tag', 'require', '请输入游戏简写！', 0, 'regex', 2),
        array('tag', '', '游戏简写已存在！', 0, 'unique', 2),
//        array('android_package_name', 'require', '请输入安卓包名！', 0, 'regex', 2),
        array('android_package_name', '', '安卓包名已存在！', 2, 'unique', 2),
//        array('ios_package_name', 'require', '请输入苹果包名！', 0, 'regex', 2),
        array('ios_package_name', '', '苹果包名已存在！', 2, 'unique', 2),
//        array('android_version', 'require', '请输入安卓版本！', 0, 'regex', 2),
//        array('ios_version', 'require', '请输入苹果版本！', 0, 'regex', 2),
//        array('android_version_num', 'require', '请输入安卓内部版本！', 0, 'regex', 2),
//        array('ios_version_num', 'require', '请输入苹果内部版本！', 0, 'regex', 2),
//        array('android_url', 'require', '请输入安卓母包地址！', 0, 'regex', 2),
//        array('ios_url', 'require', '请输入苹果母包地址！', 0, 'regex', 2),
//        array('android_payurl', 'require', '请输入安卓支付地址！', 0, 'regex', 2),
//        array('ios_payurl', 'require', '请输入苹果支付地址！', 0, 'regex', 2),
//        array('serverurl', 'require', '请输入区服地址！', 0, 'regex', 2),
//        array('bpayurl', 'require', '请输入返利地址！', 0, 'regex', 2),
        array('topup_scale', 'require', '请输入充值比例！', 0, 'regex', 2),
        array('give_scale', 'require', '请输入赠送比例！', 0, 'regex', 2),
//        array('channel', 'checkChannel', '请选择渠道类型', 1, 'callback', 2),
    );

    protected $_auto = array (
        array ('modifiy_time', 'time', 2, 'function'),
        array ('uid','getuid',2,'callback'),
        array ('trade','is_trade',2,'callback'),
        array ('double_platform','is_double_platform',2,'callback'),
        array ('is_mandatory_update','is_mandatory_update',2,'callback'),
        array ('is_pay','is_pay',2,'callback'),
        array ('is_accelerate','is_accelerate',2,'callback'),
        array ('is_audit','is_audit',2,'callback'),
        array ('update_alert','update_alert',2,'callback'),
        array ('channel','getChannel',2,'callback'),
        array ('online','getOnline',2,'callback'),
        array ('pay_platform_money','is_pay_platform_money',2,'callback')
    );

//    protected function checkChannel(){
//        if(empty($_POST['channel'])){
//            return false;
//        }
//        return true;
//    }

    protected function getChannel(){
        if($_POST['channel']){
            $ids = implode(',',$_POST['channel']);
        }else{
            $ids = '';
        }
        return $ids;
    }

    protected function getuid(){
        return session('ADMIN_ID');
    }



    protected function is_trade(){
        $data = I('trade');
        return empty($data) ? 0 : 1;
    }

    protected function is_double_platform(){
        $data = I('double_platform');
        return empty($data) ? 0 : 1;
    }

    protected function is_mandatory_update(){
        $data = I('is_mandatory_update');
        return empty($data) ? 0 : 1;
    }

    protected function is_pay(){
        $data = I('is_pay');
        return empty($data) ? 0 : 1;
    }

    protected function is_accelerate(){
        $data = I('is_accelerate');
        return empty($data) ? 0 : 1;
    }

    protected function is_audit(){
        $data = I('is_audit');
        return empty($data) ? 0 : 1;
    }

    protected function update_alert(){
        $data = I('update_alert');
        return empty($data) ? 0 : 1;
    }
    protected function getOnline(){
        $data = I('online');
        return empty($data) ? 0 : 1;
    }

    protected function is_pay_platform_money(){
        $data = I('pay_platform_money');
        return empty($data) ? 0 : 1;
    }
}
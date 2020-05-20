<?php
/**
 * 公告模型
 * User: fantasmic
 * Date: 2017/6/15
 * Time: 16:30
 */
namespace Common\Model;
use Common\Model\CommonModel;

class NoticeModel extends CommonModel {

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('title', 'require', '请输入标题！', 0, 'regex', CommonModel:: MODEL_BOTH ),
        array('desc', 'require', '请输入简介！', 0, 'regex', CommonModel:: MODEL_BOTH ),
        array('content', 'require', '请输入内容！', 0, 'regex', CommonModel:: MODEL_BOTH ),
        array('add_time', 'require', '请输入发布时间！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    protected $_auto = array (
        array ('create_time', 'time', 1, 'function'),
        array ('modifiy_time', 'time', 2, 'function'),
        array ('add_time', 'gettime', 3, 'callback'),
        array ('uid', 'getuid', 3, 'callback'),
        array ('top', 'top', 3, 'callback'),
        array ('is_display', 'is_display', 3, 'callback'),
        array ('force', 'force', 3, 'callback'),
    );

    protected function getuid(){
        return session('ADMIN_ID');
    }

    protected function gettime(){
        return strtotime(I('add_time'));
    }

    protected function top(){
        $data = I('top');
        return empty($data) ? 0 : 1;
    }

    protected function is_display(){
        $data = I('is_display');
        return empty($data) ? 0 : 1;
    }

    protected function force(){
        $data = I('force');
        return empty($data) ? 0 : 1;
    }
}
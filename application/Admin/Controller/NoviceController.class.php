<?php
/**
 * 新手教程
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/19
 * Time: 11:15
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class NoviceController extends AdminbaseController{

    /**
     * 新手课件显示
     */
    public function courseware(){
        $this->data = html_entity_decode(M('novice')->where(array('key'=>'courseware'))->getField('value'));
        $this->display();
    }

    /**
     * 常见问题
     */
    public function faq(){
        $this->data = html_entity_decode(M('novice')->where(array('key'=>'faq'))->getField('value'));
        $this->display();
    }

    /**
     * 内容列表
     */
    public function content(){
        $this->data = M('novice')->select();
        $this->display();
    }

    /**
     * 修改内容
     */
    public function doContent(){
        if(IS_POST){
            $id = I('id');
            $content = I('content');
            if(M('novice')->where(array('id'=>$id))->setField('value',$content) !== false){
                $this->success();
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $this->data = M('Novice')->where(array('id'=>$id))->find();
            $this->display();
        }
    }
}
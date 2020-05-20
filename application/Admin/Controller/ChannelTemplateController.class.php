<?php
/**
 * 渠道模板
 * @author qing.li
 * @date 2018-08-29
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class ChannelTemplateController extends AdminbaseController
{

    public function _initialize()
    {
        parent::_initialize();
        $this->channel_template_model = M("channel_template");

    }

    public function index()
    {
        $count = $this->channel_template_model->count();
        $page = $this->page($count, 20);
        $list = $this->channel_template_model
            ->field('id,name,create_time')
            ->limit($page->firstRow, $page->listRows)
            ->order('create_time desc')
            ->select();

        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    public function add()
    {
        if(IS_POST)
        {
            $_POST['create_time'] = time();
            $_POST['shouyou_qq'] = json_encode(array('number'=>$_POST['shouyou_qq_number'],'link'=>$_POST['shouyou_qq_link']));
            $_POST['fanli_qq'] = json_encode(array('number'=>$_POST['fanli_qq_number'],'link'=>$_POST['fanli_qq_link']));
            $_POST['shouyou_group'] = json_encode(array('number'=>$_POST['shouyou_group_number'],'link'=>$_POST['shouyou_group_link'],'weblink'=>$_POST['shouyou_group_weblink']));
            $_POST['box_group'] = json_encode(array('number'=>$_POST['box_group_number'],'link'=>$_POST['box_group_link'],'weblink'=>$_POST['box_group_weblink']));
            if($this->channel_template_model->add($_POST)!==false)
            {
                $this->success('添加成功',U('index'));
            }
            else
            {
                $this->error('添加失败');
            }
            exit;
        }
        $this->display();
    }

    public function edit()
    {
        $id = I('id');
        if(IS_POST)
        {
            $_POST['shouyou_qq'] = json_encode(array('number'=>$_POST['shouyou_qq_number'],'link'=>$_POST['shouyou_qq_link']));
            $_POST['fanli_qq'] = json_encode(array('number'=>$_POST['fanli_qq_number'],'link'=>$_POST['fanli_qq_link']));
            $_POST['shouyou_group'] = json_encode(array('number'=>$_POST['shouyou_group_number'],'link'=>$_POST['shouyou_group_link'],'weblink'=>$_POST['shouyou_group_weblink']));
            $_POST['box_group'] = json_encode(array('number'=>$_POST['box_group_number'],'link'=>$_POST['box_group_link'],'weblink'=>$_POST['box_group_weblink']));
            if($this->channel_template_model->where(array('id'=>$id))->save($_POST)!==false)
            {
                //将为此模板的渠道全部修改
                unset($_POST['id']);
                unset($_POST['name']);
                M('channel')->where(array('template_id'=>$id))->save($_POST);
                $this->success('修改成功',U('index'));
            }
            else
            {
                $this->error('修改失败');
            }
            exit;
        }

        $info = $this->channel_template_model->where(array('status'=>1,'id'=>$id))->find();
        $info['shouyou_qq'] =json_decode($info['shouyou_qq'],true);
        $info['fanli_qq'] = json_decode($info['fanli_qq'],true);
        $info['shouyou_group'] =json_decode($info['shouyou_group'],true);
        $info['box_group'] =json_decode($info['box_group'],true);


        $info['ad_pic_url'] = sp_get_image_preview_url($info['ad_pic']);

        $this->info = $info;
        $this->display();

    }
}
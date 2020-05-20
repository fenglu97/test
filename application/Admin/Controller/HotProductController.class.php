<?php
/**
 * 精选产品后台控制器
 * @author qing.li
 * @date 2018-08-24
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class HotProductController extends AdminbaseController
{
    public function index()
    {
        $appid = I('appid');
        $map = array('t1.status'=>1);
        if($appid) $map['t1.appid'] = $appid;

        $count = M('hot_product')->alias('t1')->where($map)->count();
        $page = $this->page($count, 20);
        $list = M('hot_product')
            ->field('t1.id,t1.theme,t1.type,t1.create_time,t1.order,t2.game_name')
            ->alias('t1')
            ->join('left join bt_game t2 on t1.appid = t2.id')
            ->where($map)
            ->limit($page->firstRow, $page->listRows)
            ->order('t1.order asc,t1.create_time desc')
            ->select();

        $this->assign('list',$list);
        $this->assign('page',$page->show('Admin'));
        $this->assign('appid',$appid);
        $this->display();

    }

    public function add()
    {
        if(IS_POST)
        {
            $_POST['create_time'] = time();
            $_POST['imgs'] = array_slice($_POST['imgs'],0,10);
            $_POST['imgs']= json_encode($_POST['imgs']);
            if(M('hot_product')->add($_POST)!==false)
            {
                $this->success('添加成功');
            }
            else
            {
                $this->error('添加失败');
            }
        }

        $this->display();
    }

    public function edit()
    {
        $id = I('id');
        if(IS_POST)
        {
            $_POST['imgs'] = array_slice($_POST['imgs'],0,10);
            $_POST['imgs']= json_encode($_POST['imgs']);
            if(M('hot_product')->where(array('id'=>$id))->save($_POST)!==false)
            {
                $this->success('编辑成功');
            }
            else
            {
                $this->error('编辑失败');
            }
        }

        $info = M('hot_product')->where(array('id'=>$id))->find();

        $info['imgs'] = json_decode($info['imgs'],true);
        $this->assign('info',$info);
        $this->display();

    }

    public function del()
    {
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('hot_product')->where($where)->setField(array('status'=>0))){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    public function info()
    {
        $id = I('id');
        $imgs = M('hot_product')->where(array('id'=>$id))->getfield('imgs');
        if($imgs){
            $imgs = json_decode($imgs,true);

            foreach($imgs as $k=>$v)
            {
                $imgs[$k] = sp_get_image_preview_url($v);
            }

            $this->success($imgs);
        }else{
            $this->error('请求失败');
        }
    }


    public function order()
    {
        $status = parent::_listorders(M('hot_product'),'order');
        if ($status) {
            $this->success("排序成功！");
        } else {
            $this->error("排序失败！");
        }
    }

    public function tg_index()
    {
        $this->display();
    }
}
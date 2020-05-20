<?php
/**
 * 三方统计配置
 * @author qing.li
 * @date 2018-05-30
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class StaticConfController extends AdminbaseController
{
    public function index()
    {

        $appid = I('appid');
        $channel = I('channel');
        $type = I('type');

        if($appid !=='')
        {
            $map['appid'] = $appid;
        }
        if($channel !=='')
        {
            $map['channel'] = $channel;
        }
        if($type)
        {
            $map['type'] = $type;
        }

        $count = M('static_conf')->where($map)->count();

        $page = $this->page($count, 20);

        $data = M('static_conf')
            ->where($map)
            ->limit($page->firstRow,$page->listRows)
            ->order('create_time desc')
            ->select();

        $channel_ids ='';
        $app_ids = '';
        foreach($data as $v)
        {
            $channel_ids.=$v['channel'].',';
            $app_ids.=$v['appid'].',';
        }
        $channel_ids = trim($channel_ids,',');
        $app_ids = trim($app_ids,',');

        $channel_names = M('channel')->where(array('id'=>array('in',$channel_ids)))->getfield('id,name',true);
        $channel_names[0] = '所有渠道';
        $app_names = M('game')->where(array('id'=>array('in',$app_ids)))->getfield('id,game_name',true);
        $app_names[0] = '所有游戏';

        $this->channel_names = $channel_names;
        $this->app_names = $app_names;
        $this->data = $data;
        $this->appid = $appid;
        $this->channel = $channel;
        $this->type = $type;
        $this->page = $page->show('Admin');
        $this->static_conf = C('STATIC_CONF');
        $this->display();
    }

    public function add()
    {
        if(IS_POST)
        {
            $data['type'] = I('post.type');
            $data['appid'] = I('post.appid');
            $data['channel'] = I('post.channel');
            $data['android_key'] = I('post.android_key');
            $data['ios_key'] = I('post.ios_key');

            if(M('static_conf')->where($data)->count()>0)
            {
                $this->error('配置已经添加过，请勿重复提交');
            }

            $data['create_time'] = time();

            if(M('static_conf')->add($data)!==false)
            {
                $this->success('添加成功');
            }
            else
            {
                $this->error('添加失败');
            }
        }
        $this->static_conf = C('STATIC_CONF');
        $this->display();
    }

    public function del()
    {
        $ids = I('ids');

        if(M('static_conf')->where(array('id'=>array('in',implode(',',$ids))))->delete()!==false)
        {
            $this->success('删除成功');
        }
        else
        {
            $this->succes('删除失败');
        }

    }
}

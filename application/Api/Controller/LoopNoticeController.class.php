<?php
/**
 * 跑马灯公告接口
 * @author qing.li
 * @date 2018-08-02
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class LoopNoticeController extends AppframeController
{
    public function notice_list()
    {
        $admin_id = SESSION('ADMIN_ID');
        if(!$admin_id)
        {
            $this->ajaxReturn(null,'请先登陆',0);
        }

        $game_role = session('game_role');
        $channel_role = session('channel_role');
        if($game_role != 'all') $where['appid'] = array('in',$game_role);
        if($channel_role != 'all') $where['channel'] = array('in',$channel_role.',0');
        $where['status'] = 1;
        $list = M('loop_notice')->field('title,content,loop_times,loop_interval')->where($where)->order('create_time desc')->select();
        $this->ajaxReturn($list);
    }
}
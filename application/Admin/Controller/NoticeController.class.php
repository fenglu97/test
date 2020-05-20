<?php
/**
 * 公告管理
 * @author fantasmic
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class NoticeController extends AdminbaseController{

    /**
     * 公告列表
     */
    public function index(){
        $title = I('title');
        $start = I('start','');
        $end = I('end','');
        $game_role = session('game_role');
        $channel_role = session('channel_role');
        if($game_role != 'all' && $game_role != 'empty') $where['appid'] = array('in',$game_role);
        if($channel_role != 'all' && $channel_role != 'empty') $where['cid'] = array('in',$channel_role);
        if($start) $where['add_time'][] = array('gt',strtotime($start));
        if($end) $where['add_time'][] = array('lt',strtotime($end.' 23:59:59'));
        if($title) $where['title'] = array('like','%'.$title.'%');
        $where['status'] = 1;

        $list = M('notice')->where($where)->order('id desc')->select();
        $count = count($list);
        $page = $this->page($count, 20);
        $list = array_slice($list,$page->firstRow, $page->listRows);

        $this->users = M('users')->where(array('user_type'=>1))->getField('id,user_login');

        $this->page = $page->show('Admin');
        $this->start = $start;
        $this->end = $end;
        $this->list = $list;
        $this->title = $title;
        $this->display();
    }

    /**
     * 新增公告
     */
    public function add(){
        if(IS_POST){
            $model = D('Common/Notice');
            if(!$model->create()){
                $this->error($model->getError());
            }else{
                if($model->add() !== false){
//                    $this->cleanCache();
                    $this->success('新增成功');
                }else{
                    $this->error('新增失败');
                }
            }
        }else{
            $this->display();
        }
    }

    /**
     * 新增公告
     */
    public function edit(){
        if(IS_POST){
            $model = D('Common/Notice');
            if(!$model->create()){
                $this->error($model->getError());
            }else{
                if($model->save() !== false){
//                    $this->cleanCache();
                    $this->success('编辑成功');
                }else{
                    $this->error('编辑失败');
                }
            }
        }else{
            $id = I('id');
            $this->data = M('notice')->where(array('id'=>$id))->find();
            $this->id = $id;
            $this->display('add');
        }
    }

    /**
     * 删除公告
     */
    public function del(){
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('notice')->where($where)->setField(array('status'=>0,'uid'=>session('ADMIN_ID'),'modifiy_time'=>time()))){
//            $this->cleanCache();
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
     * 活动列表
     */
    public function activity(){
        $title = I('title');
        $start = I('start','');
        $end = I('end','');
        $level = I('level');
        $appid = I('appid');

        if($title) $where['title'] = array('like','%'.$title.'%');
        if($level) $where['level'] = $level;
        if($appid) $where['_string'] = 'FIND_IN_SET("'.$appid.'", gids)';
        $where['status'] = 1;
        if($start && !$end)
        {
            $where['_string'] = ' NOT (
        end_time < '.strtotime($start.' 00:00:00').')';
        }

        if(!$start && $end)
        {
            $where['_string'] = ' NOT (
        add_time > '.strtotime($end.' 23:59:59').')';
        }

        if($start && $end)
        {
            $where['_string'] = ' NOT (
        (end_time < '.strtotime($start.' 00:00:00').')
        OR (add_time >'.strtotime($end.' 23:59:59').')
    )';
        }


        $list = M('activity')->where($where)->order('sort,id desc')->select();
      
        $count = count($list);
        $page = $this->page($count, 20);
        $list = array_slice($list,$page->firstRow, $page->listRows);

        $this->users = M('users')->where(array('user_type'=>1))->getField('id,user_login');

        $this->page = $page->show('Admin');
        $this->start = $start;
        $this->end = $end;
        $this->list = $list;
        $this->title = $title;
        $this->level = $level;
        $this->display();
    }

    /**
     * 新增活动
     */
    public function activityadd(){
        if(IS_POST){

            $data = I('post.');

            $data['add_time'] = strtotime($data['add_time'].' 00:00:00');
            $data['end_time'] = strtotime($data['end_time'].' 23:59:59');

            //同时段活动得数量
            $map['_string'] = '( NOT (
        (end_time < '.$data['add_time'].')
        OR (add_time >'.$data['end_time'].')
    ) )';
            $map['status'] = 1;
            $map['level'] = $data['level'];

            if(!empty($data['gids']))
            {
                $string = ' AND (';
                foreach($data['gids'] as $v)
                {
                    $string.= 'FIND_IN_SET("'.$v.'", gids) OR ';
                }
                $string = trim($string,'OR ');
                $string.=')';
            }
            else
            {
                $this->error('请选择游戏');
            }

            $map['_string'].= $string;

            if(M('activity')->where($map)->count() > 0 )
            {
                $this->error('该时段已有活动，请不要重复添加');
            }

            $data['create_time'] = time();
            $data['content'] = str_replace("\n", "<br>", $data['content']);
            $data['uid'] = session('ADMIN_ID');
            $data['gids'] = !empty($data['gids'])?implode(',',$data['gids']):'';
            if($data['level'] == 2) $data['game_platform'] = 0 ;

            if(M('activity')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $data['add_time'] = time();
            $data['end_time'] = $data['add_time'];

            $this->assign('games',get_game_list('',1,'all'));
            $this->game_platform = M('game_platform')->select();
            $this->data = $data;
            $this->display();
        }
    }

    /**
     * 修改活动
     */
    public function activityedit(){
        if(IS_POST){
            $data = I('post.');
            $data['add_time'] = strtotime($data['add_time'].' 00:00:00');
            $data['end_time'] = strtotime($data['end_time'].' 23:59:59');

            //同时段活动得数量
            $map['_string'] = '( NOT (
        (end_time < '.$data['add_time'].')
        OR (add_time >'.$data['end_time'].')
    ) )';
            $map['status'] = 1;
            $map['id'] = array('neq',$data['id']);
            $map['level'] = $data['level'];

            if(!empty($data['gids']))
            {
                $string = ' AND (';
                foreach($data['gids'] as $v)
                {
                    $string.= 'FIND_IN_SET("'.$v.'", gids) OR ';
                }
                $string = trim($string,'OR ');
                $string.=')';
            }
            else
            {
                $this->error('请选择游戏');
            }

            $map['_string'].= $string;
            if(M('activity')->where($map)->count() > 0 )
            {
                $this->error('该时段已有活动，请不要重复添加');
            }


            $data['modifiy_time'] = time();
            $data['content'] = str_replace("\n", "<br>", $data['content']);
            $data['uid'] = session('ADMIN_ID');
            $data['gids'] = !empty($data['gids'])?implode(',',$data['gids']):'';
            if($data['level'] == 2) $data['game_platform'] = 0 ;

            if(M('activity')->save($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('activity')->where(array('id'=>$id))->find();
            $data['content'] = str_replace("<br>", "\n", $data['content']);
            $this->data = $data;
            $this->id = $id;
            $this->assign('games',get_game_list(explode(',',$data['gids']),1,'all'));
            $this->game_platform = M('game_platform')->select();
            $this->display('activityadd');
        }
    }

    /**
     * 删除公告
     */
    public function activitydel(){
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('activity')->where($where)->setField(array('status'=>0,'uid'=>session('ADMIN_ID'),'modifiy_time'=>time()))){

            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
     * 排序
     */
    public function sortdo(){
        $status = parent::_listorders(M('activity'),'sort');
        if ($status) {
            $this->success("排序更新成功！");
        } else {
            $this->error("排序更新失败！");
        }
    }

    public function activity_index()
    {
        $this->display();
    }

    public function notice_index()
    {
        $game_role = session('game_role');
        $channel_role = session('channel_role');

        $where['status'] = 1;
        if($game_role != 'all') $where['appid'] = array('in',$game_role.',0');
        if($channel_role != 'all') $where['cid'] = array('in',$channel_role.',0');

        $notice = M('notice')->where($where)->order('add_time desc')->select();
        $users = M('users')->where(array('user_type'=>1))->getField('id,user_login');
        $model = M('syo_server',null,C('185DB'));

        $sql = 'select s.*,g.gamename from syo_server s left join syo_game g on g.id=s.game_id WHERE
                s.status=0 and s.is_display=1 and is_stop=0 and to_days(FROM_UNIXTIME(start_time))=to_days(NOW()) order by s.start_time';
        $server = $model->query($sql);
        $top = array();
        $new = array();
        foreach($notice as $k=>$v){
            if($v['top'] == 1 && count($top) < 5){
                $v['user'] = $users[$v['uid']];
                $top[] = $v;
            }
            if($v['top'] == 0 && count($new) < 5){
                $v['user'] = $users[$v['uid']];
                $new[] = $v;
            }
        }
        $activity = M('activity')->where(array('status'=>1,'end_time'=>array('gt',time())))->order('sort,id desc')->limit(5)->select();


        $this->activity = $activity;
        $this->server = $server;
        $this->top = $top;
        $this->new = $new;
        $this->display();
    }



    public function get_game_list()
    {
        $gids = M('game')->where(array('status'=>1,'is_audit'=>1,'game_platform'=>I('game_platform')))->getfield('id',true);
        echo json_encode($gids);
    }
}
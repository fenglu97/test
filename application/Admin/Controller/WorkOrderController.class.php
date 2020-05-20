<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/12/18
 * Time: 16:10
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class WorkOrderController extends AdminbaseController{

    private $lock_time = 1200;

    public function index()
    {
        $this->_index(1);
    }

    public function details()
    {
        $this->_details();
    }

    public function close()
    {
        $this->_close();
    }

    public function channel_index()
    {
        $this->_index(2);
    }

    public function channel_details()
    {
        $this->_details();
    }

    public function channel_close()
    {
        $this->_close();
    }

    private function _index($question_type)
    {
        $map = I('');
        $game_role = session('game_role');
        $channel_role = session('channel_role');

        $admin_id = session('ADMIN_ID');
        $gids = array();
        if($admin_id !=1)
        {
            $game_group = M('users')->where(array('id'=>$admin_id))->getfield('game_group');
            $this->game_group = $game_group;

            if($game_group)
            {
                $gids = M('game')->where(array('group'=>$game_group))->getfield('id',true);
            }

        }


        if ($game_role != 'all')
        {
            if(!empty($gids))
            {
                $where['q.appid'] = array('in',implode(',',$gids));
            }
            else
            {
                $where['q.appid'] = array('in', $game_role);
            }

        }
        else
        {
            if(!empty($gids))
            {
                $where['q.appid'] = array('in',implode(',',$gids));
            }
        }


        if ($channel_role != 'all') {
            $where['q.channel'] = array('in', $channel_role);
        }


        if ($map['title']) $where['q.title'] = array('like', '%' . $map['title'] . '%');
        if ($map['cid']) $where['q.channel'] = $map['cid'];
        if ($question_type == 1) {
            if ($map['user']) $where['p.username'] = array('like', '%' . $map['user'] . '%');
        } else {
            if ($map['user']) $where['q.username'] = array('like', '%' . $map['user'] . '%');
        }

        if ($map['appid']) $where['q.appid'] = $map['appid'];
        if ($map['status']) $where['q.status'] = $map['status'];
        if ($map['type']) $where['q.type'] = $map['type'];
        if ($map['start']) $where['q.create_time'][] = array('gt', strtotime($map['start']));
        if ($map['end']) $where['q.create_time'][] = array('lt', strtotime($map['end'] . ' 23:59:59'));
        if ($map['admin']) {
            $uid = M('users')->where(array('user_login' => $map['admin']))->getField('id');
            $qids = M('question_info')->where(array('admin_id' => $uid))->getField('group_concat(distinct(question_id))');
            if ($qids) {
                $where['q.id'] = array('in', $qids);
            } else {
                $where['q.id'] = 0;
            }
        }

        $where['question_type'] = $question_type;

        if ($question_type == 1)
        {
            $data = M('question q')
                ->field('q.*,p.username,g.game_name,c.name')
                ->join('left join __PLAYER__ p on p.id=q.uid')
                ->join('left join __GAME__ g on g.id=q.appid')
                ->join('left join __CHANNEL__ c on c.id=q.channel')
                ->where($where)
                ->order('q.create_time desc')
                ->select();
        }
        else
        {
            $data = M('question q')
                ->field('q.*,g.game_name,c.name')
                ->join('left join __GAME__ g on g.id=q.appid')
                ->join('left join __CHANNEL__ c on c.id=q.channel')
                ->where($where)
                ->order('q.order desc,q.create_time desc')
                ->select();
        }

        $page = $this->page(count($data), 20);
        $ordertype = ($question_type == 1)?C('QUESTION_TYPE'):C('CHANNEL_QUESTION_TYPE');

        $now_time = time();

        $change_pid = '';
        $lock_admin_ids = '';
        foreach($data as $k=>$v)
        {
            if($v['lock'] == 1 && $v['lock_time'] < $now_time)
            {
                $change_pid.=$v['id'].',';
                $data[$k]['lock'] = 0;
                $data[$k]['lock_time'] = 0;
                $data[$k]['lock_admin_id'] = 0;
            }
            $lock_admin_ids.=$data[$k]['lock_admin_id'].',';
        }
        $lock_admin_ids = trim($lock_admin_ids,',');
        $change_pid = trim($change_pid,',');

        if(!empty($change_pid))
        {
            M('question')->where(array('id'=>array('in',$change_pid)))->save(array('lock'=>0,'lock_time'=>0,'lock_admin_id'=>0));
        }

        if(!empty($lock_admin_ids))
        {
            $lock_admin_names = M('users')->where(array('id'=>array('in',$lock_admin_ids)))->getfield('id,user_login');
        }


        if(empty($_GET['action'])){
            $data = array_slice($data,$page->firstRow, $page->listRows);
            $this->ordertype = $ordertype;
            $this->map = $map;
            $this->data = $data;
            $this->page = $page->show('Admin');
            $this->question_type = $question_type;
            $this->lock_admin_names = $lock_admin_names;
            $this->display();
        }else{
            foreach($data as $k=>&$v){
                foreach($ordertype as $r){
                    if($r['id'] == $v['type']){
                        $v['type'] = $r['name'];
                    }
                }
                switch ($v['status']){
                    case 0:$v['status'] = '全部'; break;
                    case 1:$v['status'] = '处理中'; break;
                    case 2:$v['status'] = '已处理'; break;
                    case 3:$v['status'] = '关闭'; break;
                }
                $v['create_time'] = date('Y-m-d H:i',$v['create_time']);
                $admin = M('question_info')->where(array('admin_id'=>array('neq',0),'question_id'=>$v['id']))->getField('group_concat(distinct(admin_id))');
                $v['admin'] = '';
                if($admin){
                    $v['admin'] = M('users')->where(array('id'=>array('in',$admin)))->getField('group_concat(user_login)');
                }
            }
            $xlsTitle = iconv('utf-8', 'gb2312', '工单列表');//文件名称
            $fileName = date('YmdHis').'工单列表';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('标题','用户','渠道','所属应用','联系方式','工单类型','状态','处理客服','创建时间');

            $cellNum = count($expCellName);
            $dataNum = count($data);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for($i=0;$i<$cellNum;$i++){
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            $filed = array('title','username','name','game_name','contract','type','status','admin','create_time');
            for($i=0;$i<$dataNum;$i++){
                for($j=0;$j<$cellNum;$j++){
                    $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $data[$i][$filed[$j]]);
                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit();
        }
    }

    public function add()
    {
        $question_type = I('question_type')?I('question_type'):2;
        if(IS_POST)
        {
            if(I('post.type') !=8)
            {
                if(I('post.username') == '')
                {
                    $this->error('游戏账号不能为空');
                }

                if(I('post.role_name') == '')
                {
                    $this->error('游戏角色名不能为空');
                }

                if(I('post.server_name') == '')
                {
                    $this->error('游戏区服不能为空');
                }

                $uid = M('player')->where(array('username'=>I('post.username')))->field('id,channel')->find();

                if(!$uid)
                {
                    $this->error('游戏账号不存在');
                }

                $channel_role = session('channel_role');

                if($channel_role !='all')
                {
                    if(!in_array($uid['channel'],explode(',',$channel_role)))
                    {
                        $this->error('游戏账号不属于该账号所属渠道');
                    }
                }
            }


            //获取操作员账号
            $admin_id = $_SESSION['ADMIN_ID'];

            //查询该账号属于哪个渠道 如果查不到 默认为185渠道问题

            $channel = M('channel')->where(array('admin_id'=>$admin_id))->getfield('id');

            $channel = $channel?$channel:C('MAIN_CHANNEL');

            if($_FILES['imgs']['name'][0])
            {
                $upload = new \Think\Upload(array(
                    'rootPath' => './'.C("UPLOADPATH"),
                    'subName' => array('date', 'Ymd'),
                    'maxSize' => 10485760,
                    'exts' => array('jpg', 'png', 'jpeg','gif'),
                ));
                $info = $upload->upload();
                if(!$info){
                    $this->error(null,$upload->getError());
                }else{
                    foreach($info as $v){
                        $file_name = trim($v['fullpath'],'.');
                        $src[] = str_replace($_SERVER['HTTP_HOST'],'',$file_name);
                    }
                    $imgs =  json_encode($src);
                }
            }




            $time = time();
            //生成工单ID
            $data = array(
                'question_type'=>I('post.question_type'),
                'order_id'=>uniqid(),
                'uid'=>$uid['id'],
                'username'=>I('post.username'),
                'yf_uid'=>I('post.yf_uid'),
                'channel'=>$channel,
                'appid'=>I('post.appid'),
                'role_name'=>I('role_name'),
                'server_name'=>I('server_name'),
                'title'=>I('post.title'),
                'type'=>I('post.type'),
                'desc'=>I('post.desc'),
                'imgs'=>$imgs?$imgs:'',
                'admin_id'=>$admin_id,
                'create_time'=>$time,
                'modify_time'=>$time
            );


            if($question_type == 2)
            {
                $channel_question_type = C('CHANNEL_QUESTION_TYPE');
                foreach($channel_question_type as $v)
                {
                    if($v['id'] == $data['type'] && $v['order'] == 1)
                    {
                        $data['order'] = 1;
                    }
                }
            }


            if(($id =M('question')->add($data))!==false)
            {
                //渠道工单建立后 发送信息队列
                $link = U('Admin/WorkOrder/channel_details',array('id'=>$id));
                create_admin_message(4,$id,'all',$link,I('post.appid'));

                $this->success('提交成功',U('channel_index'));
            }
            else
            {
                $this->error('提交失败');
            }

        }

        $this->type = ($question_type == 1)?C('QUESTION_TYPE'):C('CHANNEL_QUESTION_TYPE');
        $this->question_type = $question_type;
        $this->display();
    }

    private function _details()
    {
        $id = I('id');

        $question_info = $this->_check_lock($id);

        if(IS_POST){
            $content = I('content');

            $type = 2;
            if($question_info['question_type'] == 2 && $question_info['admin_id'] == $_SESSION['ADMIN_ID'])
            {
                $type = 1;
            }

            if(!empty($_FILES['imgs']['name'][0]))
            {
                $upload = new \Think\Upload(array(
                    'rootPath' => './'.C("UPLOADPATH"),
                    'subName' => array('date', 'Ymd'),
                    'maxSize' => 10485760,
                    'exts' => array('jpg', 'png', 'jpeg','gif'),
                ));
                $info = $upload->upload();
                if(!$info){
                    $this->error(null,$upload->getError());
                }else{
                    foreach($info as $v){
                        $file_name = trim($v['fullpath'],'.');
                        $src[] = str_replace($_SERVER['HTTP_HOST'],'',$file_name);
                    }
                    $imgs =  json_encode($src);
                }
            }

            $add = array(
                'admin_id' => $_SESSION['ADMIN_ID'],
                'type' => $type,
                'question_id' => $id,
                'comment' => $content,
                'imgs'=>$imgs?$imgs:'',
                'create_time' => time()
            );

            if(M('question_info')->add($add)!==false){
                M('question')->where(array('id'=>$id))->setField(array('status'=>$type,'modify_time'=>time()));

                if($question_info['question_type'] == 2)
                {
                    $link = U('Admin/WorkOrder/channel_details',array('id'=>$id));
                    if($type == 1)
                    {
                        //如果是发起工单问题管理员回复工单，通知最后一个处理工单的客服

                        $last_admin_id = M('question_info')->where(array('type'=>2,'question_id'=>$id))->order('create_time desc')->field('admin_id')->find();

                        if($last_admin_id)
                        {

                            create_admin_message(4,$id,$last_admin_id['admin_id'],$link);
                        }

                    }
                    else
                    {
                        //如果是客服回复工单，通知发起工单问题的管理员
                        create_admin_message(4,$id,$question_info['admin_id'],$link);

                    }
                }
                if($question_info['question_type'] == 2)
                {
                    $this->success('回复成功',U('channel_index'));
                }
                else
                {
                    $this->success();
                }

            }else{
                $this->error();
            }
        }else{
            $question_info['imgs'] = json_decode($question_info['imgs'],true);

            $gamename = M('game')->where(array('id'=>$question_info['appid']))->getfield('game_name');


            $data = M('question_info q')
                ->field('q.*,u.user_login')
                ->join('left join __USERS__ u on u.id=q.admin_id')
                ->where(array('question_id'=>$id))
                ->order('create_time')
                ->select();

            foreach($data as $k=>$v)
            {
                $data[$k]['imgs'] = json_decode($v['imgs'],true);
            }

            if($question_info['question_type'] == 1)
            {
                $uname = M('player')->where(array('id'=>$question_info['uid']))->getField('username');

                $this->uname = $uname;
            }

            if($question_info['question_type'] == 2 )
            {
                //查询工单是否被评级
                $rate_info = M('admin_rate')->where(array('type'=>1,'event_id'=>$id))->find();
                if($rate_info)
                {
                    $this->rate_info = $rate_info;
                }

                if(session('ADMIN_ID') == $question_info['admin_id']) $this->is_rate_pri = 1;

            }

            $this->status = $question_info['status'];
            $this->desc = $question_info['desc'];
            $this->info = $question_info;
            $this->data = $data;
            $this->id = $id;
            $this->gamename = $gamename;
            $this->ftp_url = C('FTP_URL');
            $this->display();
        }
    }

    private function _close()
    {
        $id = I('id');

       $this->_check_lock($id);

        if(M('question')->where(array('id'=>$id))->setField(array('order'=>0,'status'=>3,'modify_time'=>time())) !== false){
            $this->success();
        }else{
            $this->error();
        }
    }


    public function lock()
    {
        $id = I('id');
        $action = I('action');

        $question = M('question')->where(array('id'=>$id))->field('lock,lock_time,lock_admin_id')->find();
        $admin_id = $_SESSION['ADMIN_ID'];
        $now_time = time();
        //0解锁 1锁定
        if($action == 0)
        {

            if($question['lock'] == 0)
            {
                $this->error('该工单已解锁');
            }

            //查询用户是否有权限解锁
            if($admin_id != $question['lock_admin_id'])
            {
                $this->error('没有权限');
            }

            $save = array(
                'lock'=>$action,
                'lock_time'=>0,
                'lock_admin_id'=>0,
            );
        }
        else
        {
            if($question['lock'] == 1 && $question['lock_time'] > $now_time)
            {
                $this->error('工单已被锁定');
            }

            $save = array(
                'lock'=>$action,
                'lock_time'=>$now_time+$this->lock_time,
                'lock_admin_id'=>$admin_id,
            );

        }

        if(M('question')->where(Array('id'=>$id))->save($save)!==false)
        {
            $this->success('操作成功');
        }
        else
        {
            $this->error('操作失败');
        }

    }

    private function _check_lock($id)
    {
        $question_info = M('question')->where(array('id'=>$id))->find();

        if($question_info['lock'] == 1 && $question_info['lock_time'] > time() && $_SESSION['ADMIN_ID'] != $question_info['admin_id'] && $_SESSION['ADMIN_ID'] != $question_info['lock_admin_id'])
        {
            $this->error('该工单正在锁定中');
        }

        return $question_info;
    }







}


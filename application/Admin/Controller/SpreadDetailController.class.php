<?php
/**
 * 推广明细
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/7/10
 * Time: 17:11
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SpreadDetailController extends AdminbaseController{

    public function index(){
        $channel = session('channel_role');
        $role_id = session('ROLE_ID');
        $type = I('type');
        $keyword = I('keyword');
        $cid = I('channel');
        $start = I('start');
        $end = I('end');
        $query = I('query',0);
        $where = '';
        $time = date('Ymd');

        //渠道权限
        if(!in_array($role_id,array(1,11,15)) && session('ADMIN_ID') != 1){
            $this->display();
            exit();
        }

        if($query == 1){
            getTodayData();
            $where['_string'] = "to_days(from_unixtime(createTime)) = to_days({$time})";
            $count = M('spread_detail s')->where($where)->count();

            $page = $this->page($count,1000);
        }else{
            if($type > 0){
                switch ($type){
                    case 1:$where['c.name'] = $keyword;break;
                    case 2:$where['p.username'] = $keyword;break;
                    case 3:$where['s.ip'] = $keyword;break;
                    case 4:$where['s.deviceID'] = $keyword;break;
                    case 5:$where['s.roleName'] = $keyword;break;
                }
            }
            if($channel != 'all'){

                $where['s.channel'] = array('in',$channel);
            }else{
                if($cid > 0){
                    $where['s.channel'] = $cid;
                }else{
                    $where['c.type'] = 2;
                }
            }
            if($start) $where['s.regTime'][] = array('egt',strtotime($start));
            if($end) $where['s.regTime'][] = array('elt',strtotime($end.' 23:59:59'));

            $count = M('spread_detail s')
                ->join('left join __PLAYER__ p on p.id=s.uid')
                ->join('left join __CHANNEL__ c on c.id=s.channel')
                ->where($where)
                ->count();

            $page = $this->page($count,20);
        }



        $data = M('spread_detail s')
                ->field('c.name cname,p.username pname,g.game_name gname,s.deviceID,s.ip,s.channel,s.serverName,s.roleName,s.reachLevel,s.todayLevel,s.status,s.remark,s.regTime,a.role_level nowLevel')
                ->join('left join __PLAYER__ p on p.id=s.uid')
                ->join('left join __GAME__ g on g.id=s.appid')
                ->join('left join __CHANNEL__ c on c.id=s.channel')
                ->join('left join __PLAYER_APPINFO__ a on a.appid=s.appid and a.uid=s.uid and a.server_id=s.serverID and a.role_id=s.roleID')
                ->where($where)
                ->limit($page->firstRow,$page->listRows)
                ->order('regTime desc')
                ->select();

        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->type = $type;
        $this->keyword = $keyword;
        $this->cid = $cid;
        $this->start = $start;
        $this->end = $end;
        $this->maxDay = date('Y-m-d');
        $this->display();
    }

    public function todayData(){
        getTodayData();
        $this->success();
    }
}


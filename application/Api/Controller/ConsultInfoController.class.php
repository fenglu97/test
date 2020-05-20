<?php
/**
 * 问题回答系统
 * @author qing.li
 * @date 2018-09-12
 */

namespace Api\Controller;
use Common\Controller\AppframeController;
class ConsultInfoController extends AppframeController
{
    private $page_size = 10;
    //回答详情
    public function info()
    {
        $consult_id = I('consult_id');
        $uid = I('uid');
        $page = I('page');

        if (empty($consult_id) || strlen($uid) < 1 || empty($page)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'consult_id' => $consult_id,
            'uid' => $uid,
            'page' => $page,
            'sign' => I('sign')
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
             $this->ajaxReturn(null,'签名错误',0);
        }

        $consult_model = M('consult');

        $consult_info_model = M('consult_info');

        $consult_data = $consult_model->where(array('id' => $consult_id))->find();

        if (!$consult_data) {
            $this->ajaxReturn(null, '提问不存在', 0);
        }

        if ($uid != 0) {
            $player = M('player')->where(array('id' => $uid))->find();
            if (!$player) {
                $this->ajaxReturn(null, '用户不存在', 0);
            }
        }

        $consult_counts = $consult_model
            ->where(array('status' => 2, 'appid' => $consult_data['appid']))
            ->count();

        $count_info_counts = $consult_info_model
            ->where(array('consult_id' => $consult_id, 'audit' => 1))
            ->count();

        if ($page == 1 && $uid!=0)
        {
            $user_list = $consult_info_model
                ->field('id,content,uid,top,is_reward,is_task_bonus,create_time')
                ->where(array('consult_id' => $consult_id, 'uid' => $uid ,'audit'=>array('neq',3)))
                ->limit(($page - 1) * $this->page_size, $this->page_size)
                ->order('top desc,is_reward desc,create_time desc')
                ->select();

        }

        $list = $consult_info_model
            ->field('id,content,uid,top,is_reward,is_task_bonus,create_time')
            ->where(array('consult_id' => $consult_id, 'audit' => 1, 'uid' => array('neq', $uid)))
            ->limit(($page - 1) * $this->page_size, $this->page_size)
            ->order('top desc,is_reward desc,create_time desc')
            ->select();


        $uids = $consult_data['uid'] . ',';

        foreach ($list as $v) {
            $uids .= $v['uid'] . ',';
        }

        if ($page == 1 && $uid!=0)
        {
            foreach ($user_list as $v) {
                $uids .= $v['uid'] . ',';
            }
        }

        $uids = trim($uids, ',');

        $player_infos = M('player_info')->where(array('uid' => array('in', $uids)))->getfield('uid,nick_name,icon_url', true);
        $players = M('player')->where(array('id' => array('in', $uids)))->getfield('id,username', true);


        foreach ($list as $k => $v) {
            $list[$k]['nick_name'] = $player_infos[$v['uid']]['nick_name'] ? $player_infos[$v['uid']]['nick_name'] : $players[$v['uid']];
            $list[$k]['icon_url'] = get_avatar_url($player_infos[$v['uid']]['icon_url']);
        }

        if ($page == 1 && $uid!=0)
        {
            foreach($user_list as $k=>$v)
            {
                $user_list[$k]['nick_name'] = $player_infos[$v['uid']]['nick_name']?$player_infos[$v['uid']]['nick_name']:$players[$v['uid']];
                $user_list[$k]['icon_url'] =  get_avatar_url($player_infos[$v['uid']]['icon_url']);
            }
        }


        if($uid == 0)
        {
            $type = 1;
        }
        elseif($uid == $consult_data['uid'])
        {
            //将未读修改为已读
            $consult_info_model->where(array('is_read'=>0))->setfield(array('is_read'=>1));
            $type = 2;
        }
        else
        {
            //查询该玩家是活跃用户
            $appid = get_sdk_appid($consult_data['appid']);
            if(M('player_app')->where(array('username'=>$player['username'],'appid'=>$appid))->count() > 0)
            {
                $type = 3;
            }
            else
            {
                $type = 4;
            }
        }

        $game_info = M('game','syo_',C('185DB'))->field('gamename,logo')->where(array('id'=>$consult_data['appid']))->find();

        $result = array();

        $result['app'] = array('game_name'=>$game_info['gamename'],
                  'logo'=>C('CDN_URL').$game_info['logo'],
                  'consult_counts'=>$consult_counts);

        $result['consult'] = array(
            'uid'=>$consult_data['uid'],
            'nick_name'=>$player_infos[$consult_data['uid']]['nick_name']?$player_infos[$consult_data['uid']]['nick_name']:$players[$consult_data['uid']],
            'icon_url'=>get_avatar_url($player_infos[$consult_data['uid']]['icon_url']),
            'id'=>$consult_id,
            'is_reward'=>($consult_data['type'] == 2)?1:0,
            'content'=>$consult_data['content'],
            'create_time'=>$consult_data['create_time'],
        );

        $result['consult_info'] = array(
            'consult_info_counts'=>$count_info_counts,
            'list'=>is_array($list)?$list:array(),
        );
        if($page == 1 && $uid!=0)
        {
            $result['consult_info']['user_list'] = is_array($user_list)?$user_list:array();
        }

        $result['type'] = $type;

        $this->ajaxReturn($result);

    }

    //回答
    public function do_answer()
    {
        $consult_id = I('consult_id');
        $uid = I('uid');
        $content = I('content');
        $trim_content = trim($content);
        if(empty($trim_content))
        {
            $this->ajaxReturn(null,'内容不能为空',0);
        }

        if(empty($consult_id) || empty($uid))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'consult_id'=>$consult_id,
            'uid'=>$uid,
            'content'=>$content,
            'sign'=>I('sign')
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $consult = M('consult')->where(array('id'=>$consult_id))->find();
        if(!$consult)
        {
            $this->ajaxReturn(null,'该提问不存在',0);
        }

        if((time() - $consult['audit_time']) > 7*3600*24)
        {
            $this->ajaxReturn(null,'该提问已超过回答时间期限，不能再进行回答',0);
        }

        $player = M('player')->where(array('id'=>$uid))->find();

        if(!$player)
        {
            $this->ajaxReturn(null,'用户不存在',0);
        }

        if($uid == $consult['uid'])
        {
            $this->ajaxReturn(null,'问题发起者不能进行回答',0);
        }

        $appid = get_sdk_appid($consult['appid']);
        //检测用户是否登陆过该游戏
        if(M('player_app')->where(array('username'=>$player['username'],'appid'=>$appid))->count()<1)
        {
            $this->ajaxReturn(null,'用户不是该游戏的活跃玩家',0);
        }

        $arr['create_time'] = time();
        $arr['appid'] = $consult['appid'];
        if(M('consult_info')->add($arr)!==false)
        {
            //回答成功后 发送信息队列
            $link = U('Admin/ConsultInfo/index',array('consult_id'=>$consult_id));
            create_admin_message(5,$consult_id,'all',$link,$appid);
            $this->ajaxReturn(null,'操作成功');
        }
        $this->ajaxReturn(null,'操作失败',0);

    }

    //悬赏
    public function do_reward()
    {
        $consult_id = I('consult_id');
        $id = I('id');
        $uid = I('uid');

        if(empty($uid) || empty($consult_id) || empty($id))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $this->key = "{$uid}_doreward_{$consult_id}";
        if(!$this->_make_mark())
        {
            //如果上一次接口未完成直接返回成功;
            $this->ajaxReturn(null,'请勿重复操作',0,false);
        }

        $arr = array(
            'consult_id'=>$consult_id,
            'id'=>$id,
            'uid'=>$uid,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $consult_info_model = M('consult_info');
        $consult_info_data = $consult_info_model->where(array('id'=>$id))->find();
        if(!$consult_info_data)
        {
            $this->ajaxReturn(null,'该答案不存在',0);
        }

        $consult_info_mobile = M('player')->where(array('id'=>$consult_info_data['uid']))->getfield('mobile');
        $consult_mobile = M('player')->where(array('id'=>$uid))->getfield('mobile');


        if(!preg_match("/^1\d{10}$/", $consult_info_mobile) ||!preg_match("/^1\d{10}$/", $consult_mobile) )
        {
            $this->ajaxReturn(null,'回答用户或提问用户未绑定手机，不能发放悬赏',0);
        }


        $consult_model = M('consult');
        $consult_data = $consult_model->where(array('id'=>$consult_id))->find();

        if(!$consult_data)
        {
            $this->ajaxReturn(null, '该提问不存在', 0);
        }

        if($consult_data['money'] ==0)
        {
            $this->ajaxReturn(null, '该提问没有悬赏金', 0);
        }

        if($consult_info_data['consult_id'] != $consult_id)
        {
            $this->ajaxReturn(null, '提问和答案没有所属关系', 0);
        }

        if($consult_data['uid'] !=$uid)
        {
            $this->ajaxReturn(null, '用户没有权限悬赏答案', 0);
        }

        if($consult_data['type'] ==2)
        {
            $this->ajaxReturn(null, '该提问已经发放悬赏', 0);
        }

        $create_time = strtotime(date('Y-m-d',$consult_info_data['create_time']).' 00:00:00');

        //查询该答案用户发表答案当天获得了多少悬赏
        $reward_coin = M('coin_log')
            ->where(array('type'=>12,'uid'=>$consult_info_data['uid'],'consult_time'=>array(array('egt',$create_time),array('lt',$create_time+3600*24))))
            ->getfield('sum(coin_change)');
        


        if($reward_coin >= C('DAY_CONSULT_REWARD_TOP'))
        {
            $this->ajaxReturn(null, '该玩家单日领取悬赏金额已达上限', 0);
        }

        $coin = ((C('DAY_CONSULT_REWARD_TOP')-$reward_coin)<$consult_data['money'])?(C('DAY_CONSULT_REWARD_TOP')-$reward_coin):$consult_data['money'];

        $Model = M(); // 实例化一个空对象
        $Model->startTrans(); // 开启事务

        $flagA = $Model->table('bt_player')->where(array('id'=>$consult_info_data['uid']))->setInc('coin',$coin);
        $flagB = $Model->table('bt_consult')->where(array('id'=>$consult_id))->setField(array('type'=>2));
        $flagC = $Model->table('bt_consult_info')->where(array('id'=>$id))->setField(array('is_reward'=>1));
        $current_coin = M('player')->where(array('id'=>$consult_info_data['uid']))->getfield('coin');
        //添加日志
        $log_data = array(
            'uid'=>$consult_info_data['uid'],
            'type'=>12,
            'coin_change'=>$coin,
            'coin_counts'=>$current_coin,
            'consult_time'=>$consult_info_data['create_time'],
            'create_time'=>time(),
        );
        //
        $flagD = $Model->table('bt_coin_log')->add($log_data);

        if(($flagA!==false) && ($flagB!==false) && ($flagC!==false) && ($flagD!==false))
        {
            $Model->commit();
            $this->ajaxReturn(null,'操作成功');
        }
        $Model->rollback();
        $this->ajaxReturn(null,'操作失败',0);
    }

    //我来回答
    public function get_answer_game()
    {
        $username = I('username');
        $page = I('page');

        if(empty($username) && empty($page))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'username'=>$username,
            'page'=>$page,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $player = M('player')->where(array('username'=>$username))->find();

        if(!$player)
        {
            $this->ajaxReturn(null,'用户不存在',0);
        }

        $appids = M('player_app')->where(array('username'=>$username))->getfield('appid',true);

        $result = array(
            'count'=>0,
            'list'=>array(),
        );
        if($appids)
        {
            $tags = M('game')->where(Array('id'=>array('in',implode(',',$appids))))->getfield('tag',true);
            $game_model = M('game','syo_',C('185DB'));
            $games = array();
            foreach($tags as $tag)
            {
                $item  = $game_model->field('id,gamename,logo')->where(array('android_pack_tag'=>$tag))->find();
                if(!$item) {
                    $item  = $game_model->field('id,gamename,logo')->where(array('ios_tag'=>$tag))->find();
                    if(!$item){
                        $tag = str_replace('_sy','',$tag);
                        $item  = $game_model->field('id,gamename,logo')->where(array('tag'=>$tag))->find();
                    }
                }
                $games[$item['id']] = $item;
            }

            $count = M('consult')
                ->where(array('uid'=>array('neq',$player['id']),'status'=>2,'appid'=>array('in',implode(',',array_keys($games)))))
                ->count();

            $where['uid'] = array('neq',$player['id']);
            $where['status'] = 2;
            $where['appid'] = array('in',implode(',',array_keys($games)));

            $list = M('consult')
                ->field('id,appid,content,create_time')
                ->where($where)
                ->limit(($page-1)*$this->page_size,$this->page_size)
                ->group('id')
                ->order('create_time desc')
                ->select();

            foreach($list as $k=>$v)
            {
                $list[$k]['answer_counts'] = M('consult_info')->where(array('consult_id'=>$v['id'],'audit'=>1))->count();
                $list[$k]['game_name'] = $games[$v['appid']]['gamename'];
                $list[$k]['logo'] = C('CDN_URL').$games[$v['appid']]['logo'];
                $list[$k]['is_reward'] = ($v['type'] == 2)?1:0;
                unset($list[$k]['type']);
            }

            $result['count'] = $count;
            $result['list'] = $list;
        }

        $this->ajaxReturn($result);

    }

    //通过用户ID 类型 类型ID 生成唯一值 防止多次点击
    private function _make_mark()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
        $this->redis->auth(C('REDIS_PASS'));

        //redis挂了不影响正常逻辑
        if(!$this->redis)
        {
            return true;
        }

        if(!$this->redis->exists($this->key))
        {
            $this->redis->set($this->key,1);
            return true;
        }
        return false;
    }

    //程序结束时删除mark
    private function _delete_mark()
    {
        if($this->redis)
        {
            $this->redis->delete($this->key);
            $this->redis->close();
        }
    }


    protected function ajaxReturn($result, $msg = '', $status = 1, $is_delete_mark = true,$json_option = 0)
    {
        //删除mark值
        if($is_delete_mark)
        {
            $this->_delete_mark();
        }

        $data = array(
            'data' => $result,
            'msg' => $msg,
            'status' => $status,
        );

        if(strlen($_GET['callback'])>0)
        {
            $type = 'JSONP';
        }

        if (empty($type)) $type = C('DEFAULT_AJAX_RETURN');

        header("Access-Control-Allow-Origin:http://across.185sy.com");

        switch (strtoupper($type)) {


            case 'JSON' :


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:application/json; charset=utf-8');


                exit(json_encode($data, $json_option));


            case 'XML'  :


                // 返回xml格式数据


                header('Content-Type:text/xml; charset=utf-8');


                exit(xml_encode($data));


            case 'JSONP':


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:application/json; charset=utf-8');


                $handler = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');


                exit($handler . '(' . json_encode($data, $json_option) . ');');


            case 'EVAL' :


                // 返回可执行的js脚本


                header('Content-Type:text/html; charset=utf-8');


                exit($data);


            case 'AJAX_UPLOAD':


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:text/html; charset=utf-8');


                exit(json_encode($data, $json_option));


            default :


                // 用于扩展其他返回格式数据


                Hook::listen('ajax_return', $data);


        }

    }


}
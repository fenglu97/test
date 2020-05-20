<?php
/**
 * 公告接口
 * @author qing.li
 * @date 2017-09-05
 */

namespace Api\Controller;

use Common\Controller\AppframeController;

class NoticeController extends AppframeController
{
    private $notice_page_size = 10;

    public function _initialize()
    {
        parent::_initialize();
        $this->notice_model = M('notice');
    }

    /**
     * 公告列表
     */
    public function notice_list()
    {
        $appid = I('appid');
        $channel = I('channel');
        $uid = I('uid');
        $page = I('page');

        if (empty($appid) || empty($channel) || empty($uid) || empty($page)) {
            $this->ajaxReturn(null, '参数错误', 11);
        }

        $app_info = M('Game')->where(array('status' => 1, 'id' => $appid))->find();

        if (!$app_info) {
            $this->ajaxReturn(null, 'app不存在', 3);
        }

        $arr = array(
            'appid' => $appid,
            'channel' => $channel,
            'uid' => $uid,
            'page' => $page,
            'sign' => I('sign')
        );

        $res = checkSign($arr, $app_info['client_key']);

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 2);
        }


        $channel_info = M('channel')->where(array('id' => $channel))->count();

        if (!$channel_info) {
            $this->ajaxReturn(null, '渠道不存在', 4);
        }

        $player = M('player')->
        field('channel')->
        where(array('status' => 1, 'id' => $uid))->
        find();

        if (!$player) {
            $this->ajaxReturn(null, '用户不存在', 5);
        }

        $page = $page ? $page : 1;

        $map = array();
        $map['cid'] = array('in', '0,' . $player['channel']);
        $map['appid'] = array('in', '0,' . $appid);
        $map['status'] = 1;
        $map['is_display'] = 1;
        $map['force'] = 0;

        $notice_count = $this->notice_model
            ->where($map)
            ->cache(true)
            ->count();

        $notice_list = $this->notice_model
            ->field('id,title,add_time')
            ->where($map)
            ->order('top desc,add_time desc')
            ->limit(($page - 1) * $this->notice_page_size . ',' . $this->notice_page_size)
            ->cache(true)
            ->select();

        $data = array(
            'count' => ceil($notice_count / $this->notice_page_size),
            'list' => $notice_list ? $notice_list : array()
        );

        $this->ajaxReturn($data, '');

    }

    /**
     * 公告详情
     */
    public function notice_info()
    {
        $appid = I('appid');
        $id = I('id');

        if (empty($appid) || empty($id)) {
            $this->ajaxReturn(null, '参数错误', 11);
        }

        $app_info = M('Game')->where(array('status' => 1, 'id' => $appid))->find();

        if (!$app_info) {
            $this->ajaxReturn(null, 'app不存在', 3);
        }

        $arr = array(
            'appid' => $appid,
            'id' => $id,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, $app_info['client_key']);
        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 2);
        }


        $notice_info = $this->notice_model->
        field('id,title,desc,content,add_time')->
        where(array('status' => 1, 'id' => $id))->
        cache(true)->
        find();

        if (!$notice_info) {
            $this->ajaxReturn(NULL, '公告不存在', 21);
        }

        $this->ajaxReturn($notice_info, '');
    }

    public function get_admin_notice()
    {
        $time = I('time') ? I('time') : date('Y-m-d', time());
        $level = I('level');
        $appid = I('appid');
        $admin_id = SESSION('ADMIN_ID');
        if (!$admin_id) {
            $this->ajaxReturn(null, '请先登陆', 0);
        }

        //获取公告标题和内容
        $result = M('activity')
            ->field('title,content')
            ->where(array('add_time' => array('elt', strtotime($time . ' 00:00:00')), 'end_time' => array('egt', strtotime($time . ' 00:00:00')), 'status' => 1, 'level' => $level, '_string' => 'FIND_IN_SET("' . $appid . '", gids) '))
            ->find();
        $this->ajaxReturn($result);
    }

    public function get_game_list()
    {

        $admin_id = SESSION('ADMIN_ID');
        if (!$admin_id) {
            $this->ajaxReturn(null, '请先登陆', 0);
        }

//        $data = M('game')->field('id,game_name')->where(array('status' => 1, 'audit' => 1))->select();
        $data = M('game')->field('id,game_name')->where(array('status' => 1))->select();
        $this->ajaxReturn($data);

    }

    public function admin_notice_index()
    {
        $time = I('time') ? I('time') : date('Y-m', time());
        $appid = I('appid');
        $admin_id = SESSION('ADMIN_ID');
        if (!$admin_id) {
            $this->ajaxReturn(null, '请先登陆', 0);
        }

        //获取本月的节日

        $where['_string'] = ' (NOT (
        (end_time < ' . strtotime($time . '-01 00:00:00') . ')
        OR (add_time >=' . strtotime("+1 month", strtotime($time)) . ')
    ) )';

        $where['_string'] .= ' AND FIND_IN_SET("' . $appid . '", gids) ';

        $where['status'] = 1;

        $list = M('activity')->field('level,remark,add_time,end_time')->where($where)->order('add_time asc')->select();
        //获取该游戏本月份的所有开服信息

        $app_tag = M('game')->where(array('id' => $appid))->getfield('tag');
        $model = M('syo_server', null, C('185DB'));
        $map['_logic'] = 'OR';
        $map['android_pack_tag'] = $app_tag;
        $map['ios_tag'] = $app_tag;
        $game_id = M('syo_game', null, C('185DB'))->where($map)->getfield('id');
        $game_id = $game_id ? $game_id : '';


        $sql = 'SELECT s.id,s.line,s.server_id,s.start_time,g.gamename FROM syo_server s
                left join syo_game g on g.id=s.game_id
                WHERE
                g.status=0 and g.isdisplay=1 and g.id = ' . $game_id . ' and
                s.is_display = 1 and s.status = 0 and s.is_stop = 0 and
                s.start_time > ' . strtotime($time . '-01 00:00:00') . ' and
                  s.start_time < ' . strtotime("+1 month", strtotime($time)) . '
                order by s.start_time asc';
        $server = $model->query($sql);

        $result = array();

        $result_1 = array();
        $result_2 = array();
        $result_3 = array();

        foreach ($list as $v) {
            if ($v['level'] == 1) {
                $result_1[] = $v;
            } elseif ($v['level'] == 2) {
                $result_2[] = $v;
            } else {
                $result_3[] = $v;
            }
        }


        foreach ($result_1 as $v) {
            //计算结束时间和开始时间之间的日期
            $sub = (strtotime(date('Y-m-d', $v['end_time'])) - strtotime(date('Y-m-d', $v['add_time']))) / (3600 * 24);
            $result['ptflhd'][] = array('time' => strtotime(date('Y-m-d', $v['add_time'])), 'remark' => $v['remark'], 'level' => $v['level']);
            for ($i = 1; $i <= $sub; $i++) {
                $result['ptflhd'][] = array('time' => strtotime("+{$i} days", $v['add_time']), 'remark' => $v['remark'], 'level' => $v['level']);
            }
        }

        foreach ($result_2 as $v) {
            //计算结束时间和开始时间之间的日期
            $sub = (strtotime(date('Y-m-d', $v['end_time'])) - strtotime(date('Y-m-d', $v['add_time']))) / (3600 * 24);
            $result['yxxxflhd'][] = array('time' => strtotime(date('Y-m-d', $v['add_time'])), 'remark' => $v['remark'], 'level' => $v['level']);
            for ($i = 1; $i <= $sub; $i++) {
                $result['yxxxflhd'][] = array('time' => strtotime("+{$i} days", $v['add_time']), 'remark' => $v['remark'], 'level' => $v['level']);
            }
        }

        foreach ($result_3 as $v) {
            //计算结束时间和开始时间之间的日期
            $sub = (strtotime(date('Y-m-d', $v['end_time'])) - strtotime(date('Y-m-d', $v['add_time']))) / (3600 * 24);
            $result['mfhd'][strtotime(date('Y-m-d', $v['add_time']))]['normal'] = array('time' => strtotime(date('Y-m-d', $v['add_time'])), 'remark' => $v['remark'], 'level' => $v['level']);
            for ($i = 1; $i <= $sub; $i++) {
                $result['mfhd'][strtotime("+{$i} days", $v['add_time'])]['normal'] = array('time' => strtotime("+{$i} days", $v['add_time']), 'remark' => $v['remark'], 'level' => $v['level']);
            }
        }

        foreach ($server as $v) {
            if ($v['line'] == 1) $v['server_name'] = '双线 ' . $v['server_id'] . '服';
            if ($v['line'] == 2) $v['server_name'] = '内测 ' . $v['server_id'] . '服';
            if ($v['line'] == 3) $v['server_name'] = '删档 ' . $v['server_id'] . '服';
            if ($v['line'] == 4) $v['server_name'] = '公测 ' . $v['server_id'] . '服';
            $time_key = strtotime(date('Y-m-d', $v['start_time']) . ' 00:00:00');
            $result['mfhd'][$time_key]['kaifu'] = $v;
        }

        ksort($result['mfhd']);

        $site_options = get_site_options();
        $data['common_activity']['title'] = $site_options['site_activity_title'];
        $data['common_activity']['content'] = html_entity_decode($site_options['site_activity_content']);

        $data['list'] = $result;

        $this->ajaxReturn($data);
    }
}

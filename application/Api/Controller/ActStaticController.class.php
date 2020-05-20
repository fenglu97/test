<?php
/**
 * 用户行为统计
 * @author liqing
 * @date 2018-09-06
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class ActStaticController extends AppframeController
{

    public function do_report()
    {

        $channel = I('channel');
        $machine_code = I('machine_code');
        $actions = I('actions');

        if (empty($channel) || empty($machine_code) || empty($actions)) {
            $this->ajaxReturn(null, '参数不能为空', 0);
        }

        $arr = array(
            'channel' => $channel,
            'machine_code' => $machine_code,
            'actions' => $actions,
            'sign' => I('sign'),
        );

        $res = checkSign($arr, C('API_KEY'));

        if (!$res) {
            $this->ajaxReturn(null, '签名错误', 0);
        }

        $is_register = M('player')->where(array('machine_code' => $machine_code))->count();

        if ($is_register == 0 || ($is_register > 0 && strpos($actions, '注册完成') !== false))
        {
            $arr['create_time'] = time();
            if(strpos($actions, '注册完成') !== false) $arr['is_register'] = 1;

            $actions = explode(',',$actions);
            foreach($actions as $k=>$action)
            {
                $key = $k+1;
                $arr['step'.$key] = $action;
            }

            if(M('activity_static')->add($arr)!==false)
            {
                $this->ajaxReturn(null,'上报成功');
            }
        }

        $this->ajaxReturn(null,'上报失败',0);

    }

}
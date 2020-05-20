<?php
/**
 * videoads统计
 * @author qing.li
 * @date 2018-10-16
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class VideoadsController extends AppframeController
{
    public function report_data()
    {

        $get_data = I('get.');
        if(empty($get_data['click_id']))
        {
            $this->ajaxReturn(null,'click_id is empty!',0);
        }

        $get_data['create_time'] = time();

        if(M('vidoads_list')->add($get_data)!==false)
        {
            $this->ajaxReturn(null,'success');
        }
    }
}
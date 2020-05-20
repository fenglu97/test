<?php
/**
 * 检查更新接口
 * @author qing.li
 * @date 2017-09-18
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class UpdateController extends AppframeController
{
	private $notice_page_size = 10;

	public function _initialize()
	{
		parent::_initialize();
	}
	/**
	 * 公告列表
	 */
	public function check_update()
	{
		$system = I('system');
		$version = I('version');
		
		if(empty($system) || empty($version))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

	
		
//		$arr = array(
//		'system'=>$system,
//		'version'=>$version,
//		'sign'=>I('sign')
//		);
//		
//		$res = checkSign($arr,'');
//		
//		if(!$res)
//		{
//			return $this->ajaxReturn(null,'签名错误',2);
//		}
		
		$data = array();
		
		$option = M('options')->where(array('option_name'=>'site_options'))->find();
        $info = json_decode($option['option_value'],true);
        
        if($system == 1)
        {
        	if($version < $info['site_android_version'])
        	{
        		$data = array(
        		'url'=>$info['site_android_download'],
        		'version'=>$info['site_android_version'],
        		);
        	}
        }
        else 
        {
        	if($version < $info['site_ios_version'])
        	{
          		$data = array(
        		'url'=>$info['site_ios_download'],
        		'version'=>$info['site_ios_version'],
        		);      		
        	}
       	
        }
		
	    $this->ajaxReturn($data,'');
		
	}
}

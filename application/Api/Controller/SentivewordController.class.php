<?php
/**
 *敏感词接口
 * @author qing.li
 * @date 2018-3-14
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class SentivewordController extends AppframeController
{

	public function create_tree()
	{
		IF($_SERVER['HTTP_REFERER'] == "{:C('API_URL')}")
		{
			$shell = '/bin/mysql -uroot -pdjw234JF8*sd -h 10.66.120.235 -D newsdk -P 3306 -N -e "select name from bt_sentiveword where 1" >'.SITE_PATH.'word.txt';
			exec($shell,$out,$status);
			if($status == 0)
			{
				$shell = SITE_PATH.'trie_filter/dpp '.SITE_PATH.'word.txt '.SITE_PATH.'words.dic'; //ls是linux下的查目录，文件的命令
				exec($shell,$out,$status);
				if($status == 0)
				{
					$this->ajaxReturn(null,'success');
				}

			}
		}
	    $this->ajaxReturn(null,'error',0);
	}

}
<?php

/**
 * 后台Controller
 */
namespace Common\Controller;
use Common\Controller\AppframeController;

class AdminbaseController extends AppframeController {

	public function __construct() {
		//将后台操作记录到日志
		$current_user = session('name');

		if($current_user && !(MODULE_NAME =='Admin' && CONTROLLER_NAME =='Main') &&
		!(MODULE_NAME =='Admin' && CONTROLLER_NAME =='Index') &&
		!(MODULE_NAME =='Admin' && CONTROLLER_NAME =='Public'))
		{
			$data = array(
			'admin_username'=>$current_user,
			'module_name'=>MODULE_NAME,
			'controller_name'=>CONTROLLER_NAME,
			'action_name'=>ACTION_NAME,
			'params'=>urldecode(http_build_query(array_merge($_GET,$_POST))),
			'ip'=>ip2long(get_client_ip(0,true)),
			'create_time'=>time(),
			);
			

			M('admin_operatelog')->add($data);
		}

		hook('admin_begin');
		$admintpl_path=C("SP_ADMIN_TMPL_PATH").C("SP_ADMIN_DEFAULT_THEME")."/";
		C("TMPL_ACTION_SUCCESS",$admintpl_path.C("SP_ADMIN_TMPL_ACTION_SUCCESS"));
		C("TMPL_ACTION_ERROR",$admintpl_path.C("SP_ADMIN_TMPL_ACTION_ERROR"));
		parent::__construct();
		$time=time();
		$this->assign("js_debug",APP_DEBUG?"?v=$time":"");
	}

	function _initialize(){
		parent::_initialize();
		define("TMPL_PATH", C("SP_ADMIN_TMPL_PATH"));

		$site_options=get_site_options();

		$this->assign($site_options);
		//暂时取消后台多语言
		$this->load_app_admin_menu_lang();

		$session_admin_id=session('ADMIN_ID');

		if(!empty($session_admin_id)){
			$users_obj= M("Users");
			$user=$users_obj->where(array('id'=>$session_admin_id))->find();
			if(!$this->check_access($session_admin_id)){
				$this->error("您没有访问权限！");
			}
			$this->assign("admin",$user);
		}else{

			if(IS_AJAX){
				$this->error("您还没有登录！",U("admin/public/login"));
				//$this->error("您还没有登录！",U("admin/public/tg_login")); //针对推广兼职界面的修改
			}else{
				header("Location:".U("admin/public/login"));
				//header("Location:".U("admin/public/tg_login"));//针对推广兼职界面的修改
				exit();
			}

		}
	}

	/**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
	protected function ajaxReturn($data, $type = '',$json_option=0) {

		$data['referer'] = $data['url'] ? $data['url'] : "";
		$data['state']   = !empty($data['status']) ? "success" : "fail";

		if(empty($type)) $type  =   C('DEFAULT_AJAX_RETURN');
		switch (strtoupper($type)){
			case 'JSON' :
				// 返回JSON数据格式到客户端 包含状态信息
				header('Content-Type:application/json; charset=utf-8');
				exit(json_encode($data,$json_option));
			case 'XML'  :
				// 返回xml格式数据
				header('Content-Type:text/xml; charset=utf-8');
				exit(xml_encode($data));
			case 'JSONP':
				// 返回JSON数据格式到客户端 包含状态信息
				header('Content-Type:application/json; charset=utf-8');
				$handler  =   isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');
				exit($handler.'('.json_encode($data,$json_option).');');
			case 'EVAL' :
				// 返回可执行的js脚本
				header('Content-Type:text/html; charset=utf-8');
				exit($data);
			case 'AJAX_UPLOAD':
				// 返回JSON数据格式到客户端 包含状态信息
				header('Content-Type:text/html; charset=utf-8');
				exit(json_encode($data,$json_option));
			default :
				// 用于扩展其他返回格式数据
				Hook::listen('ajax_return',$data);
		}

	}

	/**
	 * 初始化后台菜单
	 */
	public function initMenu() {
		$Menu = F("Menu");
		if (!$Menu) {
			$Menu=D("Common/Menu")->menu_cache();
		}
		return $Menu;
	}

	/**
	 * 消息提示
	 * @param type $message
	 * @param type $jumpUrl
	 * @param type $ajax
	 */
	public function success($message = '', $jumpUrl = '', $ajax = false) {
		parent::success($message, $jumpUrl, $ajax);
	}

	/**
	 * 模板显示
	 * @param type $templateFile 指定要调用的模板文件
	 * @param type $charset 输出编码
	 * @param type $contentType 输出类型
	 * @param string $content 输出内容
	 * 此方法作用在于实现后台模板直接存放在各自项目目录下。例如Admin项目的后台模板，直接存放在Admin/Tpl/目录下
	 */
	public function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '') {
		parent::display($this->parseTemplate($templateFile), $charset, $contentType,$content,$prefix);
	}

	/**
	 * 获取输出页面内容
	 * 调用内置的模板引擎fetch方法，
	 * @access protected
	 * @param string $templateFile 指定要调用的模板文件
	 * 默认为空 由系统自动定位模板文件
	 * @param string $content 模板输出内容
	 * @param string $prefix 模板缓存前缀*
	 * @return string
	 */
	protected function fetch($templateFile='',$content='',$prefix=''){
		$templateFile = empty($content)?$this->parseTemplate($templateFile):'';
		return parent::fetch($templateFile,$content,$prefix);
	}

	/**
	 * 自动定位模板文件
	 * @access protected
	 * @param string $template 模板文件规则
	 * @return string
	 */
	public function parseTemplate($template='') {
		$tmpl_path=C("SP_ADMIN_TMPL_PATH");
		define("SP_TMPL_PATH", $tmpl_path);
		if($this->theme) { // 指定模板主题
			$theme = $this->theme;
		}else{
			// 获取当前主题名称
			$theme      =    C('SP_ADMIN_DEFAULT_THEME');
		}

		if(is_file($template)) {
			// 获取当前主题的模版路径
			define('THEME_PATH',   $tmpl_path.$theme."/");
			return $template;
		}
		$depr       =   C('TMPL_FILE_DEPR');
		$template   =   str_replace(':', $depr, $template);

		// 获取当前模块
		$module   =  MODULE_NAME."/";
		if(strpos($template,'@')){ // 跨模块调用模版文件
			list($module,$template)  =   explode('@',$template);
		}

		$module =$module."/";

		// 获取当前主题的模版路径
		define('THEME_PATH',   $tmpl_path.$theme."/");

		// 分析模板文件规则
		if('' == $template) {
			// 如果模板文件名为空 按照默认规则定位
			$template = CONTROLLER_NAME . $depr . ACTION_NAME;
		}elseif(false === strpos($template, '/')){
			$template = CONTROLLER_NAME . $depr . $template;
		}

		$cdn_settings=sp_get_option('cdn_settings');
		if(!empty($cdn_settings['cdn_static_root'])){
			$cdn_static_root=rtrim($cdn_settings['cdn_static_root'],'/');
			C("TMPL_PARSE_STRING.__TMPL__",$cdn_static_root."/".THEME_PATH);
			C("TMPL_PARSE_STRING.__PUBLIC__",$cdn_static_root."/public");
			C("TMPL_PARSE_STRING.__WEB_ROOT__",$cdn_static_root);
		}else{
			C("TMPL_PARSE_STRING.__TMPL__",__ROOT__."/".THEME_PATH);
		}


		C('SP_VIEW_PATH',$tmpl_path);
		C('DEFAULT_THEME',$theme);
		define("SP_CURRENT_THEME", $theme);

		$file = sp_add_template_file_suffix(THEME_PATH.$module.$template);
		$file= str_replace("//",'/',$file);
		if(!file_exists_case($file)) E(L('_TEMPLATE_NOT_EXIST_').':'.$file);
		return $file;
	}

	/**
     * 排序 排序字段为listorders数组 POST 排序字段为：listorder或者自定义字段
     * @param mixed $model 需要排序的模型类
     * @param string $custom_field 自定义排序字段 默认为listorder,可以改为自己的排序字段
     */
	protected function _listorders($model,$custom_field='') {
		if (!is_object($model)) {
			return false;
		}
		$field=empty($custom_field)&&is_string($custom_field)?'listorder':$custom_field;
		$pk = $model->getPk(); //获取主键名称
		$ids = $_POST['listorders'];
		foreach ($ids as $key => $r) {
			$data[$field] = $r;
			$model->where(array($pk => $key))->save($data);
		}
		return true;
	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see \Common\Controller\AppframeController::page()
	 */
	protected function page($total_size = 1, $page_size = 0, $current_page = 1, $listRows = 6, $pageParam = '', $pageLink = '', $static = false) {
		if ($page_size == 0) {
			$page_size = C("PAGE_LISTROWS");
		}

		if (empty($pageParam)) {
			$pageParam = C("VAR_PAGE");
		}

		$page = new \Page($total_size, $page_size, $current_page, $listRows, $pageParam, $pageLink, $static);
		$page->SetPager('Admin', '{first}{prev}&nbsp;{liststart}{list}&nbsp;{next}{last}<span>共{recordcount}条数据</span>', array("listlong" => "4", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
		return $page;
	}

	/**
	 *  检查后台用户访问权限
	 * @param int $uid 后台用户id
	 * @return boolean 检查通过返回true
	 */
	private function check_access($uid){
		//如果用户角色是1，则无需判断
		if($uid == 1){
			return true;
		}

		$rule=MODULE_NAME.CONTROLLER_NAME.ACTION_NAME;
		$no_need_check_rules=array("AdminIndexindex","AdminMainindex","AdminMaingetKaifu","AdminGamegetToken","AdminGamegetGameInfo");

		if( !in_array($rule,$no_need_check_rules) ){
			return sp_auth_check($uid);
		}else{
			return true;
		}
	}

	/**
	 * 加载后台用户语言包
	 */
	private function load_app_admin_menu_lang(){
		$default_lang=C('DEFAULT_LANG');
		$langSet=C('ADMIN_LANG_SWITCH_ON',null,false)?LANG_SET:$default_lang;
		if($default_lang!=$langSet){
			$admin_menu_lang_file=SPAPP.MODULE_NAME."/Lang/".$langSet."/admin_menu.php";
		}else{
			$admin_menu_lang_file=SITE_PATH."data/lang/".MODULE_NAME."/Lang/$langSet/admin_menu.php";
			if(!file_exists_case($admin_menu_lang_file)){
				$admin_menu_lang_file=SPAPP.MODULE_NAME."/Lang/".$langSet."/admin_menu.php";
			}
		}
		if(is_file($admin_menu_lang_file)){
			$lang=include $admin_menu_lang_file;
			L($lang);
		}
	}
}
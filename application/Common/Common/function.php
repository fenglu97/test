<?php
use Common\Controller\AliyunController;

/**
 * 获取当前登录的管事员id
 * @return int
 */
function get_current_admin_id(){
	return session('ADMIN_ID');
}

/**
 * 获取当前登录的管事员id
 * @return int
 */
function sp_get_current_admin_id(){
	return session('ADMIN_ID');
}

/**
 * 判断前台用户是否登录
 * @return boolean
 */
function sp_is_user_login(){
	$session_user=session('user');
	return !empty($session_user);
}

/**
 * 获取当前登录的前台用户的信息，未登录时，返回false
 * @return array|boolean
 */
function sp_get_current_user(){
	$session_user=session('user');
	if(!empty($session_user)){
		return $session_user;
	}else{
		return false;
	}
}

/**
 * 更新当前登录前台用户的信息
 * @param array $user 前台用户的信息
 */
function sp_update_current_user($user){
	session('user',$user);
}

/**
 * 获取当前登录前台用户id,推荐使用sp_get_current_userid
 * @return int
 */
function get_current_userid(){
	$session_user_id=session('user.id');
	if(!empty($session_user_id)){
		return $session_user_id;
	}else{
		return 0;
	}
}

/**
 * 获取当前登录前台用户id
 * @return int
 */
function sp_get_current_userid(){
	return get_current_userid();
}

/**
 * 返回带协议的域名
 */
function sp_get_host(){
	$host=$_SERVER["HTTP_HOST"];
	$protocol=is_ssl()?"https://":"http://";
	return $protocol.$host;
}

/**
 * 获取前台模板根目录
 */
function sp_get_theme_path(){
	// 获取当前主题名称
	$tmpl_path=C("SP_TMPL_PATH");
	$theme      =    C('SP_DEFAULT_THEME');
	if(C('TMPL_DETECT_THEME')) {// 自动侦测模板主题
		$t = C('VAR_TEMPLATE');
		if (isset($_GET[$t])){
			$theme = $_GET[$t];
		}elseif(cookie('think_template')){
			$theme = cookie('think_template');
		}
		if(!file_exists($tmpl_path."/".$theme)){
			$theme  =   C('SP_DEFAULT_THEME');
		}
		cookie('think_template',$theme,864000);
	}

	return __ROOT__.'/'.$tmpl_path.$theme."/";
}


/**
 * 获取用户头像相对网站根目录的地址
 */
function sp_get_user_avatar_url($avatar){

	if($avatar){
		if(strpos($avatar, "http")===0){
			return $avatar;
		}else {
			if(strpos($avatar, 'avatar/')===false){
				$avatar='avatar/'.$avatar;
			}
			$url =  sp_get_asset_upload_path($avatar,false);
			if(C('FILE_UPLOAD_TYPE')=='Qiniu'){
				$storage_setting=sp_get_cmf_settings('storage');
				$qiniu_setting=$storage_setting['Qiniu']['setting'];
				$filepath=$qiniu_setting['protocol'].'://'.$storage_setting['Qiniu']['domain']."/".$avatar;
				if($qiniu_setting['enable_picture_protect']){
					$url = $url.$qiniu_setting['style_separator'].$qiniu_setting['styles']['avatar'];
				}
			}

			return $url;
		}

	}else{
		return $avatar;
	}

}

/**
 * CMF密码加密方法
 * @param string $pw 要加密的字符串
 * @return string
 */
function sp_password($pw,$authcode=''){
	if(empty($authcode)){
		$authcode=C("AUTHCODE");
	}
	$result="###".md5(md5($authcode.$pw));
	return $result;
}

/**
 * SDK用户密码加密
 *
 */
function sp_password_by_player($password='',$salt=''){
	if(strlen($password)==32){
		return md5($password.$salt);
	}else{
		return md5(md5($password).$salt);
	}
}

/**
 * CMF密码加密方法 (X2.0.0以前的方法)
 * @param string $pw 要加密的字符串
 * @return string
 */
function sp_password_old($pw){
	$decor=md5(C('DB_PREFIX'));
	$mi=md5($pw);
	return substr($decor,0,12).$mi.substr($decor,-4,4);
}

/**
 * CMF密码比较方法,所有涉及密码比较的地方都用这个方法
 * @param string $password 要比较的密码
 * @param string $password_in_db 数据库保存的已经加密过的密码
 * @return boolean 密码相同，返回true
 */
function sp_compare_password($password,$password_in_db){
	if(strpos($password_in_db, "###")===0){
		return sp_password($password)==$password_in_db;
	}else{
		return sp_password_old($password)==$password_in_db;
	}
}


function sp_log($content,$file="log.txt"){
	file_put_contents($file, $content,FILE_APPEND);
}

/**
 * 随机字符串生成
 * @param int $len 生成的字符串长度
 * @return string
 */
function sp_random_string($len = 6) {
	$chars = array(
		"a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
		"l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
		"w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
		"H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
		"S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
		"3", "4", "5", "6", "7", "8", "9"
	);
	$charsLen = count($chars) - 1;
	shuffle($chars);    // 将数组打乱
	$output = "";
	for ($i = 0; $i < $len; $i++) {
		$output .= $chars[mt_rand(0, $charsLen)];
	}
	return $output;
}

/**
 * 清空系统缓存，兼容sae
 */
function sp_clear_cache(){
	import ( "ORG.Util.Dir" );
	$dirs = array ();
	// runtime/
	$rootdirs = sp_scan_dir( RUNTIME_PATH."*" );
	//$noneed_clear=array(".","..","Data");
	$noneed_clear=array(".","..");
	$rootdirs=array_diff($rootdirs, $noneed_clear);
	foreach ( $rootdirs as $dir ) {

		if ($dir != "." && $dir != "..") {
			$dir = RUNTIME_PATH . $dir;
			if (is_dir ( $dir )) {
				//array_push ( $dirs, $dir );
				$tmprootdirs = sp_scan_dir ( $dir."/*" );
				foreach ( $tmprootdirs as $tdir ) {
					if ($tdir != "." && $tdir != "..") {
						$tdir = $dir . '/' . $tdir;
						if (is_dir ( $tdir )) {
							array_push ( $dirs, $tdir );
						}else{
							@unlink($tdir);
						}
					}
				}
			}else{
				@unlink($dir);
			}
		}
	}
	$dirtool=new \Dir("");
	foreach ( $dirs as $dir ) {
		$dirtool->delDir ( $dir );
	}

	if(sp_is_sae()){
		$global_mc=@memcache_init();
		if($global_mc){
			$global_mc->flush();
		}

		$no_need_delete=array("THINKCMF_DYNAMIC_CONFIG");
		$kv = new SaeKV();
		// 初始化KVClient对象
		$ret = $kv->init();
		// 循环获取所有key-values
		$ret = $kv->pkrget('', 100);
		while (true) {
			foreach($ret as $key =>$value){
				if(!in_array($key, $no_need_delete)){
					$kv->delete($key);
				}
			}
			end($ret);
			$start_key = key($ret);
			$i = count($ret);
			if ($i < 100) break;
			$ret = $kv->pkrget('', 100, $start_key);
		}

	}

}

/**
 * 保存数组变量到php文件
 * @param string $path 保存路径
 * @param mixed $value 要保存的变量
 * @return boolean 保存成功返回true,否则false
 */
function sp_save_var($path,$value){
	$ret = file_put_contents($path, "<?php\treturn " . var_export($value, true) . ";?>");
	return $ret;
}

/**
 * 更新系统配置文件
 * @param array $data <br>如：array("URL_MODEL"=>0);
 * @return boolean
 */
function sp_set_dynamic_config($data){

	if(!is_array($data)){
		return false;
	}

	if(sp_is_sae()){
		$kv = new SaeKV();
		$ret = $kv->init();
		$configs=$kv->get("THINKCMF_DYNAMIC_CONFIG");
		$configs=empty($configs)?array():unserialize($configs);
		$configs=array_merge($configs,$data);
		$result = $kv->set('THINKCMF_DYNAMIC_CONFIG', serialize($configs));
	}elseif(defined('IS_BAE') && IS_BAE){
		$bae_mc=new BaeMemcache();
		$configs=$bae_mc->get("THINKCMF_DYNAMIC_CONFIG");
		$configs=empty($configs)?array():unserialize($configs);
		$configs=array_merge($configs,$data);
		$result = $bae_mc->set("THINKCMF_DYNAMIC_CONFIG",serialize($configs),MEMCACHE_COMPRESSED,0);
	}else{
		$config_file="./data/conf/config.php";
		if(file_exists($config_file)){
			$configs=include $config_file;
		}else {
			$configs=array();
		}
		$configs=array_merge($configs,$data);
		$result = file_put_contents($config_file, "<?php\treturn " . var_export($configs, true) . ";");
	}
	sp_clear_cache();
	S("sp_dynamic_config",$configs);
	return $result;
}


/**
 * 转化格式化的字符串为数组
 * @param string $tag 要转化的字符串,格式如:"id:2;cid:1;order:post_date desc;"
 * @return array 转化后字符串<pre>
 * array(
 *  'id'=>'2',
 *  'cid'=>'1',
 *  'order'=>'post_date desc'
 * )
 */
function sp_param_lable($tag = ''){
	$param = array();
	$array = explode(';',$tag);
	foreach ($array as $v){
		$v=trim($v);
		if(!empty($v)){
			list($key,$val) = explode(':',$v);
			$param[trim($key)] = trim($val);
		}
	}
	return $param;
}


/**
 * 获取后台管理设置的网站信息，此类信息一般用于前台，推荐使用sp_get_site_options
 */
function get_site_options($key = ''){
	$site_options = F("site_options");
	if(empty($site_options)){
		$options_obj = M("Options");
		$option = $options_obj->where("option_name='site_options'")->find();
		if($option){
			$site_options = json_decode($option['option_value'],true);
		}else{
			$site_options = array();
		}
		F("site_options", $site_options);
	}
	$site_options['site_tongji']=htmlspecialchars_decode($site_options['site_tongji']);
	if(!empty($key)){
		return $site_options[$key];
	}
	return $site_options;
}

/**
 * 获取后台管理设置的网站信息，此类信息一般用于前台
 */
function sp_get_site_options(){
	get_site_options();
}

/**
 * 获取CMF系统的设置，此类设置用于全局
 * @param string $key 设置key，为空时返回所有配置信息
 * @return mixed
 */
function sp_get_cmf_settings($key=""){
	$cmf_settings = F("cmf_settings");
	if(empty($cmf_settings)){
		$options_obj = M("Options");
		$option = $options_obj->where("option_name='cmf_settings'")->find();
		if($option){
			$cmf_settings = json_decode($option['option_value'],true);
		}else{
			$cmf_settings = array();
		}
		F("cmf_settings", $cmf_settings);
	}
	if(!empty($key)){
		return $cmf_settings[$key];
	}
	return $cmf_settings;
}

/**
 * 更新CMF系统的设置，此类设置用于全局
 * @param array $data
 * @return boolean
 */
function sp_set_cmf_setting($data){
	if(!is_array($data) || empty($data) ){
		return false;
	}
	$cmf_settings['option_name']="cmf_settings";
	$options_model=M("Options");
	$find_setting=$options_model->where("option_name='cmf_settings'")->find();
	F("cmf_settings",null);
	if($find_setting){
		$setting=json_decode($find_setting['option_value'],true);
		if($setting){
			$setting=array_merge($setting,$data);
		}else {
			$setting=$data;
		}

		$cmf_settings['option_value']=json_encode($setting);
		return $options_model->where("option_name='cmf_settings'")->save($cmf_settings);
	}else{
		$cmf_settings['option_value']=json_encode($data);
		return $options_model->add($cmf_settings);
	}
}

/**
 * 设置系统配置，通用
 * @param string $key 配置键值,都小写
 * @param array $data 配置值，数组
 */
function sp_set_option($key,$data){
	if(!is_array($data) || empty($data) || !is_string($key) || empty($key)){
		return false;
	}

	$key=strtolower($key);
	$option=array();
	$options_model=M("Options");
	$find_option=$options_model->where(array('option_name'=>$key))->find();
	if($find_option){
		$old_option_value=json_decode($find_option['option_value'],true);
		if($old_option_value){
			$data=array_merge($old_option_value,$data);
		}

		$option['option_value']=json_encode($data);

		$result = $options_model->where(array('option_name'=>$key))->save($option);
	}else{
		$option['option_name']=$key;
		$option['option_value']=json_encode($data);
		$result = $options_model->add($option);
	}

	if($result!==false){
		F("cmf_system_options_".$key,$data);
	}

	return $result;
}

/**
 * 获取系统配置，通用
 * @param string $key 配置键值,都小写
 * @param array $data 配置值，数组
 */
function sp_get_option($key){
	if(!is_string($key) || empty($key)){
		return false;
	}

	$option_value=F("cmf_system_options_".$key);

	if(empty($option_value)){
		$options_model = M("Options");
		$option_value = $options_model->where(array('option_name'=>$key))->getField('option_value');
		if($option_value){
			$option_value = json_decode($option_value,true);
			F("cmf_system_options_".$key);
		}
	}

	return $option_value;
}

/**
 * 获取CMF上传配置
 */
function sp_get_upload_setting(){
	$upload_setting=sp_get_option('upload_setting');
	if(empty($upload_setting)){
		$upload_setting = array(
			'image' => array(
				'upload_max_filesize' => '10240',//单位KB
				'extensions' => 'jpg,jpeg,png,gif,bmp4'
			),
			'video' => array(
				'upload_max_filesize' => '10240',
				'extensions' => 'mp4,avi,wmv,rm,rmvb,mkv'
			),
			'audio' => array(
				'upload_max_filesize' => '10240',
				'extensions' => 'mp3,wma,wav'
			),
			'file' => array(
				'upload_max_filesize' => '10240',
				'extensions' => 'txt,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'
			)
		);
	}

	if(empty($upload_setting['upload_max_filesize'])){
		$upload_max_filesize_setting=array();
		foreach ($upload_setting as $setting){
			$extensions=explode(',', trim($setting['extensions']));
			if(!empty($extensions)){
				$upload_max_filesize=intval($setting['upload_max_filesize'])*1024;//转化成KB
				foreach ($extensions as $ext){
					if(!isset($upload_max_filesize_setting[$ext]) || $upload_max_filesize>$upload_max_filesize_setting[$ext]*1024){
						$upload_max_filesize_setting[$ext]=$upload_max_filesize;
					}
				}
			}
		}

		$upload_setting['upload_max_filesize']=$upload_max_filesize_setting;
		F("cmf_system_options_upload_setting",$upload_setting);
	}else{
		$upload_setting=F("cmf_system_options_upload_setting");
	}

	return $upload_setting;
}



/**
 * 全局获取验证码图片
 * 生成的是个HTML的img标签
 * @param string $imgparam <br>
 * 生成图片样式，可以设置<br>
 * length=4&font_size=20&width=238&height=50&use_curve=1&use_noise=1<br>
 * length:字符长度<br>
 * font_size:字体大小<br>
 * width:生成图片宽度<br>
 * heigh:生成图片高度<br>
 * use_curve:是否画混淆曲线  1:画，0:不画<br>
 * use_noise:是否添加杂点 1:添加，0:不添加<br>
 * @param string $imgattrs<br>
 * img标签原生属性，除src,onclick之外都可以设置<br>
 * 默认值：style="cursor: pointer;" title="点击获取"<br>
 * @return string<br>
 * 原生html的img标签<br>
 * 注，此函数仅生成img标签，应该配合在表单加入name=verify的input标签<br>
 * 如：&lt;input type="text" name="verify"/&gt;<br>
 */
function sp_verifycode_img($imgparam='length=4&font_size=20&width=238&height=50&use_curve=1&use_noise=1',$imgattrs='style="cursor: pointer;" title="点击获取"'){
	$src=__ROOT__."/index.php?g=api&m=checkcode&a=index&".$imgparam;
	$img=<<<hello
<img class="verify_img" src="$src" onclick="this.src='$src&time='+Math.random();" $imgattrs/>
hello;
	return $img;
}


/**
 * 返回指定id的菜单
 * 同上一类方法，jquery treeview 风格，可伸缩样式
 * @param $myid 表示获得这个ID下的所有子级
 * @param $effected_id 需要生成treeview目录数的id
 * @param $str 末级样式
 * @param $str2 目录级别样式
 * @param $showlevel 直接显示层级数，其余为异步显示，0为全部限制
 * @param $ul_class 内部ul样式 默认空  可增加其他样式如'sub-menu'
 * @param $li_class 内部li样式 默认空  可增加其他样式如'menu-item'
 * @param $style 目录样式 默认 filetree 可增加其他样式如'filetree treeview-famfamfam'
 * @param $dropdown 有子元素时li的class
 * $id="main";
$effected_id="mainmenu";
$filetpl="<a href='\$href'><span class='file'>\$label</span></a>";
$foldertpl="<span class='folder'>\$label</span>";
$ul_class="" ;
$li_class="" ;
$style="filetree";
$showlevel=6;
sp_get_menu($id,$effected_id,$filetpl,$foldertpl,$ul_class,$li_class,$style,$showlevel);
 * such as
 * <ul id="example" class="filetree ">
<li class="hasChildren" id='1'>
<span class='folder'>test</span>
<ul>
<li class="hasChildren" id='4'>
<span class='folder'>caidan2</span>
<ul>
<li class="hasChildren" id='5'>
<span class='folder'>sss</span>
<ul>
<li id='3'><span class='folder'>test2</span></li>
</ul>
</li>
</ul>
</li>
</ul>
</li>
<li class="hasChildren" id='6'><span class='file'>ss</span></li>
</ul>
 */

function sp_get_menu($id="main",$menu_root_ul_id="mainmenu",$filetpl="<span class='file'>\$label</span>",$foldertpl="<span class='folder'>\$label</span>",$ul_class="" ,$li_class="" ,$menu_root_ul_class="filetree",$showlevel=6,$dropdown='hasChild'){
	$navs=F("site_nav_".$id);
	if(empty($navs)){
		$navs=_sp_get_menu_datas($id);
	}

	import("Tree");
	$tree = new \Tree();
	$tree->init($navs);
	return $tree->get_treeview_menu(0,$menu_root_ul_id, $filetpl, $foldertpl,  $showlevel,$ul_class,$li_class,  $menu_root_ul_class,  1, FALSE, $dropdown);
}


function _sp_get_menu_datas($id){
	$nav_obj= M("Nav");
	$oldid=$id;
	$id= intval($id);
	$id = empty($id)?"main":$id;
	if($id=="main"){
		$navcat_obj= M("NavCat");
		$main=$navcat_obj->where("active=1")->find();
		$id=$main['navcid'];
	}

	if(empty($id)){
		return array();
	}

	$navs= $nav_obj->where(array('cid'=>$id,'status'=>1))->order(array("listorder" => "ASC"))->select();

	$default_app=strtolower(C("DEFAULT_MODULE"));
	$g=C("VAR_MODULE");
	foreach ($navs as $key=>$nav){
		$href=htmlspecialchars_decode($nav['href']);
		$hrefold=$href;
		if(strpos($hrefold,"{")){//序列 化的数据
			$href=unserialize(stripslashes($nav['href']));
			$href=strtolower(leuu($href['action'],$href['param']));
			$href=preg_replace("/\/$default_app\//", "/",$href);
			$href=preg_replace("/$g=$default_app&/", "",$href);
		}else{
			if($hrefold=="home"){
				$href=__ROOT__."/";
			}else{
				$href=$hrefold;
			}
		}
		$nav['href']=$href;
		$navs[$key]=$nav;
	}
	F("site_nav_".$oldid,$navs);
	return $navs;
}


function sp_get_menu_tree($id="main"){
	$navs=F("site_nav_".$id);
	if(empty($navs)){
		$navs=_sp_get_menu_datas($id);
	}

	import("Tree");
	$tree = new \Tree();
	$tree->init($navs);
	return $tree->get_tree_array(0, "");
}

/**
 * 获取某导航子菜单
 * @param $parent_id  导航id
 * @param $field 要获取菜单的字段,如label,href
 * @param $order
 * @return array
 */
function sp_get_submenu($parent_id,$field='',$order=''){
	$nav_model= M("Nav");
	$field = !empty($field) ? $field : '*';
	$order = !empty($order) ? $order : 'id';
	//根据参数生成查询条件
	$where = array();
	$reg = $nav_model->where(array('parentid'=>$parent_id))->select();
	if($reg) {
		$where['parentid'] = $parent_id;
	}else{
		$parentid = $nav_model->where($where['id'] = $parent_id)->getField('parentid');

		if(empty($parentid)){
			$where['id']= $parent_id;
		}else{
			$where['parentid'] = $parentid;
		}

	}

	$navs=$nav_model->field($field)->where($where)->order($order)->select();

	$default_app=strtolower(C("DEFAULT_MODULE"));
	$g=C("VAR_MODULE");
	foreach($navs as $key =>$value){

		$hrefold=$navs[$key]['href'];
		if(strpos($hrefold,"{")){//序列 化的数据
			$href =  unserialize($value['href']);
			$href=strtolower(leuu($href['action'],$href['param']));
			$href=preg_replace("/\/$default_app\//", "/",$href);
			$href=preg_replace("/$g=$default_app&/", "",$href);
			$navs[$key]['href']=$href;
		}else{
			if($hrefold=="home"){
				$navs[$key]['href']=__ROOT__."/";
			}else{
				$navs[$key]['href']=$hrefold;
			}
		}

		if(empty($value['parentid'])){
			$navs[$key]['parentid'] = $parent_id;
		}
	}
	return $navs;

}
/**
 *获取html文本里的img
 * @param string $content
 * @return array
 */
function sp_getcontent_imgs($content){
	import("phpQuery");
	\phpQuery::newDocumentHTML($content);
	$pq=pq();
	$imgs=$pq->find("img");
	$imgs_data=array();
	if($imgs->length()){
		foreach ($imgs as $img){
			$img=pq($img);
			$im['src']=$img->attr("src");
			$im['title']=$img->attr("title");
			$im['alt']=$img->attr("alt");
			$imgs_data[]=$im;
		}
	}
	\phpQuery::$documents=null;
	return $imgs_data;
}


/**
 *
 * @param unknown_type $navcatname
 * @param unknown_type $datas
 * @param unknown_type $navrule
 * @return string
 */
function sp_get_nav4admin($navcatname,$datas,$navrule){
	$nav = array();
	$nav['name']=$navcatname;
	$nav['urlrule']=$navrule;
	$nav['items']=array();

	foreach($datas as $t){
		$urlrule=array();
		$action=$navrule['action'];
		$urlrule['action']=$action;
		$urlrule['param']=array();
		if(isset($navrule['param'])){
			foreach ($navrule['param'] as $key=>$val){
				$urlrule['param'][$key]=$t[$val];
			}
		}

		$nav['items'][] = array(
			"label" => $t[$navrule['label']],
			"url" => U($action, $urlrule['param']),
			"rule" => base64_encode(serialize($urlrule)),
			"parentid" => empty($navrule['parentid']) ? 0 : $t[$navrule['parentid']],
			"id" => $t[$navrule['id']],
		);
	}
	return $nav;
}

function sp_get_apphome_tpl($tplname,$default_tplname,$default_theme=""){
	$theme      =    C('SP_DEFAULT_THEME');
	if(C('TMPL_DETECT_THEME')){// 自动侦测模板主题
		$t = C('VAR_TEMPLATE');
		if (isset($_GET[$t])){
			$theme = $_GET[$t];
		}elseif(cookie('think_template')){
			$theme = cookie('think_template');
		}
	}
	$theme=empty($default_theme)?$theme:$default_theme;
	$themepath=C("SP_TMPL_PATH").$theme."/".MODULE_NAME."/";
	$tplpath = sp_add_template_file_suffix($themepath.$tplname);
	$defaultpl = sp_add_template_file_suffix($themepath.$default_tplname);
	if(file_exists_case($tplpath)){
	}else if(file_exists_case($defaultpl)){
		$tplname=$default_tplname;
	}else{
		$tplname="404";
	}
	return $tplname;
}


/**
 * 去除字符串中的指定字符
 * @param string $str 待处理字符串
 * @param string $chars 需去掉的特殊字符
 * @return string
 */
function sp_strip_chars($str, $chars='?<*.>\'\"'){
	return preg_replace('/['.$chars.']/is', '', $str);
}

/**
 * 发送邮件
 * @param string $address
 * @param string $subject
 * @param string $message
 * @return array<br>
 * 返回格式：<br>
 * array(<br>
 * 	"error"=>0|1,//0代表出错<br>
 * 	"message"=> "出错信息"<br>
 * );
 */
function sp_send_email($address,$subject,$message){
	$mail=new \PHPMailer();
	// 设置PHPMailer使用SMTP服务器发送Email
	$mail->IsSMTP();
	$mail->IsHTML(true);
	// 设置邮件的字符编码，若不指定，则为'UTF-8'
	$mail->CharSet='UTF-8';
	// 添加收件人地址，可以多次使用来添加多个收件人
	$mail->AddAddress($address);
	// 设置邮件正文
	$mail->Body=$message;
	// 设置邮件头的From字段。
	$mail->From=C('SP_MAIL_ADDRESS');
	// 设置发件人名字
	$mail->FromName=C('SP_MAIL_SENDER');
	// 设置邮件标题
	$mail->Subject=$subject;
	// 设置SMTP服务器。
	$mail->Host=C('SP_MAIL_SMTP');
	//by Rainfer
	// 设置SMTPSecure。
	$Secure=C('SP_MAIL_SECURE');
	$mail->SMTPSecure=empty($Secure)?'':$Secure;
	// 设置SMTP服务器端口。
	$port=C('SP_MAIL_SMTP_PORT');
	$mail->Port=empty($port)?"25":$port;
	// 设置为"需要验证"
	$mail->SMTPAuth=true;
	// 设置用户名和密码。
	$mail->Username=C('SP_MAIL_LOGINNAME');
	$mail->Password=C('SP_MAIL_PASSWORD');
	// 发送邮件。
	if(!$mail->Send()) {
		$mailerror=$mail->ErrorInfo;
		return array("error"=>1,"message"=>$mailerror);
	}else{
		return array("error"=>0,"message"=>"success");
	}
}

/**
 * 转化数据库保存的文件路径，为可以访问的url
 * @param string $file
 * @param mixed $style  样式(七牛)
 * @return string
 */
function sp_get_asset_upload_path($file,$style=''){
	if(strpos($file,"http")===0){
		return $file;
	}else if(strpos($file,"/")===0){
		if(C('FILE_UPLOAD_TYPE') == 'Ftp')
		{
			$file=C('FTP_URL').$file;
		}
		return $file;
	}else{
		$filepath=C("TMPL_PARSE_STRING.__UPLOAD__").$file;
		if(C('FILE_UPLOAD_TYPE')=='Local'){
			if(strpos($filepath,"http")!==0){
				$filepath=sp_get_host().$filepath;
			}
		}

		if(C('FILE_UPLOAD_TYPE')=='Qiniu'){
			$storage_setting=sp_get_cmf_settings('storage');
			$qiniu_setting=$storage_setting['Qiniu']['setting'];
			$filepath=$qiniu_setting['protocol'].'://'.$storage_setting['Qiniu']['domain']."/".$file.$style;
		}
		return $filepath;

	}
}

/**
 * 转化数据库保存图片的文件路径，为可以访问的url
 * @param string $file
 * @param mixed $style  样式(七牛)
 * @return string
 */
function sp_get_image_url($file,$style=''){
	if(strpos($file,"http")===0){
		return $file;
	}else if(strpos($file,"/")===0){
		return $file;
	}else{
		$filepath=C("TMPL_PARSE_STRING.__UPLOAD__").$file;
		if(C('FILE_UPLOAD_TYPE')=='Local'){
			if(strpos($filepath,"http")!==0){
				$filepath=sp_get_host().$filepath;
			}
		}

		if(C('FILE_UPLOAD_TYPE')=='Qiniu'){
			$storage_setting=sp_get_cmf_settings('storage');
			$qiniu_setting=$storage_setting['Qiniu']['setting'];
			$filepath=$qiniu_setting['protocol'].'://'.$storage_setting['Qiniu']['domain']."/".$file.$style;
		}

		return $filepath;

	}
}

/**
 * 获取图片预览链接
 * @param string $file 文件路径，相对于upload
 * @param string $style 图片样式，只有七牛可以用
 * @return string
 */
function sp_get_image_preview_url($file,$style='watermark'){
	if(C('FILE_UPLOAD_TYPE')=='Qiniu'){
		$storage_setting=sp_get_cmf_settings('storage');
		$qiniu_setting=$storage_setting['Qiniu']['setting'];
		$filepath=$qiniu_setting['protocol'].'://'.$storage_setting['Qiniu']['domain']."/".$file;
		$url=sp_get_asset_upload_path($file,false);
		if($qiniu_setting['enable_picture_protect']){
			$url = $url.$qiniu_setting['style_separator'].$qiniu_setting['styles'][$style];
		}

		return $url;

	}else{
		return sp_get_asset_upload_path($file,false);
	}
}

/**
 * 获取文件下载链接
 * @param string $file
 * @param int $expires
 * @return string
 */
function sp_get_file_download_url($file,$expires=3600){
	if(C('FILE_UPLOAD_TYPE')=='Qiniu'){
		$storage_setting=sp_get_cmf_settings('storage');
		$qiniu_setting=$storage_setting['Qiniu']['setting'];
		$filepath=$qiniu_setting['protocol'].'://'.$storage_setting['Qiniu']['domain']."/".$file;
		$url=sp_get_asset_upload_path($file,false);

		if($qiniu_setting['enable_picture_protect']){
			$qiniuStorage=new \Think\Upload\Driver\Qiniu\QiniuStorage(C('UPLOAD_TYPE_CONFIG'));
			$url = $qiniuStorage->privateDownloadUrl($url,$expires);
		}

		return $url;

	}else{
		return sp_get_asset_upload_path($file,false);
	}
}


function sp_authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;

	$key = md5($key ? $key : C("AUTHCODE"));
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);

	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);

	$result = '';
	$box = range(0, 255);

	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}

	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}

	if($operation == 'DECODE') {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}

}

function sp_authencode($string){
	return sp_authcode($string,"ENCODE");
}

function Comments($table,$post_id,$params=array()){
	return  R("Comment/Widget/index",array($table,$post_id,$params));
}
/**
 * 获取评论
 * @param string $tag
 * @param array $where //按照thinkphp where array格式
 */
function sp_get_comments($tag="field:*;limit:0,5;order:createtime desc;",$where=array()){
	$where=array();
	$tag=sp_param_lable($tag);
	$field = !empty($tag['field']) ? $tag['field'] : '*';
	$limit = !empty($tag['limit']) ? $tag['limit'] : '5';
	$order = !empty($tag['order']) ? $tag['order'] : 'createtime desc';

	//根据参数生成查询条件
	$mwhere['status'] = array('eq',1);

	if(is_array($where)){
		$where=array_merge($mwhere,$where);
	}else{
		$where=$mwhere;
	}

	$comments_model=M("Comments");

	$comments=$comments_model->field($field)->where($where)->order($order)->limit($limit)->select();
	return $comments;
}

function sp_file_write($file,$content){

	if(sp_is_sae()){
		$s=new SaeStorage();
		$arr=explode('/',ltrim($file,'./'));
		$domain=array_shift($arr);
		$save_path=implode('/',$arr);
		return $s->write($domain,$save_path,$content);
	}else{
		return file_put_contents($file, $content);
	}
}

function sp_file_read($file){
	if(sp_is_sae()){
		$s=new SaeStorage();
		$arr=explode('/',ltrim($file,'./'));
		$domain=array_shift($arr);
		$save_path=implode('/',$arr);
		return $s->read($domain,$save_path);
	}else{
		file_get_contents($file);
	}
}

/*修复缩略图使用网络地址时，会出现的错误。5iymt 2015年7月10日*/
/**
 * 获取文件相对路径
 * @param string $asset_url 文件的URL
 * @return string
 */
function sp_asset_relative_url($asset_url){
	if(strpos($asset_url,"http")===0){
		return $asset_url;
	}else{
		return str_replace(C("TMPL_PARSE_STRING.__UPLOAD__"), "", $asset_url);
	}
}

function sp_content_page($content,$pagetpl='{first}{prev}{liststart}{list}{listend}{next}{last}'){
	$contents=explode('_ueditor_page_break_tag_',$content);
	$totalsize=count($contents);
	import('Page');
	$pagesize=1;
	$PageParam = C("VAR_PAGE");
	$page = new \Page($totalsize,$pagesize);
	$page->setLinkWraper("li");
	$page->SetPager('default', $pagetpl, array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
	$content=$contents[$page->firstRow];
	$data['content']=$content;
	$data['page']=$page->show('default');

	return $data;
}


/**
 * 根据广告名称获取广告内容
 * @param string $ad_name
 * @return 广告内容
 */
function sp_getad($ad_name){
	$ad_obj= M("Ad");
	$ad=$ad_obj->field("ad_content")->where(array('ad_name'=>$ad_name,'status'=>1))->find();
	return htmlspecialchars_decode($ad['ad_content']);
}

/**
 * 根据幻灯片标识获取所有幻灯片
 * @param string $slide 幻灯片标识
 * @return array;
 */
function sp_getslide($slide,$limit=5,$order = "listorder ASC"){
	$slide_obj= M("SlideCat");
	$join = '__SLIDE__ as b on a.cid =b.slide_cid';
	if($order == ''){
		$order = "b.listorder ASC";
	}
	if ($limit == 0) {
		$limit = 5;
	}
	return $slide_obj->alias("a")->join($join)->where("a.cat_idname='$slide' and b.slide_status=1")->order($order)->limit('0,'.$limit)->select();
}

/**
 * 获取所有友情连接
 * @return array
 */
function sp_getlinks(){
	$links_obj= M("Links");
	return  $links_obj->where("link_status=1")->order("listorder ASC")->select();
}

/**
 * 检查用户对某个url,内容的可访问性，用于记录如是否赞过，是否访问过等等;开发者可以自由控制，对于没有必要做的检查可以不做，以减少服务器压力
 * @param number $object 访问对象的id,格式：不带前缀的表名+id;如posts1表示xx_posts表里id为1的记录;如果object为空，表示只检查对某个url访问的合法性
 * @param number $count_limit 访问次数限制,如1，表示只能访问一次
 * @param boolean $ip_limit ip限制,false为不限制，true为限制
 * @param number $expire 距离上次访问的最小时间单位s，0表示不限制，大于0表示最后访问$expire秒后才可以访问
 * @return true 可访问，false不可访问
 */
function sp_check_user_action($object="",$count_limit=1,$ip_limit=false,$expire=0){
	$common_action_log_model=M("CommonActionLog");
	$action=MODULE_NAME."-".CONTROLLER_NAME."-".ACTION_NAME;
	$userid=get_current_userid();

	$ip=get_client_ip(0,true);//修复ip获取

	$where=array("user"=>$userid,"action"=>$action,"object"=>$object);
	if($ip_limit){
		$where['ip']=$ip;
	}

	$find_log=$common_action_log_model->where($where)->find();

	$time=time();
	if($find_log){
		$common_action_log_model->where($where)->save(array("count"=>array("exp","count+1"),"last_time"=>$time,"ip"=>$ip));
		if($find_log['count']>=$count_limit){
			return false;
		}

		if($expire>0 && ($time-$find_log['last_time'])<$expire){
			return false;
		}
	}else{
		$common_action_log_model->add(array("user"=>$userid,"action"=>$action,"object"=>$object,"count"=>array("exp","count+1"),"last_time"=>$time,"ip"=>$ip));
	}

	return true;
}

/**
 * 用于生成收藏内容用的key
 * @param string $table 收藏内容所在表
 * @param int $object_id 收藏内容的id
 */
function sp_get_favorite_key($table,$object_id){
	$auth_code=C("AUTHCODE");
	$string="$auth_code $table $object_id";

	return sp_authencode($string);
}

function sp_get_relative_url($url){
	if(strpos($url,"http")===0){
		$url=str_replace(array("https://","http://"), "", $url);

		$pos=strpos($url, "/");
		if($pos===false){
			return "";
		}else{
			$url=substr($url, $pos+1);
			$root=preg_replace("/^\//", "", __ROOT__);
			$root=str_replace("/", "\/", $root);
			$url=preg_replace("/^".$root."\//", "", $url);
			return $url;
		}
	}
	return $url;
}

/**
 *
 * @param string $tag
 * @param array $where
 * @return array
 */
function sp_get_users($tag="field:*;limit:0,8;order:create_time desc;",$where=array()){
	$where=array();
	$tag=sp_param_lable($tag);
	$field = !empty($tag['field']) ? $tag['field'] : '*';
	$limit = !empty($tag['limit']) ? $tag['limit'] : '8';
	$order = !empty($tag['order']) ? $tag['order'] : 'create_time desc';

	//根据参数生成查询条件
	$mwhere['user_status'] = array('eq',1);
	$mwhere['user_type'] = array('eq',2);//default user

	if(is_array($where)){
		$where=array_merge($mwhere,$where);
	}else{
		$where=$mwhere;
	}

	$users_model=M("Users");

	$users=$users_model->field($field)->where($where)->order($order)->limit($limit)->select();
	return $users;
}

/**
 * URL组装 支持不同URL模式
 * @param string $url URL表达式，格式：'[模块/控制器/操作#锚点@域名]?参数1=值1&参数2=值2...'
 * @param string|array $vars 传入的参数，支持数组和字符串
 * @param string $suffix 伪静态后缀，默认为true表示获取配置值
 * @param boolean $domain 是否显示域名
 * @return string
 */
function leuu($url='',$vars='',$suffix=true,$domain=false){
	$routes=sp_get_routes();
	if(empty($routes)){
		return U($url,$vars,$suffix,$domain);
	}else{
		// 解析URL
		$info   =  parse_url($url);
		$url    =  !empty($info['path'])?$info['path']:ACTION_NAME;
		if(isset($info['fragment'])) { // 解析锚点
			$anchor =   $info['fragment'];
			if(false !== strpos($anchor,'?')) { // 解析参数
				list($anchor,$info['query']) = explode('?',$anchor,2);
			}
			if(false !== strpos($anchor,'@')) { // 解析域名
				list($anchor,$host)    =   explode('@',$anchor, 2);
			}
		}elseif(false !== strpos($url,'@')) { // 解析域名
			list($url,$host)    =   explode('@',$info['path'], 2);
		}

		// 解析子域名
		//TODO?

		// 解析参数
		if(is_string($vars)) { // aaa=1&bbb=2 转换成数组
			parse_str($vars,$vars);
		}elseif(!is_array($vars)){
			$vars = array();
		}
		if(isset($info['query'])) { // 解析地址里面参数 合并到vars
			parse_str($info['query'],$params);
			$vars = array_merge($params,$vars);
		}

		$vars_src=$vars;

		ksort($vars);

		// URL组装
		$depr       =   C('URL_PATHINFO_DEPR');
		$urlCase    =   C('URL_CASE_INSENSITIVE');
		if('/' != $depr) { // 安全替换
			$url    =   str_replace('/',$depr,$url);
		}
		// 解析模块、控制器和操作
		$url        =   trim($url,$depr);
		$path       =   explode($depr,$url);
		$var        =   array();
		$varModule      =   C('VAR_MODULE');
		$varController  =   C('VAR_CONTROLLER');
		$varAction      =   C('VAR_ACTION');
		$var[$varAction]       =   !empty($path)?array_pop($path):ACTION_NAME;
		$var[$varController]   =   !empty($path)?array_pop($path):CONTROLLER_NAME;
		if($maps = C('URL_ACTION_MAP')) {
			if(isset($maps[strtolower($var[$varController])])) {
				$maps    =   $maps[strtolower($var[$varController])];
				if($action = array_search(strtolower($var[$varAction]),$maps)){
					$var[$varAction] = $action;
				}
			}
		}
		if($maps = C('URL_CONTROLLER_MAP')) {
			if($controller = array_search(strtolower($var[$varController]),$maps)){
				$var[$varController] = $controller;
			}
		}
		if($urlCase) {
			$var[$varController]   =   parse_name($var[$varController]);
		}
		$module =   '';

		if(!empty($path)) {
			$var[$varModule]    =   array_pop($path);
		}else{
			if(C('MULTI_MODULE')) {
				if(MODULE_NAME != C('DEFAULT_MODULE') || !C('MODULE_ALLOW_LIST')){
					$var[$varModule]=   MODULE_NAME;
				}
			}
		}
		if($maps = C('URL_MODULE_MAP')) {
			if($_module = array_search(strtolower($var[$varModule]),$maps)){
				$var[$varModule] = $_module;
			}
		}
		if(isset($var[$varModule])){
			$module =   $var[$varModule];
		}

		if(C('URL_MODEL') == 0) { // 普通模式URL转换
			$url        =   __APP__.'?'.http_build_query(array_reverse($var));

			if($urlCase){
				$url    =   strtolower($url);
			}
			if(!empty($vars)) {
				$vars   =   http_build_query($vars);
				$url   .=   '&'.$vars;
			}
		}else{ // PATHINFO模式或者兼容URL模式

			if(empty($var[C('VAR_MODULE')])){
				$var[C('VAR_MODULE')]=MODULE_NAME;
			}

			$module_controller_action=strtolower(implode($depr,array_reverse($var)));

			$has_route=false;
			$original_url=$module_controller_action.(empty($vars)?"":"?").http_build_query($vars);

			if(isset($routes['static'][$original_url])){
				$has_route=true;
				$url=__APP__."/".$routes['static'][$original_url];
			}else{
				if(isset($routes['dynamic'][$module_controller_action])){
					$urlrules=$routes['dynamic'][$module_controller_action];

					$empty_query_urlrule=array();

					foreach ($urlrules as $ur){
						$intersect=array_intersect_assoc($ur['query'], $vars);
						if($intersect){
							$vars=array_diff_key($vars,$ur['query']);
							$url= $ur['url'];
							$has_route=true;
							break;
						}
						if(empty($empty_query_urlrule) && empty($ur['query'])){
							$empty_query_urlrule=$ur;
						}
					}

					if(!empty($empty_query_urlrule)){
						$has_route=true;
						$url=$empty_query_urlrule['url'];
					}

					$new_vars=array_reverse($vars);
					foreach ($new_vars as $key =>$value){
						if(strpos($url, ":$key")!==false){
							$url=str_replace(":$key", $value, $url);
							unset($vars[$key]);
						}
					}
					$url=str_replace(array("\d","$"), "", $url);

					if($has_route){
						if(!empty($vars)) { // 添加参数
							foreach ($vars as $var => $val){
								if('' !== trim($val))   $url .= $depr . $var . $depr . urlencode($val);
							}
						}
						$url =__APP__."/".$url ;
					}
				}
			}

			$url=str_replace(array("^","$"), "", $url);

			if(!$has_route){
				$module =   defined('BIND_MODULE') ? '' : $module;
				$url    =   __APP__.'/'.implode($depr,array_reverse($var));

				if($urlCase){
					$url    =   strtolower($url);
				}

				if(!empty($vars)) { // 添加参数
					foreach ($vars as $var => $val){
						if('' !== trim($val))   $url .= $depr . $var . $depr . urlencode($val);
					}
				}
			}


			if($suffix) {
				$suffix   =  $suffix===true?C('URL_HTML_SUFFIX'):$suffix;
				if($pos = strpos($suffix, '|')){
					$suffix = substr($suffix, 0, $pos);
				}
				if($suffix && '/' != substr($url,-1)){
					$url  .=  '.'.ltrim($suffix,'.');
				}
			}
		}

		if(isset($anchor)){
			$url  .= '#'.$anchor;
		}
		if($domain) {
			$url   =  (is_ssl()?'https://':'http://').$domain.$url;
		}

		return $url;
	}
}

/**
 * URL组装 支持不同URL模式
 * @param string $url URL表达式，格式：'[模块/控制器/操作#锚点@域名]?参数1=值1&参数2=值2...'
 * @param string|array $vars 传入的参数，支持数组和字符串
 * @param string $suffix 伪静态后缀，默认为true表示获取配置值
 * @param boolean $domain 是否显示域名
 * @return string
 */
function UU($url='',$vars='',$suffix=true,$domain=false){
	return leuu($url,$vars,$suffix,$domain);
}

/**
 * 获取所有url美化规则
 * @param string $refresh 是否强制刷新
 * @return mixed|void|boolean|NULL|unknown[]|unknown
 */
function sp_get_routes($refresh=false){
	$routes=F("routes");

	if( (!empty($routes)||is_array($routes)) && !$refresh){
		return $routes;
	}
	$routes=M("Route")->where("status=1")->order("listorder asc")->select();
	$all_routes=array();
	$cache_routes=array();
	foreach ($routes as $er){
		$full_url=htmlspecialchars_decode($er['full_url']);

		// 解析URL
		$info   =  parse_url($full_url);

		$path       =   explode("/",$info['path']);
		if(count($path)!=3){//必须是完整 url
			continue;
		}

		$module=strtolower($path[0]);

		// 解析参数
		$vars = array();
		if(isset($info['query'])) { // 解析地址里面参数 合并到vars
			parse_str($info['query'],$params);
			$vars = array_merge($params,$vars);
		}

		$vars_src=$vars;

		ksort($vars);

		$path=$info['path'];

		$full_url=$path.(empty($vars)?"":"?").http_build_query($vars);

		$url=$er['url'];

		if(strpos($url,':')===false){
			$cache_routes['static'][$full_url]=$url;
		}else{
			$cache_routes['dynamic'][$path][]=array("query"=>$vars,"url"=>$url);
		}

		$all_routes[$url]=$full_url;

	}
	F("routes",$cache_routes);
	$route_dir=SITE_PATH."/data/conf/";
	if(!file_exists($route_dir)){
		mkdir($route_dir);
	}

	$route_file=$route_dir."route.php";

	file_put_contents($route_file, "<?php\treturn " . stripslashes(var_export($all_routes, true)) . ";");

	return $cache_routes;


}

/**
 * 判断是否为手机访问
 * @return  boolean
 */
function sp_is_mobile() {
	static $sp_is_mobile;

	if ( isset($sp_is_mobile) )
		return $sp_is_mobile;

	if ( empty($_SERVER['HTTP_USER_AGENT']) ) {
		$sp_is_mobile = false;
	} elseif ( strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false // many mobile devices (all iPhone, iPad, etc.)
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Silk/') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false ) {
		$sp_is_mobile = true;
	} else {
		$sp_is_mobile = false;
	}

	return $sp_is_mobile;
}

/**
 * 判断是否为微信访问
 * @return boolean
 */
function sp_is_weixin(){
	if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
		return true;
	}
	return false;
}

/**
 * 处理插件钩子
 * @param string $hook   钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hook($hook,$params=array()){
	tag($hook,$params);
}

/**
 * 处理插件钩子,只执行一个
 * @param string $hook   钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hook_one($hook,$params=array()){
	return \Think\Hook::listen_one($hook,$params);
}


/**
 * 获取插件类的类名
 * @param strng $name 插件名
 */
function sp_get_plugin_class($name){
	$class = "plugins\\{$name}\\{$name}Plugin";
	return $class;
}

/**
 * 获取插件类的配置
 * @param string $name 插件名
 * @return array
 */
function sp_get_plugin_config($name){
	$class = sp_get_plugin_class($name);
	if(class_exists($class)) {
		$plugin = new $class();
		return $plugin->getConfig();
	}else {
		return array();
	}
}

/**
 * 替代scan_dir的方法
 * @param string $pattern 检索模式 搜索模式 *.txt,*.doc; (同glog方法)
 * @param int $flags
 */
function sp_scan_dir($pattern,$flags=null){
	$files = array_map('basename',glob($pattern, $flags));
	return $files;
}

/**
 * 获取所有钩子，包括系统，应用，模板
 */
function sp_get_hooks($refresh=false){
	if(!$refresh){
		$return_hooks = F('all_hooks');
		if(!empty($return_hooks)){
			return $return_hooks;
		}
	}

	$return_hooks=array();
	$system_hooks=array(
		"url_dispatch","app_init","app_begin","app_end",
		"action_begin","action_end","module_check","path_info",
		"template_filter","view_begin","view_end","view_parse",
		"view_filter","body_start","footer","footer_end","sider","comment",'admin_home'
	);

	$app_hooks=array();

	$apps=sp_scan_dir(SPAPP."*",GLOB_ONLYDIR);
	foreach ($apps as $app){
		$hooks_file=SPAPP.$app."/hooks.php";
		if(is_file($hooks_file)){
			$hooks=include $hooks_file;
			$app_hooks=is_array($hooks)?array_merge($app_hooks,$hooks):$app_hooks;
		}
	}

	$tpl_hooks=array();

	$tpls=sp_scan_dir("themes/*",GLOB_ONLYDIR);

	foreach ($tpls as $tpl){
		$hooks_file= sp_add_template_file_suffix("themes/$tpl/hooks");
		if(file_exists_case($hooks_file)){
			$hooks=file_get_contents($hooks_file);
			$hooks=preg_replace("/[^0-9A-Za-z_-]/u", ",", $hooks);
			$hooks=explode(",", $hooks);
			$hooks=array_filter($hooks);
			$tpl_hooks=is_array($hooks)?array_merge($tpl_hooks,$hooks):$tpl_hooks;
		}
	}

	$return_hooks=array_merge($system_hooks,$app_hooks,$tpl_hooks);

	$return_hooks=array_unique($return_hooks);

	F('all_hooks',$return_hooks);
	return $return_hooks;

}


/**
 * 生成访问插件的url
 * @param string $url url 格式：插件名://控制器名/方法
 * @param array $param 参数
 */
function sp_plugin_url($url, $param = array(),$domain=false){
	$url        = parse_url($url);
	$case       = C('URL_CASE_INSENSITIVE');
	$plugin     = $case ? parse_name($url['scheme']) : $url['scheme'];
	$controller = $case ? parse_name($url['host']) : $url['host'];
	$action     = trim($case ? strtolower($url['path']) : $url['path'], '/');

	/* 解析URL带的参数 */
	if(isset($url['query'])){
		parse_str($url['query'], $query);
		$param = array_merge($query, $param);
	}

	/* 基础参数 */
	$params = array(
		'_plugin'     => $plugin,
		'_controller' => $controller,
		'_action'     => $action,
	);
	$params = array_merge($params, $param); //添加额外参数

	return U('api/plugin/execute', $params,true,$domain);
}

/**
 * 检查权限
 * @param name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
 * @param uid  int           认证用户的id
 * @param relation string    如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
 * @return boolean           通过验证返回true;失败返回false
 */
function sp_auth_check($uid,$name=null,$relation='or'){
	if(empty($uid)){
		return false;
	}

	$iauth_obj=new \Common\Lib\iAuth();
	if(empty($name)){
		$name=strtolower(MODULE_NAME."/".CONTROLLER_NAME."/".ACTION_NAME);
	}
	return $iauth_obj->check($uid, $name, $relation);
}

/**
 * 兼容之前版本的ajax的转化方法，如果你之前用参数只有两个可以不用这个转化，如有两个以上的参数请升级一下
 * @param array $data
 * @param string $info
 * @param int $status
 */
function sp_ajax_return($data,$info,$status){
	$return = array();
	$return['data'] = $data;
	$return['info'] = $info;
	$return['status'] = $status;
	$data = $return;

	return $data;
}

/**
 * 判断是否为SAE
 */
function sp_is_sae(){
	if(defined('APP_MODE') && APP_MODE=='sae'){
		return true;
	}else{
		return false;
	}
}


function sp_alpha_id($in, $to_num = false, $pad_up = 4, $passKey = null){
	$index = "aBcDeFgHiJkLmNoPqRsTuVwXyZAbCdEfGhIjKlMnOpQrStUvWxYz0123456789";
	if ($passKey !== null) {
		// Although this function's purpose is to just make the
		// ID short - and not so much secure,
		// with this patch by Simon Franz (http://blog.snaky.org/)
		// you can optionally supply a password to make it harder
		// to calculate the corresponding numeric ID

		for ($n = 0; $n<strlen($index); $n++) $i[] = substr( $index,$n ,1);

		$passhash = hash('sha256',$passKey);
		$passhash = (strlen($passhash) < strlen($index)) ? hash('sha512',$passKey) : $passhash;

		for ($n=0; $n < strlen($index); $n++) $p[] =  substr($passhash, $n ,1);

		array_multisort($p,  SORT_DESC, $i);
		$index = implode($i);
	}

	$base  = strlen($index);

	if ($to_num) {
		// Digital number  <<--  alphabet letter code
		$in  = strrev($in);
		$out = 0;
		$len = strlen($in) - 1;
		for ($t = 0; $t <= $len; $t++) {
			$bcpow = pow($base, $len - $t);
			$out   = $out + strpos($index, substr($in, $t, 1)) * $bcpow;
		}

		if (is_numeric($pad_up)) {
			$pad_up--;
			if ($pad_up > 0) $out -= pow($base, $pad_up);
		}
		$out = sprintf('%F', $out);
		$out = substr($out, 0, strpos($out, '.'));
	}else{
		// Digital number  -->>  alphabet letter code
		if (is_numeric($pad_up)) {
			$pad_up--;
			if ($pad_up > 0) $in += pow($base, $pad_up);
		}

		$out = "";
		for ($t = floor(log($in, $base)); $t >= 0; $t--) {
			$bcp = pow($base, $t);
			$a   = floor($in / $bcp) % $base;
			$out = $out . substr($index, $a, 1);
			$in  = $in - ($a * $bcp);
		}
		$out = strrev($out); // reverse
	}

	return $out;
}

/**
 * 验证码检查，验证完后销毁验证码增加安全性 ,<br>返回true验证码正确，false验证码错误
 * @return boolean <br>true：验证码正确，false：验证码错误
 */
function sp_check_verify_code($verifycode=''){
	$verifycode= empty($verifycode)?I('request.verify'):$verifycode;
	$verify = new \Think\Verify();
	return $verify->check($verifycode, "");
}

/**
 * 手机验证码检查，验证完后销毁验证码增加安全性 ,<br>返回true验证码正确，false验证码错误
 * @return boolean <br>true：手机验证码正确，false：手机验证码错误
 */
function sp_check_mobile_verify_code($mobile='',$verifycode=''){
	$session_mobile_code=session('mobile_code');
	$verifycode= empty($verifycode)?I('request.mobile_code'):$verifycode;
	$mobile= empty($mobile)?I('request.mobile'):$mobile;

	$result= false;

	if(!empty($session_mobile_code) && $session_mobile_code['code'] == md5($mobile.$verifycode.C('AUTHCODE')) && $session_mobile_code['expire_time']>time()){
		$result = true;
	}

	return $result;
}


/**
 * 执行SQL文件  sae 环境下file_get_contents() 函数好像有间歇性bug。
 * @param string $sql_path sql文件路径
 * @author 5iymt <1145769693@qq.com>
 */
function sp_execute_sql_file($sql_path) {

	$context = stream_context_create ( array (
		'http' => array (
			'timeout' => 30
		)
	) ) ;// 超时时间，单位为秒

	// 读取SQL文件
	$sql = file_get_contents ( $sql_path, 0, $context );
	$sql = str_replace ( "\r", "\n", $sql );
	$sql = explode ( ";\n", $sql );

	// 替换表前缀
	$orginal = 'sp_';
	$prefix = C ( 'DB_PREFIX' );
	$sql = str_replace ( "{$orginal}", "{$prefix}", $sql );

	// 开始安装
	foreach ( $sql as $value ) {
		$value = trim ( $value );
		if (empty ( $value )){
			continue;
		}
		$res = M ()->execute ( $value );
	}
}

/**
 * 插件R方法扩展 建立多插件之间的互相调用。提供无限可能
 * 使用方式 get_plugns_return('Chat://Index/index',array())
 * @param string $url 调用地址
 * @param array $params 调用参数
 * @author 5iymt <1145769693@qq.com>
 */
function sp_get_plugins_return($url, $params = array()){
	$url        = parse_url($url);
	$case       = C('URL_CASE_INSENSITIVE');
	$plugin     = $case ? parse_name($url['scheme']) : $url['scheme'];
	$controller = $case ? parse_name($url['host']) : $url['host'];
	$action     = trim($case ? strtolower($url['path']) : $url['path'], '/');

	/* 解析URL带的参数 */
	if(isset($url['query'])){
		parse_str($url['query'], $query);
		$params = array_merge($query, $params);
	}
	return R("plugins://{$plugin}/{$controller}/{$action}", $params);
}

/**
 * 给没有后缀的模板文件，添加后缀名
 * @param string $filename_nosuffix
 */
function sp_add_template_file_suffix($filename_nosuffix){

	if(file_exists_case($filename_nosuffix.C('TMPL_TEMPLATE_SUFFIX'))){
		$filename_nosuffix = $filename_nosuffix.C('TMPL_TEMPLATE_SUFFIX');
	}else if(file_exists_case($filename_nosuffix.".php")){
		$filename_nosuffix = $filename_nosuffix.".php";
	}else{
		$filename_nosuffix = $filename_nosuffix.C('TMPL_TEMPLATE_SUFFIX');
	}

	return $filename_nosuffix;
}

/**
 * 获取当前主题名
 * @param string $default_theme 指定的默认模板名
 * @return string
 */
function sp_get_current_theme($default_theme=''){
	$theme      =    C('SP_DEFAULT_THEME');
	if(C('TMPL_DETECT_THEME')){// 自动侦测模板主题
		$t = C('VAR_TEMPLATE');
		if (isset($_GET[$t])){
			$theme = $_GET[$t];
		}elseif(cookie('think_template')){
			$theme = cookie('think_template');
		}
	}
	$theme=empty($default_theme)?$theme:$default_theme;

	return $theme;
}

/**
 * 判断模板文件是否存在，区分大小写
 * @param string $file 模板文件路径，相对于当前模板根目录，不带模板后缀名
 */
function sp_template_file_exists($file){
	$theme= sp_get_current_theme();
	$filepath=C("SP_TMPL_PATH").$theme."/".$file;
	$tplpath = sp_add_template_file_suffix($filepath);

	if(file_exists_case($tplpath)){
		return true;
	}else{
		return false;
	}

}

/**
 *根据菜单id获得菜单的详细信息，可以整合进获取菜单数据的方法(_sp_get_menu_datas)中。
 *@param num $id  菜单id，每个菜单id
 * @author 5iymt <1145769693@qq.com>
 */
function sp_get_menu_info($id,$navdata=false){
	if(empty($id)&&$navdata){
		//若菜单id不存在，且菜单数据存在。
		$nav=$navdata;
	}else{
		$nav_obj= M("Nav");
		$id= intval($id);
		$nav= $nav_obj->where("id=$id")->find();//菜单数据
	}

	$href=htmlspecialchars_decode($nav['href']);
	$hrefold=$href;

	if(strpos($hrefold,"{")){//序列 化的数据
		$href=unserialize(stripslashes($nav['href']));
		$default_app=strtolower(C("DEFAULT_MODULE"));
		$href=strtolower(leuu($href['action'],$href['param']));
		$g=C("VAR_MODULE");
		$href=preg_replace("/\/$default_app\//", "/",$href);
		$href=preg_replace("/$g=$default_app&/", "",$href);
	}else{
		if($hrefold=="home"){
			$href=__ROOT__."/";
		}else{
			$href=$hrefold;
		}
	}
	$nav['href']=$href;
	return $nav;
}

/**
 * 判断当前的语言包，并返回语言包名
 */
function sp_check_lang(){
	$langSet = C('DEFAULT_LANG');
	if (C('LANG_SWITCH_ON',null,false)){

		$varLang =  C('VAR_LANGUAGE',null,'l');
		$langList = C('LANG_LIST',null,'zh-cn');
		// 启用了语言包功能
		// 根据是否启用自动侦测设置获取语言选择
		if (C('LANG_AUTO_DETECT',null,true)){
			if(isset($_GET[$varLang])){
				$langSet = $_GET[$varLang];// url中设置了语言变量
				cookie('think_language',$langSet,3600);
			}elseif(cookie('think_language')){// 获取上次用户的选择
				$langSet = cookie('think_language');
			}elseif(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){// 自动侦测浏览器语言
				preg_match('/^([a-z\d\-]+)/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
				$langSet = $matches[1];
				cookie('think_language',$langSet,3600);
			}
			if(false === stripos($langList,$langSet)) { // 非法语言参数
				$langSet = C('DEFAULT_LANG');
			}
		}
	}

	return strtolower($langSet);

}

/**
 * 删除图片物理路径
 * @param array $imglist 图片路径
 * @return bool 是否删除图片
 * @author 高钦 <395936482@qq.com>
 */
function sp_delete_physics_img($imglist){
	$file_path = C("UPLOADPATH");

	if ($imglist) {
		if ($imglist['thumb']) {
			$file_path = $file_path . $imglist['thumb'];
			if (file_exists($file_path)) {
				$result = @unlink($file_path);
				if ($result == false) {
					$res = TRUE;
				} else {
					$res = FALSE;
				}
			} else {
				$res = FALSE;
			}
		}

		if ($imglist['photo']) {
			foreach ($imglist['photo'] as $key => $value) {
				$file_path = C("UPLOADPATH");
				$file_path_url = $file_path . $value['url'];
				if (file_exists($file_path_url)) {
					$result = @unlink($file_path_url);
					if ($result == false) {
						$res = TRUE;
					} else {
						$res = FALSE;
					}
				} else {
					$res = FALSE;
				}
			}
		}
	} else {
		$res = FALSE;
	}

	return $res;
}

/**
 * 安全删除位于头像文件夹中的头像
 *
 * @param string $file 头像文件名,不含路径
 * @author rainfer <81818832@qq.com>
 */
function sp_delete_avatar($file)
{
	if($file){
		$file='data/upload/avatar/'.$file;
		if (\Think\Storage::has($file)) {
			\Think\Storage::unlink($file);
		}
	}
}

/**
 * 获取惟一订单号
 * @return string
 */
function sp_get_order_sn(){
	return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
}

/**
 * 获取文件扩展名
 * @param string $filename
 */
function sp_get_file_extension($filename){
	$pathinfo=pathinfo($filename);
	return strtolower($pathinfo['extension']);
}

/**
 * 检查手机是否还可以发送手机验证码,并返回生成的验证码
 * @param string $mobile
 */
function sp_get_mobile_code($mobile,$expire_time){
	if(empty($mobile)) return false;
	$mobile_code_log_model=M('MobileCodeLog');
	$current_time=time();
	$expire_time=(!empty($expire_time)&&$expire_time>$current_time) ? $expire_time:$current_time+60*30;
	$max_count=5;
	$find_log=$mobile_code_log_model->where(array('mobile'=>$mobile))->find();
	$result = false;
	if(empty($find_log)){
		$result = true;
	}else{
		$send_time=$find_log['send_time'];
		$today_start_time= strtotime(date('Y-m-d',$current_time));
		if($send_time<$today_start_time){
			$result = true;
		}else if($find_log['count']<$max_count){
			$result=true;
		}
	}

	if($result){
		$result=rand(100000,999999);
		session('mobile_code',array(
			'code'=>md5($mobile.$result.C('AUTHCODE')),
			'expire_time'=>$expire_time
		));
	}else{
		session('mobile_code',null);
	}
	return $result;
}

/**
 * 更新手机验证码发送日志
 * @param string $mobile
 */
function sp_mobile_code_log($mobile,$code,$expire_time){

	$mobile_code_log_model=M('MobileCodeLog');
	$find_log = $mobile_code_log_model->where(array('mobile'=>$mobile))->find();
	if($find_log){
		$sendtime   = strtotime(date("Y-m-d"));//当天0点
		if($find_log['send_time']<=$sendtime){
			$count =1;
		}else{
			$count = array('exp','count+1');
		}
		$result=$mobile_code_log_model->where(array('mobile'=>$mobile))->save(array('send_time'=>time(),'expire_time'=>$expire_time,'code'=>$code,'count'=>$count));
	}else{
		$result=$mobile_code_log_model->add(array('mobile'=>$mobile,'send_time'=>time(),'code'=>$code,'count'=>1,'expire_time'=>$expire_time));
	}

	return $result;
}

function multi_array_sort($multi_array,$sort_key,$sort=SORT_ASC){
	if(is_array($multi_array)){
		foreach ($multi_array as $row_array){
			if(is_array($row_array)){
				$key_array[] = $row_array[$sort_key];
			}else{
				return false;
			}
		}
	}else{
		return false;
	}
	array_multisort($key_array,$sort,$multi_array);

	$res = array();
	foreach($multi_array as $v)
	{
		if(trim($v['pinyin']) == '')
		{
			$res['other'][] = $v;
		}
		else
		{
			$res[$v['pinyin']][] = $v;
		}
	}
	return $res;
}

/**
 * 游戏下拉框
 * @param string $game_id 游戏ID
 * @param int $type 游戏权限
 * @param int $source 游戏来源
 * @param string $gm_pri_id GM权限信息
 * @param string $rebate 返利配置
 * @param string $sub 1安卓2苹果是否开启联运分包
 * @param string $group 游戏分组
 * @param string $access_type 接入类型
 * @return string
 */
function get_game_list($game_id ='',$type = 1,$source = 1,$gm_pri_id = 'all',$rebate = 'all',$sub = 0,$group='',$access_type ='all')
{
	import('PinYin');
	$py = new \PinYin();
	if($type == 1){
		$game_role = session('game_role');
	}else{
		$game_role = 'all';
	}

	$map = array('status'=>1,'is_audit'=>1);
	if($game_role != 'all')
	{
		$map['id'] = array('in',$game_role);
	}

	if($source != 'all')
	{
		$map['source'] = $source;
	}

	if($gm_pri_id !=='all')
	{
		$map['gm_pri_id'] = $gm_pri_id;
	}

	if($rebate !=='all')
	{
		$map['rebate_option'] = $rebate;
	}
	if($sub > 0)
	{
		$sub == 1 ? $map['is_union_sub'] = 1 : $map['is_union_ios_sub'] = 1;
		$map['tag'] = array('neq','');
	}

	if(!empty($group))
	{
		$map['group'] = $group;
	}

	if($access_type !='all')
	{
		$map['access_type'] = $access_type;
	}

	$games = M('game')->
	field('id,game_name')->
	where($map)->
	select();


	foreach($games as $k=>$v)
	{
		$games[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['game_name']),0,1));
	}
	$games = multi_array_sort($games,'pinyin');



	foreach($games as $k=>$v)
	{
		foreach($v as $k_item=>$v_item)
		{
			$select_str = '';
			if(is_array($game_id))
			{
				if(in_array($v_item['id'],$game_id))
				{
					$select_str = 'selected';
				}
			}
			else
			{
				if($game_id == $v_item['id'])
				{
					$select_str = 'selected';
				}
			}
			if($k_item == 0)
			{
				$html_str.="<option value='{$v_item['id']}' {$select_str} ><span>{$k}</span>&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['game_name']}</option>";
			}
			else
			{

				$html_str.="<option value='{$v_item['id']}' {$select_str}>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['game_name']}</option>";
			}
		}
	}


	return $html_str;
}
/**
 * 获取testbox(185sy)中游戏列表
 */
function get_sy_game_list($game_id ='',$type = 1,$source = 1,$gm_pri_id = 'all',$rebate = 'all',$sub = 0,$group='',$access_type ='all')
{
	import('PinYin');
	$py = new \PinYin();
	if($type == 1){
		$game_role = session('game_role');
	}else{
		$game_role = 'all';
	}

	$map = array('status'=>0,'isdisplay'=>1);
	if($game_role != 'all')
	{
		$map['id'] = array('in',$game_role);
	}

	if($source != 'all')
	{
		$map['source'] = $source;
	}

	if($gm_pri_id !=='all')
	{
		$map['gm_pri_id'] = $gm_pri_id;
	}

	if($rebate !=='all')
	{
		$map['rebate_option'] = $rebate;
	}
	if($sub > 0)
	{
		$sub == 1 ? $map['is_union_sub'] = 1 : $map['is_union_ios_sub'] = 1;
		$map['tag'] = array('neq','');
	}

	if(!empty($group))
	{
		$map['group'] = $group;
	}

	if($access_type !='all')
	{
		$map['access_type'] = $access_type;
	}

	$games = M('game','syo_',C('185DB'))->
	field('id,gamename')->
	where($map)->
	select();


	foreach($games as $k=>$v)
	{
		$games[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['gamename']),0,1));
	}
	$games = multi_array_sort($games,'pinyin');



	foreach($games as $k=>$v)
	{
		foreach($v as $k_item=>$v_item)
		{
			$select_str = '';
			if(is_array($game_id))
			{
				if(in_array($v_item['id'],$game_id))
				{
					$select_str = 'selected';
				}
			}
			else
			{
				if($game_id == $v_item['id'])
				{
					$select_str = 'selected';
				}
			}
			if($k_item == 0)
			{
				$html_str.="<option value='{$v_item['id']}' {$select_str} ><span>{$k}</span>&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['gamename']}</option>";
			}
			else
			{

				$html_str.="<option value='{$v_item['id']}' {$select_str}>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['gamename']}</option>";
			}
		}
	}


	return $html_str;
}
function get_bi_game_list($game_id ='',$type = 1){
	import('PinYin');
	$py = new \PinYin();
	if($type == 1){
		$game_role = session('game_role');
	}else{
		$game_role = 'all';
	}

	$map = array('status'=>1);
	if($game_role != 'all') {
		$map['game_id'] = array('in',$game_role);
	}

	$games = M('game',null,C('biDB'))->field('game_id id,game_name')->where($map)->select();


	foreach($games as $k=>$v) {
		$games[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['game_name']),0,1));
	}
	$games = multi_array_sort($games,'pinyin');

	foreach($games as $k=>$v) {
		foreach($v as $k_item=>$v_item) {
			$select_str = '';
			if(is_array($game_id)) {
				if(in_array($v_item['id'],$game_id)) {
					$select_str = 'selected';
				}
			}
			else {
				if($game_id == $v_item['id']) {
					$select_str = 'selected';
				}
			}
			if($k_item == 0) {
				$html_str.="<option value='{$v_item['id']}' {$select_str} ><span>{$k}</span>&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['game_name']}</option>";
			} else {
				$html_str.="<option value='{$v_item['id']}' {$select_str}>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$v_item['game_name']}</option>";
			}
		}
	}
	return $html_str;
}

function get_bi_channel_list($channel_id = ''){
	import('PinYin');
	$py = new \PinYin();
	$channel_role = session('channel_role');
	if($channel_role == 'all') {
		$channels = M('channel',null,C('biDB'))->field('channel_id id,channel_name name')->where(array('status'=>1))->select();
	} else {
		$channels = M('channel',null,C('biDB'))->field('channel_id id,channel_name name')->where(array('status'=>1,'channel_id'=>array('in',$channel_role)))->select();
	}

	foreach($channels as $v) {
		$select_str = '';
		if($channel_id == $v['id']) {
			$select_str = 'selected';
		}
		$v['name'] = trim($v['name']);
		$html_str.="<option value='{$v['id']}' {$select_str}>{$v['name']}-{$v['id']}</option>";
	}
	return $html_str;
}

/**
 * 渠道下拉框
 * @param  $channel_id(下拉框选中的channel_id 默认为空)
 * @param  $type(渠道类型)
 * @param  $parent (父元素)
 * @param $not in
 * @reutrn htmlstr
 */
function get_channel_list($channel_id = '',$type = '',$parent = '',$not_in = '')
{
	$role_id = session('ROLE_ID');
	if($role_id == 5 || $role_id == 14)
	{
		return 	'';
	}

	$channel_role = session('channel_role');
	$map = array();

	if($type!= '')
	{
		$map['type'] = $type;
	}

	if($parent !=='')
	{
		$map['parent'] = $parent;
	}


	if($not_in !=='')
	{
		$map['id'] = array('not in',$not_in);
	}

	if($channel_role == 'all')
	{
		$channels = M('channel')->field('id,name')->where(array_merge(array('status'=>1),$map))
			->select();
	}
	else
	{
		$channels = M('channel')->field('id,name')->where(array_merge(array('status'=>1,'id'=>array('in',$channel_role)),$map))->select();
	}


	$html_str = '';
	foreach($channels as $v)
	{
		$select_str = '';

		if(is_array($channel_id))
		{
			if(in_array($v['id'],$channel_id))
			{
				$select_str = 'selected';
			}
		}
		else
		{
			if($channel_id == $v['id'])
			{
				$select_str = 'selected';
			}
		}

		$v['name'] = trim($v['name']);
		$html_str.="<option value='{$v['id']}' {$select_str}>{$v['name']}-{$v['id']}</option>";
	}

	return $html_str;
}

/**
 * 生成key
 * @param $type
 * @return string
 */
function makeKey($type){
	$ABCStr = 'abcdefghijklmnopqrstuvwxyz';
	$numStr = '0123456789';
	$str = "";
	for ($i = 0; $i < 4; $i++) {
		if ($i % 2 == 0) {
			for ($j = 0; $j < 2; $j++) {
				$ABCRand = rand(0, 25);
				$str .= substr($ABCStr, $ABCRand, 1);
			}
		} else {
			for ($j = 0; $j < 2; $j++) {
				$numRand = rand(0, 9);
				$str .= substr($numStr, $numRand, 1);
			}
		}
	}
	$seStr = md5($str);
	$type == 1 ? $where['server_key'] = $seStr : $where['client_key'] = $seStr;
	$check = M('Game')->where($where)->getField('id');
	if ($check) {
		makeKey($type);
	} else {
		return $seStr;
	}
}

/**
 * 检查下载地址是否正确
 * @param $url
 * @return mixed
 */
function checkUrl($url,$type = 1){
	$curl = curl_init($url);
	if($type == 1){
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');

	}else{
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	}
	$result = curl_exec($curl);
	if($type == 1){
		$state = false;
		if($result !== false){
			$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			if($code == 200){
				$state = true;
			}
		}
	}else{
		$state = $result;
	}
	curl_close($curl);
	return $state;
}


/**
 * @param $url
 * @param array|NULL $post
 * @param array $options
 * @return mixed
 */
function curl_post($url, array $post = NULL, array $options = array()){

	$defaults = array(
		CURLOPT_POST => 1,
		CURLOPT_HEADER => 0,
		CURLOPT_URL => $url,
		CURLOPT_FRESH_CONNECT => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_FORBID_REUSE => 1,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_POSTFIELDS => http_build_query($post),
		CURLOPT_HTTPHEADER => array('Expect:')
	);
	$ch = curl_init();

	curl_setopt_array($ch, ($options + $defaults));
	if( ! $result = curl_exec($ch)) {
		@trigger_error(curl_error($ch));
	}

	curl_close($ch);

	return $result;
}

/**
 * 分包单个游戏
 * @param int $system 系统，1安卓2苹果
 * @param string $tag 游戏简写
 * @param int $cid 渠道ID
 * @param int $ver 游戏版本
 * @param int $gid 游戏ID
 * @return mixed
 */
function packOne($system,$tag,$cid,$ver,$gid,$sdk){
	$url = $system == 1 ? C('PACK_ONE_ANDROID_URL') : C('PACK_ONE_IOS_URL');
	$key = C('FUNCTION_KEY');
	$sign = md5("appid={$gid}&tag={$tag}&channel={$cid}&version={$ver}&sdk={$sdk}".$key);
	$data = array(
		'appid'   => $gid,
		'tag'     => $tag,
		'channel' => $cid,
		'version' => $ver,
		'sdk'     => $sdk,
		'sign'    => $sign
	);
	return curl_post($url,$data);
}

/**
 * 修复分包
 * @param string $tag 游戏简写
 * @param int $cid 渠道ID
 * @param int $ver 游戏版本
 * @param int $gid 游戏ID
 * @return mixed
 */
function reTryOne($tag,$cid,$ver,$gid,$sdk){
	$url = C('RETRY_ONE_IOS_URL');
	$key = C('FUNCTION_KEY');
	$sign = md5("appid={$gid}&tag={$tag}&channel={$cid}&version={$ver}&sdk={$sdk}".$key);
	$data = array(
		'appid'   => $gid,
		'tag'     => $tag,
		'channel' => $cid,
		'version' => $ver,
		'sdk'     => $sdk,
		'sign'    => $sign
	);
	return curl_post($url,$data);
}

/**
 * 批量分包
 * @param int $system 系统，1安卓2苹果
 * @param array $arr 批量请求数组
 * @return mixed
 */
function packAll($system,$arr,$type = 1){
	$packs = base64_encode(json_encode($arr));

	$url = $system == 1 ? C('PACK_ALL_ANDROID_URL') : C('PACK_ALL_IOS_URL');
	if($type != 1){
		$url = C('RETRY_ALL_IOS_URL');
	}
	$sign = md5("data={$packs}".C('FUNCTION_KEY'));
	$data = array(
		'data' => $packs,
		'sign' => $sign
	);
	return curl_post($url,$data);
}

/**
 * 查询一个包的进度
 * @param int $system 系统，1安卓2苹果
 * @param string $tag 游戏简写
 * @param int $cid 渠道ID
 * @param int $ver 游戏版本
 * @param int $gid 游戏ID
 * @return mixed
 */
function checkOne($system,$tag,$cid,$ver,$gid,$sdk){
	$url = $system == 1 ? C('CHECK_ONE_ANDROID_URL') : C('CHECK_ONE_IOS_URL');
	$key = C('FUNCTION_KEY');
	$sign = md5("appid={$gid}&tag={$tag}&channel={$cid}&version={$ver}&sdk={$sdk}".$key);
	$data = array(
		'appid'   => $gid,
		'tag'     => $tag,
		'channel' => $cid,
		'version' => $ver,
		'sdk'     => $sdk,
		'sign'    => $sign
	);
	return curl_post($url,$data);
}

/**
 * 批量查询分包进度
 * @param int $system 系统，1安卓2苹果
 * @param array $arr 请求数据
 * @return mixed
 */
function checkAll($system,$arr){
	$packs = base64_encode(json_encode($arr));
	$url = $system == 1 ? C('CHECK_ALL_ANDROID_URL') : C('CHECK_ALL_IOS_URL');
	$sign = md5("data={$packs}".C('FUNCTION_KEY'));
	$data = array(
		'data' => $packs,
		'sign' => $sign
	);
	return curl_post($url,$data);
}

/**
 * 返回打包错误信息
 * @param $error
 * @return string
 */
function getPackError($error){
	switch ($error){
		case 0:
			$str = '请求失败';
			break;
		case 2:
			$str = '参数错误';
			break;
		case 3:
			$str = '签名错误';
			break;
		case 4:
			$str = '没有分包信息';
			break;
		case 5:
			$str = '正在分包中';
			break;
		case 6:
			$str = '分包完成';
			break;
		case 7:
			$str = '分包失败';
			break;
		case 8:
			$str = '母包不存在,请上传母包';
			break;
		default:
			$str = '请求失败';
			break;
	}
	return $str;
}

/**
 * 更新缓存
 * @param $url
 * @param $op
 * @param $value
 * @param $sign
 * @return bool
 */
function updateCache($url,$op,$value,$sign){
	if(!$url || !$op || !$value || !$sign) return false;
	$data = array(
		'op'    => $op,
		'value' => $value,
		'sign'  => $sign
	);
	return curl_post($url,$data);
}

/**
 * 执行融合相关操作
 * @param $appid
 * @param $name
 * @param $clientKey
 * @param $serverKey
 * @param $type
 * @return bool|mixed
 */
function doRh($appid,$name,$clientKey,$serverKey,$type){
	if(!$appid || !$name || !$clientKey || !$serverKey) return false;
	$sign = "appid={$appid}&name={$name}&clientKey={$clientKey}&serverKey={$serverKey}".C('RH_KEY');
	$data = array(
		'appid'     => $appid,
		'name'      => $name,
		'clientKey' => $clientKey,
		'serverKey' => $serverKey,
		'sign'      => md5($sign)
	);
	$url = $type == 1 ? C('RH_ADD') : C('RH_INFO');
	return curl_post($url,$data);
}

/**
 * 签名检测
 * @param $arr
 * @param $key
 * @return bool
 */
function checkSign($arr,$key){
	$sign = $arr['sign'];
	unset($arr['sign']);
	if($arr['payMode'] == 3){
	    unset($arr['app_uid']);
    }
	$str = '';
	foreach ($arr as $k=>$v){
		$str .= $k.'='.$v.'&';
	}
	$str = md5(trim($str,'&').$key);
	if($sign != $str){
		return false;
	}else{
		return true;
	}
}

function getClientIP(){
	global $ip;
	if (getenv("HTTP_CLIENT_IP"))
		$ip = getenv("HTTP_CLIENT_IP");
	else if(getenv("HTTP_X_FORWARDED_FOR"))
		$ip = getenv("HTTP_X_FORWARDED_FOR");
	else if(getenv("REMOTE_ADDR"))
		$ip = getenv("REMOTE_ADDR");
	else $ip = "Unknow";
	return $ip;
}

/**生成随机数**/
function getRandomString($len, $chars=null)
{
	if (is_null($chars)) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	}
	mt_srand(10000000*(double)microtime());
	for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
		$str .= $chars[mt_rand(0, $lc)];
	}
	return $str;
}

/**
 * 验证中文姓名
 * @param $name
 * @return boolen
 */
function isChineseName($name){
	if (preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,15}$/', $name)) {
		return true;
	} else {
		return false;
	}
}


/**
 * 日志记录
 * @param $path
 * @param $content
 */
function logs($path,$content){
	$today = date('Y-m-d').'.log';
	if(!is_dir(PAYLOGS_PATH.$path)){
		mkdir(PAYLOGS_PATH.$path,0755);
	}
	file_put_contents(PAYLOGS_PATH.$path.'/'.$today,date('Y-m-d H:i:s',time())."\r\n".json_encode($content)."\r\n",FILE_APPEND);
}

function diylogs($path,$content){
	$today = date('Y-m-d').'.log';
	if(!is_dir(PAYLOGS_PATH.$path)){
		mkdir(PAYLOGS_PATH.$path,0755);
	}
	file_put_contents(PAYLOGS_PATH.$path.'/'.$today,date('Y-m-d H:i:s',time())."\r\n".print_r($content,true)."\r\n",FILE_APPEND);
}

/**
 * CURL GET
 */
function curl_get($url)
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($curl, CURLOPT_TIMEOUT, 500);
	curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
	curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
	curl_setopt($curl,CURLOPT_URL,$url);
	$res = curl_exec($curl);
	curl_close($curl);
	return $res;
}

/**
 * 检测用户是否是VIP
 * @param $uid
 * @return bool
 */
function checkVip($uid){
	$where['id&vip_end'] = array($uid,array('gt',time()),'_multi'=>true);

	if(M('player')->where($where)->find()){
		return true;
	}else{
		M('player')->where(array('id'=>$uid))->setField(array('vip_start'=>0,'vip_end'=>0));
		return false;
	}
}


/**
 * 验证短信
 */
function checkSMSCode($mobile,$code)
{
	$nowTimeStr = date('Y-m-d H:i:s');
	$smscode = M('smscode');
	$smscodeObj = $smscode->where("mobile='$mobile'")->order('create_time desc')->limit(1)->find();
	if($smscodeObj){
		$smsCodeTimeStr = $smscodeObj['update_time'];
		$recordCode = $smscodeObj['code'];
		$flag = checkTime($nowTimeStr, $smsCodeTimeStr);
		if(!$flag){
			return 1;
		}
		if($code != $recordCode){
			return 2;
		}
		//如果验证成功，置为失效
		$smscode->where(array('mobile'=>$mobile))->save(array('update_time'=>'1970-01-01 00:00:00'));
		return 0;
	}
	return 2;
}

/**
 * 验证短信是否过期
 */
function checkTime($nowTimeStr,$smsCodeTimeStr)
{
	//$nowTimeStr = '2016-10-15 14:39:59';
	//$smsCodeTimeStr = '2016-10-15 14:30:00';
	$nowTime = strtotime($nowTimeStr);
	$smsCodeTime = strtotime($smsCodeTimeStr);
	if(($nowTime - $smsCodeTime) > 300)
	{
		return false;
	}
	return true;

}

/**
 * 生成短信验证码
 */
function createSMSCode($length = 6)
{
	$min = pow(10 , ($length - 1));
	$max = pow(10, $length) - 1;
	return rand($min, $max);
}

function sendSms($mobile,$code){
    $resultArray = AliyunController::getInstance()->sendSms($mobile,$code);

    if(is_array($resultArray) && $resultArray['Code'] == 'OK') {
        return true;
    }
    diylogs('aliyunSms+++',$resultArray);
    return false;
}

function uc_create_username($username)
{
	$res = uc_user_checkname($username);
	if($res<0)
	{
		$username = $username.'_';
		uc_create_username($username);
	}
	return $username;
}

/**
 * 判断是否在微信浏览器打开
 */
function is_weixin()
{
	if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
		return true;
	}
	return false;
}

/**
 * 获取头像
 */
function get_avatar_url($avatar)
{

	if(isset($avatar) && !empty($avatar))
	{
		return 'http://'.$_SERVER['HTTP_HOST'].'/'.$avatar;
	}
	return 'http://'.$_SERVER['HTTP_HOST'].'/themes/simplebootx/Public/assets/images/noavatar_middle.gif';
}

/**
 * BI密码加密
 */
function bi_password($password='',$salt='')
{
	if(strlen($password)==32){
		return md5($password.$salt);
	}else{
		return md5(md5($password).$salt);
	}
}

/**
 * 生成时间规则的订单号
 * @return string
 */
function orderID($table = 'inpour'){
	$id = time().substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 9);
	if(M($table)->where(array('orderID'=>$id))->find()){
		$this->orderID();
	}
	return $id;
}

function cutStr($str,$type){
	$len = mb_strlen($str,'utf-8');
	switch ($type){
		case 'mobile':
			if($len >= 11){
				$str1 = mb_substr($str,0,3,'utf-8') . '****';
				$str2 = mb_substr($str,7,4,'utf-8');
				$res = $str1.$str2;
			}else{
				$res = mb_substr($str,-3,3,'utf-8') . '****';
			}
			break;
		case 'idCard':
			if($len == 15) $res = substr_replace($str,'******',6,6);
			if($len == 18) $res = substr_replace($str,'********',6,8);
			break;
		case 'bankCard':
			$res = substr_replace($str,'******',7,6);
			break;
		case 'alipay':
			if($len <= 5){
				$res = substr_replace($str,'****',1,4);
			}else{
				$res = substr_replace($str,'****',3,4);
			}
			break;
		case 'qq':
			if($len <= 4){
				$res = substr_replace($str,'****',1,4);
			}else{
				$res = substr_replace($str,'****',-4,4);
			}
			break;
		case 'uname':
			if($len == 2){
				$res = mb_substr($str,0,1,'utf-8') . '*';
			}elseif($len == 3){
				$first = mb_substr($str,0,1,'utf-8');
				$last = mb_substr($str,2,1,'utf-8');
				$res = $first. '*' . $last;
			}elseif($len >= 4){
				$first = mb_substr($str,0,1,'utf-8');
				$last = mb_substr($str,3,null,'utf-8');
				$res = $first. '**' . $last;
			}else{
				$res = $str;
			}
			break;
	}
	return $res;
}

function showInfo($str,$type){
	$admin_id = session('ADMIN_ID');

	$mobile_enabled = M('users')->where(Array('id'=>$admin_id))->getfield('mobile_enabled');

	if($admin_id != 1 && $mobile_enabled ==0){
		$res = cutStr($str,$type);
	}else{
		$res = $str;
	}
	return $res;
}

function i_array_column($input, $columnKey, $indexKey=null){
	if(!function_exists('array_column')){
		$columnKeyIsNumber  = (is_numeric($columnKey))?true:false;
		$indexKeyIsNull            = (is_null($indexKey))?true :false;
		$indexKeyIsNumber     = (is_numeric($indexKey))?true:false;
		$result                         = array();
		foreach((array)$input as $key=>$row){
			if($columnKeyIsNumber){
				$tmp= array_slice($row, $columnKey, 1);
				$tmp= (is_array($tmp) && !empty($tmp))?current($tmp):null;
			}else{
				$tmp= isset($row[$columnKey])?$row[$columnKey]:null;
			}
			if(!$indexKeyIsNull){
				if($indexKeyIsNumber){
					$key = array_slice($row, $indexKey, 1);
					$key = (is_array($key) && !empty($key))?current($key):null;
					$key = is_null($key)?0:$key;
				}else{
					$key = isset($row[$indexKey])?$row[$indexKey]:0;
				}
			}
			$result[$key] = $tmp;
		}
		return $result;
	}else{
		return array_column($input, $columnKey, $indexKey);
	}
}
/**
 * 获取用户老司机指数
 */
function user_driver_level($uid)
{
	$fans_counts = M('player_info')->where(array('uid'=>$uid))->getfield('fans_counts');
	$fans_counts = $fans_counts?$fans_counts:0;
	$driver_level = C('DRIVER_LEVEL');
	foreach($driver_level as $v)
	{
		$range = explode('-',$v['fans_counts']);
		if($fans_counts >= $range[0] && $fans_counts <= $range[1])
		{
			return $v['level'];
		}
	}
}
/**
 * 二维数组全值比较去重
 */
function array_unique_fb($arr){
	foreach ($arr as $v){
		$v = join(',',$v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
		$temp[]=$v;
	}
	$temp = array_unique($temp); //去掉重复的字符串,也就是重复的一维数组
	$data = array_values(array_intersect_key ($arr,$temp));
	return $data;
}

/**
 * 二维数组排序
 * @param $multi_array-排序的数组
 * @param $sort_key-排序的字段
 * @param int $sort-排序规则
 * @return bool
 */
function array_sort_td($multi_array,$sort_key,$sort=SORT_ASC){
	if(is_array($multi_array)){
		foreach ($multi_array as $row_array){
			if(is_array($row_array)){
				$key_array[] = $row_array[$sort_key];
			}else{
				return false;
			}
		}
	}else{
		return false;
	}
	array_multisort($key_array,$sort,$multi_array);
	return $multi_array;
}

function arraySort($arr, $keys, $type = 'asc') {
    $keysvalue = $new_array = array();
    foreach ($arr as $k => $v){
        $keysvalue[$k] = $v[$keys];
    }
    $type == 'asc' ? asort($keysvalue) : arsort($keysvalue);
    reset($keysvalue);
    foreach ($keysvalue as $k => $v) {
        $new_array[$k] = $arr[$k];
    }
    return $new_array;
}

function get_redis()
{
	$redis = new \Redis();
	$redis->connect(C('REDIS_HOST'),6379);
	$redis->auth(C('REDIS_PASS'));
	return $redis;
}

/**
 * 获取185游戏信息
 * @param $appid sdk游戏ID
 * @return array or null
 */
function get_185_gameinfo($tag,$type = 1){
    $game_model = M('game g','syo_',C('185DB'));
//    $game_info  = $game_model->where(array('tag'=>$tag))->find();
    if($type == 1){
        $where['tag'] = $tag;
    }else{
        $where['id'] = $tag;
    }
    $game_info = $game_model
        ->field('g.*,GROUP_CONCAT(l.tid) tid')
        ->join('left join __GAME_LINK_TYPE__ l on l.gid=g.id')
        ->where($where)
        ->find();

	return $game_info;
}



/**
 * 用户名、邮箱、手机账号中间字符串以*隐藏
 */
function hideStar($str) {
	if (strpos($str, '@')) {
		$email_array = explode("@", $str);
		$prevfix = (strlen($email_array[0]) < 4) ? "" : substr($str, 0, 3); //邮箱前缀
		$count = 0;
		$str = preg_replace('/([\d\w+_-]{0,100})@/', '***@', $str, -1, $count);
		$rs = $prevfix . $str;
	} else {
		$pattern = '/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i';
		if (preg_match($pattern, $str)) {
			$rs = preg_replace($pattern, '$1****$2', $str); // substr_replace($name,'****',3,4);
		} else {
			$rs = substr($str, 0, 3) . "***" . substr($str, -1);
		}
	}
	return $rs;
}

/**
 * 推广奖金计算
 * @params $count 达标数量
 * @return int
 */
function tg_bonus($count)
{
	$tg_qualitied_config = C('TG_QUALIFIED');
	$res = $count - $tg_qualitied_config['qualified_counts'];
	if($res > 0)
	{
		if($res>=$tg_qualitied_config['bonus_top_counts'])
		{
			return $tg_qualitied_config['bonus_top_counts']*$tg_qualitied_config['bonus'];
		}
		return $res*$tg_qualitied_config['bonus'];

	}
	return 0;

}

/**
 * 获取推广本月流水数据
 * @param $channel
 */

function get_tg_data($channel)
{
	$channel_model = M('channel');
	$start_day = strtotime(date('Y-m-01'));

	$parent = $channel_model->where(array('id'=>$channel))->getfield('parent');

	if($parent!=0)
	{
		$map = array();
		$map['create_time'] = array('egt',$start_day);
		$map['status'] = 1;

		$ztg_channel = $channel_model->
		where(array('type'=>2,'status'=>1,'parent'=>array('neq',0)))->
		getfield('id',true);

		$map['cid'] = array('in',implode(',',$ztg_channel));

		$month_rank_new = M('inpour')->
		where($map)->
		group('cid')->
		cache(true)->
		getfield('cid,sum(getmoney) money',true);


		$list = array();
		foreach($ztg_channel as $v)
		{
			$item = array();
			$item['cid'] = $v;
			$item['money'] = (isset($month_rank_new[$v])?$month_rank_new[$v]:0);
			$list[] = $item;
		}

		$new_order = array();
		foreach($list as $k=>$v)
		{
			$new_order[$k] = $v['money'];
		}
		array_multisort($new_order, SORT_DESC, $list);

		foreach($list as $k=>$v)
		{
			if($v['cid'] == $channel)
			{
				$curr_channel = array(
					'rank'=>$k+1,
					'money'=>$v['money']
				);
				break;
			}
		}

		$yg_rules = ($curr_channel['rank']<=10)?C('ZTG_TOP_YG'):C('ZTG_YG');
		$comission = 0;
		$ratio = '0%';

		if($curr_channel['money'] > $yg_rules['start'])
		{
			foreach($yg_rules['range'] as $range)
			{
				$money_range = explode('-',$range['money']);

				if($curr_channel['money']>=$money_range[0])
				{
					if($money_range[1] != '')
					{
						if($curr_channel['money'] > $money_range[1])
						{
							continue;
						}
					}
					$comission = $range['commision'];
					break;
				}
			}

			$ratio = $comission;

			$comission = ($curr_channel['money'] - $yg_rules['start'])*$comission*0.01;
		}

		$curr_channel['commision'] = sprintf("%.2f",$comission);
		$curr_channel['ratio'] = $ratio;

	}
	else
	{
		$map = array();
		$map['create_time'] = array('egt',$start_day);
		$map['status'] = 1;
		$map['cid'] = $channel;
		$money = M('inpour')
			->where($map)
			->getfield('sum(getmoney) money');

		$map = array();
		$map['type'] = 1;
		$map['status'] = 1;
		$map['created'] = array('egt',$start_day);
		$map['channel'] = $channel;
		$curr_channel['money'] = $money;
	}

	$tg_level = get_tg_level($curr_channel['money']);
	$curr_channel['level_name'] = $tg_level['name'];
	$curr_channel['color'] = $tg_level['color'];
	$curr_channel['level_bonus'] = $tg_level['bonus'];
	return $curr_channel;

}

/**
 * 计算军衔（通过比例计算军衔）
 * @param $moeny
 */
function get_tg_level($money)
{
	$v_plan = C('V_PLAN');
	foreach($v_plan['stage'] as $k=>$v)
	{
		if($money >= $v['mark'])
		{
			if(isset($v_plan['stage'][$k+1]) && $money >= $v_plan['stage'][$k+1]['mark'])
			{
				continue;
			}
		}
		return $v;
	}
}

/**
 * 推广渠道达标奖励
 */
function spread_reward($data,$time){
	if(empty($data)){
		return;
	}
	M('tg_qualified_info')->where(array('_string'=>"to_days(from_unixtime(create_time)) = to_days({$time})"))->delete();
	$item = array();
	$add = array();
	foreach($data as $k=>$v){
		if($v['status'] == 1){
			if(array_key_exists($v['channel'],$item)){
				++$item[$v['channel']];
			}else{
				$item[$v['channel']] = 1;
			}
		}
	}
	$conf = C('TG_QUALIFIED');
	if(count($item) > 0){
		foreach($item as $k=>$v){
			if($v > $conf['qualified_counts']){
				$sub = $v - $conf['qualified_counts'];
				$num = $sub < 10 ? $sub : 10;
				$bonus = $num * $conf['bonus'];
			}else{
				$bonus = 0;
			}
			$add[] = array(
				'channel' => $k,
				'tg_qualified_counts' => $v,
				'tg_qualified_bonus' => $bonus,
				'create_time' => strtotime($time)
			);
		}
		if(count($add) > 0){
			M('tg_qualified_info')->addAll($add);
		}
	}
}

/**
 * 当天推广明细
 */
function getTodayData(){
	$time = date('Ymd');
	$table = 'bt_report_data'.$time;

	M('spread_detail')->where(array('_string'=>"to_days(from_unixtime(createTime)) = to_days({$time})"))->delete();
	M('blacklist')->where(array('_string'=>"to_days(from_unixtime(createTime)) = to_days({$time})"))->delete();
	$model = new \Think\Model;
	$sql = "SELECT `channel`,`appid`,a.`deviceID`,`ip`,`userID`,`serverID`,`serverName`,`roleID`,`roleName`,a.roleLevel,`money`,`regTime`
                FROM {$table} a join (select id,max(roleLevel) roleLevel,deviceID from
                (select * from {$table}  WHERE ( to_days(from_unixtime(regTime)) = to_days({$time}) )
                order by roleLevel desc) c group by c.deviceID) b on a.id=b.id GROUP BY a.ip";
	$data = $model->query($sql);

	if($data){
		$black = array();
		$spread = array();
		foreach($data as $k=>$v){
			$status = 0;
			$remark = '';

			$level = M('game')->where(array('id'=>$v['appid']))->getField('reach_level');

			if($v['roleLevel'] < $level){
				$remark = '等级未达标';
			}

			$res = M('blacklist')->where(array('channel'=>$v['channel'],'deviceID'=>$v['deviceID']))->find();

			if($res){
				$remark = '设备号重复';
			}else{
				$black[] = array('channel'=>$v['channel'],'deviceID'=>$v['deviceID'],'createTime'=>strtotime($time));
			}


			//满足达标条件
			if(empty($res) && $v['roleLevel'] >= $level){
				$status = 1;
			}
			$spread[] = array(
				'channel'    => $v['channel'],
				'appid'      => $v['appid'],
				'uid'        => $v['userID'],
				'deviceID'   => $v['deviceID'],
				'ip'         => $v['ip'],
				'serverID'   => $v['serverID'],
				'serverName' => $v['serverName'],
				'roleID'     => $v['roleID'],
				'roleName'   => $v['roleName'],
				'reachLevel' => $level,
				'todayLevel' => $v['roleLevel'],
				'money'      => $v['money'],
				'regTime'    => $v['regTime'],
				'status'     => $status,
				'remark'     => $remark,
				'createTime' => strtotime($time)
			);
		}

		M('spread_detail')->addAll($spread);
		spread_reward($spread,$time);
		if(count($black) > 1){
			M('blacklist')->addAll($black);
		}
	}
}
/**
 * 推广渠道时间条件内要满足的注册数
 * @param $channel-渠道，多个渠道用逗号分隔
 * @param $date-查询时间，月或天,格式2018-7-1，不论查月或天时间都要精确到天否则数据不准确
 * @param $type-时间类型，1-月，2-天
 */
function getRegsNum($channel,$date,$type){
	if(!$channel) return false;
	$conf = C('REG_COUNT');
	$arr = explode(',',$channel);
	$cid = array();
	foreach ($arr as $k=>$v){
		$info = M('channel c')
			->field('c.off_days,u.effective')
			->join('join __USERS__ u on u.id=c.admin_id')
			->where(array('c.id'=>$v))
			->find();

		//取整天
		$effective = date('Y-m-d',$info['effective']);
		//当月有多少天
		$t = date('t',strtotime($date));
		if($info){
			//查询时间距生效时间相差多少天
			if($type == 1){
				$last = date('Y-m',strtotime($effective)) == date('Y-m',strtotime($date)) ? $t : '1';
				$days = ceil((strtotime(date('Y-m',strtotime($date)).'-'.$last) - strtotime($effective)) / 86400);
			}else{
				$days = ceil((strtotime($date) - strtotime($effective))/86400 + 1);
			}
//dump($days);die;
			//如果生效时间大于当前时间
			if($info['effective'] > time() || $days < 0){
				$cid[] = array(
					'cid' => $v,
					'nums' => 0
				);
				continue;
			}

			//大于30天计算按老员工，小于按新员工
			if($days > 30){
				if($type == 1){
					//如果是月
					$cid[] = array(
						'cid' => $v,
						'nums' => getWorkDay($date,$info['off_days']) * $conf[2]
					);
				}else{
					//如果是天
					$weekday = date('w',strtotime($date));
					if($weekday == 6 || $weekday == 0){
						if($weekday == 0){
							$cid[] = array(
								'cid' => $v,
								'nums' => 0
							);
						}else{
							$cid[] = array(
								'cid' => $v,
								'nums' => $info['off_days'] == 1 ? $conf[2] : 0
							);
						}
					}else{
						$cid[] = array(
							'cid' => $v,
							'nums' => $conf[2]
						);
					}
				}
			}else{
				if($type == 1){
					//如果查询月是本月
					if(date('Y-m',strtotime($effective)) == date('Y-m',strtotime($date))){
						$startDay = date('d',strtotime("{$effective} +7 day"));
						//并且用户生效时间是1号或当月有31天
						if(date('d',strtotime($effective)) == 1 || date('t',strtotime($date)) == 31){

							$nums1 = getWorkDay($date,$info['off_days'],$startDay,30) * $conf[1];
							if(date('d',strtotime($effective)) != 1){
								$nums2 = 0;
							}else{
								$weekday = date('w',strtotime(date('Y-m',strtotime($date)).'-31'));
								if($weekday == 6 || $weekday == 0){
									$nums2 = $info['off_days'] == 1 ? $conf[1] : 0;
								}else{
									$nums2 = $conf[2];
								}
							}

							$cid[] = array(
								'cid' => $v,
								'nums' => $nums1 + $nums2
							);
						}else{
							$cid[] = array(
								'cid' => $v,
								'nums' => getWorkDay($date,$info['off_days'],$startDay) * $conf[1]
							);
						}
					}else{
						$sub = date('Y-m-d',strtotime("{$effective} +6 day"));
						if(date('Y-m',strtotime($sub)) == date('Y-m',strtotime($effective))){
							//用户当月剩余新人规则天数
							$thisDays = date('t',strtotime($effective)) - date('d',strtotime($sub));
							$newDays = 23 - $thisDays;
							$newrule = getWorkDay($date,$info['off_days'],1,$newDays) * $conf[1];
							$oldrule = getWorkDay($date,$info['off_days'],$newDays + 1) * $conf[2];

						}else{
							$thisDays = 30-(7-date('d',strtotime($sub)));
							$newrule = getWorkDay($date,$info['off_days'],date('d',strtotime($sub)) + 1,$thisDays) * $conf[1];
							$oldrule = getWorkDay($date,$info['off_days'],$thisDays + 1) * $conf[2];

						}
						$cid[] = array(
							'cid' => $v,
							'nums' => $newrule + $oldrule
						);
					}
				}else{
					if($days <= 7){
						$cid[] = array(
							'cid' => $v,
							'nums' => 0
						);
					}else{
						$weekday = date('w',strtotime($date));
						if($weekday == 6 || $weekday == 0){
							if($weekday == 0){
								$cid[] = array(
									'cid' => $v,
									'nums' => 0
								);
							}else{
								$cid[] = array(
									'cid' => $v,
									'nums' => $info['off_days'] == 1 ? $conf[1] : 0
								);
							}
						}else{
							$cid[] = array(
								'cid' => $v,
								'nums' => $conf[1]
							);
						}
					}
				}
			}
		}
	}
	return $cid;
}

/**
 * 获取每月工作日
 */
function getWorkDay($date,$off_days,$start = 1,$total = 0){
	$month = date('Y-m',strtotime($date));
	$days = $total > 0 ? $total : date('t',strtotime($date));
	$count = 0;
	$arr = array();
	for ($i = $start;$i <= $days;$i++){
		$day = date('w',strtotime($month.'-'.$i));

		if($day == 6 || $day == 0){
			$arr[] = $day;
		}else{
			$count++;
		}
	}

	//$off_days为1单休，为2双休
	if($off_days == 1){
		foreach($arr as $k=>$v){
			if($v == 0){
				unset($arr[$k]);
			}
		}
		$count = $count + count($arr);
	}
	return $count;
}

function get_channel_task($channel,$date_time,$type=1)
{
	$info = M('channel c')
		->join('left join __USERS__ u on u.id=c.admin_id')
		->where(array('c.id'=>array('in',$channel)))
		->getfield('c.id,c.off_days,u.effective',true);


	$res = array();
	if($type == 1)
	{
		$date_time = strtotime($date_time.' 00:00:00');
	}
	else
	{
		$month_start = strtotime($date_time.'-01 00:00:00');
		$month_end = strtotime('+1 month',strtotime($date_time.'-01 00:00:00'))-3600*24;
	}

	foreach($info as $v)
	{
		$item = array();
		$item['cid'] = $v['id'];
		if($type == 1)
		{

			$item['task'] = count_day_task(strtotime(date('Y-m-d',$v['effective']).' 00:00:00'),$date_time,$v['off_days']);
		}
		else
		{
			$item['task'] = count_task(strtotime(date('Y-m-d',$v['effective']).' 00:00:00'),$month_start,$month_end,$v['off_days']);
		}

		$res[] =$item;
	}
	return $res;
}

function count_task($start_time,$month_start,$month_end,$weekend_days)
{
	if(!$start_time)
	{
		return 0 ;
	}

	$level1_start = $start_time+7*3600*24;
	$level1_end = $start_time+29*3600*24;
	$level2_start = $start_time+30*3600*24;

	if($level2_start<=$month_start)
	{
		$res = array(
			'level2_start'=>date('Y-m-d',$month_start),
			'level2_end'=>date('Y-m-d',$month_end),
		);
	}
	else
	{
		if($level1_start > $month_end)
		{
			return 0;
		}

		$level1_start = ($month_start >= $level1_start)?$month_start:$level1_start;
		$level1_end = ($month_end>=$level1_end)?$level1_end:$month_end;

		if($level1_end<$month_end)
		{
			$level2_start = $level1_end+3600*24;
			$level2_end = $month_end;
			$res = array(
				'level1_start'=>date('Y-m-d',$level1_start),
				'level1_end'=>date('Y-m-d',$level1_end),
				'level2_start'=>date('Y-m-d',$level2_start),
				'level2_end'=>date('Y-m-d',$level2_end),
			);
		}
		else
		{
			$res = array(
				'level1_start'=>date('Y-m-d',$level1_start),
				'level1_end'=>date('Y-m-d',$level1_end),
			);
		}

	}

	$res =  (isset($res['level1_start'])?(get_weekend_days($res['level1_start'],$res['level1_end'],$weekend_days)*5):0)
		+(isset($res['level2_start'])?(get_weekend_days($res['level2_start'],$res['level2_end'],$weekend_days)*10):0);
	return $res;

}

function count_day_task($start_time,$date_time,$weekend_days=1)
{
	if(!$start_time)
	{
		return 0 ;
	}

	$week_day = date('w', $date_time);
	if($weekend_days == 1)
	{
		if($week_day == 0)
		{
			return 0;
		}
	}
	else
	{
		if($week_day == 0 ||$week_day == 6 )
		{
			return 0;
		}
	}
	$sub = ($date_time-$start_time)/(3600*24);
	if($sub >= 7)
	{
		if($sub >= 30)
		{
			return 10;
		}
		return 5;
	}
	return 0;
}

function get_weekend_days($start_date, $end_date, $weekend_days=1)
{
	if(!$start_date && !$end_date)
	{
		return 0;
	}
	$data = array();
	if (strtotime($start_date) > strtotime($end_date)) list($start_date, $end_date) = array($end_date, $start_date);

	$start_reduce = $end_add = 0;
	$start_N = date('N', strtotime($start_date));
	$start_reduce = ($start_N == 7) ? 1 : 0;

	$end_N = date('N', strtotime($end_date));

	// 进行单、双休判断，默认按单休计算
	$weekend_days = intval($weekend_days);
	switch ($weekend_days) {
		case 2:
			in_array($end_N, array(6, 7)) && $end_add = ($end_N == 7) ? 2 : 1;
			break;
		case 1:
		default:
			$end_add = ($end_N == 7) ? 1 : 0;
			break;
	}

	$days = round(abs(strtotime($end_date) - strtotime($start_date)) / 86400) + 1;
	$data['total_days'] = $days;
	$data['total_relax'] = floor(($days + $start_N - 1 - $end_N) / 7) * $weekend_days - $start_reduce + $end_add;
	return $data['total_days']-$data['total_relax'];
}

function get_performance_deduct($valid_new_users,$task)
{
	$sub = $task - $valid_new_users;
	if($sub > 0)
	{
		return -($sub*C('TG_ACHIEVEMENTS'));
	}
	return 0;
}


//验证身份证是否有效
function validateIDCard($IDCard) {
	if (strlen($IDCard) == 18) {
		return check18IDCard($IDCard);
	} elseif ((strlen($IDCard) == 15)) {
		$IDCard = convertIDCard15to18($IDCard);
		return check18IDCard($IDCard);
	} else {
		return false;
	}
}

function doValidate($IDCard, $realName) {
    validateIDCardReal($IDCard, $realName);
}


// 验证身份证是否有效
function validateIDCardReal($IDCard, $realName) {
    $host = "https://checkid.market.alicloudapi.com";
    $path = "/IDCard";
    $method = "GET";
    $appcode = "583d11c3eea249c5adecc5e7822d0ebb";
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    $name = $realName;
    $idcard = $IDCard;
    $querys = "idCard=".$idcard."&name=".$name;
    echo $querys;
    $bodys = "";
    $url = $host . $path . "?" . $querys;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    //curl_setopt($curl, CURLOPT_HEADER, true); 如不输出json, 请打开这行代码，打印调试头部状态码。
    //状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    $out_put = curl_exec($curl);
    $ret = json_decode($out_put, true);
    if ($out_put && $ret) {
        if($ret["status"] == '01' || $ret["status"] == '403'){
            return true;
        }
    }
    return false;
}

//计算身份证的最后一位验证码,根据国家标准GB 11643-1999
function calcIDCardCode($IDCardBody) {
	if (strlen($IDCardBody) != 17) {
		return false;
	}

	//加权因子
	$factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
	//校验码对应值
	$code = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
	$checksum = 0;

	for ($i = 0; $i < strlen($IDCardBody); $i++) {
		$checksum += substr($IDCardBody, $i, 1) * $factor[$i];
	}

	return $code[$checksum % 11];
}

// 将15位身份证升级到18位
function convertIDCard15to18($IDCard) {
	if (strlen($IDCard) != 15) {
		return false;
	} else {
		// 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
		if (array_search(substr($IDCard, 12, 3), array('996', '997', '998', '999')) !== false) {
			$IDCard = substr($IDCard, 0, 6) . '18' . substr($IDCard, 6, 9);
		} else {
			$IDCard = substr($IDCard, 0, 6) . '19' . substr($IDCard, 6, 9);
		}
	}
	$IDCard = $IDCard . calcIDCardCode($IDCard);
	return $IDCard;
}

// 18位身份证校验码有效性检查
function check18IDCard($IDCard) {
	if (strlen($IDCard) != 18) {
		return false;
	}

	$IDCardBody = substr($IDCard, 0, 17); //身份证主体
	$IDCardCode = strtoupper(substr($IDCard, 17, 1)); //身份证最后一位的验证码

	if (calcIDCardCode($IDCardBody) != $IDCardCode) {
		return false;
	} else {
		return true;
	}
}

function getRoleId($uid){
	$id = M('role_user')->where(array('user_id'=>$uid))->getField('role_id');
	return $id;
}

function get_sdk_appid($gameid)
{
	$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$gameid))->field('tag')->find();
	if(!$game_185_info)
	{
		return 0;
	}

	$game_model = M('game');
	$appid = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');

	return $appid;
}

/**
 * 后台管理员通知消息
 * @param $type 消息类型 1 发车 2 账号买卖  3 玩家工单 4 渠道工单 5游戏问答
 * @param $id 关联ID
 * @param $kefus 客服
 * @param $link 相关链接
 * @param $appid  应用ID
 */
function create_admin_message($type,$id,$kefus,$link,$appid = '')
{

	if($kefus =='all')
	{
		$map = array('a.user_type'=>1,'b.role_id'=>3);
		if($appid)
		{
			$game_group = M('game')->where(array('id'=>$appid))->getfield('group');
			$map['a.game_group'] = $game_group;
		}

		$kefus = M('users')
			->alias('a')
			->join('bt_role_user b ON a.id = b.user_id')
			->where($map)
			->getfield('id',true);
	}
	else
	{
		$kefus = explode(',',$kefus);
	}

	//$kefus = array(5833);

	switch ($type)
	{
		case 1:
			$word = '发车';
			break;
		case 2:
			$word = '账号买卖';
			break;
		case 3:
			$word = '玩家工单';
			break;
		case 4:
			$word = '渠道工单';
			break;
		default:
			$word = '游戏问答';
	}

	$redis = get_redis();
	//如果redis开启则执行 不开启不影响其他逻辑
	if($redis)
	{
		$data = array();
		$data['type'] = $type;
		$data['id'] = $id;
		$data['message'] = $word.'功能有需要处理的新消息';
		$data['link'] = $link;
		$data['create_time'] = time();
		$data = json_encode($data);

		foreach($kefus as $kefu)
		{
			$redis->rpush('ADMIN_MESSAGE_'.$kefu,$data);
		}
	}
}

function check_system()
{
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
		return 2;
	}else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
		return 1;
	}else{
		return 0;
	}
}

/**
 * 上报维度统计
 * @param $channel 渠道ID
 * @param $appid 游戏ID
 * @param $android_id 安卓ID
 * @param $imei 设备ID
 * @param $event_type 1 激活 2 注册 3 付费
 * @param $money 付费金额
 * @param $currency 金额类型
 * @param $system 默认为1
 */
function postback_videoads($channel,$appid,$android_id,$imei,$event_type,$money = 0,$currency = 'RMB',$system =1)
{
	if(!empty($android_id) || !empty($imei))
	{
		$static_conf_field = ($system == 1)?'type,android_key as `key`':'type,ios_key as `key`';

		$system_field = ($system == 1)?'android_key':'ios_key';

		//查询统计配置
		$static_conf_model = M('static_conf');

		$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>$appid,'channel'=>$channel,$system_field=>array('neq','')))->select();

		if(!empty($static_conf_info))
		{
			$static_type = $static_conf_info;
		}
		else
		{
			$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>$appid,'channel'=>0,$system_field=>array('neq','')))->select();
			if(!empty($static_conf_info))
			{
				$static_type = $static_conf_info;
			}
			else
			{
				$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>0,'channel'=>$channel,$system_field=>array('neq','')))->select();
				if(!empty($static_conf_info))
				{
					$static_type = $static_conf_info;
				}
				else
				{
					$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>0,'channel'=>0,$system_field=>array('neq','')))->select();
					if(!empty($static_conf_info))
					{
						$static_type = $static_conf_info;
					}
					else
					{
						$static_type = array();
					}
				}
			}
		}

		foreach($static_type as $value)
		{
			if($value['type'] == 3)
			{

				//如果该渠道和游戏启动了维度统计 进行上报
				//匹配click_id
				$map['_logic'] = 'or';
				if(!empty($android_id))
				{
					$map['android_id'] = $android_id;
					$map['androididmd5'] = md5($android_id);
				}
				if(!empty($imei))
				{
					$map['imei'] = $imei;
				}

				$click_id = M('vidoads_list')->where($map)->order('create_time desc')->limit(1)->getfield('click_id');
				//匹配到click_id 进行上报，构建post_data数组
				if($click_id)
				{
					//产生回调过后 永久保存click_id
					M('vidoads_list')->where(array('click_id'=>$click_id))->setfield(array('permanent'=>1));

					$data = array();
					$data['ref_id'] =$click_id;
					if($event_type == 2)
					{
						$data['event_name'] = 'vp_registeration';
					}
					elseif($event_type == 3)
					{
						$data['event_name'] = 'vp_purchase';
						$data['currency'] = $currency;
						$data['revenue'] = $money;
					}
					$url = C('VIDEOADS_URL').'?';
					foreach($data as $k=>$v)
					{
						$url.="{$k}={$v}&";
					}

					$url = trim($url,'&');
					if(!is_dir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time())))
					{
						mkdir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time()),0777);
					}
					$file_name = SITE_PATH."data/log/bisdk/".date('Y-m-d',time())."/vidos.log";

					$log = date('Y-m-d H:i:s',time())."\r\n".$url."\r\n\r\n";
					file_put_contents($file_name,$log,FILE_APPEND);
					curl_get($url);
				}

			}
		}
	}

}


function freesub_channel($channel)
{
	return urlsafe_b64encode(C('FREESUB_KEY').'_'.$channel.'_sy99');
}

/**
 * 获取用户狂人标签
 * @param $uid
 */
function get_crazy_label($uids)
{
	$data = array();
	if(empty($uids))
	{
		return $data;
	}

	//开车记录
	$drive = M('dynamics')->where(array('uid'=>array('in',$uids),'audit'=>1,'status'=>0))->group('uid')->cache(true)->getfield('uid,count(*)',true);
	//点评记录
	$comment = M('comment')->where(array('uid'=>array('in',$uids),'status'=>1,'comment_type'=>2,'order'=>1))->group('uid')->cache(true)->getfield('uid,count(*)',true);
	//助人记录
	$help = M('consult_info')->where(array('uid'=>array('in',$uids),'audit'=>1))->group('uid')->cache(true)->getfield('uid,count(*)',true);
	//签到记录
	$signin = M('sign_log')->where(array('uid'=>array('in',$uids)))->group('uid')->cache(true)->getfield('uid,count(*)',true);

	$uids = explode(',',$uids);

	foreach($uids as $uid)
	{
        $data[$uid]['driveLevel'] = (int)crazy_level($drive[$uid],4);
        $data[$uid]['commentLevel'] = (int)crazy_level($comment[$uid],3);
        $data[$uid]['helpLevel'] = (int)crazy_level($help[$uid],2);
        $data[$uid]['signLevel'] = (int)crazy_level($signin[$uid],1);
	}

	return $data;

}

/**
 * 确定狂人等级
 * @param $count 统计数
 * @param $type 1签到狂，2助人狂，3点评狂，4开车狂
 * @return mixed
 */
function crazy_level($count,$type){
	if($count == 0) return intval($count);
	switch ($type){
		case 1: $conf = C('CRAZY_MAN.signIn');break;
		case 2: $conf = C('CRAZY_MAN.help');break;
		case 3: $conf = C('CRAZY_MAN.comment');break;
		case 4: $conf = C('CRAZY_MAN.drive');break;
	}
	foreach ($conf as $k=>$v){
        if($k < 9){
            if($count >= $v && $count < $conf[$k + 1]){
                $level = $k;
                break;
            }elseif($count < $v){
                $level = 0;
                break;
            }else{
                continue;
            }

        }else{
            $level = 9;
        }
	}
	return $level;
}

/**
 * base 64 url安全加密
 * @param $string
 * @return mixed|string
 */
function urlsafe_b64encode($string)
{
	$data = base64_encode($string);
	$data = str_replace(array('+','/','='),array('-','_',''),$data);
	return $data;
}

/**
 * base 64 url安全解密
 * @param $string
 * @return mixed|string
 */
function urlsafe_b64decode($string)
{
	$data = str_replace(array('-','_'),array('+','/'),$string);
	$mod4 = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	return $data;
}

/**
 * 上传token
 * @param $username
 * @param $password
 */
function upload_token($username,$password)
{
	$password = md5($password);
	$sign = md5("username={$username}&password={$password}&key=dcdf133425c5c9510646b131c2952577");
	$url = "http://download.xiyou7.com/upload/token?username={$username}&password={$password}&sign={$sign}";
	dump(curl_get($url));die;

}

/**
 * 获得游戏类型
 */
function getTypes($tid,$types){
    $tids = explode(",",$tid);
    $tag = '';
    if(count($tids) > 1){
        foreach ($tids as $v1){
            foreach($types as $v2){
                if($v2['id'] == $v1){
                    $tag .= $v2['name'].',';
                }
            }
        }
        $tag = rtrim($tag,",");
    }else{
        foreach($types as $k=>$v){
            if($v['id'] == $tid){
                $tag = $v['name'];
            }
        }
    }
    return $tag;
}

// 通过身份证号 获取年龄
function getAgeByIdcard($code){
	$age_time = strtotime(substr($code, 6, 8));
    if($age_time === false){
        return false;
	}
    list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age_time)); 

    $now_time = strtotime("now");
 
    list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now_time));
    $age = $y2 - $y1;
    if((int)($m2.$d2) < (int)($m1.$d1)){
        $age -= 1;
    }
    return $age; 
}
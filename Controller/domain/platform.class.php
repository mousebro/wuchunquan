<?
namespace Controller\domain;
use Library\Controller;
use Model\Member\Member;
use Model\Subdomain\SubdomainInfo;
use PFT\Tool\Tools;
use Model\Product\Ticket;
use Model\Order\OrderTools;
use Library\Response; 
class platform extends Controller
{
    private $code;
    private $msg;
    public function getdomainInfo(){                                        //配置页获取信息
        $a = include __DIR__ . '/../../Conf/domain.conf.php';
        $sid = $_SESSION['sid'];
        $account = $_SESSION['account']; 
        $oldrecord = self::getShopConfig($sid);
        $record = self::getNewdlogin($sid);
        if(!$record){
            $info = $oldrecord;
        }else{
            $info = $record;
        }
        $info = $info ?: [];
        if(in_array($account, $a)){ 
            $info['power'] = 1;
        }else{
            $info['power'] = 0;
        }
        if(!isset($_SESSION['sid'])) {
            $info = [];
            $data = $this->apiReturn(102, $info, '登陆超时！');
        }else{
            $data = $this->apiReturn(200, $info, '成功');
        }
        return $data;
    }
    
    public function domaininfo(){                                           //登陆获取
        $platform = new \Model\domain\platform();
        $domainInfo = new \Model\Subdomain\SubdomainInfo();
        $newSql = new \Library\Model('pft001');                          
        $host_info = explode('.', $_SERVER['HTTP_HOST']);
        $domain_info = $domainInfo->getBindedSubdomainInfo($host_info[0], 'account');
        $newdomain_info = $platform->getDetails($newSql,$host_info[0], 'account');
        $oldrecord = self::getShopConfig($domain_info['fid']);
        $record = self::getNewdlogin($newdomain_info['fid']);
        // if(!$record && $oldrecord['site_name']===NULL)
        // {   
            // $this->apiReturn(100,[], '您访问的地址无效');
            // exit;
        // }
        // if(!$record){
            // $info = $oldrecord;
        // }else{
            // $info = $record;
        // }
       // var_dump($domain_info);
       // var_dump($newdomain_info);
        //exit;
        if($domain_info && !$newdomain_info){
            $domainFid = $domain_info['fid'];
            $record = self::getNewdlogin($domainFid);
            if($record){
               $this->apiReturn(100,[], '您访问的地址无效');
               exit;
            }
        }
        if(!$record){
            $info = $oldrecord;
        }else{
            $info = $record;
        }
        
        
        
        $info = $info ?: [];
        $data = $this->apiReturn(200, $info, '成功');
        return $data;
    }
    
    public static function getShopConfig($memberid) {      //店铺信息
        $SubdomainInfo = new SubdomainInfo();
        $platform = new \Model\domain\platform();
        $wxModel = new \Library\Model('remote_1');                          //查询微信二维码
        $wxInfo = $platform->wxopen($wxModel,$memberid);
        $config = $platform->getBindedSubdomainInfo($memberid);
        if ((int)$memberid > 0 && !$config) {
            //self::initSubdomainInfo($memberid, self::getAccount);  
            $config = $SubdomainInfo->getBindedSubdomainInfo($memberid);
        }

        $banner = array(
            array('imgpath' => 'http://www.12301.cc/images/img/benner1.jpg', 'url' =>''),
            array('imgpath' => 'http://www.12301.cc/images/img/benner2.jpg', 'url' =>''),
            array('imgpath' => 'http://www.12301.cc/images/img/benner3.jpg', 'url' =>'')
        );

        return array(
                'site_name'         => $config['M_name'],
                'logo'              => $config['M_logo1']?$config['M_logo1']:"http://images.12301.test/images/index_logo.png",
                'banner'            => $banner,
                'tel'               => $config['M_tel'],
                'address'           => $config['M_addr'],
                'copyright'         => $config['M_copyright'],
                'qq'                => $config['M_qq'],
                'host'              => $config['M_account_domain']?$config['M_account_domain']:$_SESSION['account'],
                'domain'            => $config['M_domain'],
                'groupInfo'         => json_decode($config['M_slider'], true),
                'setgroup'          => '1',
                'weixinLogo'        => $wxInfo['qrcode_url']
        );
    }
    
    public static function getNewdlogin($memberid){    //新版登陆页面获取信息
        $platform = new \Model\domain\platform();
        $wxModel = new \Library\Model('remote_1');
        $newSql = new \Library\Model('pft001');
        $wxInfo = $platform->wxopen($wxModel,$memberid);
        $config = $platform->getDetails($newSql,$memberid);
        //$config = $platform->getDetails($memberid);
        $fid = $config['fid'];
        if(!$fid){
            return false;
        }else{
            return array(
                'site_name'         => $config['p_name'],
                'logo'              => $config['p_logo']?$config['p_logo']:"http://images.12301.test/images/index_logo.png",
                'banner'            => json_decode($config['p_banner'], true),
                'tel'               => $config['p_tel'],
                'address'           => $config['p_addr'],
                'copyright'         => $config['p_copyright'],
                'qq'                => $config['p_qq'],
                'host'              => $config['p_host']?$config['p_host']:$_SESSION['account'],
                'domain'            => $config['p_domain'],
                'groupInfo'         => json_decode($config['p_groupInfo'], true),
                'setgroup'          => $config['p_setgroup'],
                'weixinLogo'        => $wxInfo['qrcode_url']                
            );
        } 
    }
    
    public static function uploadImg() {
        $num = $_REQUEST['num'];
        if($num>=3){
            echo '<script>window.parent.ImgUploador.complete({"status":"fail", "msg" : "抱歉最多只允许添加3张轮播图"});</script>';
            exit;
        }
        if (!isset($_SESSION['sid'])) {
            Response::send(array('status' => 0, 'code' => 102, 'msg' => '请先登录'));
        }
        $name = \safe_str($_REQUEST['name']);
        include '/var/www/html/new/d/class/Uploader.class.php';
        $config = array(
            "savePath"      => IMAGE_UPLOAD_DIR ."shops/{$_SESSION['account']}",
            "maxSize"       => 2048, //单位KB
            "allowFiles"    => array(".gif", ".png", ".jpg", ".jpeg", ".bmp"),
            'simpleFolder'  => true,
        );
        $img_size = getimagesize($_FILES['img_upload_file_input']['tmp_name']);
        if ($name == 'logo') {
            $width      =   66;
            $length     =   280;
            $uploader   =   'ImgUplogo';
        } elseif ($name == 'slider') {
            $width      =   400;
            $length     =   1000;
            $uploader   =   'ImgUploador';
        } elseif ($name == 'group') {
            $width      =   422;
            $length     =   750;
            $uploader   =   'ImgUpgroup';
            $type = $_REQUEST['type'];
        }
        if ($img_size[0] != $length || $img_size[1] != $width) {
            echo '<script>alert("请上传指定大小的图片")</script>';
            exit();
        }

        $file = key($_FILES);
        $Upload = new \Uploader($file, $config);
        $img_info = $Upload->getFileInfo();
        if ($img_info['state'] == 'SUCCESS') {
            $img_url = IMAGE_URL . "shops/{$_SESSION['account']}/".$img_info['name'];
            echo '<script>window.parent.'.$uploader.'.complete({"status":"ok", "data" : "'.$type.'","src":"'.$img_url.'"});</script>';
        } else {
            echo '<script>alert("上传失败")</script>';
            exit();
        }
    }
    
    public static function save(){
        include '/var/www/html/new/d/class/Tools.class.php';
        if (!isset($_SESSION['sid'])) {
            Response::send(array('status' => 0, 'code' => 102,'msg' =>'登陆超时！'));
            exit;
        }
        $clean_request = Tools::deepRemoveXss($_REQUEST);
        if ($clean_request !== $_REQUEST) {
            Response::send(array('status' => 0, 'code' => 1001,'msg' =>'含有非法字符，请检查！'));
        }
        $P_domain =  \safe_str(I('Cusdomain'));
        $Sitename =  \safe_str(I('Sitename'));
        $Cusqq =     \safe_str(I('Cusqq'));
        $Custel =    \safe_str(I('Custel'));
        $Address =   \safe_str(I('Address'));
        $Copyright = \safe_str(I('Copyright'));
        if(!$Sitename){
           Response::send(array('status' => 0, 'code' => 104,'msg' =>'网站名称含有非法字符或不能为空！'));
           exit;
        }
        $config = array(
            'fid'               => $_SESSION['sid'],
            'p_domain'          => $P_domain ? $P_domain : $_SESSION['account'],
            'p_name'            => $Sitename,
            'p_logo'            => $_REQUEST['logo'],
            'p_qq'              => $Cusqq,
            'p_banner'          => json_encode($_REQUEST['banner']),
            'p_tel'             => $Custel,
            'p_host'            => $_REQUEST['Dedomain'],
            'p_addr'            => $Address,
            'p_about'           => '关于我们',
            'p_setgroup'        => '2',
            'p_groupInfo'       => json_encode($_REQUEST['groupInfo']),
            'p_copyright'       => $Copyright,
            'p_account_domain'  => $_SESSION['account'],
            'createtime'        => date('Y-m-d H:i:s'),
        );
        $upconfig = array(
            'M_domain'   =>    $_REQUEST['Cusdomain'] ? $_REQUEST['Cusdomain'] : $_SESSION['account'],
        );
        $SubdomainInfo = new \Model\domain\platform();
        $newSql = new \Library\Model('pft001'); 
        $exist = $SubdomainInfo->getDetails($newSql,$_SESSION['sid']);  
        $existold = $SubdomainInfo->getBindedSubdomainInfo($_SESSION['sid']);  
        $reDomain = $_SESSION['account'];                                     
        $oldinfo = $SubdomainInfo->checkOlddomainInfo($reDomain);             
        $newinfo = $SubdomainInfo->checknewddomainInfo($newSql,$reDomain);
        $checkNagain = $SubdomainInfo->checknewdAgain($newSql,$P_domain);     
        $checkOagain = $SubdomainInfo->checkolddAgain($P_domain);            
        $ofid = $oldinfo['fid'];
        $nfid = $newinfo['fid']; 
        $oldfid = $existold['fid'];
        $upconfig['id'] = $existold['id'];
        if($exist){
            $config['id'] = $exist['id'];
            if($checkNagain){
                if($_SESSION['sid']!=$nfid){
                    Response::send(array('status' => 0, 'code' => 1001,'msg' =>'自定义域名重复，请更换！'));
                }else{
                    $result = $newSql->table('pft_member_domain_platform')->save($config);
                }
            }else{
                $result = $newSql->table('pft_member_domain_platform')->save($config);
            }
        }else{
            $result = $newSql->table('pft_member_domain_platform')->add($config);
        }
        if ($result !== false) {
            Response::send(array('status' => 1, 'code' => 200 , 'msg' =>'保存成功！'));
        } else {    
           Response::send(array('status' => 0, 'code' => 1005,'msg'=> '保存失败，请重试'));
        }
          
    }
}

?>
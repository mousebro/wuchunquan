<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/4/30
 * Time: 09:29
 */

namespace Controller\product;


use Library\Controller;
use Library\Tools;

class Product extends Controller
{
    private $config;
    public function __construct()
    {
        $this->config = C(include  __DIR__ .'/Conf/product.conf.php');
    }

    public function saveLand()
    {
        $sData = [];
        $sData['ptype'] = I('post.ptype');
        if (!in_array($sData['ptype'],$this->config['LIMIT_TYPES']) ) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '产品类型不对');
        }
        $sData['title'] = I('post.mainTitle');
        if(mb_strlen($sData['title'],'utf8')>30){
            parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '景区名称不能超过 30 个字符');
        }
        $sData['apply_did'] = I('post.applydid', 0, 'intval');

        $tel = I('post.mainPhoneNum', '', 'trim');
        if($tel) {
             if ( !Tools::isphone($tel) && Tools::ismobile($tel) ) {
                 parent::apiReturn(parent::CODE_INVALID_REQUEST,[],'联系电话格式不正确');
             }
            $sData['tel'] = $tel;
        }
        $sData['topic'] = '';
        if( !empty($_POST['topic'])) {
            $topic = $_POST['topic'];
            if(is_array($_POST['topic']))
                $topic = implode(',',$_POST['topic']);
            $sData['topic'] = addslashes(strip_tags($topic));
        }elseif($_POST['resourceID']){
            Response('{"res":0,"msg":"请输入景区主题"}');
        }else{

        }

        $sData['custom_made'] = $_POST['custom']; // 私人定制
        $sData['px'] = intval($_POST['px']);
        if($sData['px']>30000 && $_SESSION['account']!='123997')//非云游 热门值不能高于30000
            Response('{"res":0,"msg":"热门度不能大于30000"}');
        $sData['terminal_type'] = intval($_POST['terminal_type']);
        $sData['jtype'] = safetxt($_POST['jtype']);
        $sData['runtime'] = safetxt($_POST['runtime']);//营业时间OR出发时间
        //套票产品属性，非空为套票 格式:二维数组json array(array('pid'=>301,'num'=>1),
        //array('pid'=>302,'num'=>3))
        if(isset($_POST['package_attr'])) {
            $sData['attribute'] = trim($_POST['package_attr']);
        }
        if($_POST['d_province']==""||$_POST['d_city']==""){
            Response('{"res":0,"msg":"请先选择省市"}');
        }

        if(isset($_POST['d_province'])) {
            if(is_string($_POST['d_province'])){
                $area_name = "'".mysql_real_escape_string($_POST['d_province'])."','".mysql_real_escape_string($_POST['d_city'])."'";
                $sql = "select area_name,area_id from uu_area where area_name in($area_name) limit 2";
//                echo $sql;exit;
                $GLOBALS['le']->query($sql);
                while($row=$GLOBALS['le']->fetch_assoc()){
                    $_POST['d_province'] = str_replace($row['area_name'],$row['area_id'],$_POST['d_province']);
                    $_POST['d_city'] = str_replace($row['area_name'],$row['area_id'],$_POST['d_city']);
                }
            }
            $sData['area']  = abs($_POST['d_province']) .'|'
                . abs($_POST['d_city']) .'|' .abs($_POST['d_zone']);
        }
        $code=$sData['area'];
        $s_code=explode('|',$code);
        if($s_code[0]==1||$s_code[0]==2||$s_code[0]==3||$s_code[0]==4||$s_code[0]==32||$s_code[0]==33 ||$s_code[0]==34){//32 香港 33 澳门 34 台湾
            $code=$s_code[0].'|1|0';
        }

        //旧版延迟验证会传delaytime=1 如果没选延迟验证 那么delaytime=0
        //新版没有传delaytime这个字段 所以是false
        if(($_POST['delaytime']==1 && (trim($_POST['vtimehour']) || trim($_POST['vtimeminu']))) || ((trim($_POST['vtimehour']) || trim($_POST['vtimeminu'])) && $_POST['delaytime']===null)) {
            $sData['delaytime']  = abs($_POST['vtimehour']) .'|'
                . abs($_POST['vtimeminu']);
        }
        else $sData['delaytime']=null;

        if(isset($_POST['mainAddress'])) {
            $sData['address'] = p_match($_POST['mainAddress'])
                ? Response('{"res":0,"msg":"地址含有非法字符","address":"'.$_POST['mainAddress'].'"}')
                : safetxt($_POST['mainAddress']);
        }elseif($_POST['resourceID']){
            Response('{"res":0,"msg":"请输入景区地址"}');
        }elseif(isset($_POST['start_place'])) {
            //线路产品将出发地、目的地存在runtime字段
            $sData['runtime'] = safetxt($_POST['start_place']) . '|' . safetxt($_POST['end_place']);
        }
        if(is_array($_POST['topic'])){
            if(mb_strlen(end($_POST['topic']),'utf8')>6){
                Response('{"res":0,"msg":"自定义主题不能超过 6 个字符"}');
            }
            if(p_match(end($_POST['topic']))) {
                Response('{"res":0,"msg":"自定义主题含有非法字符！添加失败！"}');
            }
        }
        if(is_string($topic)){
            $topic = explode(',',$topic);
            foreach($topic as $v)
                if(mb_strlen($v,'utf8')>6)
                    Response('{"res":0,"msg":"主题不能超过 6 个字符"}');
        }

        $sData['p_type'] = $ptype;
        //exit;
        $sData['jdjj'] = safetxt($_POST['infoIntro']);
        if(strlen($_POST['detailInfo'])>0) {
            $sData['bhjq'] = safehtml($_POST['detailInfo']);
            if(fliter_words($sData['bhjq'])) {
                Response('{"res":0,"msg":"'.$FieldList[$ptype]['bhjq']['t'].'含有非法字符！添加失败！"}');
            }
        }else{
            $sData['bhjq'] = '';
        }

        if(!empty($_POST['buyTips']) ) {
            if(fliter_words($_POST['buyTips'])) {
                Response('{"res":0,"msg":"'.$FieldList[$ptype]['jqts']['t'].'含有非法字符！添加失败！"}');
            }
            $sData['jqts'] = safetxt($_POST['buyTips']);
        }
        if(!empty($_POST['trafficInfo'])) {
            if(fliter_words($_POST['trafficInfo'])) {
                Response('{"res":0,"msg":"'.$FieldList[$ptype]['jtzn']['t'].'含有非法字符,添加失败！"}');
            }
            $sData['jtzn'] =safetxt($_POST['trafficInfo']);
        }
        if(is_array($_POST['thumb_img'])){
            $_POST['thumb_img'] = array_filter($_POST['thumb_img']);
            if(count($_POST['thumb_img'])){

                $sData['imgpathGrp'] = serialize($_POST['thumb_img']);
                $sData['imgpath'] = $_POST['thumb_img'][0];
            }else{
                Response('{"res":0,"msg":"请上传产品缩略图！"}');
            }

        }else{
            if(empty($_POST['thumb_img'])) {
                Response('{"res":0,"msg":"请上传产品缩略图！"}');
            }else {
                $sData['imgpath'] = $_POST['thumb_img'];
            }
        }
//    elseif(fliter_words($_POST['thumb_img'])) {
//        exit('{"res":0,"msg":"缩略图地址不正确"}');
//    }
        // 经纬度信息
        if(isset( $_POST['end_place_tude'])){
            // $sData['lng_lat_pos'] = $_POST['start_place_tude'].'|'.$_POST['end_place_tude'];
            $sData['lng_lat_pos'] = $_POST['end_place_tude'];
        }elseif($_POST['resource_id']){
            Response('{"res":0,"msg":"请输入经纬度信息"}');
        }
        // 演出类场馆ID
        if($ptype=='H'){
            $sData['venus_id'] = $_POST['venue_id']+0;
            if($sData['venus_id']==0) exit('{"res":0,"msg":"请关联演出场馆"}');
        }

        //添加区域代码
        $sql="select n_code from pft_area_code_concat where s_code='$code' limit 1";
        $GLOBALS['le']->query($sql);
        $GLOBALS['le']->fetch_assoc();
        $sData['areacode'] = $GLOBALS['le']->f('n_code');

        //关联资源库
        $sData['resourceID'] = $_POST['resource_id']+0;
        if( !empty($_POST['lastid']) && abs($_POST['lastid'])>0 ) {
            $lastid = abs($_POST['lastid']);
            $res = $prod->UpdateScenery($sData, $lastid, $sData['delaytime']);

            // 演出类更改了关联场馆ID
            if($res['errcode']==1000 && $ptype=='H' && $_POST['venue_old']!=$sData['venus_id']){
                $sql = "update uu_land_f set zone_id=0 where lid={$_POST['lastid']}";
                $GLOBALS['le']->query($sql);
            }
        }
        else {
            $now = date('Y-m-d H:i:s');
            if($byadmin ) {
                $sData['passtime'] = $now;
                $sData['status'] = 1;
            }
            $sData['addtime'] = $now;
            $res = $prod->AddProduct($sData, $mem, $parent_id, $sData['delaytime']);
        }

        if($byadmin) $res['byadmin'] = 1;

        Response(json_encode($res));
    }
}
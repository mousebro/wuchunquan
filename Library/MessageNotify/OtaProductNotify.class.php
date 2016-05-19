<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/19-019
 * Time: 11:48
 * 产品变更通知OTA
 */

namespace Library\MessageNotify;


use Library\Model;

class OtaProductNotify
{
    public function __construct($tid,$action, $status)
    {
        $model = new Model();
        $now   = date('Y-m-d H:i:s');
        // $selids = "select tid_aid,DockingMode,cooperation_way,signkey,supplierIdentity from uu_qunar_use where tid=$tid";
        $otaInfo = $model->table('uu_qunar_use')->where(['tid'=>$tid])
            ->field('tid_aid,DockingMode,cooperation_way,signkey,supplierIdentity')
            ->select();
        foreach($otaInfo as $arr){
            $tid_aid            = $arr['tid_aid'];
            $signkey            = $arr['signkey'];
            $supplierIdentity   = $arr['supplierIdentity'];
            $cooperation_way    = $arr['cooperation_way'];
            if($arr['DockingMode'] == 0){ //去哪儿
                $this->QunarNotify($arr['signkey'], $arr['supplierIdentity'], $now, $tid_aid);
            }elseif($arr['DockingMode'] == 1){ //美团
                file_get_contents("http://".IP_INSIDE."/new/d/module/api/meituanV2/MT_ChangeNotice.php?ids=$tid_aid&status=$status&signkey=$signkey&supplierIdentity=$supplierIdentity");
            }
        }
    }

    public function QunarNotify($signkey, $supplierIdentity, $now, $tid_aid)
    {
        $xml = <<<xml
<?xml version="1.0" encoding="UTF-8"?>
<request xsi:schemaLocation="http://piao.qunar.com/2013/QMenpiaoRequestSchema QMRequestDataSchema-2.0.1.xsd" xmlns="http://piao.qunar.com/2013/QMenpiaoRequestSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
	<header>
		<application>Qunar.Menpiao.Agent</application>
		<processor>SupplierDataExchangeProcessor</processor>
		<version>v2.1.0</version>
		<bodyType>NoticeProductChangedRequestBody</bodyType>
		<createUser>{$supplierIdentity}</createUser>
		<createTime>{$now}</createTime>
		<supplierIdentity>{$supplierIdentity}</supplierIdentity>
	</header>
	<body xsi:type="NoticeProductChangedRequestBody">
		<resourceId>{$tid_aid}</resourceId>
	</body>
</request>
xml;
        $bstr   = base64_encode($xml);
        $signed = strtoupper(md5($signkey.$bstr));
        $arr    = array('data'=>$bstr,'signed'=>$signed,'securityType'=>'MD5');
        $post_data = array();
        $post_data['method']        = 'noticeProductChanged';
        $post_data['requestParam']  = json_encode($arr);
        $url='http://agent.piao.qunar.com/api/external/supplierServiceV2.qunar';
        $response = curl_post($url, $post_data);
        $response_data = json_decode($response,true);
        $response_xml = simplexml_load_string(base64_decode($response_data['data']));
        $qunar_code = (int)$response_xml->header->code;
        pft_log('api/notify', "QUNAR 0|{$tid_aid}|{$qunar_code}");
        return true;
    }

    public function MtV2Notify()
    {
        file_get_contents("http://".IP_INSIDE."/new/d/module/api/meituanV2/MT_ChangeNotice.php?ids=$tid_aid&status=$status&signkey=$signkey&supplierIdentity=$supplierIdentity");
    }
}
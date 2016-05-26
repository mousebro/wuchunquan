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
    public static function notify($tid, $status)
    {
        $model = new Model();
        $now   = date('Y-m-d H:i:s');
        $otaInfo = $model->table('uu_qunar_use')->where(['tid'=>$tid])
            ->field('tid_aid,DockingMode,cooperation_way,signkey,supplierIdentity')
            ->select();
        if (!$otaInfo) return true;
        foreach($otaInfo as $arr){
            if($arr['DockingMode'] == 0){ //去哪儿
                self::QunarNotify($arr['signkey'], $arr['supplierIdentity'], $now, $arr['tid_aid']);
            }elseif($arr['DockingMode'] == 1){ //美团
                self::MtV2Notify($status, $arr['signkey'], $arr['supplierIdentity'], $arr['tid_aid']);
            }
        }
    }

    /**
     * 去哪儿通知
     *
     * @param $signkey
     * @param $supplierIdentity
     * @param $now
     * @param $tid_aid
     * @return bool
     */
    public static function QunarNotify($signkey, $supplierIdentity, $now, $tid_aid)
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

    /**
     * 美团通知
     *
     * @param $status
     * @param $signkey
     * @param $supplierIdentity
     * @param $tid_aid
     */
    public static function MtV2Notify($status, $signkey, $supplierIdentity, $tid_aid)
    {
        $url = "http://ota.12301.cc/meituanV2/MT_ChangeNotice.php";
        $data = [
            'ids'       =>$tid_aid,
            'status'    =>$status,
            'signkey'   => $signkey,
            'supplierIdentity' => $supplierIdentity,
        ];
        curl_post($url, $data);
    }
}
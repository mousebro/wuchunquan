<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/19-019
 * Time: 11:48
 */

namespace Library\MessageNotify;


use Library\Model;

class OtaProductNotify
{
    public function __construct($tid,$action, $status)
    {
        $model = new Model();
        // $selids = "select tid_aid,DockingMode,cooperation_way,signkey,supplierIdentity from uu_qunar_use where tid=$tid";
        $otaInfo = $model->table('uu_qunar_use')->where(['tid'=>$tid])
            ->field('tid_aid,DockingMode,cooperation_way,signkey,supplierIdentity')
            ->select();
        foreach($otaInfo as $arr){
            $tid_aid            = $arr['tid_aid'];
            $signkey            = $arr['signkey'];
            $supplierIdentity   = $arr['supplierIdentity'];
            $cooperation_way = $arr['cooperation_way'];
            if($arr['DockingMode'] == 0){ //去哪儿
                file_get_contents("http://coop.12301.cc/callback/ProductChangeNotice.php?ids=$tid_aid&status=$status");
            }elseif($arr['DockingMode'] == 1){ //美团
                file_get_contents("http://".IP_INSIDE."/new/d/module/api/meituanV2/MT_ChangeNotice.php?ids=$tid_aid&status=$status&signkey=$signkey&supplierIdentity=$supplierIdentity");
            }
        }
    }

    public function QunarNotify()
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
    }
}
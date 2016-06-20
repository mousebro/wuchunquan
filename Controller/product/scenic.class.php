<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/23-023
 * Time: 17:22
 */
namespace Controller\product;
use Model\Product\Land;

class scenic extends ProductBasic
{
    private $memberID;
    //private $ticketObj = ;
    public function __construct()
    {
        if (!$_SESSION['memberID']) parent::apiReturn(self::CODE_AUTH_ERROR,[],'未登录');
        //$this->ticketObj = parent::model('\Product\Ticket');
        $this->memberID = $_SESSION['sid'];
        parent::__construct();
    }
    public function save()
    {
        $land = new Land();
        $this->SaveBasicInfo($this->memberID, $land);
    }

    /**
     * 景区编辑页面，获取景区信息接口(暂时只适配年卡产品)
     * @return [type] [description]
     */
    public function get() {
        $lid = I('lid', '', 'intval');

        if ($lid < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $LandModel = new Land();

        $field = 'land.title,land.area,land.jqts,land.address,land.imgpath,land.tel,f.p_type';

        $land = $LandModel->getLandInfo($lid, true, $field);

        if (!$land) {
            $this->apiReturn(204, [], '景区未找到');
        }

        $return = [
            'product_name'      => $land['title'],
            'product_type'      => $land['p_type'],
            'province'          => explode('|', $land['area'])[0],
            'city'              => explode('|', $land['area'])[1],
            'address'           => $land['address'],
            'telphone'          => $land['tel'],
            'image'             => $land['imgpath'],
            'jqts'         => $land['jqts']
        ];

        $this->apiReturn(200, $return);
    }

}
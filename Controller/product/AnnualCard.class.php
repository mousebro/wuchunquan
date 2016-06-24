<?php

namespace Controller\Product;

use Library\Controller;
use Model\Product\AnnualCard as CardModel;
use Model\Product\Ticket;
use Model\Product\Land;
use Model\Member\Member;

class AnnualCard extends Controller {

    private $_CardModel = null;

    public function __construct() {

        if (!isset($_SESSION['memberID'])) {
            $this->apiReturn(401, [], '请先登录');
        }

        // if (!$this->isAjax()) {
        //     $this->apiReturn(403, [], '我要报警了!!!');
        // }
        
        $this->_CardModel =  new CardModel();

    }

    /**
     * 获取所属供应商的年卡产品列表
     * @return [type] [description]
     */
    public function getAnnualCardProducts() {

        $options = [
            'page_size' => I('page_size', '10', 'intval'),
            'page'      => I('page', '1', 'intval')
        ];

        $products = $this->_CardModel->getAnnualCardProducts($_SESSION['sid'], $options , 'select');

        $total = $total_page = 0;
        if ($products) {
            $total = $this->_CardModel->getAnnualCardProducts($_SESSION['sid'], $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $return = [
            'list'      => $products ?: [],
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total
        ];

        $this->apiReturn('200', $return);
    }

    /**
     * 获取指定产品的关联年卡
     * @return [type] [description]
     */
    public function getAnnualCards() {
        $pid = I('pid', '', 'intval');
        $sid = $_SESSION['sid'];

        if (intval($pid) < 1) {
            $this->apiReturn(400, [], '参数错误');
        }

        $options = [
            'status'    => 3,
            'page'      => I('page', '1', 'intval'),
            'page_size' => I('page_size', '10', 'intval'),
            
        ];

        $list = $this->_CardModel->getAnnualCards($sid, $pid, $options);

        $total = $total_page = 0;
        if ($list) {
            $total = $this->_CardModel->getAnnualCards($sid, $pid, $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $virtual_stg = $this->_CardModel->getAnnualCardStorage($sid, $pid);
        $physics_stg = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'physics');

        $return = [
            'list'      => $list ?: [],
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total,
            'virtual'   => $virtual_stg,
            'physics'   => $physics_stg
        ];

        $this->apiReturn('200', $return);
    }


    /**
     * 创建\录入年卡,此时都还处于未绑定物理卡状态
     * @return [type] [description]
     */
    public function createAnnualCard() {
        $pid    = I('pid', '', 'intval');  //关联的年卡产品
        $list   = I('list');

        if (intval($pid) < 1 || !is_array($list)) {
            $this->apiReturn(400, [], '参数错误');
        }

        if  (count($list) == 0) {
            $this->apiReturn(200, [], '录入成功');
        } 

        if (!$this->_isProductAvalid($pid, $_SESSION['sid'])) {
            $this->apiReturn(400, [], '请选择正确的产品');
        }

        $result = $this->_CardModel->createAnnualCard($list, $_SESSION['sid'], $pid);

        if (!$result) {
            $this->apiReturn(204, [], '录入失败,请重试');
        }

        $this->apiReturn(200, [], '录入成功');
    }

    /**
     * 选择的年卡产品是否合法
     * @param  [type]  $pid [description]
     * @param  [type]  $sid [description]
     * @return boolean      [description]
     */
    private function _isProductAvalid($pid, $sid) {
        $options['where'] = [
            'id'        => $pid,
            'apply_did' => $sid,
            'p_status'  => 0,
            // 'p_type'    => 'I'
        ];

        $Ticket = new Ticket();

        $find = $Ticket->getProductInfo($options);

        $type = $Ticket->getProductType($pid);

        return $find && $type == 'I' ? true : false;
    }


    /**
     * 删除年卡
     * @return [type] [description]
     */
    public function deleteAnnualCard() {

        if (isset($_GET['virtual_no'])) {
            $where = [
                'sid'        => $_SESSION['sid'],
                'virtual_no' => I('virtual_no'),
                'status'     => 3
            ];
        } else {
            $where = [
                'sid'        => $_SESSION['sid'],
                'status'     => 3
            ];
        }

        if ($this->_CardModel->deleteAnnualCard($where)) {
            $this->apiReturn(200, [], '删除成功');
        } else {
            $this->apiReturn(200, [], '删除失败');
        }

    }

    /**
     * 绑定物理卡（完善年卡信息）
     * @return [type] [description]
     */
    public function bindAnnualCard() {
        $card_no    = I('card_no');
        $physics_no = I('physics_no');
        $virtual_no = I('virtual_no');

        if (!$card_no || !$physics_no || !$virtual_no) {
            $this->apiReturn(400, [], '参数错误');
        }

        $result = $this->_CardModel->bindAnnualCard($_SESSION['sid'], $virtual_no, $card_no, $physics_no);

        if ($result) {
            $this->apiReturn(200, [], '绑定成功');
        } else {
            $this->apiReturn(204, [], '绑定失败');
        }
    }

    /**
     * PC端供应商手动激活年卡,TODO://待优化
     */
    public function activateForPc() {

        $identify   = I('identify');
        $mobile     = I('mobile');
        $name       = I('name');

        $card = $this->_activateCheck($identify, $_SESSION['sid']);

        $replace = I('replace') || false;

        //会员只能某个供应商的一张年卡
        $memberid = $this->_isNeedToReplace($mobile, $_SESSION['sid'], $replace);

        if (!$this->activeAction($card['id'], $memberid)) {

            $this->apiReturn(200, [], '激活失败');

        }

        $this->apiReturn(200, [], '激活成功');

    }

    /**
     * 平台销售虚拟卡时激活
     * @return [type] [description]
     */
    public function activeForVirtualSale() {
        $mobile     = I('mobile');
        $name       = I('name');

        $replace = I('replace') || false;

        $this->_isNeedToReplace($mobile, $_SESSION['sid'], $replace);

        $options = [
            'where' => [
                'card_no'=> '',
                'status' => 3
            ]
        ];

        //从虚拟库存中获取一张未激活的卡片
        $memberid = $card = $this->_CardModel->getAnnualCard(1, 1, $options);

        if (!$this->activeAction($card['id'], $memberid)) {

            $this->apiReturn(200, [], '激活失败');

        }

        $this->apiReturn(200, [], '激活成功');

    }

    /**
     * 对会员已绑定的年卡进行替换
     * @param  [type]  $mobile  [description]
     * @param  [type]  $sid     [description]
     * @param  [type]  $replace [description]
     * @return boolean          [description]
     */
    private function _isNeedToReplace($mobile, $sid, $replace) {
        $memberid = $this->_getMemberid($mobile);

        $card = $this->_hasBindAnnualCard($memberid, $sid);

        //向用户确认是否进行替换
        if ($card && !$replace) {

            $product = (new Ticket)->getProductInfo($card['pid'], ['field' => 'p_name']);
            // $ticket = (new Ticket)->getTicketInfoByPid($card['pid']);

            // $use = $this->_CardModel->getRemainTimes($card['sid'], $ticket['id'], $memberid, true);

            $data = [
                'exist' => 1,
                'name' => $product['p_name'],
                // 'left' => '1/20'
            ];

            $this->apiReturn(200, $data);
        }

        //用户确认进行替换
        if ($card && $replace) {
            if (!$this->_CardModel->forbiddenAnnualCard($card['id'])) {
                $this->apiReturn(204, [], '替换失败');
            }
        }

        return $memberid;
    }

    /**
     * pc端年卡激活检测
     * @return [type] [description]
     */
    private function _activateCheck($identify, $sid) {

        $identify = "sid={$sid} and 
            (card_no='{$identify}' or 
            virtual_no='{$identify}' or 
            physics_no='{$identify}')";

        $card_info = $this->_CardModel->getAnnualCard($identify, '_string');

        if (!$card_info) {
            $this->apiReturn(204, [], '未找到相应的卡片信息');
        }

        if ($card_info['memberid']) {
            $this->apiReturn(204, [], '该卡已被使用');
        }

        return $card_info;
    }

    /**
     * 根据手机号获取用户id，不存在则注册
     * @param  [type] $mobile [description]
     * @return [type]         [description]
     */
    private function _getMemberid($mobile) {
        $member = (new Member())->getMemberInfo($mobile, 'mobile');

        if (!$member) {
            //注册新会员
        }

        return $member['id'];
    }
    

    /**
     * 判断用户是否已经绑定过其他年卡
     * @param  [type]  $memberid [description]
     * @param  [type]  $sid      [description]
     * @return boolean           [description]
     */
    private function _hasBindAnnualCard($memberid, $sid) {
        $identify = "sid={$sid} and memberid={$memberid}";

        return $this->_CardModel->getAnnualCard($identify, '_string');
    }

    /**
     * 年卡激活接口(用于所有渠道的激活操作)
     * @access  public
     * @return [type] [description]
     */
    public function activeAction($card_id, $memberid) {

        //状态变更
        if (!$this->_CardModel->activateAnnualCard($card_id, $memberid)) {
            return false;
        }

        //卡片激活后，清算订单资金
        // if (0) {
        //     // $this->_CardModel->rollback();
        //     return false;
        // }


        return true;
    }

    /**
     * 根据手机号查询会员信息是否已经存在
     * @param  [type]  $mobile [description]
     * @return boolean         [description]
     */
    public function isMemberExists() {

        if (!I('mobile') || strlen(I('mobile')) != 11) {
            $this->apiReturn(400, '请填写正确的手机号');
        }

        //TODO:需要返回的字段筛选
        $member = (new Member())->getMemberInfo(I('mobile'), 'mobile');

        $this->apiReturn(200, $member);

    }

    /**
     * 获取年卡会员列表
     * @return [type] [description]
     */
    public function getMemberList() {

        $options = [
            'page_size' => I('page_size', 10, 'intval'),
            'page'      => I('page', 1, 'intval'),
            'status'    => I('status', 1, 'intval'),
            'identify'  => I('identify', '')
        ];

        $result = $this->_CardModel->getMemberList($_SESSION['sid'], $options, 'select');

        $total = $total_page = 0;
        if ($result) {
            $total = $this->_CardModel->getMemberList($_SESSION['sid'], $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $result = $result ?: [];

        $memberid_arr = [];
        foreach ($result as $item) {
            if ($item['memberid'])
                $memberid_arr[] = $item['memberid'];
        }   

        $members = $result ? $this->_getMemberInfoByMulti($memberid_arr) : [];
        $members = $this->_replaceKey($members, 'id');

        foreach ($result as $key => $item) {
            if (isset($members[$item['memberid']])) {
                $result[$key]['account'] = $members[$item['memberid']]['account'];
                $result[$key]['mobile'] = $members[$item['memberid']]['mobile'];
            }
        }

        $return = [
            'list'      => $result,
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total
        ];

        $this->apiReturn(200, $return ?: []);
    }

    /**
     * 获取多用户信息
     * @param  [type] $memberid_arr [description]
     * @return [type]               [description]
     */
    private function _getMemberInfoByMulti($memberid_arr) {

        return (new Member())->getMemberInfoByMulti($memberid_arr, 'id', 'id,account,mobile');

    }

    /**
     * 获取会员详细信息
     * @return [type] [description]
     */
    public function getMemberDetail() {
        if (($memberid = I('memberid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $result = $this->_CardModel->getMemberDetail($_SESSION['sid'], $memberid);
        $result = $result ?: [];

        foreach ($result as $item) {

        }

        $this->apiReturn(200, $result, []);
    }

    /**
     * 年卡库存详细信息
     * @return [type] [description]
     */
    public function getAnnualCardStorage() {
        if (($pid = I('pid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $sid = $_SESSION['sid'];

        $vir_storage = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'virtual');
        $phy_storage = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'physics');

        $cards = [];
        if ($vir_storage && $phy_storage) {
            $options = [
                'status'    => 3,
                'page_size' => I('page_size', '10', 'intval'),
                'page'      => I('page', 1, 'intval'),
            ];

            $cards = $this->_CardModel->getAnnualCards($sid, $pid, $options);
        }

        $return = [
            'cards'   => $cards,
            'virtual' => $vir_storage,
            'physics' => $phy_storage
        ];

        $this->apiReturn(200, $return);

    }

    /**
     * 可添加到年卡特权的产品(自供应 + 转分销一级)
     * @return [type] [description]
     */
    public function getLands() {

        $result = $this->_CardModel->getLands($_SESSION['sid'], I('keyword'));

        $this->apiReturn(200, $result);
    }

    /**
     * [可添加到年卡特权的门票(自供应 + 转分销一级)
     * @return [type] [description]
     */
    public function getTickets() {

        include '/var/www/html/new/d/class/SoapInit.class.php';
        include '/var/www/html/new/d/class/abc/PFTCoreAPI.class.php';

        $soap_cli = (new \SoapInit())->GetSoapInside();

        $tickets = $this->_CardModel->getTickets($_SESSION['sid'], (int)I('aid'), (int)I('lid'));

        foreach ($tickets as $key => $item) {

            if ($item['apply_did'] == $_SESSION['sid']) {
                continue;
            }

            $price = \PFTCoreAPI::pStorage(
                $soap_cli, $_SESSION['saccount'], 
                $item['pid'], (int)I('aid'), date('Y-m-d'), 2
            );

            if ($price['js']['p'] == -1) {
                unset($tickets[$key]);
            }
        }
        
        $this->apiReturn(200, $tickets);
    }

    /**
     * 图片上传
     * @return [type] [description]
     */
    public function uploadImg() {
        include '/var/www/html/new/d/class/Uploader.class.php';

        $callback_id = I('callback_id', 0, 'intval');

        $config = array(
            "savePath"      => IMAGE_UPLOAD_DIR ."{$_SESSION['account']}/".date('Y-m-d'),
            "maxSize"       => 2048, //单位KB
            "allowFiles"    => array(".gif", ".png", ".jpg", ".jpeg", ".bmp"),
            'simpleFolder'  => true,
        );

        $file   = key($_FILES);
        $Upload = new \Uploader($file, $config);
        $img_info = $Upload->getFileInfo();

        if ($img_info['state'] == 'SUCCESS') {

            $img_url = IMAGE_URL . "{$_SESSION['account']}/".date('Y-m-d').'/'.$img_info['name'];
            $r = ['code' => 200, 'data' => ['src' => $img_url]];

        } else {
            $r = ['code' => 204, 'data' => [], 'msg' => '上传失败']; 
        }

        $r = json_encode($r);

        $script = '<script type="text/javascript">
                var FileuploadCallbacks=window.parent.FileuploadCallbacks['.$callback_id.'];
                for(var i in FileuploadCallbacks) FileuploadCallbacks[i]('.$r.');
                </script>';
        echo $script;
    }

    //下单页面获取卡片接口
    public function getCardsForOrder() {

        $pid = I('pid', '', 'intval');

        if ($pid < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $physics = I('physics', '');

        //购买虚拟卡
        if ($physics == '') {
            $options = [
                'where' => ['pid' => $pid,'card_no'=> '','status' => 3],
                'field' => 'virtual_no,sid,physics_no,card_no',
                'order' => 'create_time asc'
            ];
            $virtual = $this->_CardModel->getAnnualCard(1, 1, $options);

            if (!$virtual) {
                $this->apiReturn(204, [], '虚拟卡库存不足');
            } else {
                $this->apiReturn(200, [$virtual]);
            }
        }

        $physics_arr = explode(',', $physics);

        if (count($physics_arr) > 10) {
            $this->apiReturn(204, [], '一次最多只能购买10张物理卡');
        }

        $options = [
            'where' => [
                'pid'       => $pid,
                'physics_no'=> ['in', $physics],
                'status'    => 3
            ],
            'field' => 'virtual_no,sid,physics_no,card_no',
        ];

        $physics_cards = $this->_CardModel->getAnnualCard(1, 1, $options, 'select');
        
        $this->apiReturn(200, $physics_cards ?: []);
    }

    /**
     * 获取下单信息
     * @return [type] [description]
     */
    public function getOrderInfo() {

        include '/var/www/html/new/d/class/abc/PFTCoreAPI.class.php';

        $soap = $this->getSoap();

        $aid    = I('aid', '', 'intval');
        $pid    = I('pid', '', 'intval');
        $type   = I('type');

        if ($aid < 1 || $pid < 1 || !in_array($type, ['virtual', 'physics'])) {
            $this->apiReturn(204, [], '参数错误');
        }

        $data = [];

        $price = \PFTCoreAPI::pStorage($soap, $_SESSION['saccount'], $pid, $aid, date('Y-m-d'));

        if ($price['js']['p'] == -1) {  
            //获取不到价格
            $this->apiReturn(403, [], '当前产品不可购买');
        }

        //门票信息
        $product = (new Ticket())->getTicketInfoByPid($pid);
        //景区信息
        $land    = (new Land())->getLandInfo($product['landid'], false, 'title,bhjq');

        //虚拟库存
        $storage = -1;
        if ($type == 'virtual') {
            $storage = $this->_CardModel->getAnnualCardStorage($product['apply_did'], $pid, $type);
        }

        $data['product'] = [
            'ltitle'    => $land['title'],
            'title'     => $product['title'],
            'price'     => $price['js']['p'],
            'storage'   => $storage
        ];

        //年卡囊括的特权信息
        $data['privileges'] = $this->_CardModel->getPrivileges($pid);

        $data['pay']['is_self'] = intval($aid == $_SESSION['sid']);

        if (!$data['pay']['is_self']) {
            $remain = $this->_getRemainAndCredit($_SESSION['memberID'], $aid, $soap);
            $data['pay']['remain'] = $remain['remain'];
            $data['pay']['credit'] = $remain['credit'];
        }

        //供应商信息
        $supplier = (new Member())->getMemberInfo($product['apply_did']);

        $data['supplier'] = [
            'name'      => $supplier['dname'],
            'linkman'   => $supplier['mobile'],
            'intro'     => $land['bhjq']
        ];

        $this->apiReturn(200, $data);

    }

    /**
     * 获取账户余额和供应商授信余额
     * @param  [type] $memberid 会员id
     * @param  [type] $aid      供应商id
     * @return [type]           [description]
     */
    private function _getRemainAndCredit($memberid, $aid, $soap) {
        $remain = $soap->PFT_Member_Fund($memberid, 0, null);
        $remain = simplexml_load_string($remain);
        $distri_info['remain'] = (int)$remain->Rec->UUamoney / 100;

        //供应商账户余额
        $remain = $soap->PFT_Member_Fund($memberid, 1, $aid);
        $remain = simplexml_load_string($remain);
        $s_remain = (int)$remain->Rec->UUkmoney / 100;

        $limit = $soap->PFT_Member_Fund($memberid, 2, $aid);
        $limit = simplexml_load_string($limit);
        $distri_info['credit'] = sprintf("%.2f", ($s_remain + (int)$limit->Rec->UUbasecredit / 100) / 10000);

        return $distri_info;
    }

    /**
     * 虚拟卡下单，判断是否需要替换
     * @return boolean [description]
     */
    public function isNeedToReplace() {
        $mobile     = I('mobile', '', 'intval');
        $name       = I('name');
        $sid        = I('sid');
        $id_card    = I('id_card');

        if (!$mobile || !$name || !$sid) {
            $this->apiReturn(204, [], '参数错误');
        }

        $this->_isNeedToReplace(I('mobile'), $sid, $replace);
    }



    public function test() {
        // echo json_encode(($this->_CardModel->getCrdConf(5938)), JSON_UNESCAPED_UNICODE);die;
        var_dump((new \Api\AnnualCard())->annualConsume());
        // $this->_CardModel->consumeCheck(3385, 3385, 28460);
    }


    /**
     * 实例化年卡model
     * @return [type] [description]
     */
    private function _initializeCardModel() {
        static $CardModel = null;

        if (is_object($CardModel)) return $CardModel;

        $CardModel = new CardModel();

        return $CardModel;
    }

    private function _replaceKey($arr, $key) {
        $new_arr = [];

        foreach ($arr as $item) {
            $new_arr[$item[$key]] = $item;
        }

        return $new_arr;
    }
}
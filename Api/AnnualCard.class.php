<?php

//年卡终端消费接口

namespace Api;

use Library\Cache\Cache;
use Library\Controller;
use Model\Product\AnnualCard as CardModel;
use Model\Member\Member;
use Model\Product\Ticket;
use Controller\product\AnnualCard as CardCtrl;

// if ( !defined('PFT_API') ) { exit('Access Deny'); }


 class AnnualCard extends Controller {

    private $_CardModel = null;

    private $_config = [];  //年卡配置

    private $_privileges = [];  //年卡特权信息

    private $_pri_left = [];    //特权剩余次数

    public function __construct() {

        $this->_CardModel =  new CardModel();

    }

    /**
     * 购买的门票是否包含特权产品
     * @return boolean [description]
     */
    public function annualConsume() {
        $aid        = I('aid');         //供应商id
        $products   = I('tickets');     //门票 [['pid' => num]]
        $identify   = I('identify');
        $type       = I('type');

        if (!$aid || !$products || !$identify || !$type) {
            $this->apiReturn(204, [], '参数错误');
        }
        
        $tmp_pros = array_chunk(explode(',', $products), 2);

        $products = [];
        foreach ($tmp_pros as $item) {
            $products[$item[0]] = $item[1];
        }

        $card = $this->_parseAnnualCard($aid, $identify, $type);

        if ($type != 'physics_no') {
            $mobile     = (new Member)->getMemberInfo($card['memberid'])['mobile'];
            $vcode      = I('vcode');
            $this->_checkVcode($mobile, $vcode);
        }

        //未激活
        if ($card['status'] == 3) {

            $this->apiReturn(204, [], '年卡处于未出售状态');

        } elseif ($card['status'] == 0) {

            $this->_toActivate($card['sid'], $card['pid']);

        } elseif ($card['status'] == 2) {

            $this->apiReturn(204, [], '年卡已被禁用');

        }

        if ($card['sid'] != $aid) {
             $this->apiReturn(204, [], '无法使用特权支付');
        }
 
        // 年卡有效期检测
        if (!$this->_periodOfValidityCheck($card)) {
            $this->apiReturn(204, [], '年卡已过期');
        }

        $error = $this->_privilegesCheck($card['sid'], $card['memberid'], $products, $card['pid']);

        if (count($error) > 0) {
            //账户余额是否足够支付,一期都是0
            // $this->_balanceEnough();    
            $left = [];
            foreach ($error as $pid=> $item) {
                $tmp = $this->_privileges[$pid];

                $left['remain'][] = [
                    'title' => $tmp['ltitle'] . $tmp['title'],
                    'left'  => $item
                ];
            }
            $this->apiReturn(203, $left, '特权次数不足');
        }

        $order_info = $this->_orderAction($products, $aid, $card['memberid'], I(null));

        $data = $this->_getExtraData($card);

        $data['ordernum'] = $order_info['orderNum'];

        if ($type != 'physics') {
            $this->_removeCodeInfo($mobile);
        }

        $this->apiReturn(200, $data, '下单成功');

    }


    private function _toActivate($sid, $pid) {

        $ticket = (new ticket)->getTicketInfoByPid($pid);

        $need = $this->_CardModel->isNeedID($sid, $ticket['id']);

        $this->apiReturn(202, ['need_ID' => $need], '请先激活');
    }

    private function _getExtraData($card) {
        $Member = new Member();

        $member = $Member->getMemberInfo($card['memberid']);

        $supply = $Member->getMemberInfo($card['sid']);

        $product = (new Ticket)->getProductInfo($card['pid']);
        $ticket =  (new Ticket)->getTicketInfoByPid($card['pid']);

        $data = [
            'mobile'        => $member['mobile'],
            'card_title'    => $product['p_name'],
            'card_no'       => $card['card_no'],
            'virtual_no'    => $card['virtual_no'],
            'valid_time'    => $this->_CardModel->getPeriodOfValidity($card['sid'], $ticket['id'], $card['sale_time'], $card['active_time']),
            'supply'        => $supply['dname'],
        ];

        foreach ($this->_privileges as $item) {

            if (!isset($this->_pri_left[$item['tid']])) {
                continue;
            }

            $left = $this->_pri_left[$item['tid']] == -1 ? -1 : implode(',', $this->_pri_left[$item['tid']]);

            $data['pri'][] = [
                'title' => $item['ltitle'] . $item['title'],
                'left' => $left
            ];
        }

        return $data;

    }

    /**
     * 终端年卡激活接口
     * @return [type] [description]
     */
    public function activate() {

        // $string = '{"aid":3385,"mobile":"13123196340","identify":"13123196340","id_card":"777777777777777777"}';

        // $_POST = $_GET = json_decode($string, true);
        // 
        // var_dump(file_exists('/var/www/html/Service/Controller/product/AnnualCard.class.php'));die;

        $Ctrl = new CardCtrl();

        $_POST['type'] = 'physics_no';

        $Ctrl->activateForPc(I('aid', '', 'intval'));
    }

    /**
     * 发送验证码
     * @return [type] [description]
     */
    public function sendVcode() {
        $mobile = I('mobile');

        $aid    = I('aid');

        $card = $this->_parseAnnualCard($aid, $mobile, 'other');

        if (!$card || $card['status'] != 1) {
            $this->apiReturn(204, [], '年卡不存在或者非激活状态');
        }

        $mobile = (new Member)->getMemberInfo($card['memberid'])['mobile'];

        $code_info = $this->_getCodeInfo($mobile);

        if ($code_info && (time() - $code_info['time'] < 60)) {
            $this->apiReturn(204, [], '操作太频繁');
        }

        $code = substr(str_shuffle('123456789'), 0, 6);

        $content = str_replace('{vcode}', $code, $this->_getVcodeTpl());

        $soap = $this->getSoap();

        $result = $soap->Send_SMS_V($mobile, $content);

        if ($result == 100) {

            $data = [
                'code'  => $code,
                'time'  => time()
            ];

            Cache::getInstance('redis')->set(md5($mobile . 'annual'), json_encode($data), '', 1800);

            $this->apiReturn(200, [], '验证码发送成功');

        } else {
            $this->apiReturn(204, [], '验证码发送失败');
        }
    }

    /**
     * 验证码检测
     * @param  [type] $mobile [description]
     * @param  [type] $code   [description]
     * @return [type]         [description]
     */
    private function _checkVcode($mobile, $code) {
        $cache_info = $this->_getCodeInfo($mobile);

        if (!$cache_info) {
            $this->apiReturn(204, [], '验证码已过期');
        }

        if ($cache_info['code'] != $code) {
            $this->apiReturn(204, [], '验证码错误');
        }
    }

    private function _getCodeInfo($mobile) {

        $cache = Cache::getInstance('redis')->get(md5($mobile . 'annual'));

        return json_decode($cache, true);

    }

    private function _removeCodeInfo($mobile) {
        Cache::getInstance('redis')->rm(md5($mobile . 'annual'));
    }

    private function _getVcodeTpl() {

        return '您正在使用年卡消费，验证码：{vcode}';

    }

    /**
     * 获取年卡的包含的特权产品
     * @param  int    $aid      供应商id
     * @param  string $identify 标识
     * @param  string $type     物理卡号|手机号
     * @return [type]           [description]
     */
    private function _parseAnnualCard($aid, $identify, $type = 'physics_no') {

        $type = $this->_CardModel->parseIdentifyType($identify, $type);

        $options = [];

        switch ($type) {

            case 'physics_no':
            case 'card_no':
            case 'virtual_no':
                $options['where'] = [
                    'sid' => $aid,
                    $type => $identify,
                ];

                break;

            case 'mobile':
                $member = (new Member)->getMemberInfo($identify, 'mobile');

                if (!$member) {
                    $this->apiReturn(204, [], '会员账户不存在');
                }

                $options['where'] = [
                    'sid'       => $aid,
                    'memberid'  => $member['id'],
                    'status'    => 1
                ];

                break;

            default:
                $this->apiReturn(204, [], '请输入正确的标识');

        }

        $card = $this->_CardModel->getAnnualCard(1, 1, $options);

        if (!$card) {
            $this->apiReturn(204, [], '查无此年卡');
        }

        $ticket = (new Ticket())->getTicketInfoByPid($card['pid']);

        $config = $this->_CardModel->getAnnualCardConfig($ticket['id']);
        //TODO:可用验证方式判断

        $this->_config = $config;

        return $card ?: false;

    }

    /**
     * 年卡是否处于有效期
     * @param  [type] $card 年卡信息
     * @return [type]       [description]
     */
    private function _periodOfValidityCheck($card) {
        $ticket = (new Ticket())->getTicketInfoByPid($card['pid']);

        if (!$config = $this->_config) {
            $config = $this->_CardModel->getAnnualCardConfig($ticket['id']);
        }

        $res = $this->_CardModel->_periodOfValidityCheck($card, $config);

        return $res ? true : false;
    }

    /**
     * 特权支付次数检测
     * @param  [type] $products [description]
     * @param  [type] $pid      [description]
     * @return [type]           [description]
     */
    private function _privilegesCheck($sid, $memberid, $products, $pid) {

        $privileges = $this->_CardModel->getPrivileges($pid);

        foreach ($privileges as $key => $item) {
            $privileges[$item['pid']] = $item;
            unset($privileges[$key]);
        }

        if (!$privileges) {
            $this->apiReturn(204, [], '未找到任何特权产品');
        }

        $this->_privileges = $privileges;

        $error = [];
        foreach ($products as $pid => $num) {

            if (isset($privileges[$pid])) {

                $res = $this->_isAnnualPayAllowed(
                    $sid,
                    $privileges[$pid]['tid'], 
                    $memberid, 
                    $num, 
                    $privileges[$pid]
                );

                if ($res['status'] == 0) {
                    $error[$pid] = $res['left'];
                }

            } else {
                $tmp = (new Ticket)->getProductInfo($pid);
                $this->apiReturn(204, [], $tmp['p_name']. '无法使用特权支付');
            }
        }

        return $error;
    }


    /**
     * 特权支付剩余次数
     * @param  [type]  $config 特权配置
     * @param  [type]  $num    购买张数
     * @return array         [description]
     */
    private function _isAnnualPayAllowed($sid, $tid, $memberid, $num, $config) {

        //不限制
        if ($config['use_limit'] == -1) {
            $this->_pri_left[$tid] = -1;
            return ['status' => 1];
        }

        $times = $this->_CardModel->getRemainTimes($sid, $tid, $memberid);

        $limit_count = explode(',', $config['use_limit']);

        $left_arr = [];

        foreach ($limit_count as $i => $val) {
            if ($val[$i] != -1 && $times[$i] + $num > $val) {
                $left_arr[] = ($val - $times[$i]);
            }

            $this->_pri_left[$tid][] = $val[$i] == -1 ? -1 : $val - ($times[$i] + $num);
        }

        if (count($left_arr) > 0) {
            return ['status' => 0, 'left' => min($left_arr)];
        } else {
            return ['status' => 1];
        }
    }

    /**
     * 下单动作
     * @param  [type] $products [[pid => num]]
     * @param  [type] $aid      供应商id
     * @param  [type] $memberid 会员id
     * @param  [type] $extra    额外信息
     * @return [type]           [description]
     */
    private function _orderAction($products, $aid, $memberid, $extra = []) {

        array_map(function($class) {
            include '/var/www/html/new/d/class/' . $class;
        }, ['DisOrder.php', 'Member.php', 'ProductInfo.php']);

        if (!isset($GLOBALS['le'])) {
            include_once("/var/www/html/new/conf/le.je");
            $le = new \go_sql();
            $le->connect();
            $GLOBALS['le'] = $le;
        }

        $pid = key($products);  //主票，剩下的为联票
        $tnum = $products[$pid];
        unset($products[$pid]);

        $lian = $products;

        $soap = $this->getSoap();

        $Pro        = new \ProductInfo($soap, $pid, $aid);
        $Member     = new \Member($soap, $memberid);
        $DisOrder   = new \DisOrder($soap, $Pro, $Member);
        $DisOrder->setSaleMode();

        $options = [
            'pid'       => $pid,
            'begintime' => date('Y-m-d'),
            'ordername' => $Member->m_info['dname'],
            'ordertel'  => $Member->m_info['mobile'],
            'tnum'      => $tnum,
            'c_pids'    => $lian,
            'paymode'   => 12
        ];

        try {
            return $DisOrder->order($options, $aid);

        } catch (DisOrderException $e) {

            $this->api(204, [], $e->getMessage());
        } 

    }

    

 }


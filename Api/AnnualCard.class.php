<?php

//年卡终端消费接口

namespace Api;

use Library\Controller;
use Model\Product\AnnualCard as CardModel;
use Model\Member\Member;
use Model\Product\Ticket;

// if ( !defined('PFT_API') ) { exit('Access Deny'); }


 class AnnualCard extends Controller {

    private $_CardModel = null;

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
        $products   = ['25271' => 2, '25584' => 1];

        if (!$aid || !$products) {
            $this->apiReturn(204, [], '参数错误');
        }

        $card = $this->_parseAnnualCard($aid, I('identify'), I('type'));

        //if ($card['sid'] != $aid) {...}

        // 年卡有效期检测
        // if (!$this->_periodOfValidityCheck($card)) {
        //     $this->apiReturn(204, [], '年卡已过期');
        // }

        $error = $this->_privilegesCheck($card['sid'], $card['memberid'], $products, $card['pid']);

        if (count($error) > 0) {
            //账户余额是否足够支付,一期都是0
            // $this->_balanceEnough();    
            
            $this->apiReturn(202, $error, ['特权次数不足']);
        }

        $this->_orderAction($products, $aid, $card['memberid'], I(null));


    }

    /**
     * 获取年卡的包含的特权产品
     * @param  int    $aid      供应商id
     * @param  string $identify 标识
     * @param  string $type     物理卡号|手机号
     * @return [type]           [description]
     */
    private function _parseAnnualCard($aid, $identify, $type = 'physics_no') {

        $options = [];

        switch ($type) {

            case 'physics_no':
                $options['where'] = [
                    'sid'        => $aid,
                    'physics_no' => $identify
                ];

                break;

            case 'mobile':
                $member = (new Member)->getMemberInfo($identify, 'mobile');

                if (!$member) {
                    $this->apiReturn(204, [], '会员账户不存在');
                }

                $options['where'] = [
                    'sid'       => $aid,
                    'memberid'  => $member['id']
                ];

                break;

            default:
                $this->apiReturn(204, [], '请输入正确的标识');

        }

        $card = $this->_CardModel->getAnnualCard(1,1, $options);

        return $card ?: false;

    }

    /**
     * 年卡是否处于有效期
     * @param  [type] $card 年卡信息
     * @return [type]       [description]
     */
    private function _periodOfValidityCheck($card) {
        $ticket = (new Ticket())->getTicketInfoByPid($card['pid']);

        $config = $this->_CardModel->getAnnualCardConfig($ticket['id']);

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

        $error = [];
        foreach ($privileges as $item) {
            //特权产品
            if (isset($products[$item['pid']])) {

                $res = $this->_isAnnualPayAllowed(
                    $sid,
                    $item['tid'], 
                    $memberid, 
                    $products[$item['pid']], 
                    $item
                );

                if ($res['status'] == 0) {
                    $error[$item['pid']] = $res['left'];
                }
            }
        }

        return $error;
    }


    /**
     * 特权支付剩余次数
     * @param  [type]  $config 特权配置
     * @param  [type]  $num    购买张数
     * @return boolean         [description]
     */
    private function _isAnnualPayAllowed($sid, $tid, $memberid, $num, $config) {

        $times = $this->_CardModel->getRemainTimes($sid, $tid, $memberid);

        $limit_count = explode(',', $config['limit_count']);

        $left_arr = [];

        foreach ($limit_count as $i => $val) {

            if ($val[$i] != -1 && $times[$i] + $num > $val) {
                $left_arr[] = ($val - $times[$i]);
            }

        }


        if (count($left_arr) > 0) {
            return ['status' => 0, 'left' => min($left_arr)];
        } else {
            return ['status' => 1];
        }
    }

    private function _orderAction($products, $aid, $memberid, $extra) {
        // $aid=6970;
        include '/var/www/html/new/d/class/DisOrder.php';
        include '/var/www/html/new/d/class/Member.php';
        include '/var/www/html/new/d/class/ProductInfo.php';

        if (!isset($GLOBALS['le'])) {
            include_once("/var/www/html/new/conf/le.je");
            $le=new \go_sql();
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

        $options = [
            'pid'       => $pid,
            'begintime' => date('Y-m-d'),
            'ordername' => $Member->m_info['dname'],
            'ordertel'  => $Member->m_info['mobile'],
            'tnum'      => $tnum,
            'c_pids'    => $lian,
            'paymode'     => 12
        ];

        try {

            $order_info = $DisOrder->order($options, $aid);

            $this->apiReturn(200, [], '下单成功');

        } catch (DisOrderException $e) {

            $this->api(204, [], $e->getMessage());

        }

    }

 }


<?php
/**
 * User: Fang
 * Date: 2016/6/27
 * Time: 17:57
 */
namespace Model\Finance;


use Model\Member\Member;

trait TradeRecordMemberRecomposer
{
    private $memberModel;
    private $account_types;
    private $member;
    private $partner;
    private $is_acc_reverse;

    public function __construct()
    {
        $this->account_types = C('account_type');
        $pay_types = array_combine(array_keys(C('pay_type')), array_column(C('pay_type'), 2));
        $this->online_pay_type = array_keys($pay_types, 0);
    }

    /**
     * 获取交易账户类型
     *
     * @param   int $acc_type 账户类型
     * @param   int $is_payee 是否收款方
     * @param   int $memberid 会员id
     *
     * @return int|string
     */
    private function getDefaultAccountType($acc_type, $is_payee, $memberid)
    {
        if ($acc_type && $acc_type != -1) {
            return $acc_type;
        }

        switch ($this->record['ptype']) {
            //授信账户和现金账户双方账户类型相同
            case 2: //no break;
            case 3: //no break;
            case 9: //no break;
                return $this->record['ptype'];
            case 0:
                //非取消订单
                if (!in_array($this->record['dtype'], [1, 17])) {
                    return 0;
                } else {
                    if($memberid == 112 || !$memberid) {
                        return '';
                    } else {
                        return 0;
                    }
                }
            default:
                if ($is_payee) {
                    return 0;
                } else {
                    return $this->record['ptype'];
                }
        }
    }


    /**
     * 判断双方用户的收付款关系
     */
    private function whoIsPayee()
    {
        $this->member['is_payee'] = ($this->record['daction'] == 0);
        $this->partner['is_payee'] = !($this->member['is_payee']);
    }

    /**
     * 重组交易详情中的会员信息
     *
     * @param array $member 会员
     *
     * @return string
     */
    private function _recomposeMemberDetail($member)
    {
        if (isset($member['acc_type'])) {
            $extend_info = $member['acc_type'];
            if (isset($member['account']) && in_array($member['acc_type'], [0, 1])) {
                $extend_info = self::combineStr([$extend_info, $member['account']], ':');
            }
            $extend_info = self::join_bracket([$extend_info]);
        } else {
            $extend_info = '';
        }
        $result = $member['dname'] ? self::combineStr([$member['dname'], $extend_info], '') : '';

        return $result;
    }

    /**
     * 获取商户的交易账户类型说明
     *
     * @param $acc_type
     * @param $is_fid
     *
     * @return string
     */
    private function getMemberAccountType($acc_type, $is_fid)
    {
        if (in_array($acc_type, [2, 3])) {
            return $is_fid ? '信用账户(分销商)' : '信用账户(供应商)';
        }

        return $this->account_types[ $acc_type ];
    }

    /**
     * 获取商户基本信息
     *
     * @param array $member 会员
     * @param bool  $is_fid 是否为fid
     */
    private function getMemberInfo(&$member, $is_fid)
    {
        $member['dname'] = $this->getMemberName($member['id']);
        $member['trade_acc'] = $this->getMemberTradeAccount($member['is_payee']);
        $member['account'] = $this->getMemberAccount($member['id'], $member['acc_type'], $member['trade_acc']);
        $member['acc_type'] = $this->getMemberAccountType($member['acc_type'], $is_fid);
    }

    /**
     * 获取商户名
     *
     * @param int $memberid 会员id
     *
     * @return string
     */
    private function getMemberName($memberid)
    {
        if (!$memberid) {
            return '';
        } elseif ($memberid == 1) {
            return '票付通信息科技';
        } else {
            return $this->getMemberModel()->getMemberCacheById($memberid,
                'dname') ?: '';
        }
    }

    /**
     * 获取会员交易账号
     *
     * @param   bool $is_payee 是否收款方
     *
     * @return  mixed
     */
    private function getMemberTradeAccount($is_payee)
    {
        if ($is_payee) {
            return $this->record['payee_acc'];
        } else {
            return $this->record['payer_acc'];
        }
    }


    /**
     * 初始化交易双方信息
     */
    private function initMemberInfo()
    {
        $this->member['id'] = $this->record['fid'];
        $this->partner['id'] = $this->record['aid'];
        $this->whoIsPayee();
        $this->member['acc_type'] = $this->getDefaultAccountType($this->record['member_acc_type'],
            $this->member['is_payee'], $this->member['id']);
        $this->partner['acc_type'] = $this->getDefaultAccountType($this->record['partner_acc_type'],
            $this->partner['is_payee'], $this->partner['id']);
        if (!in_array($this->member['acc_type'], [0, 2, 3]) && $this->record['lmoney'] == 0) {
            $this->record['lmoney'] = '';
        }
        $this->getMemberInfo($this->member, true);
        $this->getMemberInfo($this->partner, false);
    }

    /**
     * 获取商家账号
     *
     * @param   int $memberid  商户id
     * @param   int $acc_type  交易账户类型
     * @param   int $trade_acc 交易账户
     *
     * @return string
     */
    private function getMemberAccount($memberid, $acc_type, $trade_acc)
    {
        if (!$memberid || $memberid == 1) {
            return '';
        } else {
            if (in_array($acc_type, [0, 2, 3])) {
                return $this->getMemberModel()->getMemberCacheById($memberid,
                    'account') ?: '';
            } elseif ($acc_type == 1) {
                return $trade_acc;
            } else {
                return '';
            }
        }
    }

    /**
     * 获取会员模型
     *
     * @return mixed
     */
    public function getMemberModel()
    {
        if (!isset($this->memberModel)) {
            $this->memberModel = new Member();
        }

        return $this->memberModel;
    }


}
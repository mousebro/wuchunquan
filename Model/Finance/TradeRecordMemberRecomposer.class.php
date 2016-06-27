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

    private function getPartnerAccountType($partner_acc_type, $pay_type)
    {
        if (!$partner_acc_type) {
            if (in_array($pay_type, [0, 2, 3])) {
                return $pay_type;
            } else {
                return 0;
            }
        } else {
            return $partner_acc_type;
        }
    }

//获取会员账号

    private function whoIsPayee()
    {
        $this->member['is_payee'] = ($this->record['daction'] == 0);
        $this->partner['is_payee'] = !($this->member['is_payee']);
    }

    private function _recomposeMemberDetail($member)
    {
        if (isset($member['acc_type'])) {
            $extend_info = $member['acc_type'];
            if (isset($member['account'])) {
                $extend_info = self::combineStr([$extend_info, $member['account']], ':');
            }
            $extend_info = self::join_bracket([$extend_info]);
        } else {
            $extend_info = '';
        }
        $result = $member['dname'] ? self::combineStr([$member['dname'], $extend_info], '') : '';

        return $result;
    }

    private function getMemberAccountType($acc_type, $is_fid)
    {
        if (in_array($acc_type, [2, 3])) {
            return $is_fid ? '分销商授信账户' : '供应商授信账户';
        }

        return $this->account_types[ $acc_type ];
    }

    private function getMemberInfo(&$member, $is_fid)
    {
        $member['dname'] = $this->getMemberName($member['id']);
        $member['trade_acc'] = $this->getMemberTradeAccount($member['is_payee']);
        $member['account'] = $this->getMemberAccount($member['id'], $member['acc_type'], $member['trade_acc']);
        $member['acc_type'] = $this->getMemberAccountType($member['acc_type'], $is_fid);
    }

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

    private function getMemberTradeAccount($is_payee)
    {
        if ($is_payee) {
            return $this->record['payee_acc'];
        } else {
            return $this->record['payer_acc'];
        }
    }

    //获取会员名(dname)

    private function initMemberInfo()
    {
        $this->member['id'] = $this->record['fid'];
        $this->partner['id'] = $this->record['aid'];
        $this->member['acc_type'] = $this->record['member_acc_type'];
        $this->partner['acc_type'] = $this->getPartnerAccountType($this->record['partner_acc_type'],
            $this->record['ptype']);
        $this->whoIsPayee();
        $this->getMemberInfo($this->member, true);
        $this->getMemberInfo($this->partner, false);
    }

    private function getMemberAccount($memberid, $acc_type, $trade_acc)
    {
        if (!$memberid || $memberid == 1) {
            return '';
        } else {
            if (in_array($acc_type, [0, 2, 3])) {
                return $this->getMemberModel()->getMemberCacheById($memberid,
                    'account') ?: '';
            } else {
                return $trade_acc;
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
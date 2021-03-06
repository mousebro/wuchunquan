<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/18-018
 * Time: 14:50
 */

namespace Model\Member;
use Library\Cache\Cache as cache;
use Library\Model;
class Member extends Model
{
    const __MEMBER_TABLE__ = 'pft_member';
    const __MEMBER_EXT_TABLE__ = 'pft_member_extinfo';
    const __MEMBER_RELATIONSHOP_TABLE__ = 'pft_member_relationship';

    const MONEY_ADD = 0;
    const MONEY_CUT = 1;

    const MONEY_TYPE_ACC = 0;//账户余额
    const MONEY_TYPE_CRE = 1;//授信账户

    const ACCOUNT_MONEY             = 0;//账户余额
    const ACCOUNT_APPLYER_MONEY     = 1;//可用供应商余额
    const ACCOUNT_APPLYER_CREDIT    = 2;//信用额度
    const ACCOUNT_APPLYER_BOTH      = 3;//可用供应商余额 & 信用额度

    const D_TYPE_BUY                = 0;//下单
    const D_TYPE_CANCEL_ORDER       = 1;//取消
    const D_TYPE_ACCOUNT_MONEY      = 3;//账户资金变化
    const D_TYPE_APPLYER_MONEY      = 4;//供应商处可用资金变化
    const D_TYPE_PROFIT             = 5;//5利润
    const D_TYPE_FREEZE             = 6;//提现或转账的资金冻结
    const D_TYPE_CODE               = 7;//凭证码
    const D_TYPE_SMS                = 8;//短信息费
    const D_TYPE_BANK_CHARGE        = 9;//银行交易手续费
    const D_TYPE_PLATFORM_FEE       = 10;//平台使用费
    const D_TYPE_APPLYER_CREDIT     = 11;//供应商信用额度变化
    const D_TYPE_CACEL_WITHDRAW     = 12;//取消提现
    const D_TYPE_REFUSE_WITHDRAW    = 13;//拒绝提现
    const D_TYPE_CANCEL_FEE         = 14;//退款手续费
    const D_TYPE_ALLIANCE_DEPOSIT   = 15;//联盟押金
    const D_TYPE_RETURNING_CASH     = 16;//充值返现
    const D_TYPE_MACHINE_CANCEL     = 17;//撤销撤改
    const D_TYPE_ACCOUNT_TRANS      = 18;//转账
    //金额类型
    const P_TYPE_ACCOUNT_MONEY      = 0;//帐号资金
    const P_TYPE_ACCOUNT_ALIPAY     = 1;//支付宝
    const P_TYPE_APPLYER_MONEY      = 2;//供应商处可用资金
    const P_TYPE_APPLYER_CREDIT     = 3;//供应商信用额度设置
    const P_TYPE_ONLINE_TXPAY       = 4;//财付通
    const P_TYPE_ONLINE_UNPAY       = 5;//银联
    const P_TYPE_ONLINE_HXPAY       = 6;//环迅

    protected $connection = '';

    public function __construct($defaultDb='localhost', $tablePrefix='pft')
    {
        parent::__construct($defaultDb, $tablePrefix);
    }

    private function getLimitReferer()
    {
        return  array(
            '12301.cc',
            '16u.cc',
            '12301.local',
            '12301.test',
            '12301dev.com',
            '9117you.cn',
            '9117you.cn',
            );
    }

    public function login($account, $password, $chk_code='')
    {
        $where = [
            'account|mobile'  =>':account',
            'status'          =>':status',
            //':password'=>':password',
        ];
        $res = $this->table('pft_member')
            ->getField('id,account,member_auth,dname,satus,id,password,derror,errortime,dtype')
            ->where($where)
            ->bind([':account'=>$account, ':status'=>[0, 3]])
            ->find();
        if (!$res)  return false;
        if ($res['password']!=$password) {
            $this->table('pft_member')
                ->where("id={$res['id']}")
                ->save(
                    [
                        'derror'    =>$res['derror']+1,
                        'errortime' =>date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
                    ]);
            return ['code'=>201,'msg'=>'账号或密码错误'];
        }
    }

    /**
     * 根据账号获取用户信息
     * @param  mixed $identify 字段值
     * @param  mixed $field    字段名
     * @return mixed        [description]
     */
    public function getMemberInfo($identify, $field = 'id') {
        
        $where[$field] = $identify;

        $member = $this->table(self::__MEMBER_TABLE__)->where($where)->find();

        return $member ?: false;
    }

    /**
     * 一次性获取多用户信息
     * @param  [type] $identify_arr [description]
     * @param  string $column       [description]
     * @param  string $field        [description]
     * @return [type]               [description]
     */
    public function getMemberInfoByMulti($identify_arr, $column = 'id', $field = '*') {

        $where[$column] = array('in', implode(',', $identify_arr));

        $members = $this->table(self::__MEMBER_TABLE__)->where($where)->field($field)->select();

        return $members ?: false;
    }


    /**
     * 根据账号获取用户扩展表信息
     * @param  mixed $identify 字段值
     * @param  mixed $field    字段名
     * @return mixed        [description]
     */
    public function getMemberExtInfo($fid, $field = 'id') {
        $where['fid'] = $fid;
        $member = $this->table(self::__MEMBER_EXT_TABLE__)->where($where)->getField($field);
        return $member ?: false;
    }

    /**
     * 从缓存里面获取会员的数据
     * @author Guangpeng Chen
     * @param int $id 会员ID
     * @param string $field 需要的字段
     * @return bool|mixed
     */
    public function getMemberCacheById($id, $field)
    {
        /** @var $cache \Library\Cache\CacheRedis;*/
        $cache = Cache::getInstance('redis');
        $name = "member:$id";
        $data = $cache->hget($name, '', $field);
        if (!$data) {
            $data = $this->table(self::__MEMBER_TABLE__)->where("id=$id")->getField($field);
            $cache->hset($name, '', [$field=>$data]);
        }
        return $data;
    }

    /**
     * 检测旧密码是否正确
     *
     * @param $memberid
     * @param $old_password
     * @return bool
     */
    public function checkOldPassword($memberid, $old_password)
    {
        $old = $this->table(self::__MEMBER_TABLE__)->where(['id'=>$memberid])->getField('password');
        return $old_password == $old;
    }

    /**
     * 检查是否建立过对应的关系
     *
     * @param $parent_id
     * @param $son_id
     * @param $ship_type
     */
    public function checkRelationShip($parent_id, $son_id, $ship_type)
    {
        $where = [
            'parent_id'=>':parent_id',
            'son_id'   => ':son_id',
            'ship_type' => ':ship_type',
        ];
        $bind = [
            ':parent_id'=> $parent_id,
            ':son_id'   => $son_id,
            ':ship_type'=> $ship_type,
        ];
        return $this->table(self::__MEMBER_RELATIONSHOP_TABLE__)
            ->where($where)
            ->bind($bind)
            ->getField('id');
    }

    /**
     * 重置用户密码
     * @param  [type] $memberid     [description]
     * @param  [type] $new_password [description]
     * @return [type]               [description]
     */
    public function resetPassword($memberid, $new_password, $hasMd5=false) {
        $new_password = $hasMd5 ? md5($new_password) : md5(md5($new_password));
        $data = array(
            'id'        => $memberid,
            'password'  => $new_password
        );
        $affect_rows = $this->table(self::__MEMBER_TABLE__)->save($data);
        return $affect_rows ? true : false;
    }

    /**
     * 查询账户余额或授信额度
     *
     * @param int $mid 会员ID
     * @param int $dmode 查询模式0账户余额1授信额度2授信余额3授信额度和授信余额
     * @param int $aid 供应商ID dmode>0必须
     * @param bool $order 是否按数组键值方式返回
     * @return mixed
     */
    public function getMoney($mid, $dmode, $aid=0, $ret_arr=false)
    {
        if ($dmode==0) {
            return $this->table('pft_member_money')
                ->where(['fid'=>$mid])->limit(1)
                ->getField('amoney');
        }
        $field  = 'kmoney';
        if ($dmode==2) $field='basecredit';
        elseif ($dmode==3) $field .= ',basecredit';
        $query = $this->table('pft_member_credit')->where(['fid'=>$mid, 'aid'=>$aid])->limit(1);
        if ($ret_arr===true) {
            return $query->field($field)->find();
        }

        return $query->getField($field);
        //echo $this->_sql();
        //echo $this->getDbError();
        //print_r($ret);

    }

    /**
     * 检查是否有授信关系
     *
     * @author Guangpeng Chen
     * @param int $mid 分销商id
     * @param int $aid 供应商ID
     * @return mixed
     */
    private function checkCreditExitst($mid, $aid)
    {
        $id = $this->table('pft_member_credit')
                ->where(['fid'=>$mid, 'aid'=>$aid])->getField('id');
        if (!$id) {
            $id = $this->table('pft_member_credit')->data(['fid'=>$mid, 'aid'=>$aid])->add();
        }
        return $id;
    }
    /**
     * 资金修改模块 dtype[6]+action[0]冻结资金，dtype[6]+action[1]解冻资金
     * @author Guangpeng Chen
     * @date  2016年4月27日14:10:25
     *
     * @param $id int 会员ID
     * @param $opID int 操作人员ID
     * @param $Mmoney int 金额
     * @param int $action int 修改动作action：0增加1减少
     * @param int $dmode int 修改类型dmode：0账户余额1可用供应商余额2信用额度
     * @param null $aid int 供应商ID[dmode>0时必填]
     * @param null $dtype int D_TYPE
     * @param null $ptype int P_TYPE
     * @param string $orderid string 订单号
     * @param null $memo string 备注说明
     * @param null $memo string 备注说明
     * @param false $accountType 收款账号类型(支出流水和ptype一致，收入流水就要看具体去向)：0帐号资金 1支付宝 2供应商处可用资金 3供应商信用额度设置 4财付通 5银联 6环迅 9现金 11商户拉卡拉 12平台拉卡拉
     *      收入流水需要主动传入该参数（主要是有在线支付的情况下）
     * @param string $tradeNo 外部流水号
     * @return int|string
     */

    public function PFT_Member_Fund_Modify($id, $opID, $Mmoney, $action=0, $dmode=0, $aid=NULL,
                                           $dtype=NULL, $ptype=NULL, $orderid='', $memo='', $accountType = false, $tradeNo = false)
    {
        if ($dmode>0 && (!$aid || $aid<0)) return false;
        $id  = (int)$id;
        $aid = (int)$aid;
        $act    = $action ? "-" : "+";
        $act_res= $action ? "+" : "-";
        if ($dmode>0 && $dtype==6) {
            E('授信额度不允许提现');
        }

        $journalData = [
            'fid'       => $id,
            'opid'      => $opID,
            'aid'       => $aid ? $aid : 0,
            'dmoney'    => $Mmoney,
            'orderid'   => $orderid,
            'daction'   => $action,
            'dtype'     => $dtype,
            'ptype'     => $ptype,
            'memo'      => $memo,
            'rectime'   => date('Y-m-d H:i:s'),
        ];
        $result1 = true;
        $result3 = true;
        $this->startTrans();
        if ($dmode==0) {
            $result1 = $this->Table('pft_member_money')
                ->where(['fid'=>$id])
                ->data(['amoney'=>['exp', "amoney{$act}{$Mmoney}"]])
                ->limit(1)->save();
            if ($dtype==6){
                $result3 = $this->Table('pft_member_money')
                    ->where(['fid'=>$id])
                    ->data([
                            'frozentime'=> time(),
                            'fmoney'    => ['exp', "fmoney{$act_res}{$Mmoney}"],
                        ])
                    ->limit(1)->save();
                ;
            }
            $journalData['dtype']  = (!is_numeric($dtype)) ? 3 : $dtype;
            $journalData['ptype']  = (!is_numeric($ptype)) ? 0 : $ptype;
        }
        elseif ($dmode==1){
            //查看有无记录
            $this->checkCreditExitst($id, $aid);
            $result1 = $this->Table('pft_member_credit')
                ->where(['fid'=>$id,'aid'=>$aid])
                ->data(['kmoney'=>['exp', "kmoney{$act}{$Mmoney}"]])
                ->limit(1)->save();
            $journalData['dtype']  = (!is_numeric($dtype))?4:$dtype;
            $journalData['ptype']  = (!is_numeric($ptype))?1:$ptype;
        }
        elseif ($dmode==2){
            //查看有无记录
            $this->checkCreditExitst($id, $aid);
            $result1 = $this->Table('pft_member_credit')->where(['fid'=>$id,'aid'=>$aid])
                ->data( [
                    'basetime'      => time(),
                    'baseauthority' => $opID,
                    'basecredit'    => ['exp', "basecredit{$act}{$Mmoney}"]
                    ] )
                ->limit(1)->save();
            $journalData['dtype'] =(!is_numeric($dtype)) ? 11 : $dtype;
            $journalData['ptype'] =(!is_numeric($ptype)) ? 3  : $ptype;
        }

        //添加收款人标识
        if($accountType !== false) {
            $accountType = intval($accountType);
            $journalData['account_type'] = $accountType;
        } else {
            $journalData['account_type'] = $journalData['ptype'];
        }

        //添加外部流水号
        if($tradeNo !== false) {
            $tradeNo = strval($tradeNo);
            $journalData['trade_no'] = $tradeNo;
        }

        $journalData['lmoney'] = $this->getMoney($id, $dmode, $aid);
        $result2 = $this->table('pft_member_journal')
            ->data($journalData)
            ->add();
        if ($result1 && $result2 && $result3) {
            $this->commit();
            return ['code'=>200, 'msg'=>'ok'];
        }
        $this->rollback();
        return ['code'=>401, 'msg'=>'sql:'.$this->getLastSql() .',errmsg:'.  $this->getDbError()];
    }
    public function addMemberJournal($id, $opID, $Mmoney, $action=0, $aid=NULL,
                                     $dtype=NULL, $ptype=NULL, $orderid='', $memo='', $tradeNo = false, $accountType = false)
    {
        $journalData = [
            'fid'       => $id,
            'opid'      => $opID,
            'aid'       => $aid ? $aid : 0,
            'dmoney'    => $Mmoney,
            'orderid'   => $orderid,
            'daction'   => $action,
            'dtype'     => $dtype,
            'ptype'     => $ptype,
            'memo'      => $memo,
            'rectime'   => date('Y-m-d H:i:s'),
        ];

        //添加外部流水号
        if($tradeNo !== false) {
            $tradeNo = strval($tradeNo);
            $journalData['trade_no'] = $tradeNo;
        }

        //添加交易账号类型
        if($accountType !== false) {
            $accountType = intval($accountType);
            $journalData['account_type'] = $accountType;
        }

        $result2 = $this->table('pft_member_journal')
            ->data($journalData)
            ->add();
        return $result2;
    }
    /**
     * 收取短信费
     * @author Guangpeng Chen
     *
     * @param $memberId
     * @param $count
     * @param string $ordernum
     * @param int $fromMemberId
     * @return int|string
     */
    public function ChargeSms($memberId, $count, $ordernum='', $fromMemberId=1)
    {
        $fee_sms = $this->getMemberCacheById($memberId, 'fee_sms');
        $dmoney  = $fee_sms * $count;
        $this->PFT_Member_Fund_Modify($fromMemberId, 0, $dmoney, self::MONEY_ADD, 0, $memberId,
            self::D_TYPE_SMS, self::P_TYPE_ACCOUNT_MONEY, $ordernum);
        $this->PFT_Member_Fund_Modify($memberId, 0, $dmoney, self::MONEY_CUT, 0, $fromMemberId,
            self::D_TYPE_SMS, self::P_TYPE_ACCOUNT_MONEY, $ordernum);
        return true;
    }

    /**
     * 获取账号权限
     * @return [type] [description]
     */
    public function getMemberAuth($memberid) {
        $auth = $this->table(self::__MEMBER_TABLE__)->where(['id' => $memberid])->getField('member_auth');

        if (!$auth) {
            return [];
        } else {
            return explode(',', $auth);
        }
    }

}
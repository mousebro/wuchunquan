<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/12-012
 * Time: 15:12
 */

namespace Library\MessageNotify;


use Library\Model;
use Model\Wechat\WxMember;

class OrderNotifyBase
{
    protected $order_tel;
    protected $aid;
    protected $fid;
    protected $tid;
    protected $pid;
    protected $p_type;
    protected $order_num;
    protected $soap;
    protected $db = null;
    protected $source_apply_did;

    protected $begin_time = null;
    protected $end_time   = null;

    const SMS_FORMAT_STR  = 1;
    const SMS_FORMAT_ARR  = 2;

    protected function GetApplyDid($lid)
    {
        $m = new Model('slave');
        return $m->table('uu_land')->where(['id'=>$lid])->getField('apply_did, p_type');
    }
    /**
     * 设置参数
     *
     * @param string $order_tel 客户手机号
     * @param int $aid 上级供应商id
     * @param int $fid 分销商id
     * @param int $tid 票id
     * @param int $pid 产品id
     * @param string $order_num 订单号
     * @param int $lid 景区id
     */
    public function SetParam($order_tel, $fid, $tid, $pid, $order_num, $lid)
    {
        $this->order_tel = (string)$order_tel;
        $this->fid = (int)$fid;//$mainOrder['UUmid'];
        $this->tid = (int)$tid;//$mainOrder['UUtid'];
        $this->pid = (int)$pid;//$mainOrder['UUpid'];
        $this->order_num = (string)$order_num;//$mainOrder['UUordernum'];
        list($this->source_apply_did, $this->p_type) = $this->GetApplyDid((int)$lid);
    }
    protected function GetApplerTel($pids)
    {
        $map = is_array($pids) ? ['pid'=>['in', $pids]] : ['pid'=>$pids];
        $model = new Model('slave');
        return $model->table('uu_land_f f')->join('uu_land l on l.id=f.lid')
            ->field('f.pid,f.confirm_sms,f.confirm_wx,l.fax')
            ->where($map)
            ->select();
    }
    /**
     * 检查是否可以通过微信发送通知
     *
     * @param int $fid 会员id
     * @param int $limit 是否限制查询条数，大于0表示限制返回条数
     * @return bool/string false/微信openid
     */
    public function WxNotifyChk($fid, $limit=0, $useOtherAppid=false)
    {
        $wx = new WxMember();
        if (is_bool($useOtherAppid)) {
            $appid = $useOtherAppid ? WECHAT_APPID : OpenExt::PFT_APP_ID;
        } else {
            $appid = $useOtherAppid;
        }
        $data = $wx->getWxInfo($fid, $appid);

        $tmp    = array();
        $openid = array();
        foreach ($data as $row) {
            if ($row['verifycode']==1) {
                $openid[] = $row['fromusername'];
            }
            $tmp[] = $row['fromusername'];
        }
        $cnt = count($openid);
        if (($cnt==1||$cnt==0) && count($tmp)) {
            //如果都没有设置的话，那么随机选择一个
            return array_shift($tmp);
        }
        elseif(count($openid)) {
            return $openid;
        }
        return false;

    }
}
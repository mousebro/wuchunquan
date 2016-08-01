<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/10-010
 * Time: 13:46
 *
 * 订单追踪
 */

namespace Model\Order;


use Library\Model;

//CREATE TABLE `pft_order_track` (
//  `id` int(10) NOT NULL AUTO_INCREMENT,
//  `ordernum` varchar(20) NOT NULL,/*订单号*/
//`action` tinyint(1) unsigned not null default 0,/*事件类型 0 验证 1 取消 2 出票 3支付 4验证 5撤销 6撤改 7提交审核 8操作审核  */
//  `tid` int(10) unsigned not null,/*本次操作票类*/
//  `tnum` int(10) unsigned not null,/*本次操作门票张数*/
//  `left_num` int(10) unsigned not null,/*剩余门票张数*/
//  `source` tinyint(1) unsigned not null default 0,/* 操作来源 0 终端机 1 软终端 2 自助机 3 外部通知更新 4云票务 5云闸机*/
//  `terminal` int(11) unsigned not null,/* 终端号 */
//  `branchTerminal` int(11) unsigned not null,/* 终端号分支 */
//  `id_card` varchar(20) not null, /* 验证身份证/手机号/凭证码 */
//  `insertTime` datetime NOT NULL,/*记录时间*/
//  `oper_member`  int(10) unsigned not null,/*操作员id*/


class OrderTrack extends Model
{
    const ORDER_CREATE       = 0;
    const ORDER_MODIFY       = 1;
    const ORDER_PAY          = 4;
    const ORDER_VERIFIED     = 5;
    const ORDER_VERIFIED_CANCEL = 6;//撤销
    const ORDER_VERIFIED_CHG = 7;//撤改
    const ORDER_EXPIRE       = 12;

    const SOURCE_INSIDE_SOAP = 16;
    const SOURCE_OUTSIDE_SOAP = 17;
    public static function getSourceList()
    {
        return [
            0=>'终端机',
            1=>'软终端',
            2=>'自助机',
            3=>'外部通知更新',
            4=>'云票务',
            5=>'云闸机',
            6=>'PC-支付宝',
            7=>'手机支付宝',
            8=>'支付宝刷卡',
            9=>'支付宝扫码',
            10=>'微信支付',
            11=>'微信刷卡',
            12=>'微信扫码',
            13=>'PC-银联',
            14=>'手机-银联',
            15=>'PC-环迅',
            16=>'内部接口',
            17=>'外部接口',
            18=>'undefined',
            19=>'自运行服务',
            20=>'安卓智能终端机',
            21=>'验证服务器',
        ];
    }
    public static function getActionList()
    {
        return [
            0=>'下单',
            1=>'修改',
            2=>'取消',
            3=>'出票',
            4=>'支付',
            5=>'验证',
            6=>'撤销',
            7=>'撤改',
            8=>'重打印',
            9=>'离线订单下载',
            10=>'处理退票申请',
            11=>'提交退票申请',
            12=>'过期',
            13=>'同意退票申请',
            14=>'拒绝退票申请',
            15=>'核销',
        ];
    }
    /**
     * 新增追踪记录
     *
     * @param $ordernum string 订单号
     * @param $action int 事件类型
     * @param $tid int 门票ID
     * @param $tnum int 本次记录票数
     * @param $left_num int 剩余票数
     * @param $source int 来源
     * @param $terminal_id int 终端ID
     * @param $branch_terminal int 分终端ID
     * @param $id_card string 身份证
     * @param $oper int 操作员ID
     * @param $salerid int 景区6位ID
     * @param $create_time string 时间
     * @return mixed
     */
    public function addTrack($ordernum, $action, $tid, $tnum, $left_num, $source, $terminal_id=0,
        $branch_terminal=0, $id_card='', $oper=0,$salerid=0, $create_time='', $msg='')
    {
        $oper = $oper ? $oper : 0;
        $data = [
            'ordernum'       => $ordernum,
            'action'         => $action,
            'tid'            => $tid,
            'tnum'           => $tnum,
            'left_num'       => $left_num,
            'source'         => $source,
            'terminal'       => $terminal_id,
            'branchTerminal' => $branch_terminal,
            'id_card'        => $id_card,
            'SalerID'        => $salerid,
            'insertTime'     => empty($create_time) ? date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) : $create_time,
            'oper_member'    => $oper,
            'msg'            => $msg
        ];
        $last = $this->table('pft_order_track')->data($data)->add();
        echo $this->getDbError();
        return $last;
    }

    /**
     * 订单追踪多记录插入
     * @param $data
     */
    public function addTrackMulti($data)
    {
        $this->table('pft_order_track')->addAll($data);
    }

    public function getTnumByAction($ordernum, $action=5)
    {
        return $this->table('pft_order_track')
                ->where(['ordernum'=>$ordernum, 'action'=>$action])
                ->sum('tnum');
    }

    public function getLog($ordernum, $source=null, $action=null)
    {
        $where['ordernum'] = ':ordernum';
        if (!is_null($source) && is_numeric($source)) $where['source'] = $source;
        if (!is_null($action) && is_numeric($action)) $where['action'] = $action;
        return $this->Table('pft_order_track')
            ->where($where)
            ->bind(':ordernum',$ordernum)
            ->order('id ASC')
            ->select();
    }

    public function QueryLog($ordernum)
    {
        $where['ordernum'] = ':ordernum';
        $where['action'] = array('neq', self::ORDER_EXPIRE);
        $where['tnum'] = array('gt', 0);
        return $this->Table('pft_order_track')
            ->where($where)
            ->field('left_num')
            ->bind(':ordernum',$ordernum)
            ->order('id DESC')
            ->limit(1)
            ->select();
    }
}
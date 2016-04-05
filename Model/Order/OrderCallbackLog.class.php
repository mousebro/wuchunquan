<?php
/**
 * User: Fang
 * Time: 15:47 2016/4/1
 */

namespace Model\Order;


use Library\Model;

class OrderCallbackLog extends Model
{
    private $_orderSyncTable = 'order_status_synchronize';
    private $_memberTable = 'pft_member';
    /**
     * OTANoticeLog constructor.
     */
    public function __construct()
    {
        parent::__construct('orderdata');
    }

//    public function getNoticeList(array $ordernum){
//notice—type 1-修改 2-取消
    public function getNoticeList($orderNum=null,$noticeType=null,$noticeDate=null,$receiver=null,$page=1,$limit=20){
        $orderNum   = $orderNum ? $orderNum : null;
        $noticeType = (in_array($noticeType,[1, 2])) ? $noticeType : null;
        $page       = $page ? $page : 1;
        $limit      = $limit ? $limit : 20;
        $table      = $this->_orderSyncTable;
//        $where = array(
//          'ordernum' => array('in', $ordernum),
//        );
//        $where = array();
            $where['notice_type'] = array('in', [1, 2]);
        if($orderNum){
            $where['ordernum'] = $orderNum; //订单号优先级最高
        }else{
            //变动通知类型
            if($noticeType){
                $where['notice_type'] = $noticeType;
            }
            //通知日期
            if($noticeDate){
                $noticeDate = substr($noticeDate,0,10);
                $bTime = $noticeDate . ' 00:00:00';
                $eTime = $noticeDate . ' 23:59:59';
                $where['pushtime'] = array('between',"{$bTime},{$eTime}");
            }
            //通知接口
            if($receiver){
                $where['pushchannel'] = $receiver;
            }
        }

        $field = array(
            'id as notice_id',
            'ordernum',
            'pushlasttime',
            'pushstatus',
            'notice_type',
        );
        $order = array(
            'id DESC',
        );
        $result = $this->table($table)->field($field)->where($where)->order($order)->page($page)->limit($limit)->select();
        $this->test();
        return $result;
    }

    /**
     * 获取通知接口列表
     * @param string $dname 可按照用户名查询
     *
     * @return mixed
     */
    public function getReceiverList($dname){
        $table = $this->_memberTable;
        $where = array(
            'dcodeurl' => array('neq',''),
        );
        if($dname){
            $where['dname'] = array('like', "%{$dname}%");
        }
        $field = array(
          'dname',
          'id as memberid',
        );
        $result = $this->table($table)->where($where)->field($field)->select();
        return $result;
    }
    /**
     * 测试用：打印调用的sql语句
     *
     * @return string
     */
    private function test()
    {
        $str = $this->getLastSql();
        print_r($str . PHP_EOL);
    }
}
/**
 * order_status_synchronize表内容
 * id                          "1664159"
 * ordernum      订单号         "3283237"
 * pushtext      推送报文       ""
 * pushnum       推送次数       "1"
 * status        状态          "1"
 * pushtime      推送时间       "0000-00-00 00:00:00
 * pushchannel   推送渠道       "0"
 * pushlasttime  上次推送时间    "2016-03-22 15:32:44"
 * pushstatus    推送状态       "0"
 * notice_type   通知类型       "0"
 */
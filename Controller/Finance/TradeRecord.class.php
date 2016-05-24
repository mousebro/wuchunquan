<?php
/**
 * User: Fang
 * Time: 11:14 2016/5/17
 */
namespace Controller\Finance;

use Library\Controller;
use Library\Exception;

class TradeRecord extends Controller
{

    //获取交易记录列表
    public function getList()
    {
        try {
            $memberId = $this->isLogin('ajax');

            //记录传入参数
            $input = ['member'=>$memberId, 'input'=>I('param.')];
            \pft_log('trade_record/get_list/input',json_encode($input));

            //超级管理员查看会员交易记录
            $super = (intval(I('super')) && $memberId == 1);

            $map = [];
            //订单号
            $orderid = \safe_str(I('ordreid'));
            if ($orderid) {
                $map['orderid'] = $orderid;
            }

            //开始时间
            $btime = \safe_str(I('btime'));

            if ($btime && ! strtotime($btime)) {
                throw new Exception('时间格式错误', 201);
            }
            if(!$btime){
                $btime = $orderid ? "2013-10-29 13:47:52" : "today midnight";
            }
            $time[0] = date('Y-m-d H:i:s', strtotime($btime));

            //结束时间 - 默认为当前时间
            $etime = \safe_str(I('etime'));

            if ($etime && ! strtotime($etime)) {
                throw new Exception('时间格式错误', 201);
            }
            if(!$etime){
                $etime = "now";
            }
            $time[1] = date('Y-m-d H:i:s', strtotime($etime));

            //支付方式用"|"分隔
            if (isset($_REQUEST['ptypes'])) {
                $ptypes = \safe_str(I('ptypes'));
                if ($ptypes != '') {
                    $ptypes = explode('|', $ptypes);
                    if ($ptypes != array_intersect($ptypes, array_keys(\Model\Finance\TradeRecord::getPayTypes()))) {
                        throw new Exception('支付类型错误', 202);
                    } else {
                        $map['ptype'] = ['in', $ptypes];
                    }
                }
            }

            //交易类目用"|"分隔
            if (isset($_REQUEST['items'])) {
                $items = \safe_str(I('items'));
                if ($items != '') {
                    $items = explode('|', $items);
                    if ($items != array_intersect($items, array_keys(\Model\Finance\TradeRecord::getTradeItems()))) {
                        throw new Exception('交易类目错误', 202);
                    } else {
                        $subtype = [];
                        foreach($items as $item){
                            $subtype = array_merge($subtype,array_keys(\Model\Finance\TradeRecord::getItemCat(),$item));
                        }

                        if($subtype){
                            $map['dtype'] = ['in', $subtype];
                        }
                    }
                }
            }

            //交易类型
            if (isset($_REQUEST['dtype'])) {
                $dtype = \safe_str(I('dtype'));
                if (is_numeric($dtype) && in_array($dtype,array_column(\Model\Finance\TradeRecord::getItemCat(),0)) ) {
                    if(isset($subtype)){
                        if(!in_array($dtype,$subtype)){
                            throw new Exception('交易类型与交易类目不符');
                        }
                    }
                    $map['dtype'] = $dtype;
                }else{
                    throw new Exception('交易类型错误:', 203);
                }
            }
            $map['dmoney'] = ['gt', 0 ];
            //分页
            $page  = intval(I('page'));
            $limit = intval(I('limit'));
            $page  = ($page > 0) ? $page : 1;
            $limit = ($limit > 0) ? $limit : 15;

            //是否导出excel
            $excel = intval(I('excel'));

            $recordModel = new \Model\Finance\TradeRecord();
            if ($excel) {
                $data = $recordModel->getExList($memberId, $map, $time, $super);

                if (is_array($data)) {
                    $filename = date('YmdHis') . '交易记录';
                    $this->exportExcel($data, $filename);
                } else {
                    throw new Exception('查询失败', '203');
                }
            } else {
                $data = $recordModel->getList($memberId, $map, $time, $page, $limit, $super);
                if (is_array($data)) {
                    $this->apiReturn(200, $data);
                } else {
                    throw new Exception('查询失败', '203');
                }
            }
        } catch (Exception $e) {
            \pft_log('trade_record/get_list/err', $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }

    }

    //获取交易记录详情
    public function getDetails()
    {
        $memberId = $this->isLogin('ajax');
        $recordModel = new \Model\Finance\TradeRecord();

        $trade_id = \safe_str(I('trade_id'));

        if ( !$trade_id) {
            $this->apiReturn(201, [], '传入参数不合法');
        }

        $record = $recordModel->getDetails($trade_id);

        if ( ! empty($record['fid']) && ! empty($record['aid'])) {
            if ( ! in_array($memberId, [1, $record['fid'], $record['aid']])) {
                $this->apiReturn(201, [], '无权查看');
            } else {
                unset($record['fid']);
                unset($record['aid']);
            }
        } else {
            $record = [];
        }

        $this->apiReturn(200, $record, '操作成功');
    }

    protected function exportExcel(array $data,$filename='')
    {
        if(!$filename){
            $filename = date('YmdHis');
        }

//        var_dump($data[0]);
//        exit;
        $r[0] = self::getExcelHead();
        $r = array_merge($r,$data);
        include_once("/var/www/html/new/d/class/SimpleExcel.class.php");
        $xls = new \SimpleExcel('UTF-8', true, 'orderList');
        $xls->addArray($r);
        $xls->generateXML($filename);
        exit;
    }

    public static function getExcelHead()
    {
        return array(
            'rectime'   => '交易时间',
            'orderid'   => '订单号',
            'dtype'     => '交易类型',
            'ptype'     => '支付方式',
            'dmoney'    => '收支',
            'body'      => '交易内容',
            'memo'      => '备注',
            'member'    => '交易商户',
            'counter'   => '对方商户',
            'oper'      => '操作人',
            'cre_money' => '授信余额',
            'acc_money' => '账户余额',

        );
    }
    //$url       = 'http://www.12301.local/route/?c=Finance_TradeRecord&a=test';
    public function test()
    {
        $_SESSION['sid'] = 1;
//        $_REQUEST        = [
//            'orderid' => '3306776',
//            'item'    => '',
//            'btime'   => '2015-05-01 99:00:00',
//            'etime'   => '',
//            'page'    => 1,
//            'limit'   => 1,
//            'excel'   => '',
//            'dtype'   => '',
//            'ptype'   => '',
//            'super'   => 1,
//        ];
        $this->getList();
//        $this->getDetails();
    }
}
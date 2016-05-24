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
            $input = ['member' => $memberId, 'input' => I('param.')];
            \pft_log('trade_record/input', 'get_list|'. json_encode($input));

            $map = [];

            //是否管理员查看所有会员记录--管理员&不传fid
            $super = ($memberId == 1 && ! ($fid = intval(I('fid')))) ? 1 : 0;

            //被查询账号
            $fid = empty($fid) ? $memberId : $fid;

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

            //按订单号查询时可不受时间限制
            if ( ! $btime) {
                $btime = $orderid ? "2013-10-29 13:47:52" : "today midnight";
            }
            $time[0] = date('Y-m-d H:i:s', strtotime($btime));

            //结束时间 - 默认为当前时间
            $etime = \safe_str(I('etime'));

            if ($etime && ! strtotime($etime)) {
                throw new Exception('时间格式错误', 202);
            }

            //按订单号查询时可不受时间限制
            if ( ! $etime) {
                $etime = "now";
            }

            $time[1] = date('Y-m-d H:i:s', strtotime($etime));


            //支付方式用"|"分隔
            if (isset($_REQUEST['ptypes'])) {
                $ptypes = \safe_str(I('ptypes'));
                if ($ptypes != '' && ! is_numeric($ptypes)) {
                    $ptypes = explode('|', $ptypes);
                    if ($key = array_search(99, $ptypes) !== false) {
                        unset($ptypes[$key]);
                        $map['_complex'] = [
                            [
                                'aid'   => $fid,
                                'ptype' => 2,
                            ],
                            [
                                'fid'   => $fid,
                                'ptype' => ['in', $ptypes],
                            ],
                            '_logic' => 'or',
                        ];
                    } else {
                        $map['ptype'] = ['in', $ptypes];
                    }
                } else {
                    if ($ptypes == 99) {
                        $map['ptype'] = 2;
                        $map['aid']   = $fid;
                    } else {
                        $map['ptype'] = $ptypes;
                    }
                }
            }

            if (empty($map['aid']) && empty($map['fid'])) {
                $map['fid'] = $fid;
            }

            if ($super || isset($ptypes)) {
                unset($map['fid']);
            }
            //交易类目用"|"分隔
            if (isset($_REQUEST['items'])) {
                $items = \safe_str(I('items'));
                if ($items != '') {
                    $items = explode('|', $items);
                    if ($items != array_intersect($items, array_keys(\Model\Finance\TradeRecord::getTradeItems()))) {
                        throw new Exception('交易类目错误', 204);
                    } else {
                        $subtype = [];
                        foreach ($items as $item) {
                            $subtype = array_merge($subtype, array_keys(\Model\Finance\TradeRecord::getItemCat(), $item));
                        }
                        if ($subtype) {
                            $map['dtype'] = ['in', $subtype];
                        }
                    }
                }
            }

            //交易类型
            if (isset($_REQUEST['dtype'])) {
                $dtype = \safe_str(I('dtype'));
                if (is_numeric($dtype) && in_array($dtype, array_column(\Model\Finance\TradeRecord::getItemCat(), 0))) {
                    if (isset($subtype)) {
                        if ( ! in_array($dtype, $subtype)) {
                            throw new Exception('交易类型与交易类目不符', 205);
                        }
                    }
                    $map['dtype'] = $dtype;
                } else {
                    throw new Exception('交易类型错误:', 206);
                }
            }

            $map['dmoney'] = ['gt', 0];
            //分页
            $page  = intval(I('page'));
            $limit = intval(I('limit'));
            $page  = ($page > 0) ? $page : 1;
            $limit = ($limit > 0) ? $limit : 15;

            //是否导出excel
            $excel = intval(I('excel'));

            $recordModel = new \Model\Finance\TradeRecord();
            if ($excel) {
                $data = $recordModel->getExList($map, $time);

                if (is_array($data)) {
                    $filename = date('YmdHis') . '交易记录';
                    $this->exportExcel($data, $filename);
                } else {
                    throw new Exception('查询结果为空', 207);
                }
            } else {
                $data = $recordModel->getList($map, $time, $page, $limit);
                if (is_array($data)) {
                    $this->apiReturn(200, $data);
                } else {
                    throw new Exception('查询结果为空', 208);
                }
            }
        } catch (Exception $e) {
            \pft_log('trade_record/err', 'get_list|'. $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }

    }

    //获取交易记录详情
    public function getDetails()
    {
        $memberId    = $this->isLogin('ajax');
        $recordModel = new \Model\Finance\TradeRecord();

        $trade_id = \safe_str(I('trade_id'));

        if ( ! $trade_id) {
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

    protected function exportExcel(array $data, $filename = '')
    {
        if ( ! $filename) {
            $filename = date('YmdHis');
        }

        //        var_dump($data[0]);
        //        exit;
        $r[0] = self::getExcelHead();
        $r    = array_merge($r, $data);
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
            //            'body'      => '交易内容',
            'memo'      => '备注',
            'member'    => '交易商户',
            'counter'   => '对方商户',
            'oper'      => '操作人',
            'cre_money' => '授信余额',
            'acc_money' => '账户余额',

        );
    }

    public function srchMem()
    {
        $memberId = $this->isLogin('ajax');
        $srch     = \safe_str(I('srch'));
        try {
            if ($memberId != 1) {
                throw new Exception('无权查看', 209);
            }
            if ($srch) {
                $model = new \Model\Finance\TradeRecord();
                $data  = $model->getMember($srch);
                if ($data) {
                    $this->apiReturn(200, $data, '操作成功');
                } else {
                    throw new Exception('查询结果为空', 210);
                }
            } else {
                throw new Exception('参数缺失', 212);
            }
        } catch (Exception $e) {
            \pft_log('trade_record/err', 'srch_mem|'. $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    //$url       = 'http://www.12301.local/route/?c=Finance_TradeRecord&a=test';
    public function test()
    {
        $_SESSION['sid'] = 1;
        $this->getList();
        //            $this->srchMem();
        //            $this->getDetails();
    }
}
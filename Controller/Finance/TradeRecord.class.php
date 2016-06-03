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

    public function __construct()
    {
        C(include __DIR__ . '/../../Conf/trade_record.conf.php');
    }

    /**
     * 获取交易记录列表
     */
    public function getList()
    {
        try {

            $memberId = $this->isLogin('ajax');

            self::logInput($memberId);

            $map = [];
            //被查询账号
            $fid = intval(I('fid'));
            $fid = ($memberId == 1 && $fid) ? $fid : $memberId;

            //订单号
            $orderid = \safe_str(I('ordreid'));
            if ($orderid) {
                $map['orderid'] = $orderid;
            }
            //时段
            $interval = $this->parseTime();
            $map['rectime'] = array('between', $interval);

            //支付方式用"|"分隔
            $this->parsePtype($fid, $map);

            if (empty($map['aid']) && empty($map['fid'])) {
                $map['fid'] = $fid;
            }

            //交易类目用"|"分隔
            $subtype = $this->parseTradeCategory($map);

            //交易类型
            $this->parseTradeType($subtype, $map);

            $map['dmoney'] = ['gt', 0];
            //分页
            $page  = intval(I('page'));
            $limit = intval(I('limit'));
            $page  = ($page > 0) ? $page : 1;
            $limit = ($limit > 0) ? $limit : 15;

            //数据输出形式
            $form        = intval(I('form'));
            $recordModel = new \Model\Finance\TradeRecord();
            $this->output($form, $recordModel, $map, $page, $limit, $interval);
        } catch (Exception $e) {
            \pft_log('trade_record/err', 'get_list|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * 获取交易记录详情
     *
     * @param $memberId
     */
    static public function logInput($memberId)
    {
        $input  = ['member' => $memberId, 'input' => I('param.')];
        $prefix = __CLASS__ ? strtolower(__CLASS__) . '/' : '';
        $action = debug_backtrace()['function'] ?: '';
        \pft_log($prefix . 'input', $action . '|' . json_encode($input));
    }

    /**
     * 解析输入时间参数
     *
     * @return array|bool
     * @throws \Library\Exception
     */
    private function parseTime()
    {
        //开始时间
        $btime = $this->validateTime('btime', "today midnight");
        //结束时间 - 默认为当前时间
        $etime = $this->validateTime('etime', "now");
        $interval =  [$btime, $etime];
        return $interval;
    }

    /**
     * 验证输入时间格式
     *
     * @param $timeTag
     * @param $defaultVal
     *
     * @return bool|mixed|string
     * @throws \Library\Exception
     */
    private function validateTime($timeTag, $defaultVal)
    {
        $time = \safe_str(I($timeTag));
        if ($time && ! strtotime($time)) {
            throw new Exception('时间格式错误', 201);
        }

        $time = $time ?: $defaultVal;
        $time = date('Y-m-d H:i:s', strtotime($time));

        return $time;
    }

    /**
     * @param $fid
     * @param $map
     *
     * @return array
     */
    private function parsePtype($fid, &$map)
    {
        //支付类型的值可能是0，不能用empty判断
        if ( ! isset($_REQUEST['ptypes'])) {
            return false;
        }

        $ptypes = \safe_str(I('ptypes'));

        if ($ptypes == '') {
            return false;
        }

        //多种支付方式用|分隔
        if ( ! is_numeric($ptypes) && strpos($ptypes, '|')) {
            $ptypes = explode('|', $ptypes);
            //查看分销商账户
            if ($key = array_search(99, $ptypes) !== false) {
                unset($ptypes[$key]);
                $map['_complex'] = [
                    [
                        'aid'   => $fid,
                        'ptype' => ['in', [2, 3]],
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
                $map['ptype'] = ['in', [2, 3]];
                $map['aid']   = $fid;
            } else {
                $map['ptype'] = $ptypes;
            }
        }

        return $ptypes;
    }

    /**
     * 解析交易大类
     *
     * @param $map
     *
     * @return array
     * @throws \Library\Exception
     */
    private function parseTradeCategory(&$map)
    {
        if ( ! isset($_REQUEST['items'])) {
            return false;
        }

        $items = \safe_str(I('items'));

        if ('' != $items) {
            return false;
        }

        $items = explode('|', $items);
        if (array_intersect($items, array_keys(C('trade_item'))) != $items) {
            throw new Exception('交易类目错误', 204);
        }

        $subtype  = [];
        $item_cat = C('item_category');
        foreach ($items as $item) {
            $subtype = array_merge($subtype, array_keys($item_cat, $item));
        }
        if ($subtype) {
            $map['dtype'] = ['in', $subtype];

            return $subtype;
        } else {
            return false;
        }

    }

    /**
     * 解析交易类型
     *
     * @param $subtype
     * @param $map
     *
     * @return mixed
     * @throws \Library\Exception
     */
    private function parseTradeType($subtype, &$map)
    {
        if ( ! isset($_REQUEST['dtype'])) {
            return false;
        }

        $dtype = \safe_str(I('dtype'));
        if ($dtype == '') {
            return false;
        }
        $item_cat = C('item_category');
        if (is_numeric($dtype) && in_array($dtype, array_column($item_cat, 0))) {
            if (is_array($subtype) && ! in_array($dtype, $subtype)) {
                throw new Exception('交易类型与交易类目不符', 205);
            }
            $map['dtype'] = $dtype;
        } else {
            throw new Exception('交易类型错误:', 206);
        }

        return false;
    }

    /**
     * 根据传入的form值输出结果
     *
     * @param                            $form
     * @param \Model\Finance\TradeRecord $recordModel
     * @param                            $map
     * @param                            $page
     * @param                            $limit
     *
     * @throws \Library\Exception
     */
    private function output($form, \Model\Finance\TradeRecord $recordModel, $map, $page, $limit, $interval)
    {
        switch ($form) {
            case 0:
                $data = $recordModel->getList($map, $page, $limit, $interval);
                if (is_array($data)) {
                    $data['btime'] = $interval[0];
                    $data['etime'] = $interval[1];
                    $this->apiReturn(200, $data);
                } else {
                    throw new Exception('查询结果为空', 208);
                }
                break;
            case 1:
                $data = $recordModel->getExList($map);

                if (is_array($data)) {
                    $filename = date('YmdHis') . '交易记录';
                    $this->exportExcel($data, $filename);
                } else {
                    throw new Exception('查询结果为空', 207);
                }
                break;
            case 2:
                $data = $recordModel->getSummary($map);
                if (is_array($data)) {
                    $this->apiReturn(200, $data);
                } else {
                    throw new Exception('查询结果为空', 209);
                }
                break;
            default:
                throw new Exception('传入参数错误', 210);
        }
    }

    private function exportExcel(array $data, $filename = '')
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

    protected static function getExcelHead()
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
            \pft_log('trade_record/err', 'srch_mem|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * @url http://www.12301.local/route/?c=Finance_TradeRecord&a=test
     */
    public function test()
    {
        if (ENV == 'DEVELOP') {
            $_SESSION['sid'] = 1;
//            $this->getList();
            $this->getDetails();
        } else {
            $this->apiReturn(213);
        }

        //            $this->srchMem();
        //            $this->getDetails();
    }

    public function getDetails()
    {
        $memberId = $this->isLogin('ajax');
        self::logInput($memberId);
        $recordModel = new \Model\Finance\TradeRecord();

        $trade_id = \safe_str(I('trade_id'));

        if ( ! $trade_id) {
            $this->apiReturn(201, [], '传入参数不合法');
        }

        $record = $recordModel->getDetails($trade_id);
        if ( ! empty($record['fid']) || ! empty($record['aid'])) {
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
}
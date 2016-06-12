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
    private $tradeModel;   //交易记录模型

    public function __construct()
    {
        C(include __DIR__ . '/../../Conf/trade_record.conf.php');
    }

    /**
     * 获取交易记录详情
     *
     * @param string [trade_id] 交易记录id
     *
     */
    public function getDetails()
    {
        $memberId = $this->isLogin('ajax');
        self::logInput($memberId);
        $recordModel = $this->_getTradeModel();

        $trade_id = \safe_str(I('trade_id'));

        if (!$trade_id) {
            $this->apiReturn(201, [], '传入参数不合法');
        }

        $record = $recordModel->getDetails($trade_id);
        //无权查看时返回数据为空
        if (isset($record['fid'], $record['aid']) && in_array($memberId, [1, $record['fid'], $record['aid']])) {
            unset($record['fid'], $record['aid']);
        } else {
            $record = [];
        }

        $this->apiReturn(200, $record, '操作成功');
    }

    /**
     * 获取交易记录列表
     *
     * @param   int [fid]       被查询的会员id   管理员账号用
     * @param   string [orderid]   交易号
     * @param   string [btime]     开始时间        yy-mm-dd hh:ii:ss
     * @param   string [etime]     结束时间        yy-mm-dd hh:ii:ss
     * @param   int [items]     交易大类        见 C('trade_item'); 多值以'|'分隔
     * @param   int [ptypes]    支付类型        见 C('pay_type'); 多值以'|'分隔
     * @param   int [form]      数据格式        0-交易记录列表 1-导出excel表 2-交易记录统计
     * @param   int [page]      当前页数
     * @param   int [limit]     每页显示条数
     */
    public function getList()
    {
        try {
            $memberId = $this->isLogin('ajax');
            //日志记录传入数据
            self::logInput($memberId);

            $map = [];
            //被查询会员id
            $fid = intval(I('fid'));

            //非管理员不能指定被查询对象
            if ($memberId == 1) {
                $fid = $fid ?: 0;
            } else {
                $fid = $memberId;
            }

            //支付方式
            $this->_parsePayType($fid, $map);

            //订单号
            $orderid = \safe_str(I('orderid'));
            if ($orderid) {
                $map['orderid'] = $orderid;
            }

            //时段
            $interval = $this->_parseTime();
            $map['rectime'] = array('between', $interval);

            //交易大类
            $subtype = $this->_parseTradeCategory($map);

            //交易类型
            $this->_parseTradeType($subtype, $map);

            //交易金额为0的交易记录不显示
            $map['dmoney'] = ['gt', 0];

            //分页
            $page = intval(I('page'));
            $limit = intval(I('limit'));
            $page = ($page > 0) ? $page : 1;
            $limit = ($limit > 0) ? $limit : 15;

            //数据输出形式
            $form = intval(I('form'));

            $recordModel = $this->_getTradeModel();
            $this->_output($form, $recordModel, $map, $page, $limit, $interval);

        } catch (Exception $e) {
            \pft_log('trade_record/err', 'get_list|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * 管理员查询会员
     *
     */
    public function srchMem()
    {
        $memberId = $this->isLogin('ajax');
        $srch = \safe_str(I('srch'));
        try {
            //只有管理员可查看
            if ($memberId != 1) {
                throw new Exception('无权查看', 209);
            }
            if (empty($srch)) {
                throw new Exception('传入参数错误', 210);
            }
            $model = $this->_getTradeModel();
            $data = $model->getMember($srch);
            $data = is_array($data) ? $data : [];
            $this->apiReturn(200, $data, '操作成功');
        } catch (Exception $e) {
            \pft_log('trade_record/err', 'srch_mem|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * @url 下载excel http://www.12301.local/route/?c=Finance_TradeRecord&a=test&fid=57675&form=1&btime=2016-05-23 00:00:00
     * @url 显示交易报表 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&fid=57675&form=0&btime=2016-05-23 00:00:00
     * @url 查看交易详情 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&trade_id=6483409
     * @url 查询会员 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&srch=技术部
     */
    public function test()
    {
        if ('DEVELOP' == ENV) {
            $_SESSION['sid'] = 1;
//            $this->getList();
//            $this->getDetails();
            $this->srchMem();
        } else {
            $this->apiReturn(213);
        }
    }

    /**
     * 导出excel报表
     *
     * @param array $data 数据
     * @param string $filename 文件名
     */
    private function _exportExcel(array $data, $filename = '')
    {
        if (!$filename) {
            $filename = date('YmdHis');
        }
        $r = [];
        if (is_array($data) && count($data)) {
            foreach ($data as $record) {
                uksort($record, [$this, '_rearrangeData']);
                $r[] = $record;
            }
        }
        array_unshift($r, C('excel_head'));
        include_once("/var/www/html/new/d/class/SimpleExcel.class.php");
        $xls = new \SimpleExcel('UTF-8', true, 'orderList');
        $xls->addArray($r);
        $xls->generateXML($filename);
        exit;
    }

    /**
     * 获取交易记录模型
     *
     * @return mixed
     */
    private function _getTradeModel()
    {
        if (null === $this->tradeModel) {
            $this->tradeModel = new \Model\Finance\TradeRecord();
        }

        return $this->tradeModel;
    }

    /**
     * 根据传入的form值输出结果
     *
     * @param                            $form
     * @param \Model\Finance\TradeRecord $recordModel
     * @param                            $map
     * @param                            $page
     * @param                            $limit
     * @param                            $interval
     *
     * @throws \Library\Exception
     */
    private function _output($form, \Model\Finance\TradeRecord $recordModel, $map, $page, $limit, $interval)
    {
        switch ($form) {
            case 0:
                $data = $recordModel->getList($map, $page, $limit);
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
                    $this->_exportExcel($data, $filename);
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

    /**
     * 解析支付类型
     *
     * @param   string|int $fid 分销商id
     * @param   array $map 查询条件
     *
     * @return array
     */
    private function _parsePayType($fid, &$map)
    {
        //支付类型的值可能是0
        if (!isset($_REQUEST['ptypes'])) {
            return false;
        }

        $ptypes = \safe_str(I('ptypes'));

        if ('' == $ptypes) {
            return false;
        }
        $ptypes = explode('|', $ptypes);

        $key = array_search(99, $ptypes);

        if (false !== $key) {

            $search_dist_credit =                     [
                'ptype' => ['in', [2, 3]],
                'aid' => $fid
            ];

            unset($ptypes[$key]);

            if (count($ptypes) && $fid) {
                $map['complex'] = [
                    $search_dist_credit,
                    [
                        'ptype' => ['in', $ptypes],
                        'fid' => $fid
                    ],
                    '_logic' => 'or'
                ];
            } else if($fid){
                $map = array_merge($search_dist_credit);
            } else {
                $ptypes = array_merge([2, 3], $ptypes);
                $map['ptype'] = ['in', $ptypes];
            }
        }else{
            $map['ptype'] = ['in', $ptypes];
            if($fid){
                $map['fid'] = $fid;
            }
        }
        return $ptypes;
    }

    /**
     * 解析输入时间参数
     *
     * @return array|bool
     * @throws \Library\Exception
     */
    private function _parseTime()
    {
        //开始时间
        $btime = $this->_validateTime('btime', "today midnight", "00:00:00");
        //结束时间 - 默认为当前时间
        $etime = $this->_validateTime('etime', "now", "23:59:59");
        $interval = [$btime, $etime];

        return $interval;
    }

    /**
     * 解析交易大类
     *
     * @param array $map 查询条件
     *
     * @return array
     * @throws \Library\Exception
     */
    private function _parseTradeCategory(&$map)
    {
        if (!isset($_REQUEST['items'])) {
            return false;
        }

        $items = \safe_str(I('items'));

        if ('' == $items) {
            return false;
        }

        $items = explode('|', $items);

        $subtype = [];
        $item_cat = array_column(C('item_category'),0);
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
     * @param   string $subtype 交易类型
     * @param   array $map 查询条件
     *
     * @return mixed
     * @throws \Library\Exception
     */
    private function _parseTradeType($subtype, &$map)
    {
        if (!isset($_REQUEST['dtype'])) {
            return false;
        }

        $dtype = \safe_str(I('dtype'));
        if ($dtype == '') {
            return false;
        }
        $item_cat = C('item_category');
        if (is_numeric($dtype) && in_array($dtype, array_column($item_cat, 0))) {
            if (is_array($subtype) && !in_array($dtype, $subtype)) {
                throw new Exception('交易类型与交易类目不符', 205);
            }
            $map['dtype'] = $dtype;
        } else {
            throw new Exception('交易类型错误:', 206);
        }

        return false;
    }

    /**
     * 重排excel数据
     *
     * @param $field_a
     * @param $field_b
     *
     * @return int
     */
    private function _rearrangeData($field_a, $field_b)
    {
        $excel_head = array_keys(C('excel_head'));
        $pos_a = array_search($field_a, $excel_head);
        $pos_b = array_search($field_b, $excel_head);
        if (false !== $pos_a && false !== $pos_b) {
            return ($pos_a < $pos_b) ? -1 : 1;
        } else {
            return 0;
        }
    }

    /**
     * 验证输入时间格式
     *
     * @param   string $timeTag 时间字段
     * @param   string $defaultVal 默认值
     *
     * @return bool|mixed|string
     * @throws \Library\Exception
     */

    private function _validateTime($timeTag, $defaultVal, $postfix)
    {
        $time = \safe_str(I($timeTag));

        if ($time) {
            if (!strtotime($time)) {
                throw new Exception('时间格式错误', 201);
            } else if (strlen($time) < 10) {
                $time .= ' ' . $postfix;
            }
        }

        $time = $time ?: $defaultVal;

        $time = date('Y-m-d H:i:s', strtotime($time));

        return $time;
    }

    /**
     * 记录接收数据日志
     *
     * @param $memberId
     */
    static function logInput($memberId)
    {
        $input = ['member' => $memberId, 'input' => I('param.')];
        $prefix = __CLASS__ ? strtolower(__CLASS__) . '/' : '';
        $action = debug_backtrace()['function'] ?: '';
        \pft_log($prefix . 'input', $action . '|' . json_encode($input));
    }
}
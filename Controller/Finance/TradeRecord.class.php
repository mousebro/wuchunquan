<?php
/**
 * Description: 交易记录查询接口
 * User: Fang
 * Time: 11:14 2016/5/17
 */
namespace Controller\Finance;

use Library\Controller;
use Library\Exception;
use Model\Member\MemberRelationship;
use Process\Finance\TradeRecord as TradeProcess;

class TradeRecord extends Controller{

    private $tradeModel;   //交易记录模型
    private $memberId;

    public function __construct() {
       C(include HTML_DIR . '/Service/Conf/trade_record.conf.php');
        $this->memberId = $this->isLogin();
    }

    /**
     * 获取交易记录详情
     *
     * @param string [trade_id] 交易记录id
     *
     */
    public function getDetails() {
        $trade_id = \safe_str(I('trade_id'));

        if (!$trade_id) {
            $this->apiReturn(201, [], '传入参数不合法');
        }
        $fid = ($this->memberId == 1 && isset($_REQUEST['fid'])) ? intval(I('fid')) : $this->memberId;

        $partner_id = intval(I('partner_id')) ?: 0;

        if ($this->memberId == 1 && !$fid && $partner_id) {
            $this->apiReturn(220, [], '请先选择交易商户');
        }

        $record = $this->_getTradeModel()->getDetails($trade_id, $fid, $partner_id);

        //无权查看时返回数据为空
        if (isset($record['fid'], $record['aid']) && in_array($this->memberId, [1, $record['fid'], $record['aid']])) {
            unset($record['fid'], $record['aid']);
        } else {
            $record = [];
        }

        $this->apiReturn(200, $record, '操作成功');
    }

    /**
     * 获取交易记录列表
     *
     * @param   int    [fid]        被查询的会员id   管理员账号查询全平台用户交易记录时传0，其他情况默认为session中的sid
     * @param   int    [partner_id] 供应商/分销商id  管理员账号用
     *
     * @param   string [orderid]    交易号
     *
     * @param   string [btime]      开始时间         yy-mm-dd hh:ii:ss
     * @param   string [etime]      结束时间         yy-mm-dd hh:ii:ss
     *
     * @param   string [dtype]      交易类型         见 C('item_category')
     * @param   int    [items]      交易大类         见 C('trade_item'); 多值以'|'分隔
     * @param   int    [ptypes]     支付类型         见 C('pay_type'); 多值以'|'分隔
     *
     * @param   int    [form]       数据格式         0-交易记录列表 1-导出excel表 2-交易记录统计
     *
     * @param   int    [page]       当前页数         返回给前端的页数比实际值多1
     * @param   int    [limit]      每页显示条数
     */
    public function getList() {
        try {
            $map = [];
            $fid = ($this->memberId == 1 && isset($_REQUEST['fid'])) ? intval(I('fid')) : $this->memberId;

            $partner_id = intval(I('partner_id')) ?: 0;

            if ($this->memberId == 1 && !$fid && $partner_id) {
                $this->apiReturn(220, [], '请先选择交易商户');
            }

            //时段
            $interval = TradeProcess::parseTime();
            $map['rectime'] = array('between', $interval);

            //交易大类
            $items = \safe_str(I('items', ''));
            if ('' != $items) {
                $items    = explode('|', $items);
                $subtype  = [];
                $item_cat = array_column(C('item_category'), 0);

                foreach ($items as $item) {
                    $subtype = array_merge($subtype, array_keys($item_cat, $item));
                }
                if ($subtype) {
                    $map['dtype'] = ['in', $subtype];
                }
            }

            //订单号
            $orderid = \safe_str(I('orderid'));
            if ($orderid) {
                $map['orderid'] = $orderid;
                if (!$_REQUEST['btime'] && !$_REQUEST['etime']) {
                    unset($map['rectime']);
                    $interval = ['', ''];
                }
            }

            //支付方式
            $tmpMap = TradeProcess::parseAccountType($this->memberId, $fid, $partner_id);
            if($tmpMap) {
                $map = array_merge($map, $tmpMap);
            }

            //交易金额为0的交易记录不显示
            $map['dmoney'] = ['gt', 0];

            //分页
            $page  = intval(I('page'));
            $limit = intval(I('limit'));
            $page  = ($page > 0) ? $page : 1;
            $limit = ($limit > 0) ? $limit : 15;

            //数据输出形式
            $form        = intval(I('form'));
            $recordModel = $this->_getTradeModel();
            $this->_output($form, $recordModel, $map, $page, $limit, $interval, $fid, $partner_id);

        } catch (Exception $e) {
            \pft_log('trade_record/err', 'get_list|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * 获取对方商户信息
     *
     * @param   string  [srch]  查询关键字
     * @param   integer [limit] 单页限制数
     *
     */
    public function getPartner() {
        $srch = \safe_str(I('srch'));

        if ($this->memberId == 1) {
            $fid = \safe_str(I('fid'));
            if (!$fid) {
                $this->srchMem($srch);
                exit;
            } else {
                $memberId = $fid;
            }
        } else {
            $memberId = $this->memberId;
        }
        $limit = intval(I('limit')) ?: 20;

        $memberModel = new MemberRelationship($memberId);
        $field       = ['distinct m.id as fid', 'm.account', 'm.dname'];
        $data        = $memberModel->getRelevantMerchants($srch, $field, $limit);
        $data        = $data ?: [];

        $this->apiReturn(200, $data, '操作成功');
    }

    /**
     * 管理员模糊搜索会员
     *
     * @param   string [srch]       会员名称/会员id/会员账号
     * @param   string [ptypes]     交易账户类型                0-查看当前用户授信; 1-查看分销商授信
     */
    public function srchMem($srch = null) {
        if (!$srch) {
            $this->memberId = $this->isLogin('ajax');
            $srch = \safe_str(I('srch'));
        }

        try {
            if (empty($srch)) {
                $this->apiReturn(200, [], '查询结果为空');
            }

            if ($this->memberId != 1) {
                throw new Exception('没有查询权限', 210);
            } else {
                $data = $this->_getTradeModel()->getMember($srch);
            }

            $data = is_array($data) ? $data : [];

            $this->apiReturn(200, $data, '操作成功');

        } catch (Exception $e) {
            \pft_log('trade_record/err', 'srch_mem|' . $e->getCode() . "|" . $e->getMessage(), 'month');
            $this->apiReturn($e->getCode(), [], $e->getMessage());
        }
    }

    /**
     * 根据传入的form值输出结果
     *
     * @param   int                        $form        数据格式    0-交易记录列表 1-导出excel表 2-交易记录统计
     * @param   \Model\Finance\TradeRecord $recordModel 交易记录模型
     * @param   array                      $map         查询条件
     * @param   int                        $page        当前页数
     * @param   int                        $limit       每页行数
     * @param   array                      $interval    起止时间段   [开始时间,结束时间]
     *
     * @throws \Library\Exception
     */
    private function _output(
        $form,
        \Model\Finance\TradeRecord $recordModel,
        $map,
        $page,
        $limit,
        $interval,
        $fid,
        $partner_id
    ) {
        switch ($form) {
            case 0:
                $data = $recordModel->getList($map, $page, $limit, $fid, $partner_id);
                if (is_array($data)) {
                    $data['btime'] = $interval[0];
                    $data['etime'] = $interval[1];
                    $this->apiReturn(200, $data);
                } else {
                    throw new Exception('查询结果为空', 208); 
                }
                break;
            case 1:
                $data = $recordModel->getExList($map, $fid, $partner_id);
                if (!is_array($data)) {
                    $data = [];
                }
                $filename = date('YmdHis') . '交易记录';

                $this->_exportExcel($data, $filename);
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
     * 导出excel报表
     *
     * @param array  $data     数据
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
                $record = self::array_recompose($record, array_keys(C('excel_head')));
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
     * @return \Model\Finance\TradeRecord
     */
    private function _getTradeModel() {
        if (null === $this->tradeModel) {
            $this->tradeModel = new \Model\Finance\TradeRecord();
        }

        return $this->tradeModel;
    }

    /**
     * 按指定格式和指定顺序重排数组
     *
     * @param   array $data   数据
     * @param   array $format 格式
     *
     * @return  array
     */
    static function array_recompose(array $data, array $format) {
        $format_data = array_fill_keys($format, '');
        foreach ($data as $key => $value) {
            if (!in_array($key, $format)) {
                continue;
            }
            $format_data[ $key ] = $data[ $key ];
        }

        return $format_data;

    }
}
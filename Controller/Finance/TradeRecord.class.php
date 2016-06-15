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

class TradeRecord extends Controller
{
    private $tradeModel;   //交易记录模型

    private $memberId;

    public function __construct()
    {
        C(include __DIR__ . '/../../Conf/trade_record.conf.php');
        $this->memberId = $this->isLogin('ajax');
        //if(ENV == 'DEVELOP'){
        //    ini_set('display_errors','on');
        //    error_reporting(E_ALL);
        //}
    }

    /**
     * 按指定格式和指定顺序重排数组
     *
     * @param   array $data   数据
     * @param   array $format 格式
     *
     * @return  array
     */
    static function array_recompose(array $data, array $format)
    {
        $format_data = array_flip($format);
        foreach ($data as $key => $value) {
            if (!in_array($key, $format)) {
                continue;
            }
            $format_data[$key] = $data[$key];
        }
        return $format_data;

    }

    /**
     * 获取交易记录详情
     *
     * @param string [trade_id] 交易记录id
     *
     */
    public function getDetails()
    {
        $trade_id = \safe_str(I('trade_id'));

        if (!$trade_id) {
            $this->apiReturn(201, [], '传入参数不合法');
        }

        $record = $this->_getTradeModel()->getDetails($trade_id);

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
     * @param   int    [fid]        被查询的会员id   管理员账号用
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
    public function getList()
    {
        try {
            $map = [];

            //被查询会员id
            $fid = intval(I('fid'));
            //fid=0时可查看所有会员记录

            $fid = ($this->memberId == 1) ? ($fid ?: 0) : $this->memberId;

            $partner_id = intval(I('partner_id'));
            $partner_id = $partner_id ?: 0;


            //支付方式
            $this->_parsePayType($fid, $partner_id, $map);

            //时段
            $interval = $this->_parseTime();
            $map['rectime'] = array('between', $interval);

            //订单号
            $orderid = \safe_str(I('orderid'));
            if ($orderid) {
                $map['orderid'] = $orderid;
                unset($map['rectime']);
            }

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
     * 获取对方商户信息
     *
     * @param   string  [srch]  查询关键字
     * @param   integer [limit] 单页限制数
     *
     */
    public function getPartner()
    {
        $srch = \safe_str(I('srch'));
        $limit = intval(I('limit')) ?: 20;

        $memberModel = new MemberRelationship($this->memberId);

        $field = ['distinct m.id as fid', 'm.account', 'm.dname'];

        $data = $memberModel->getRelevantMerchants($srch, $field, $limit);

        $data = $data ?: [];

        $this->apiReturn(200, $data, '操作成功');
    }

    /**
     * 管理员模糊搜索会员
     *
     * @param   string [srch]       会员名称/会员id/会员账号
     * @param   string [ptypes]     支付类型                0-查看当前用户授信; 1-查看分销商授信
     */
    public function srchMem()
    {
        $this->memberId = $this->isLogin('ajax');
        $srch = \safe_str(I('srch'));
        $limit = intval(I('limit')) ?: 20;

        try {
            if (empty($srch)) {
                throw new Exception('传入参数错误', 210);
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
     * @url 下载excel http://www.12301.local/route/?c=Finance_TradeRecord&a=test&fid=57675&form=1&btime=2016-05-23
     *      00:00:00
     * @url 显示交易报表 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&fid=57675&form=0&btime=2016-05-23
     *      00:00:00
     * @url 查看交易详情 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&trade_id=6483409
     * @url 查询会员 http://www.12301.local/route/?c=Finance_TradeRecord&a=test&srch=技术部
     */
    public function test()
    {
//        if ('DEVELOP' == ENV) {
//            $_SESSION['sid'] = 1;
////            $this->getList();
////            $this->getDetails();
//            $this->srchMem();
//        } else {
        $this->apiReturn(213);
//        }
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
     * @param   int                        $form        数据格式    0-交易记录列表 1-导出excel表 2-交易记录统计
     * @param   \Model\Finance\TradeRecord $recordModel 交易记录模型
     * @param   array                      $map         查询条件
     * @param   int                        $page        当前页数
     * @param   int                        $limit       每页行数
     * @param   array                      $interval    起止时间段   [开始时间,结束时间]
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
     * @param string $fid       被查询用户id
     * @param string $partnerId 合作商家id
     * @param array  $map       查询条件
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    private function _parsePayType($fid, $partnerId, &$map)
    {
        $ptype = \safe_str(I('ptypes'));

        if (!is_numeric($ptype)) {
            return false;
        }

        switch ($ptype) {
            case 2: //no break;
            case 99:
                $map['ptype'] = ['in', [2, 3]];
                break;
            case 98:
                $pay_types = array_combine(array_keys(C('pay_type')), array_column(C('pay_type'), 2));
                $map['ptype'] = ['in', array_keys($pay_types, 0)];
                break;
            case 100:
                break;
            default:
                $map['ptype'] = $ptype;
        }
        if ($ptype == 100) {
            if (!$partnerId) {
                $map['_complex'][] = [
                    [
                        'aid' => $fid,
                        'ptype' => ['in', '2,3'],
                    ],
                    'fid' => $fid,
                    '_logic' => 'or',
                ];
            } else {
                $map['_complex'][] = [
                    [
                        'aid' => $fid,
                        'fid' => $partnerId,
                    ],
                    [
                        'aid' => $partnerId,
                        'fid' => $fid,
                    ],
                    '_logic' => 'or',
                ];
            }
        } else {
            if ($ptype == 99) {
                $self = 'aid';
                $other = 'fid';
            } else {
                $self = 'fid';
                $other = 'aid';
            }
            if (!$fid && $this->memberId != 1) {
                throw new Exception('无权限查看', 201);
            } else {
                if ($fid) {
                    $map[$self] = $fid;
                }
            }
            if ($partnerId) {
                $map[$other] = $partnerId;
            }

        }

        return $ptype;
    }

    /**
     * 解析时间参数
     *
     * @param   string [btime]     开始时间        yy-mm-dd hh:ii:ss
     * @param   string [etime]     结束时间        yy-mm-dd hh:ii:ss
     *
     * @return  array|bool
     * @throws  \Library\Exception
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
     * @param         integer [items]     交易大类        见 C('trade_item'); 多值以'|'分隔
     * @param   array $map    查询条件
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
        $item_cat = array_column(C('item_category'), 0);
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
     * @param   array  $map     查询条件
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
     * @param   string $timeTag    时间字段
     * @param   string $defaultVal 绝对默认时间
     * @param   string $postfix    相对默认时间：未传入时分秒时的默认时间
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    private function _validateTime($timeTag, $defaultVal, $postfix)
    {
        $time = \safe_str(I($timeTag));

        if ($time) {
            if (!strtotime($time)) {
                throw new Exception('时间格式错误', 201);
            } else {
                if (strlen($time) < 11) {
                    $time .= ' ' . $postfix;
                }
            }
        }

        $time = $time ?: $defaultVal;

        $time = date('Y-m-d H:i:s', strtotime($time));

        return $time;
    }
}
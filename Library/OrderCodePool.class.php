<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/7-2016
 * Time: 12:17
 * Description: 票付通凭证码POOL，保证相同景点不重码
 *              每次下单从POOL中获取一个code（lpop），如果POOL为空，生成2000个code
 * Usage:      $code = Library\OrderCodePool::GetCode($lid);
 */
namespace Library;
//use Library\Model;
class BasePool
{
    const __TBL_ORDER__ = 'uu_ss_order';
    const __TBL_POOL__  = 'pft_code_pool';

    public static function GetCode($lid, $forceGenerate=false){}
    protected static function uk_code_verify($lid, Array $codes)
    {
        $m      = new Model('slave');
        $exist_codes = $m->table(self::__TBL_ORDER__)
            ->where(['lid'=>$lid, 'code'=>['in', $codes], 'status'=>0])
            ->field('code')
            ->limit(count($codes))
            ->select();
        $ret = [];
        foreach ($exist_codes as $code) {
            $ret[] = $code['code'];
        }
        return $ret;
    }

    protected static function code($reverse=false)
    {
        $list = [0,1,2,3,4,5,6,7,8,9];
        if ($reverse) $list = array_reverse($list);
        for ($i=10;$i>1;$i--) {
            $rand           = mt_rand(0,9);
            $tmp            = $list[$rand];
            $list[$rand]    = $list[$i - 1];
            $list[$i-1]     = $tmp;
        }
        $result = 0;

        for ($i=0; $i<6; $i++) {
            $result = $result * 10 + $list[$i];
        }
        return $result;
    }

    protected static function Generate($lid,$ptype='')
    {
        $pool = [];
        $flag = $ptype=='F' ? true : false;
        for ($i=0; $i<2000; $i++) {
            $code =  self::code($flag);
            if (in_array($code, $pool)){
                $i -= 1;
                continue;
            }
            $pool[] = $code;
        }
        //校验未使用的订单是否存在重码
        $chk_ret = self::uk_code_verify($lid, $pool);
        $uk_code = array_diff_assoc($pool, $chk_ret);
        $data = [];
        foreach ($uk_code as $code) {
            $data[] = [
                'code'=> $code,
                'lid' => $lid,
            ];
        }
        //file_put_contents(BASE_LOG_DIR . '/debug.log', $m->_sql());
        return $data;
    }
}
class CodePoolRedis extends BasePool
{
    private static $redis=null;

    public static function GetCode($lid, $ptype='', $forceGenerate=false)
    {
        $code = self::getRedis()->lPop("code:$lid");
        if (!$code || $forceGenerate===true) {
            self::Generate($lid, $ptype);
            $code = self::getRedis()->lPop("code:$lid");
        }
        return $code;
    }

    public static function getRedis($timeout=1)
    {
        if (is_null(self::$redis)) {
            $config = C('redis');
            self::$redis = new \Redis();
            self::$redis->connect($config['master']['db_host'], $config['master']['db_port'], $timeout);
            if (isset($config['master']['db_pwd'])) {
                self::$redis->auth($config['master']['db_pwd']);
            }
            try {
                self::$redis->select(1);
            } catch(\RedisException $e) {
                return false;
            }

        }
        return self::$redis;
    }

    protected static function Generate($lid, $ptype='')
    {
        $uk_code = parent::Generate($lid, $ptype);
        self::getRedis()->multi(\Redis::PIPELINE);
        foreach ($uk_code as $item) {
            self::getRedis()->lPush("code:$lid", $item['code']);
        }
        self::getRedis()->exec();
        return true;
    }
}
class CodePoolMysql extends BasePool
{
    public static function GetCode($lid, $ptype='', $forceGenerate=false)
    {
        $m = new Model('localhost');
        $w = ['lid'=>$lid];
        $code = $m->table(self::__TBL_POOL__)->where($w)->getField('code');
        if (!$code || $forceGenerate===true) {
            $codes = self::Generate($lid, $ptype);
            $codes = array_shift($codes);
            $code = $codes['code'];
        }
        $w['code'] = $code;
        $m->table(self::__TBL_POOL__)->where($w)->limit(1)->delete();
        return $code;
    }
    protected  static function Generate($lid, $ptype='')
    {
        $data = parent::Generate($lid, $ptype);
        $m = new Model('localhost');
        $m->table(self::__TBL_POOL__)->addAll($data);
        return $data;
    }
}
class OrderCodePool
{
    public static function GetCode($lid, $ptype='', $forceGenerate=false)
    {
        //var_dump(CodePoolRedis::getRedis(1));exit;
        if (CodePoolRedis::getRedis(0.2)!==false) {
            return CodePoolRedis::GetCode($lid, $ptype, $forceGenerate);
        }
        return CodePoolMysql::GetCode($lid, $ptype, $forceGenerate);
    }
}
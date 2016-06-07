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


class OrderCodePool
{
    private static $redis=null;
    const __TBL_ORDER__ = 'uu_ss_order';
    const __TBL_POOL__  = 'pft_code_pool';

    private function getRedis()
    {
        if (is_null(self::$redis)) {
            $config = C('redis');
            self::$redis = new \Redis();
            self::$redis->connect($config['master']['db_host'], $config['master']['db_port']);
            if (isset($config['master']['db_pwd'])) {
                self::$redis->auth($config['master']['db_pwd']);
            }
            self::$redis->select(1);
        }
        return self::$redis;
    }

    public static function GetCode($lid, $forceGenerate=false)
    {
        $code = self::getRedis()->lPop("code:$lid");
        if (!$code || $forceGenerate===true) {
            self::Generate($lid);
            $code = self::getRedis()->lPop("code:$lid");
        }
        return $code;
    }

    private static function Generate($lid)
    {
        self::getRedis()->multi(\Redis::PIPELINE);
        //$this->redis->multi(\Redis::MULTI);
        $pool = [];
        for ($i=0; $i<2000; $i++) {
            $code =  self::code();
            if (in_array($code, $pool)){
                $i -= 1;
                continue;
            }
            $pool[] = $code;
        }
        //校验未使用的订单是否存在重码
        $chk_ret = self::uk_code_verify($lid, $pool);
        $uk_code = array_diff_assoc($pool, $chk_ret);
        foreach ($uk_code as $item) {
            self::getRedis()->lPush("code:$lid", $item);
        }
        self::getRedis()->exec();
        return true;
    }

    private static function uk_code_verify($lid, Array $codes)
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

    private static function code()
    {
        $list = [0,1,2,3,4,5,6,7,8,9];
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
}
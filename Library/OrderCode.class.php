<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/7-007
 * Time: 12:17
 */

namespace Library;


class OrderCode
{
    private $redis;
    const __TBL_ORDER__ = 'uu_ss_order';
    const __TBL_POOL__  = 'pft_code_pool';

    public function __construct()
    {
        $this->config = C('redis');
        $this->redis = new \Redis();
        $this->enable = $this->redis->connect($this->config['master']['db_host'], $this->config['master']['db_port']);
        if (isset($this->config['master']['db_pwd'])) {
            $this->redis->auth($this->config['master']['db_pwd']);
        }
        $this->redis->select(1);
    }

    public function GetCode($lid)
    {
        $code = $this->redis->blPop("code:$lid");
        if (!$code) {
            self::Generate($lid);
            $code = $this->redis->blPop("code:$lid");
        }
        return $code;
    }

    public function Generate($lid)
    {
        $this->redis->multi(\Redis::PIPELINE);
        //$this->redis->multi(\Redis::MULTI);
        $pool = [];
        for ($i=0; $i<2000; $i++) {
            $code =  self::code();
            if (in_array($code, $pool)){
                $i -= 1;
                continue;
            }
            $pool[] = $code;
            $this->redis->lPush("code:$lid", $code);
        }
        $this->redis->exec();
        return true;
    }

    static function code()
    {
        $list = [0,1,2,3,4,5,6,7,8,9];
        for ($i=10;$i>1;$i--) {
            $rand = mt_rand(0,9);
            $tmp  = $list[$rand];
            $list[$rand] = $list[$i - 1];
            $list[$i-1] = $tmp;
        }
        $result = 0;
        for ($i=0; $i<6; $i++) {
            $result = $result * 10 + $list[$i];
        }
        return $result;
    }
}
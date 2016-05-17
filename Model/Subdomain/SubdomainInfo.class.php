<?php
/**
 * 域名信息模型
 */

namespace Model\Subdomain;
use Library\Model;
use Model\Member\Member;

class SubdomainInfo extends Model {

    const __DOMAIN__            = 'www.12301.cc';  //默认主域名

    const __MEMBER__TABLE__     = 'pft_member';   //会员信息主表

    const __DUBDOMAIN_TABLE__   = 'pft_member_domain_info'; //二级域名绑定信息表

    const __WX_CONFIG_TABLE__   = 'pft_wx_shop_config'; //微商城配置表

    const __CHANNEL_TABLE__     = 'pft_sale_channel';   //销售渠道配置表
    
    /**
     * 判断是否是子域名
     * @param  string  $subdomain 子域名
     * @param  string  $domain    主域名
     * @return boolean true/false
     */
    public static function isSubDomain($subdomain, $domain = null) {
        $domain = $domain ?: self::__DOMAIN__;

        $domain_arr = explode('.' , $domain);
        $domain_tail = $domain_arr[1] . $domain_arr[2];

        $subdomain_arr = explode('.', $subdomain);
        $subdomain_tail = $subdomain_arr[1] . $subdomain_arr[2];

        return $domain_tail == $subdomain_tail ? true : false;
    }

    /**
     * 获取已绑定的二级域名信息
     * @param  mixed $memberid  id/account
     * @return mixed           [description]
     */
    public function getBindedSubdomainInfo($memberid, $identify = 'id') {

        if ($identify == 'id') {
            return $this->table(self::__DUBDOMAIN_TABLE__)->where(['fid' => $memberid])->find();
        } elseif ($identify == 'account') {
            $domain_info = $this->table(self::__DUBDOMAIN_TABLE__)
                            ->where(['M_account_domain' => $memberid, 'M_domain'=> $memberid, '_logic' => 'OR'])
                            ->find();
            return $domain_info;
            // if (!$memberid) {
            //     return false;
            // }
            // return $this->table(self::__DUBDOMAIN_TABLE__)->where(['fid' => $memberid])->find();
        } else {
            return false;
        }

    }


    public function subdomainChannelPids($memberid) {
        $where = "fid={$memberid} and find_in_set(2, channel)";
        return $this->table(self::__CHANNEL_TABLE__)->where($where)->field('pid,px')->select();
    }

    public function getMallConfig($memberid) {
        return $this->table(self::__WX_CONFIG_TABLE__)->where(['member_id' => $memberid])->find();
    }

    




}
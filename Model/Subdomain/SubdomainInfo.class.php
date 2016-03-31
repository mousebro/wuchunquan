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
            $memberid = $this->table(self::__MEMBER__TABLE__)->where(['account' => $memberid])->getField('id');
            if (!$memberid) {
                return false;
            }
            return $this->table(self::__DUBDOMAIN_TABLE__)->where(['fid' => $memberid])->find();
        } else {
            return false;
        }

    }

    /**
     * 获取二级店铺可销售的产品
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function getProductsForSubShop($memberid, $option = array()) {
        // $shop_account = Member::getAccountById($memberid);
        // var_dump($shop_account);
        $channel_pids = $this->subdomainChannelPids(3385);
        var_dump($channel_pids);die;
    }


    public function subdomainChannelPids($memberid) {
        $where = "fid={$memberid} and find_in_set(2, channel)";
        return $this->table(self::__CHANNEL_TABLE__)->where($where)->field('pid,px')->select();
    }

    




}
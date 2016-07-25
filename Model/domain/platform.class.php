<?
namespace Model\domain;
use Library\Model;
use Model\Member\Member;

class Platform extends Model{
    const __DOMAIN__            = 'www.12301.cc';               //默认主域名

    const __MEMBER__TABLE__     = 'pft_member';                 //会员信息主表

    const __DUBDOMAIN_TABLE__   = 'pft_member_domain_platform'; //新版二级域名B端 数据
    
    const __OLDDUBDOMAIN_TABLE__   = 'pft_member_domain_info';  //二级域名绑定信息表
    
    const __WEIXINDOMAIN_TABLE__   = 'pft_wx_open';             //微信logo信息
    
    // public function __construct(){
        // parent::__construct('remote_1');
    // }
 
    public function wxopen($wxModel,$sid){
        $domain_info = $wxModel->table(self::__WEIXINDOMAIN_TABLE__)->where(['fid' => $sid])->find();
        return $domain_info;
    }
    
    public function getDetails($memberid, $identify = 'id') { 
        if ($identify == 'id') {
            return $this->table(self::__DUBDOMAIN_TABLE__)->where(['fid' => $memberid])->find();
        } elseif ($identify == 'account') {
            $domain_info = $this->table(self::__DUBDOMAIN_TABLE__)
                            ->where(['p_account_domain' => $memberid, 'p_domain'=> $memberid, '_logic' => 'OR'])
                            ->find();
            return $domain_info;
        } else {
            return false;
        }
    }
    
    public function getBindedSubdomainInfo($memberid, $identify = 'id') {
        if ($identify == 'id') {
            return $this->table(self::__OLDDUBDOMAIN_TABLE__)->where(['fid' => $memberid])->find();
        } elseif ($identify == 'account') {
            $domain_info = $this->table(self::__OLDDUBDOMAIN_TABLE__)
                            ->where(['M_account_domain' => $memberid, 'M_domain'=> $memberid, '_logic' => 'OR'])
                            ->find();
            return $domain_info;
        } else {
            return false;
        }

    }
    
    public function checkOlddomainInfo($domain){
        return $this->table(self::__OLDDUBDOMAIN_TABLE__)->where(['M_domain' => $domain])->find();
    }
   
    public function checknewddomainInfo($domain){
        return $this->table(self::__DUBDOMAIN_TABLE__)->where(['p_domain' => $domain])->find();
    }
  
}


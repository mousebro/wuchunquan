<?php

namespace Controller\Mall;

use Library\Controller;
use Model\Subdomain\SubdomainInfo;
use Model\Member\Member;

class Common extends Controller {

    /**
     * 获取微商城配置
     * @return [type] [description]
     */
    public function getMallConfig() {
        $SubdomainModel = new SubdomainInfo('remote_1');

        $MemberModel = new Member();

        $account = explode('.', $_SERVER['HTTP_HOST'])[0];
        $owner = $MemberModel->getMemberInfo((int)$account, 'account');

        if (!$owner) {
            $this->apiReturn(400, [], '我要报警了');
        }

        $config = $SubdomainModel->getMallConfig($owner['id']);

        if (!$config) {
            $this->apiReturn(200, [], '');
        }

        $return['name'] = $config['name'];
        $return['banner'] = json_decode($config['others'], true)['banner'];
        
        $this->apiReturn(200, $return, '');

    }
}

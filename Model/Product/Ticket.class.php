<?php
/**
 * 门票信息模型
 */

namespace Model\Product;
use Library\Model;

class Ticket extends Model {

	protected $tableName = 'uu_jq_ticket';

	/**
	 * 根据票类id获取票类信息
	 * @param  int $id 票类id
	 * @return array   
	 */
	public function getTicketInfoById($id) {
		return $this->find($id);
	}

	public function getPackageInfoByTid($tid){
		$table = 'uu_jq_ticket AS t';
		$join = 'join uu_land AS l ON l.id=t.landid';
		$where = ['t.id' => $tid];
		$field = 'l.attribute';
		$jsonRes = $this->table($table)->join($join)->where($where)->field($field)->find();
		if($jsonRes){
			$result = json_decode($jsonRes);
		}else{
			$result=false;
		}
		return $result;
	}
}

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
}

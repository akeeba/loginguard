<?php

namespace SMSApi\Api\Response;

defined('_JEXEC') || die;

/**
 * Class CountableResponse
 * @package SMSApi\Api\AbstractContactsResponse
 */
class CountableResponse extends AbstractResponse
{

	/**
	 * @var int
	 */
	protected $count;

	/**
	 * @param $data
	 */
	function __construct($data)
	{
		parent::__construct($data);

		$this->count = $this->obj->count ?? 0;
	}

	/**
	 * @return int
	 */
	public function getCount()
	{
		return $this->count;
	}

}

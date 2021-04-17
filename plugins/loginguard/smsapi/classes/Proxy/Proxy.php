<?php

namespace SMSApi\Proxy;

defined('_JEXEC') || die;

use SMSApi\Api\Action\AbstractAction;
use SMSApi\Client;
use SMSApi\Exception\ProxyException;

interface Proxy
{
	/**
	 * @param AbstractAction $action
	 *
	 * @return string
	 * @throws ProxyException
	 */
	public function execute(AbstractAction $action);

	/**
	 * @return string|null
	 */
	public function getProtocol();

	/**
	 * @return string|null
	 */
	public function getHost();

	/**
	 * @return int|null
	 */
	public function getPort();

	public function setBasicAuthentication(Client $client);
}

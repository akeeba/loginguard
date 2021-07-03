<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

/**
 * Based on Pushbullet-for-PHP 2.10.1 – https://github.com/ivkos/Pushbullet-for-PHP/tree/v2
 *
 * The license for the original class is as follows:
 * ----------
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Ivaylo Stoyanov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * ----------
 *
 * The following class is a derivative work, NOT the original work.
 */
class LoginGuardPushbulletApi
{
	private $_apiKey;
	private $_curlCallback;

	public const URL_TOKEN = 'https://api.pushbullet.com/oauth2/token';
	public const URL_PUSHES = 'https://api.pushbullet.com/v2/pushes';
	public const URL_DEVICES = 'https://api.pushbullet.com/v2/devices';
	public const URL_CONTACTS = 'https://api.pushbullet.com/v2/contacts';
	public const URL_UPLOAD_REQUEST = 'https://api.pushbullet.com/v2/upload-request';
	public const URL_USERS = 'https://api.pushbullet.com/v2/users';
	public const URL_SUBSCRIPTIONS = 'https://api.pushbullet.com/v2/subscriptions';
	public const URL_CHANNEL_INFO = 'https://api.pushbullet.com/v2/channel-info';
	public const URL_EPHEMERALS = 'https://api.pushbullet.com/v2/ephemerals';
	public const URL_PHONEBOOK = 'https://api.pushbullet.com/v2/permanents/phonebook';

	/**
	 * Pushbullet constructor.
	 *
	 * @param string $apiKey API key.
	 *
	 * @throws LoginGuardPushbulletApiException
	 */
	public function __construct($apiKey)
	{
		$this->_apiKey = $apiKey;

		if (!function_exists('curl_init'))
		{
			throw new LoginGuardPushbulletApiException('cURL library is not loaded.');
		}
	}

	/**
	 * Get the access token for a PushBullet OAuth Client given a redirection code.
	 *
	 * See https://docs.pushbullet.com/#for-client-side-apps-responsetypetoken
	 *
	 * @param   string  $code      The redirection code received by PushBullet
	 * @param   string  $clientId  The OAuth Client's client_id
	 * @param   string  $secret    The OAuth Client's client_secret
	 *
	 * @return  string  The access token for this user
	 *
	 * @throws  LoginGuardPushbulletApiException  When something goes wrong
	 */
	public function getToken($code, $clientId, $secret)
	{
		$data = array(
			'grant_type' => 'authorization_code',
		    'client_id' => $clientId,
		    'client_secret' => $secret,
		    'code' => $code,
		);

		$response = $this->_curlRequest(self::URL_TOKEN, 'POST', $data, true);

		return $response->access_token;
	}


	/**
	 * Push a note.
	 *
	 * @param string $recipient Recipient. Can be device_iden, email or channel #tagname.
	 * @param string $title     The note's title.
	 * @param string $body      The note's message.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function pushNote($recipient, $title, $body = null)
	{
		$data = array();

		LoginGuardPushbulletApi::_parseRecipient($recipient, $data);
		$data['type']  = 'note';
		$data['title'] = $title;
		$data['body']  = $body;

		return $this->_curlRequest(self::URL_PUSHES, 'POST', $data);
	}

	/**
	 * Push a link.
	 *
	 * @param string $recipient Recipient. Can be device_iden, email or channel #tagname.
	 * @param string $title     The link's title.
	 * @param string $url       The URL to open.
	 * @param string $body      A message associated with the link.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function pushLink($recipient, $title, $url, $body = null)
	{
		$data = array();

		LoginGuardPushbulletApi::_parseRecipient($recipient, $data);
		$data['type']  = 'link';
		$data['title'] = $title;
		$data['url']   = $url;
		$data['body']  = $body;

		return $this->_curlRequest(self::URL_PUSHES, 'POST', $data);
	}

	/**
	 * Push a checklist.
	 *
	 * @param string   $recipient Recipient. Can be device_iden, email or channel #tagname.
	 * @param string   $title     The list's title.
	 * @param string[] $items     The list items.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function pushList($recipient, $title, array $items)
	{
		$data = array();

		LoginGuardPushbulletApi::_parseRecipient($recipient, $data);
		$data['type']  = 'list';
		$data['title'] = $title;
		$data['items'] = $items;

		return $this->_curlRequest(self::URL_PUSHES, 'POST', $data);
	}

	/**
	 * Push a file.
	 *
	 * @param string $recipient   Recipient. Can be device_iden, email or channel #tagname.
	 * @param string $filePath    The path of the file to push.
	 * @param string $mimeType    The MIME type of the file. If null, we'll try to guess it.
	 * @param string $title       The title of the push notification.
	 * @param string $body        The body of the push notification.
	 * @param string $altFileName Alternative file name to use instead of the original one.
	 *                            For example, you might want to push 'someFile.tmp' as 'image.jpg'.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function pushFile($recipient, $filePath, $mimeType = null, $title = null, $body = null, $altFileName = null)
	{
		$data = array();

		$fullFilePath = realpath($filePath);

		if (!is_readable($fullFilePath))
		{
			throw new LoginGuardPushbulletApiException('File: File does not exist or is unreadable.');
		}

		if (filesize($fullFilePath) > 25 * 1024 * 1024)
		{
			throw new LoginGuardPushbulletApiException('File: File size exceeds 25 MB.');
		}

		$data['file_name'] = $altFileName ?? basename($fullFilePath);

		// Try to guess the MIME type if the argument is NULL
		$data['file_type'] = $mimeType ?? mime_content_type($fullFilePath);

		// Request authorization to upload the file
		$response         = $this->_curlRequest(self::URL_UPLOAD_REQUEST, 'GET', $data);
		$data['file_url'] = $response->file_url;

		if (version_compare(PHP_VERSION, '5.5.0', '>='))
		{
			$response->data->file = new CURLFile($fullFilePath);
		}
		else
		{
			$response->data->file = '@' . $fullFilePath;
		}

		// Upload the file
		$this->_curlRequest($response->upload_url, 'POST', $response->data, false, false);

		LoginGuardPushbulletApi::_parseRecipient($recipient, $data);
		$data['type']  = 'file';
		$data['title'] = $title;
		$data['body']  = $body;

		return $this->_curlRequest(self::URL_PUSHES, 'POST', $data);
	}

	/**
	 * Get push history.
	 *
	 * @param int    $modifiedAfter Request pushes modified after this UNIX timestamp.
	 * @param string $cursor        Request the next page via its cursor from a previous response. See the API
	 *                              documentation (https://docs.pushbullet.com/http/) for a detailed description.
	 * @param int    $limit         Maximum number of objects on each page.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function getPushHistory($modifiedAfter = 0, $cursor = null, $limit = null)
	{
		$data                   = array();
		$data['modified_after'] = $modifiedAfter;

		if ($cursor !== null)
		{
			$data['cursor'] = $cursor;
		}

		if ($limit !== null)
		{
			$data['limit'] = $limit;
		}

		return $this->_curlRequest(self::URL_PUSHES, 'GET', $data);
	}

	/**
	 * Dismiss a push.
	 *
	 * @param string $pushIden push_iden of the push notification.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function dismissPush($pushIden)
	{
		return $this->_curlRequest(self::URL_PUSHES . '/' . $pushIden, 'POST', array('dismissed' => true));
	}

	/**
	 * Delete a push.
	 *
	 * @param string $pushIden push_iden of the push notification.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function deletePush($pushIden)
	{
		return $this->_curlRequest(self::URL_PUSHES . '/' . $pushIden, 'DELETE');
	}

	/**
	 * Get a list of available devices.
	 *
	 * @param int    $modifiedAfter Request devices modified after this UNIX timestamp.
	 * @param string $cursor        Request the next page via its cursor from a previous response. See the API
	 *                              documentation (https://docs.pushbullet.com/http/) for a detailed description.
	 * @param int    $limit         Maximum number of objects on each page.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function getDevices($modifiedAfter = 0, $cursor = null, $limit = null)
	{
		$data                   = array();
		$data['modified_after'] = $modifiedAfter;

		if ($cursor !== null)
		{
			$data['cursor'] = $cursor;
		}

		if ($limit !== null)
		{
			$data['limit'] = $limit;
		}

		return $this->_curlRequest(self::URL_DEVICES, 'GET', $data);
	}

	/**
	 * Get information about the current user.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function getUserInformation()
	{
		return $this->_curlRequest(self::URL_USERS . '/me', 'GET');
	}

	/**
	 * Update preferences for the current user.
	 *
	 * @param array $preferences Preferences.
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	public function updateUserPreferences($preferences)
	{
		return $this->_curlRequest(self::URL_USERS . '/me', 'POST', array('preferences' => $preferences));
	}

	/**
	 * Add a callback function that will be invoked right before executing each cURL request.
	 *
	 * @param callable $callback The callback function.
	 */
	public function addCurlCallback(callable $callback)
	{
		$this->_curlCallback = $callback;
	}

	/**
	 * Parse recipient.
	 *
	 * @param string $recipient Recipient string.
	 * @param array  $data      Data array to populate with the correct recipient parameter.
	 */
	private static function _parseRecipient($recipient, array &$data)
	{
		if (!empty($recipient))
		{
			if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false)
			{
				$data['email'] = $recipient;
			}
			else
			{
				if (substr($recipient, 0, 1) == "#")
				{
					$data['channel_tag'] = substr($recipient, 1);
				}
				else
				{
					$data['device_iden'] = $recipient;
				}
			}
		}
	}

	/**
	 * Send a request to a remote server using cURL.
	 *
	 * @param string $url        URL to send the request to.
	 * @param string $method     HTTP method.
	 * @param array  $data       Query data.
	 * @param bool   $sendAsJSON Send the request as JSON.
	 * @param bool   $auth       Use the API key to authenticate
	 *
	 * @return object Response.
	 * @throws LoginGuardPushbulletApiException
	 */
	private function _curlRequest($url, $method, $data = null, $sendAsJSON = true, $auth = true)
	{
		$curl = curl_init();

		if ($method == 'GET' && $data !== null)
		{
			$url .= '?' . http_build_query($data);
		}

		curl_setopt($curl, CURLOPT_URL, $url);

		$headers = array();

		if ($auth)
		{
			$headers[] = 'Access-Token: ' . $this->_apiKey;
		}

		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

		if ($method == 'POST' && $data !== null)
		{
			if ($sendAsJSON)
			{
				$data = json_encode($data);
				$headers[] = 'Content-Type: application/json';
				$headers[] = 'Content-Length: ' . strlen($data);
			}

			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}

		if (!empty($headers))
		{
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);

		$caCertPath = class_exists('\\Composer\\CaBundle\\CaBundle')
			? \Composer\CaBundle\CaBundle::getBundledCaBundlePath()
			: JPATH_LIBRARIES . '/src/Http/Transport/cacert.pem';

		@curl_setopt($curl, CURLOPT_CAINFO, $caCertPath);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		if ($this->_curlCallback !== null)
		{
			$curlCallback = $this->_curlCallback;
			$curlCallback($curl);
		}

		$response = curl_exec($curl);

		if ($response === false)
		{
			$curlError = curl_error($curl);
			curl_close($curl);
			throw new LoginGuardPushbulletApiException('cURL Error: ' . $curlError);
		}

		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ($httpCode >= 400)
		{
			curl_close($curl);
			$responseParsed = json_decode($response);
			throw new LoginGuardPushbulletApiException('HTTP Error ' . $httpCode .
				' (' . $responseParsed->error->type . '): ' . $responseParsed->error->message);
		}

		curl_close($curl);

		return json_decode($response);
	}
}

class LoginGuardPushbulletApiException extends Exception
{
	// Exception thrown by Pushbullet
}

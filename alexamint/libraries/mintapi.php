<?php defined('BASEPATH') || exit('No direct script access allowed');
/**
 * A simple PHP class for interacting with Mint.com
 *
 * This class provides methods for connecting to Mint.com as a specific
 * user and downloading that user's transactions in CSV format.
 *
 * PHP version 5
 *
 * @author     Aaron Forgue <forgue@gmail.com>
 * @link       https://github.com/forgueam/php-mint-api
 */
class MintApi {
	/**
	 * The base Mint.com URL used for all HTTP reuqests
	 */
	private $mintBaseUrl = 'https://wwws.mint.com';
	/**
	 * The URL action used for authenticating a Mint.com user
	 */
	private $mintLoginAction = 'loginUserSubmit.xevent';
	/**
	 * The URL action used for downloading all transactions from 
	 * a user's Mint.com account
	 */
	private $mintTransactionsAction = 'transactionDownload.event?';
	/**
	 * The absolute path to a writeable file in which to store
	 * cURL session cookie data
	 */
	private $cookieFilePath;
	/**
	 * Mint.com user credentials
	 */
	private $mintUserEmail;
	private $mintUserPassword;
	/**
	 * Initialize object with user credentials and session cookie file
	 *
	 * @param sting $email Mint.com user email
	 * @param string $password Mint.com user password
	 * @param string $cookieFilePath Absolute path to writeable file
	 */
	function __construct($email, $password, $cookieFilePath) {
		// Make sure the cookie jar is writeable
		if (!file_exists($cookieFilePath) || !is_writable($cookieFilePath)) {
			throw new Exception('Cookie file does not exist or is not writeable.');
		}
		$this->mintUserEmail = $email;
		$this->mintUserPassword = $password;
		$this->cookieFilePath = $cookieFilePath;
	}
	/**
	 * Log user into Mint.com and store session cookies
	 */
	public function connect() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->mintBaseUrl . '/' . $this->mintLoginAction);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFilePath);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'username' => $this->mintUserEmail,
			'password' => $this->mintUserPassword,
			'task' => 'L',
			'nextPage' => ''
		));
		$response = curl_exec($ch);
		$curlError = curl_error($ch);
		curl_close($ch);
		unset($ch);
		if ($response === false) {
			throw new Exception('cURL Error: ' . $curlError);
		}
		if (strpos(strtolower($response), 'javascript-token') === false) {
			throw new Exception('Mint.com login failed.');
		}
	}
	/**
	 * Download a comma-separated value string of all account transactions
	 *
	 * @param resource $outputFilePointer File pointer to which output will be written
	 * @return string|bool The comma-separated value data returned from the HTTP request
	 */
	public function getTransactions($outputFilePointer = null) {
		// Throw exception if $outputFilePointer is not a valid file pointer resource
		if (isset($outputFilePointer)) {
			$streamMeta = stream_get_meta_data($outputFilePointer);
			if (!isset($streamMeta['wrapper_type']) || $streamMeta['wrapper_type'] != 'plainfile') {
				throw new Exception('Invalid resource type. File pointer required for output paramenter.');
				return false;
			}
			unset($streamMeta);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->mintBaseUrl . '/' . $this->mintTransactionsAction);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFilePath);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		if (isset($outputFilePointer)) {
			curl_setopt($ch, CURLOPT_FILE, $outputFilePointer);
		}
		$response = curl_exec($ch);
		$curlError = curl_error($ch);
		curl_close($ch);
		unset($ch);
		if ($response === false) {
			throw new Exception('cURL Error: ' . $curlError);
			return false;
		}
		if (!isset($outputFilePointer)) {
			return $response;
		}
		return true;
	}
}

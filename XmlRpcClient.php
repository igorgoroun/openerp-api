<?php

namespace Simbigo\OpenERP;

class XmlRpcClient
{
	public $userAgent = 'Simbigo XML-RPC Client';

	private $_host;
	private $_port;
	private $_path = '';
	private $_charset = 'utf-8';
	private $_lastRawResponse;
	private $_lastRequest;

	public function __construct($host, $port = 80, $charset = 'utf-8')
	{
		$this->setHost($host);
		$this->setPort($port);
		$this->setCharset($charset);
	}
	
	public function getHost()
	{
		return $this->_host;
	}

	public function setHost($host)
	{
		$this->_host = rtrim($host, '/');
	}
	
	public function setCharset($charset)
	{
		$this->_charset = $charset;
	}
	
	public function getCharset()
	{
		return $this->_charset;
	}
	
	public function getPort()
	{
		return $this->_port;
	}
	
	public function setPort($port)
	{
		$this->_port = (int)$port;
	}
	
	public function getPath()
	{
		return $this->_path;
	}
	
	public function setPath($path)
	{
		$this->_path = $path;
	}
	
	public function getLastResponse()
	{
		return $this->_lastRawResponse;
	}
	
	public function getLastRequest()
	{
		return $this->_lastRequest;
	}
	
	public function call($method, $params = [])
	{
		if (function_exists('xmlrpc_encode_request')) {
			$options = [
				'encoding' => $this->getCharset(), 
				'version' => 'xmlrpc',
				'escaping' => 'markup',
			];
			$payload = xmlrpc_encode_request($method, $params, $options);
		} else {
			$payload = $this->encodeRequest($method, $params);
		}
		
		$this->_lastRequest = $payload;

		$context = stream_context_create([
				'http' => [
					'method' => "POST",
					'header' => $this->getDefaultHeader(),
					'content' => $payload
				]
		]);
		$uri = $this->getHost() . ':' . $this->getPort() . $this->getPath();
		$xml = file_get_contents($uri, false, $context);
		
		
		$this->_lastRawResponse = $xml;

		$response = new \SimpleXMLElement($xml);
		$response = json_encode($response);
		$response = json_decode($response, true);
		return $response;
	}
	
	public function getDefaultHeader()
	{
		$headers  = "";
		$headers .= "Content-Type: text/xml\r\n";
		$headers .= "User-Agent: " . $this->userAgent . "\r\n";
		return $headers;
	}

	public function encodeRequest($method, array $params)
	{
		$payload = '<?xml version="1.0" encoding="' . $this->getCharset() . '"?>' . "\r\n";
		$payload .= "\t" . '<methodCall>' . "\r\n";
		$payload .= "\t\t" . '<methodName>' . $method . '</methodName>' . "\r\n";
		$payload .= "\t\t" . '<params>' . "\r\n";

		foreach ($params as $param) {
			$payload .= "<param>\r\n" . $this->encodeParam($param) . "</param>\r\n";
		}

		$payload .= "\t\t" . '</params>' . "\r\n";
		$payload .= "\t" . '</methodCall>' . "\r\n";

		return $payload;
	}

	public function encodeParam($param)
	{
		switch (gettype($param)) {
			case 'boolean':
				$encoded = '<value><boolean>' . $param . '</boolean></value>' . "\r\n";
				break;
			
			case 'double':
				$encoded = '<value><double>' . $param . '</double></value>' . "\r\n";
				break;

			case 'integer':
				$encoded = '<value><int>' . $param . '</int></value>' . "\r\n";
				break;

			case 'string':
				$encoded = '<value><string>' . $param . '</string></value>' . "\r\n";
				break;

			case 'array':
				$encoded = $this->encodeArray($param);
				break;

			default:
				$encoded = false;
				break;
		}
		return $encoded;
	}

	private function encodeArray($array)
	{
		if ($this->isAssoc($array)) {
			$encoded = '<struct>' . "\r\n";

			foreach ($array as $key => $value) {
				$encoded .= '<member>' . "\r\n";
				$encoded .= '<name>' . $key . '</name>' . "\r\n";
				$encoded .= $this->encodeParam($value);
				$encoded .= '</member>' . "\r\n";
			}
			$encoded .= '</struct>' . "\r\n";
		} else {
			$encoded = '<array>' . "\r\n";
			$encoded .= '<data>' . "\r\n";

			foreach ($array as $value) {
				$encoded .= $this->encodeParam($value);
			}
			$encoded .= '</data>' . "\r\n";
			$encoded .= '</array>' . "\r\n";
		}
		return $encoded;
	}

	private function isAssoc($array)
	{
		if (is_array($array) && !is_numeric(array_shift(array_keys($array)))) {
			return true;
		}
		return false;
	}
}
<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP WebServer class.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// Internal client class constructed by the web server class.
	class WebServer_Client
	{
		public function GetHTTPOptions()
		{
			return ($this->httpstate !== false ? $this->httpstate["options"] : false);
		}

		public function SetHTTPOptions($options)
		{
			if ($this->httpstate !== false)  $this->httpstate["options"] = $options;
		}

		public function SetResponseCode($code)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				if (is_int($code))
				{
					$codemap = array(
						100 => "Continue", 101 => "Switching Protocols", 102 => "Processing",

						200 => "OK", 201 => "Created", 202 => "Accepted", 203 => "Non-Authoritative Information", 204 => "No Content", 205 => "Reset Content",
						206 => "Partial Content", 207 => "Multi-Status", 208 => "Already Reported", 226 => "IM Used",

						300 => "Multiple Choices", 301 => "Moved Permanently", 302 => "Found", 303 => "See Other", 304 => "Not Modified", 305 => "Use Proxy",
						306 => "Switch Proxy", 307 => "Temporary Redirect", 308 => "Permanent Redirect",

						400 => "Bad Request", 401 => "Unauthorized", 402 => "Payment Required", 403 => "Forbidden", 404 => "Not Found", 405 => "Method Not Allowed",
						406 => "Not Acceptable", 407 => "Proxy Authentication Required", 408 => "Request Timeout", 409 => "Conflict", 410 => "Gone",
						411 => "Length Required", 412 => "Precondition Failed", 413 => "Payload Too Large", 414 => "URI Too Long", 415 => "Unsupported Media Type",
						416 => "Range Not Satisfiable", 417 => "Expectation Failed", 418 => "I'm a teapot", 421 => "Misdirected Request",
						422 => "Unprocessable Entity", 423 => "Locked", 424 => "Failed Dependency", 426 => "Upgrade Required", 428 => "Precondition Required",
						429 => "Too Many Requests", 431 => "Request Header Fields Too Large", 451 => "Unavailable For Legal Reasons",

						500 => "Internal Server Error", 501 => "Not Implemented", 502 => "Bad Gateway", 503 => "Service Unavailable", 504 => "Gateway Timeout",
						505 => "HTTP Version Not Supported", 506 => "Variant Also Negotiates", 507 => "Insufficient Storage", 508 => "Loop Detected",
						510 => "Not Extended", 511 => "Network Authentication Required",
					);

					if (!isset($codemap[$code]))  $code = 500;

					$code = $code . " " . $codemap[$code];
				}

				$this->httpstate["data"] = $this->request["httpver"] . " " . $code . "\r\n";
				$this->writedata = "";
			}
		}

		public function SetResponseContentType($contenttype)
		{
			$this->AddResponseHeader("Content-Type", $contenttype, true);
		}

		public function SetResponseCookie($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false)
		{
			if (!empty($domain))
			{
				// Remove port information.
				$pos = strpos($domain, "]");
				if (substr($domain, 0, 1) == "[" && $pos !== false)  $domain = substr($domain, 0, $pos + 1);
				else
				{
					$port = strpos($domain, ":");
					if ($port !== false)  $domain = substr($domain, 0, $port);

					// Fix the domain to accept domains with and without 'www.'.
					if (strtolower(substr($domain, 0, 4)) == "www.")  $domain = substr($domain, 4);
					if (strpos($domain, ".") === false)  $domain = "";
					else if (substr($domain, 0, 1) != ".")  $domain = "." . $domain;
				}
			}

			$this->AddResponseHeader("Set-Cookie", rawurlencode($name) . "=" . rawurlencode($value)
									. (empty($expires) ? "" : "; expires=" . gmdate("D, d-M-Y H:i:s", $expires) . " GMT")
									. (empty($path) ? "" : "; path=" . $path)
									. (empty($domain) ? "" : "; domain=" . $domain)
									. (!$secure ? "" : "; secure")
									. (!$httponly ? "" : "; HttpOnly"));
		}

		public function SetResponseNoCache()
		{
			$this->AddResponseHeader("Expires", "Tue, 03 Jul 2001 06:00:00 GMT", true);
			$this->AddResponseHeader("Last-Modified", gmdate("D, d M Y H:i:s T"), true);
			$this->AddResponseHeader("Cache-Control", "max-age=0, no-cache, must-revalidate, proxy-revalidate", true);
		}

		public function AddResponseHeader($name, $val, $replace = false)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				$name = preg_replace('/\s+/', "-", trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)));

				if (!isset($this->responseheaders[$name]) || $replace)  $this->responseheaders[$name] = array();
				$this->responseheaders[$name][] = $val;
			}
		}

		public function AddResponseHeaders($headers, $replace = false)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				foreach ($headers as $name => $vals)
				{
					if (is_string($vals))  $vals = array($vals);

					$name = preg_replace('/\s+/', "-", trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)));

					if (!isset($this->responseheaders[$name]) || $replace)  $this->responseheaders[$name] = array();
					foreach ($vals as $val)  $this->responseheaders[$name][] = $val;
				}
			}
		}

		public function SetResponseContentLength($bodysize)
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				$this->responsebodysize = $bodysize;

				if ($this->mode !== "handle_response")  $this->mode = "response_ready";
			}
		}

		public function AddResponseContent($data)
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				if ($this->deflate !== false)
				{
					$this->deflate->Write($data);
					$data = $this->deflate->Read();
				}

				$this->writedata .= $data;

				if ($this->mode !== "handle_response")  $this->mode = "response_ready";
			}
		}

		public function FinalizeResponse()
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				if ($this->deflate !== false)
				{
					$this->deflate->Finalize();
					$data = $this->deflate->Read();

					$this->writedata .= $data;

					$this->deflate = false;
				}

				if ($this->mode !== "handle_response")  $this->mode = "response_ready";

				$this->responsefinalized = true;
			}
		}
	}
?>

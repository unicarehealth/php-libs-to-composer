<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP WebServer class.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	// Requires the CubicleSoft PHP HTTP class.
	// Compression support requires the CubicleSoft PHP DeflateStream class.
	class WebServer
	{
		private $fp, $ssl, $initclients, $clients, $nextclientid;
		private $defaulttimeout, $defaultclienttimeout, $maxrequests, $defaultclientoptions, $usegzip, $cachedir;

		public function __construct()
		{
			$this->Reset();
		}

		public function Reset()
		{


			$this->fp = false;
			$this->ssl = false;
			$this->initclients = array();
			$this->clients = array();
			$this->nextclientid = 1;

			$this->defaulttimeout = 30;
			$this->defaultclienttimeout = 30;
			$this->maxrequests = 30;
			$this->defaultclientoptions = array();
			$this->usegzip = false;
			$this->cachedir = false;
		}

		public function __destruct()
		{
			$this->Stop();
		}

		public function SetDefaultTimeout($timeout)
		{
			$this->defaulttimeout = (int)$timeout;
		}

		public function SetDefaultClientTimeout($timeout)
		{
			$this->defaultclienttimeout = (int)$timeout;
		}

		public function SetMaxRequests($num)
		{
			$this->maxrequests = (int)$num;
		}

		public function SetDefaultClientOptions($options)
		{
			$this->defaultclientoptions = $options;
		}

		public function EnableCompression($compress)
		{


			$this->usegzip = (bool)$compress;
		}

		public function SetCacheDir($cachedir)
		{
			if ($cachedir !== false)
			{
				$cachedir = str_replace("\\", "/", $cachedir);
				if (substr($cachedir, -1) !== "/")  $cachedir .= "/";
			}

			$this->cachedir = $cachedir;
		}

		// Starts the server on the host and port.
		// $host is usually 0.0.0.0 or 127.0.0.1 for IPv4 and [::0] or [::1] for IPv6.
		public function Start($host, $port, $sslopts = false)
		{
			$this->Stop();

			$context = stream_context_create();

			if (is_array($sslopts))
			{
				// Mozilla Intermediate setting.
				// Last updated April 22, 2016.
				stream_context_set_option($context, "ssl", "ciphers", "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS");
				stream_context_set_option($context, "ssl", "disable_compression", true);
				stream_context_set_option($context, "ssl", "allow_self_signed", true);
				stream_context_set_option($context, "ssl", "verify_peer", false);

				// 'local_cert' and 'local_pk' are common options.
				foreach ($sslopts as $key => $val)
				{
					stream_context_set_option($context, "ssl", $key, $val);
				}

				$this->ssl = true;
			}

			$this->fp = stream_socket_server("tcp://" . $host . ":" . $port, $errornum, $errorstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
			if ($this->fp === false)  return array("success" => false, "error" => \CubicleSoft\HTTP::HTTPTranslate("Bind() failed.  Reason:  %s (%d)", $errorstr, $errornum), "errorcode" => "bind_failed");

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			return array("success" => true);
		}

		public function Stop()
		{
			if ($this->fp !== false)
			{
				foreach ($this->initclients as $id => $client)
				{
					@fclose($client->fp);
				}

				foreach ($this->clients as $id => $client)
				{
					$this->RemoveClient($id);
				}

				fclose($this->fp);

				$this->initclients = array();
				$this->clients = array();
				$this->fp = false;
				$this->ssl = false;
			}

			$this->nextclientid = 1;
		}

		// Dangerous but allows for stream_select() calls on multiple, separate stream handles.
		public function GetStream()
		{
			return $this->fp;
		}

		public function AddClientRecvHeader($id, $name, $val)
		{
			$client = $this->clients[$id];

			if (substr($name, -2) !== "[]")  $client->requestvars[$name] = $val;
			else
			{
				$name = substr($name, 0, -2);

				if (!isset($client->requestvars[$name]) || !is_array($client->requestvars[$name]))  $client->requestvars[$name] = array();
				$client->requestvars[$name][] = $val;
			}
		}

		public function ProcessClientRequestHeaders($request, $headers, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			$client->request = $request;

			$client->headers = array();
			foreach ($headers as $key => $vals)
			{
				$client->headers[$key] = $vals[count($vals) - 1];
			}

			if (isset($client->headers["Content-Type"]))  $client->contenttype = \CubicleSoft\HTTP::ExtractHeader($client->headers["Content-Type"]);

			if (isset($client->headers["Host"]))  $client->headers["Host"] = preg_replace('/[^a-z0-9.:\[\]_-]/', "", strtolower($client->headers["Host"]));

			$client->url = ($this->ssl ? "https" : "http") . "://" . (isset($client->headers["Host"]) ? $client->headers["Host"] : "localhost") . $request["path"];

			// Process cookies.
			$client->cookievars = array();
			$client->requestvars = array();
			if (isset($client->headers["Cookie"]))
			{
				$cookies = explode(";", $client->headers["Cookie"]);
				foreach ($cookies as $cookie)
				{
					$pos = strpos($cookie, "=");
					if ($pos === false)
					{
						$name = $cookie;
						$val = "";
					}
					else
					{
						$name = substr($cookie, 0, $pos);
						$val = urldecode(trim(substr($cookie, $pos + 1)));
					}

					$name = urldecode(trim($name));

					$this->AddClientRecvHeader($id, $name, $val);

					if (substr($name, -2) !== "[]")  $client->cookievars[$name] = $val;
					else
					{
						$name = substr($name, 0, -2);

						if (!isset($client->cookievars[$name]) || !is_array($client->cookievars[$name]))  $client->cookievars[$name] = array();
						$client->cookievars[$name][] = $val;
					}
				}
			}

			// Process query string.
			$url = \CubicleSoft\HTTP::ExtractURL($client->url);
			foreach ($url["queryvars"] as $name => $vals)
			{
				foreach ($vals as $val)  $this->AddClientRecvHeader($id, $name, $val);
			}

			return true;
		}

		public function ProcessClientRequestBody($request, $body, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			if ($body !== "")
			{
				if (is_object($client->readdata))  $client->readdata->Write($body);
				else
				{
					$client->readdata .= $body;
					if (strlen($client->readdata) > 262144)  return false;

					if ($client->contenttype !== false)
					{
						if ($client->contenttype[""] === "application/x-www-form-urlencoded")
						{
							$pos = 0;
							$pos2 = strpos($client->readdata, "&");
							while ($pos2 !== false)
							{
								$str = (string)substr($client->readdata, $pos, $pos2 - $pos);
								$pos3 = strpos($str, "=");
								if ($pos3 === false)
								{
									$name = $str;
									$val = "";
								}
								else
								{
									$name = substr($str, 0, $pos3);
									$val = urldecode(trim(substr($str, $pos3 + 1)));
								}

								$name = urldecode(trim($name));

								$this->AddClientRecvHeader($id, $name, $val);

								$pos = $pos2 + 1;
								$pos2 = strpos($client->readdata, "&", $pos);
							}

							if ($pos)  $client->readdata = substr($client->readdata, $pos);
						}
						else if ($client->contenttype[""] === "multipart/form-data" && isset($client->contenttype["boundary"]))
						{
							$pos = 0;
							do
							{
								$origmode = $client->mode;

								switch ($client->mode)
								{
									case "handle_request":
									{
										$pos2 = strpos($client->readdata, "\n", $pos);
										if ($pos2 === false)  break;
										$str = trim(substr($client->readdata, $pos, $pos2 - $pos));
										if ($str === "--" . $client->contenttype["boundary"] . "--")
										{
											$pos = $pos2 + 1;
										}
										else if ($str === "--" . $client->contenttype["boundary"])
										{
											$pos = $pos2 + 1;
											$client->mode = "handle_request_mime_headers";
											$client->mimeheaders = array();
											$client->lastmimeheader = "";
										}

										break;
									}
									case "handle_request_mime_headers":
									{
										while (($pos2 = strpos($client->readdata, "\n", $pos)) !== false)
										{
											$header = rtrim(substr($client->readdata, $pos, $pos2 - $pos));
											$pos = $pos2 + 1;
											if ($header != "")
											{
												if ($client->lastmimeheader != "" && (substr($header, 0, 1) == " " || substr($header, 0, 1) == "\t"))  $client->mimeheaders[$client->lastmimeheader] .= $header;
												else
												{
													$pos2 = strpos($header, ":");
													if ($pos2 === false)  $pos2 = strlen($header);
													$client->lastmimeheader = \CubicleSoft\HTTP::HeaderNameCleanup(substr($header, 0, $pos2));
													$client->mimeheaders[$client->lastmimeheader] = ltrim(substr($header, $pos2 + 1));
												}

												if (isset($client->httpstate["options"]["maxheaders"]) && count($client->mimeheaders) > $client->httpstate["options"]["maxheaders"])  return false;
											}
											else
											{
												if (!isset($client->mimeheaders["Content-Disposition"]))  $client->mode = "handle_request_mime_content_skip";
												else
												{
													$client->mime_contentdisposition = \CubicleSoft\HTTP::ExtractHeader($client->mimeheaders["Content-Disposition"]);

													if ($client->mime_contentdisposition[""] !== "form-data" || !isset($client->mime_contentdisposition["name"]) || $client->mime_contentdisposition["name"] === "")
													{
														$client->mode = "handle_request_mime_content_skip";
													}
													else if ($this->cachedir !== false && isset($client->mime_contentdisposition["filename"]) && $client->mime_contentdisposition["filename"] !== "")
													{
														if ($client->currfile !== false)  $client->files[$client->currfile]->Close();

														$filename = $this->cachedir . $id . "_" . count($client->files) . ".dat";
														$client->currfile = $filename;

														@unlink($filename);
														$tempfile = new \CubicleSoft\WebServer_TempFile();
														$tempfile->filename = $filename;
														$tempfile->Open();

														$client->files[$filename] = $tempfile;
														$this->AddClientRecvHeader($id, $client->mime_contentdisposition["name"], $tempfile);

														$client->mode = "handle_request_mime_content_file";
													}
													else
													{
														$client->mime_value = "";

														$client->mode = "handle_request_mime_content";
													}
												}

												break;
											}
										}

										break;
									}
									case "handle_request_mime_content_skip":
									case "handle_request_mime_content_file":
									case "handle_request_mime_content":
									{
										$pos3 = $pos2 = strpos($client->readdata, "\r\n--" . $client->contenttype["boundary"], $pos);
										if ($pos3 === false)
										{
											$pos3 = strlen($client->readdata) - strlen($client->contenttype["boundary"]) - 6;
											if ($pos3 < $pos)  $pos3 = $pos;
										}

										$data = (string)substr($client->readdata, $pos, $pos3 - $pos);
										if ($data !== "")
										{
											if ($origmode === "handle_request_mime_content_file")  $client->files[$client->currfile]->Write($data);
											else if ($origmode === "handle_request_mime_content")
											{
												$client->mime_value .= $data;

												if (strlen($client->mime_value) > 262144)  return false;
											}
										}

										if ($pos2 === false)  $pos = $pos3;
										else
										{
											if ($client->mode === "handle_request_mime_content")  $this->AddClientRecvHeader($id, $client->mime_contentdisposition["name"], $client->mime_value);

											$client->mode = "handle_request";

											$pos = $pos2 + 2;
										}

										break;
									}
								}
							} while ($origmode !== $client->mode);

							if ($pos)  $client->readdata = (string)substr($client->readdata, $pos);
						}
						else if ($this->cachedir !== false && strlen($client->readdata) > 100000)
						{
							$client->contenthandled = false;

							$filename = $this->cachedir . $id . ".dat";
							$client->currfile = $filename;

							@unlink($filename);
							$tempfile = new \CubicleSoft\WebServer_TempFile();
							$tempfile->filename = $filename;
							$tempfile->Open();

							$client->files[$filename] = $tempfile;
							$client->files[$filename]->Write($client->readdata);

							$client->readdata = $tempfile;
						}
						else
						{
							$client->contenthandled = false;
						}
					}
				}
			}

			return true;
		}

		public function ProcessClientResponseBody(&$data, &$bodysize, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			$data .= $client->writedata;
			$client->writedata = "";

			if ($bodysize === false && $client->responsefinalized)  $bodysize = true;

			return true;
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			if ($this->fp !== false)  $readfps[$prefix . "http_s"] = $this->fp;
			if ($timeout === false || $timeout > $this->defaulttimeout)  $timeout = $this->defaulttimeout;

			$ts = microtime(true);
			foreach ($this->initclients as $id => $client)
			{
				if ($client->mode === "init")
				{
					$readfps[$prefix . "http_c_" . $id] = $client->fp;
					if ($timeout > 1)  $timeout = 1;
				}
			}
			foreach ($this->clients as $id => $client)
			{
				if ($client->httpstate !== false)
				{
					if ($ts < $client->httpstate["waituntil"] && $timeout > $client->httpstate["waituntil"] - $ts + 0.5)
					{
						$timeout = (int)($client->httpstate["waituntil"] - $ts + 0.5);

						$client->lastts = $ts;
					}
					else if (\CubicleSoft\HTTP::WantRead($client->httpstate))  $readfps[$prefix . "http_c_" . $id] = $client->fp;
					else if ($client->mode !== "init_response" && ($client->writedata !== "" || $client->httpstate["data"] !== ""))  $writefps[$prefix . "http_c_" . $id] = $client->fp;
				}
			}
		}

		// Sometimes keyed arrays don't work properly.
		public static function FixedStreamSelect(&$readfps, &$writefps, &$exceptfps, $timeout)
		{
			// In order to correctly detect bad outputs, no '0' integer key is allowed.
			if (isset($readfps[0]) || isset($writefps[0]) || ($exceptfps !== NULL && isset($exceptfps[0])))  return false;

			$origreadfps = $readfps;
			$origwritefps = $writefps;
			$origexceptfps = $exceptfps;

			$result2 = @stream_select($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return false;

			if (isset($readfps[0]))
			{
				$fps = array();
				foreach ($origreadfps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($readfps as $num => $fp)
				{
					$readfps[$fps[(int)$fp]] = $fp;

					unset($readfps[$num]);
				}
			}

			if (isset($writefps[0]))
			{
				$fps = array();
				foreach ($origwritefps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($writefps as $num => $fp)
				{
					$writefps[$fps[(int)$fp]] = $fp;

					unset($writefps[$num]);
				}
			}

			if ($exceptfps !== NULL && isset($exceptfps[0]))
			{
				$fps = array();
				foreach ($origexceptfps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($exceptfps as $num => $fp)
				{
					$exceptfps[$fps[(int)$fp]] = $fp;

					unset($exceptfps[$num]);
				}
			}

			return true;
		}

		public function InitNewClient()
		{
			$client = new \CubicleSoft\WebServer_Client();

			$client->id = $this->nextclientid;
			$client->mode = "init";
			$client->httpstate = false;
			$client->readdata = "";
			$client->request = false;
			$client->url = "";
			$client->headers = false;
			$client->contenttype = false;
			$client->contenthandled = true;
			$client->cookievars = false;
			$client->requestvars = false;
			$client->requestcomplete = false;
			$client->keepalive = true;
			$client->requests = 0;
			$client->responseheaders = false;
			$client->responsefinalized = false;
			$client->deflate = false;
			$client->writedata = "";
			$client->lastts = microtime(true);
			$client->fp = false;
			$client->ipaddr = false;
			$client->currfile = false;
			$client->files = array();

			$this->initclients[$this->nextclientid] = $client;

			$this->nextclientid++;

			return $client;
		}

		protected function HandleNewConnections(&$readfps, &$writefps)
		{
			if (isset($readfps["http_s"]))
			{
				while (($fp = @stream_socket_accept($this->fp, 0)) !== false)
				{
					// Enable non-blocking mode.
					stream_set_blocking($fp, 0);

					$client = $this->InitNewClient();
					$client->fp = $fp;
					$client->ipaddr = stream_socket_get_name($fp, true);
				}

				unset($readfps["http_s"]);
			}
		}

		// Handles new connections, the initial conversation, basic packet management, rate limits, and timeouts.
		// Can wait on more streams than just sockets and/or more sockets.  Useful for waiting on other resources.
		// 'http_s' and the 'http_c_' prefix are reserved.
		// Returns an array of clients that may need more processing.
		public function Wait($timeout = false, $readfps = array(), $writefps = array(), $exceptfps = NULL)
		{
			$this->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);

			$result = array("success" => true, "clients" => array(), "removed" => array(), "readfps" => array(), "writefps" => array(), "exceptfps" => array());
			if (!count($readfps) && !count($writefps))  return $result;

			$result2 = self::FixedStreamSelect($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return array("success" => false, "error" => \CubicleSoft\HTTP::HTTPTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Handle new connections.
			$this->HandleNewConnections($readfps, $writefps);

			// Handle clients in the read queue.
			foreach ($readfps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 8 || substr($cid, 0, 7) !== "http_c_")  continue;

				$id = (int)substr($cid, 7);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				$client->lastts = microtime(true);

				if ($client->httpstate !== false)
				{
					$result2 = \CubicleSoft\HTTP::ProcessState($client->httpstate);
					if ($result2["success"])
					{
						// Trigger the last variable to process when extracting form variables.
						if ($client->contenttype !== false && $client->contenttype[""] === "application/x-www-form-urlencoded")  $this->ProcessClientRequestBody($result2["request"], "&", $id);

						if ($client->currfile !== false)
						{
							$client->files[$client->currfile]->Close();

							$client->currfile = false;
						}

						$result["clients"][$id] = $client;

						$client->requestcomplete = true;
						$client->requests++;
						$client->mode = "init_response";
						$client->responseheaders = array();
						$client->responsefinalized = false;
						$client->responsebodysize = false;

						$client->httpstate["type"] = "request";
						$client->httpstate["startts"] = microtime(true);
						$client->httpstate["waituntil"] = -1.0;
						$client->httpstate["data"] = "";
						$client->httpstate["bodysize"] = false;
						$client->httpstate["chunked"] = false;
						$client->httpstate["secure"] = $this->ssl;
						$client->httpstate["state"] = "send_data";

						$client->SetResponseCode(200);
						$client->SetResponseContentType("text/html; charset=UTF-8");

						if (isset($client->headers["Connection"]))
						{
							$connection = \CubicleSoft\HTTP::ExtractHeader($client->headers["Connection"]);
							if (strtolower($connection[""]) === "close")  $client->keepalive = false;
						}

						$ver = explode("/", $client->request["httpver"]);
						$ver = (double)array_pop($ver);
						if ($ver < 1.1)  $client->keepalive = false;

						if ($client->requests >= $this->maxrequests)  $client->keepalive = false;

						if ($this->usegzip && isset($client->headers["Accept-Encoding"]))
						{
							$encodings = \CubicleSoft\HTTP::ExtractHeader($client->headers["Accept-Encoding"]);
							$encodings = explode(",", $encodings[""]);
							$gzip = false;
							foreach ($encodings as $encoding)
							{
								if (strtolower(trim($encoding)) === "gzip")  $gzip = true;
							}

							if ($gzip)
							{
								$client->deflate = new \CubicleSoft\DeflateStream();
								$client->deflate->Init("wb", -1, array("type" => "gzip"));

								$client->AddResponseHeader("Content-Encoding", "gzip", true);
							}
						}
					}
					else if ($result2["errorcode"] !== "no_data")
					{
						if ($client->requests)  $result["removed"][$id] = array("result" => $result2, "client" => $client);

						$this->RemoveClient($id);
					}
					else if ($client->requestcomplete === false && $client->httpstate["state"] !== "request_line" && $client->httpstate["state"] !== "headers")
					{
						// Allows the caller an opportunity to adjust some client options based on inputs on a per-client basis (e.g. recvlimit).
						$result["clients"][$id] = $client;
					}
				}

				unset($readfps[$cid]);
			}

			// Handle clients in the write queue.
			foreach ($writefps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 7) !== "http_c_")  continue;

				$id = (int)substr($cid, 7);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				$client->lastts = microtime(true);

				if ($client->httpstate !== false)
				{
					// Transform the client response into real data.
					if ($client->mode === "response_ready")
					{
						if ($client->responsefinalized)
						{
							$client->AddResponseHeader("Content-Length", (string)strlen($client->writedata), true);
							$client->httpstate["bodysize"] = strlen($client->writedata);
						}
						else if ($client->responsebodysize !== false)
						{
							$client->AddResponseHeader("Content-Length", (string)$client->responsebodysize, true);
							$client->httpstate["bodysize"] = $client->responsebodysize;
						}
						else if ($client->keepalive)
						{
							$client->AddResponseHeader("Transfer-Encoding", "chunked", true);
							$client->httpstate["chunked"] = true;
						}

						$client->AddResponseHeader("Date", gmdate("D, d M Y H:i:s T"), true);

						if (!$client->keepalive || $client->requests >= $this->maxrequests)  $client->AddResponseHeader("Connection", "close", true);

						foreach ($client->responseheaders as $name => $vals)
						{
							foreach ($vals as $val)  $client->httpstate["data"] .= $name . ": " . $val . "\r\n";
						}
						$client->responseheaders = false;

						$client->httpstate["data"] .= "\r\n";

						$client->mode = "handle_response";
					}

					$result2 = \CubicleSoft\HTTP::ProcessState($client->httpstate);
					if ($result2["success"])
					{
						if (!$client->responsefinalized)
						{
							$result["clients"][$id] = $client;
						}
						else if ($client->keepalive && $client->requests < $this->maxrequests)
						{
							// Reset client.
							$client->mode = "init_request";
							$client->httpstate = false;
							$client->readdata = "";
							$client->request = false;
							$client->url = "";
							$client->headers = false;
							$client->contenttype = false;
							$client->contenthandled = true;
							$client->cookievars = false;
							$client->requestvars = false;
							$client->requestcomplete = false;
							$client->deflate = false;
							$client->writedata = "";

							foreach ($client->files as $filename => $tempfile)
							{
								unset($client->files[$filename]);
							}

							$client->files = array();

							$this->initclients[$id] = $client;
							unset($this->clients[$id]);
						}
						else
						{
							$result["removed"][$id] = array("result" => array("success" => true), "client" => $client);

							$this->RemoveClient($id);
						}
					}
					else if ($result2["errorcode"] !== "no_data")
					{
						$result["removed"][$id] = array("result" => $result2, "client" => $client);

						$this->RemoveClient($id);
					}
				}

				unset($writefps[$cid]);
			}

			// Initialize new clients.
			foreach ($this->initclients as $id => $client)
			{
				do
				{
					$origmode = $client->mode;

					switch ($client->mode)
					{
						case "init":
						{
							$result2 = ($this->ssl ? @stream_socket_enable_crypto($client->fp, true, STREAM_CRYPTO_METHOD_TLS_SERVER) : true);

							if ($result2 === true)  $client->mode = "init_request";
							else if ($result2 === false)
							{
								@fclose($client->fp);

								unset($this->initclients[$id]);
							}

							break;
						}
						case "init_request":
						{
							// Use the HTTP class in server mode to handle state.
							// The callback functions are located in WebServer to avoid the issue of pass-by-reference memory leaks.
							$options = $this->defaultclientoptions;
							$options["async"] = true;
							$options["read_headers_callback"] = array($this, "ProcessClientRequestHeaders");
							$options["read_headers_callback_opts"] = $id;
							$options["read_body_callback"] = array($this, "ProcessClientRequestBody");
							$options["read_body_callback_opts"] = $id;
							$options["write_body_callback"] = array($this, "ProcessClientResponseBody");
							$options["write_body_callback_opts"] = $id;

							if (!isset($options["readlinelimit"]))  $options["readlinelimit"] = 116000;
							if (!isset($options["maxheaders"]))  $options["maxheaders"] = 1000;
							if (!isset($options["recvlimit"]))  $options["recvlimit"] = 1000000;

							$startts = microtime(true);
							$timeout = (isset($options["timeout"]) ? $options["timeout"] : false);
							$result2 = array("success" => true, "rawsendsize" => 0, "rawsendheadersize" => 0, "rawrecvsize" => 0, "rawrecvheadersize" => 0, "startts" => $startts);
							$debug = (isset($options["debug"]) && $options["debug"]);
							if ($debug)
							{
								$result2["rawsend"] = "";
								$result2["rawrecv"] = "";
							}

							$client->httpstate = \CubicleSoft\HTTP::InitResponseState($client->fp, $debug, $options, $startts, $timeout, $result2, false, false);
							$client->mode = "handle_request";

							$client->lastts = microtime(true);

							$this->clients[$id] = $client;
							unset($this->initclients[$id]);

							break;
						}
					}
				} while (isset($this->initclients[$id]) && $origmode !== $client->mode);
			}

			// Handle client timeouts.
			$ts = microtime(true);
			foreach ($this->clients as $id => $client)
			{
				if ($client->lastts + $this->defaultclienttimeout < $ts)
				{
					if ($client->requests)  $result["removed"][$id] = array("result" => $result2, "client" => $client);

					$this->RemoveClient($id);
				}
			}

			// Return any extra handles that were being waited on.
			$result["readfps"] = $readfps;
			$result["writefps"] = $writefps;
			$result["exceptfps"] = $exceptfps;

			return $result;
		}

		public function GetClients()
		{
			return $this->clients;
		}

		public function GetClient($id)
		{
			return (isset($this->client[$id]) ? $this->client[$id] : false);
		}

		public function DetachClient($id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			unset($this->clients[$id]);

			return $client;
		}

		public function RemoveClient($id)
		{
			if (isset($this->clients[$id]))
			{
				$client = $this->clients[$id];

				// Remove the client.
				foreach ($client->files as $filename => $fp2)
				{
					@fclose($fp2);
					@unlink($filename);
				}

				if ($client->fp !== false)  @fclose($client->fp);

				unset($this->clients[$id]);
			}
		}
	}
?>

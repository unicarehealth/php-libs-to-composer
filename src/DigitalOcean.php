<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft DigitalOcean PHP SDK.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	// Load dependencies.


	// Implements the full suite of DigitalOcean v2 APIs.
	class DigitalOcean
	{
		private $web, $fp, $debug, $accesstokens, $callbacks;

		public function __construct()
		{
			$this->web = new \CubicleSoft\WebBrowser();
			$this->fp = false;
			$this->debug = false;

			$this->accesstokens = array(
				"clientid" => false,
				"clientsecret" => false,
				"clientscope" => false,
				"returnurl" => false,
				"refreshtoken" => false,
				"bearertoken" => false,
				"bearerexpirets" => -1,
			);

			$this->callbacks = array();
		}

		public function SetAccessTokens($info)
		{
			$this->web = new \CubicleSoft\WebBrowser();
			if (is_resource($this->fp))  @fclose($this->fp);
			$this->fp = false;

			$this->accesstokens = array(
				"clientid" => false,
				"clientsecret" => false,
				"clientscope" => false,
				"returnurl" => false,
				"refreshtoken" => false,
				"bearertoken" => false,
				"bearerexpirets" => -1,
			);

			$this->accesstokens = array_merge($this->accesstokens, $info);

			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)
			{
				$this->accesstokens["clientid"] = "a8a18c6f991462c8b964c2cf226e4aa577c736757cd7d98694c4d0a92839157b";
				$this->accesstokens["clientsecret"] = "e5f9424c1f4fb4b6a9de984df7cbdf7b2bd14802b589cbf19939bdfecd8e193f";
				$this->accesstokens["clientscope"] = array("read", "write");
				$this->accesstokens["returnurl"] = "https://localhost:23121";
				$this->accesstokens["bearerexpirets"] = -1;
			}
		}

		public function GetAccessTokens()
		{
			return $this->accesstokens;
		}

		public function AddAccessTokensUpdatedNotify($callback)
		{
			if (is_callable($callback))  $this->callbacks[] = $callback;
		}

		public function GetLoginURL()
		{
			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)  $this->SetAccessTokens(array());

			$url = "https://cloud.digitalocean.com/v1/oauth/authorize?client_id=" . urlencode($this->accesstokens["clientid"]) . "&scope=" . urlencode(is_array($this->accesstokens["clientscope"]) ? implode(" ", $this->accesstokens["clientscope"]) : $this->accesstokens["clientscope"]) . "&response_type=code&redirect_uri=" . urlencode($this->accesstokens["returnurl"]);

			return $url;
		}

		// This is an internal callback function.  Do not directly call it.
		public function InteractiveLogin__HandleRequest(&$state)
		{
			if (substr($state["url"], 0, strlen($this->accesstokens["returnurl"])) === $this->accesstokens["returnurl"])
			{
				echo self::DO_Translate("DigitalOcean redirected to '%s'.  Processing response.\n\n", $state["url"]);

				$url = \CubicleSoft\HTTP::ExtractURL($state["url"]);

				if (isset($url["queryvars"]["error"]))  echo self::DO_Translate("Unfortunately, an error occurred:  %s (%s)\n\nDid you deny/cancel the consent?\n\n", $url["queryvars"]["error_description"][0], $url["queryvars"]["error"][0]);
				else
				{
					echo self::DO_Translate("Retrieving refresh token from DigitalOcean...\n");

					$result = $this->UpdateRefreshToken($url["queryvars"]["code"][0]);
					if (!$result["success"])  echo self::DO_Translate("Unfortunately, an error occurred while attempting to retrieve tokens:  %s (%s)\n\n", $result["error"], $result["errorcode"]);
					else  echo self::DO_Translate("Refresh token and initial bearer token successfully retrieved!\n\n");
				}

				return false;
			}

			echo self::DO_Translate("Retrieving '%s'...\n\n", $state["url"]);

			return true;
		}

		public function InteractiveLogin()
		{



			echo self::DO_Translate("***************\n");
			echo self::DO_Translate("Starting interactive login for DigitalOcean.\n\n");
			echo self::DO_Translate("During the next few minutes, you will be asked to sign into your DigitalOcean account here on the command-line and then approve this application to have access to perform actions on your behalf within your DigitalOcean account.  You may press Ctrl+C at any time to terminate this application.\n\n");
			echo self::DO_Translate("Every web request made from this point on will be dumped to your console and take the form of \"Retrieving '[URL being retrieved]'...\".\n");
			echo self::DO_Translate("***************\n\n");

			$html = new \CubicleSoft\simple_html_dom();
			$web = new \CubicleSoft\WebBrowser(array("httpopts" => array("pre_retrievewebpage_callback" => array($this, "InteractiveLogin__HandleRequest"))));
			$filteropts = \CubicleSoft\TagFilter::GetHTMLOptions();

			$this->accesstokens["refreshtoken"] = false;
			$this->accesstokens["bearertoken"] = false;
			$this->accesstokens["bearerexpirets"] = -1;

			$result = array(
				"url" => $this->GetLoginURL(),
				"options" => array()
			);

			do
			{
				$result["options"]["sslopts"] = self::InitSSLOpts(array());

				$result2 = $web->Process($result["url"], "auto", $result["options"]);
				if (!$result2["success"])
				{
					if ($this->accesstokens["refreshtoken"] === false)  return $result2;

					echo self::DO_Translate("***************\n");
					echo self::DO_Translate("Interactive login completed successfully.  Resuming where you left off.\n");
					echo self::DO_Translate("***************\n\n");

					return array("success" => true);
				}
				else if ($result2["response"]["code"] != 200)
				{
					return array("success" => false, "error" => self::DO_Translate("Expected a 200 response from DigitalOcean.  Received '%s'.", $result2["response"]["line"]), "errorcode" => "unexpected_digitalocean_response", "info" => $result2);
				}
				else
				{
					$body = \CubicleSoft\TagFilter::Run($result2["body"], $filteropts);
					$html->load($body);

					echo "-----------------------\n\n";

					$title = $html->find('title', 0);
					if ($title)  echo trim($title->plaintext) . "\n\n";

					$h1 = $html->find('h1', 0);
					if ($h1)  echo trim($h1->plaintext) . "\n\n";

					$h2 = $html->find('h2', 0);
					if ($h2)  echo trim($h1->plaintext) . "\n\n";

					$error = $html->find('.errors', 0);
					if ($error)  echo trim(preg_replace('/\s+/', " ", $error->plaintext)) . "\n\n";

					$forms = $web->ExtractForms($result2["url"], $body);

					foreach ($forms as $num => $form)
					{
						if ($form->info["action"] === "https://cloud.digitalocean.com/login/refresh")  unset($forms[$num]);
					}

					if (!count($forms))
					{
						$url = \CubicleSoft\HTTP::ExtractURL($result2["url"]);

						if ($url["host"] === "cloud.digitalocean.com" && $url["path"] === "/v1/oauth/authorize")
						{
							// Construct a fake form.  Might be a touch fragile.
							// Find the window.currentUser Javascript object.
							$user = false;
							$preauth = false;
							$lines = explode("\n", $body);
							foreach ($lines as $line)
							{
								$line = trim($line);
								if (preg_match('/window\.currentUser\s*=\s*(\{.*\})/', $line, $matches))
								{
									$user = json_decode($matches[1], true);
								}

								if ($preauth === false && substr($line, 0, 14) === "window.preAuth")  $preauth = true;
								else if (substr($line, 0, 5) === "name:" || substr($line, 0, 12) === "description:" || substr($line, 0, 4) === "url:")  echo $line . "\n\n";
							}

							if ($user !== false)
							{
								$html2 = "<body>";
								$html2 .= "<form action=\"/oauth/authorize\" method=\"post\">";
								$html2 .= "<input type=\"hidden\" name=\"" . htmlspecialchars($html->find('meta[name=csrf-param]', 0)->content) . "\" value=\"" . htmlspecialchars($html->find('meta[name=csrf-token]', 0)->content) . "\">";
								foreach ($url["queryvars"] as $key => $vals)  $html2 .= "<input type=\"hidden\" name=\"" . htmlspecialchars($key) . "\" value=\"" . htmlspecialchars($vals[0]) . "\">";
								$html2 .= "<input type=\"hidden\" name=\"context_id\" value=\"" . htmlspecialchars($user["current_context_id"]) . "\">";
								$html2 .= "<input type=\"submit\" name=\"cancel\" value=\"Cancel\">";
								$html2 .= "<input type=\"submit\" value=\"Authorize application\">";
								$html2 .= "</form>";
								$html2 .= "</body>";

								$forms = $web->ExtractForms($result2["url"], $html2);
							}
						}
					}
					else
					{
						$text = $html->find('p');
						if ($text)
						{
							foreach ($text as $text2)  echo trim(preg_replace('/\s+/', " ", $text2->plaintext)) . "\n\n";
						}
					}

					$result = $web->InteractiveFormFill($forms);
					if ($result === false)  return array("success" => false, "error" => self::DO_Translate("Expected at least one form to exist.  Received none."), "errorcode" => "invalid_digitalocean_response", "info" => $result2);
				}
			} while (1);
		}

		public function UpdateRefreshToken($code)
		{
			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)  $this->SetAccessTokens(array());

			$this->accesstokens["bearertoken"] = false;

			$options = array(
				"postvars" => array(
					"grant_type" => "authorization_code",
					"code" => $code,
					"client_id" => $this->accesstokens["clientid"],
					"client_secret" => $this->accesstokens["clientsecret"],
					"redirect_uri" => $this->accesstokens["returnurl"]
				),
				"sslopts" => self::InitSSLOpts(array())
			);

			$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/token", false, $options);
			if (!$result["success"])  return $result;

			$data = $result["data"][0];
			$this->accesstokens["refreshtoken"] = $data["refresh_token"];
			$this->accesstokens["bearertoken"] = $data["access_token"];
			$this->accesstokens["bearerexpirets"] = time() + (int)$data["expires_in"] - 30;

			return array("success" => true);
		}

		public function UpdateBearerToken()
		{
			if ($this->accesstokens["refreshtoken"] === false)  return array("success" => true);

			if ($this->accesstokens["bearerexpirets"] <= time())
			{
				$this->accesstokens["bearertoken"] = false;

				$options = array(
					"postvars" => array(
						"grant_type" => "refresh_token",
						"refresh_token" => $this->accesstokens["refreshtoken"]
					),
					"sslopts" => self::InitSSLOpts(array())
				);

				$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/token", false, $options);
				if (!$result["success"])  return $result;

				$data = $result["data"][0];
				$this->accesstokens["refreshtoken"] = $data["refresh_token"];
				$this->accesstokens["bearertoken"] = $data["access_token"];
				$this->accesstokens["bearerexpirets"] = time() + (int)$data["expires_in"] - 30;

				foreach ($this->callbacks as $callback)
				{
					if (is_callable($callback))  call_user_func_array($callback, array($this));
				}
			}

			return array("success" => true);
		}

		public function OAuthRevokeSelf()
		{
			if ($this->accesstokens["bearertoken"] === false)  return array("success" => true);

			$options = array(
				"postvars" => array(
					"token" => $this->accesstokens["bearertoken"]
				),
				"sslopts" => self::InitSSLOpts(array())
			);

			$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/revoke", false, $options);
			if (!$result["success"])  return $result;

			$this->SetAccessTokens(array());

			return $result;
		}

		public function SetDebug($debug)
		{
			$this->debug = (bool)$debug;
		}

		// Account.
		public function AccountGetInfo($apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "account" . $apiextra, "account", $options);
		}

		// Actions.
		public function ActionsList($numpages = 1, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "actions" . $apiextra, "actions", $numpages, $options);
		}

		public function ActionsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "actions/" . self::MakeValidID($id) . $apiextra, "action", $options);
		}

		public function WaitForActionCompletion($id, $defaultwait, $initwait = array(), $callback = false, $callbackopts = false, $apiextra = "", $options = array())
		{
			$result = $this->ActionsGetInfo($id, $apiextra, $options);
			if (!$result["success"])  return $result;

			if ($result["action"]["status"] !== "completed")
			{
				if (is_callable($callback))  call_user_func_array($callback, array(true, $result, &$callbackopts));

				do
				{
					if (count($initwait))  $wait = array_shift($initwait);
					else  $wait = $defaultwait;

					sleep($wait);

					$result = $this->ActionsGetInfo($id, $apiextra, $options);
					if (!$result["success"])  return $result;

					if (is_callable($callback))  call_user_func_array($callback, array(false, $result, &$callbackopts));

					if ($result["action"]["status"] === "completed")  break;

				} while (1);
			}

			return array("success" => true);
		}

		// Volumes.
		public function VolumesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "volumes" . $apiextra, "volumes", $numpages, $options);
		}

		public function VolumesCreate($name, $desc, $size, $region, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "volumes" . $apiextra, "volume", self::MakeJSONOptions(array("name" => $name, "description" => $desc, "size_gigabytes" => $size, "region" => $region), $options), 201);
		}

		public function VolumesGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "volumes/" . $id . $apiextra, "volume", $options);
		}

		public function VolumesGetInfoByName($region, $name, $apiextra = "", $options = array())
		{
			return $this->VolumesGetInfo("", "?region=" . urlencode($region) . "&name=" . urlencode($name) . $apiextra, $options);
		}

		public function VolumesSnapshotsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "volumes/" . $id . "/snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function VolumeSnapshotCreate($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "volumes/" . $id . "/snapshots" . $apiextra, "snapshot", self::MakeJSONOptions(array("name" => $name), $options), 201);
		}

		public function VolumesDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "volumes/" . $id . $apiextra, $options);
		}

		public function VolumesDeleteByName($region, $name, $apiextra = "", $options = array())
		{
			return $this->VolumesDelete("", "?region=" . urlencode($region) . "&name=" . urlencode($name) . $apiextra, $options);
		}

		// Volume actions.
		public function VolumeActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "volumes/" . $id . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Domains.
		public function DomainsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "domains" . $apiextra, "domains", $numpages, $options);
		}

		public function DomainsCreate($name, $ipaddr, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "domains" . $apiextra, "domain", self::MakeJSONOptions(array("name" => $name, "ip_address" => $ipaddr), $options), 201);
		}

		public function DomainsGetInfo($name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "domains/" . $name . $apiextra, "domain", $options);
		}

		public function DomainsDelete($name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "domains/" . $name . $apiextra, $options);
		}

		// Domain records.
		public function DomainRecordsList($domainname, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "domains/" . $domainname . "/records" . $apiextra, "domain_records", $numpages, $options);
		}

		public function DomainRecordsCreate($domainname, $type, $name, $data, $priority, $port, $weight, $ttl = 1800, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "domains/" . $domainname . "/records" . $apiextra, "domain_record", self::MakeJSONOptions(array("type" => $type, "name" => $name, "data" => $data, "priority" => $priority, "port" => $port, "weight" => $weight, "ttl" => $ttl), $options), 201);
		}

		public function DomainRecordsUpdate($domainname, $id, $updatevalues, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, "domain_record", self::MakeJSONOptions($updatevalues, $options));
		}

		public function DomainRecordsGetInfo($domainname, $id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, "domain_record", $options);
		}

		public function DomainRecordsDelete($domainname, $id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, $options);
		}

		// Droplets.
		public function DropletsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets" . $apiextra, "droplets", $numpages, $options);
		}

		public function DropletsCreate($name, $region, $size, $image, $optionalvalues = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "droplets" . $apiextra, "droplet", self::MakeJSONOptions(array_merge(array("name" => $name, "region" => $region, "size" => $size, "image" => $image), $optionalvalues), $options), 202);
		}

		public function DropletsKernelsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/kernels" . $apiextra, "kernels", $numpages, $options);
		}

		public function DropletsSnapshotsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function DropletsBackupsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/backups" . $apiextra, "backups", $numpages, $options);
		}

		public function DropletsActionsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function DropletsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "droplets/" . $id . $apiextra, $options);
		}

		public function DropletsDeleteByTag($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "droplets?tagname=" . urlencode($tagname) . $apiextra, $options);
		}

		public function DropletsNeighborsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			if ($id === "all")  return $this->RunAPIGetList("GET", "reports/droplet_neighbors" . $apiextra, "neighbors", $numpages, $options);

			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/neighbors" . $apiextra, "droplets", $numpages, $options);
		}

		// Droplet actions.
		public function DropletActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "droplets/" . self::MakeValidID($id) . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		public function DropletActionsByTag($tagname, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			return $this->RunAPIGetOne("POST", "droplets/actions?tagname=" . urlencode($tagname) . $apiextra, "actions", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
		}

		// Images.
		public function ImagesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "images" . $apiextra, "images", $numpages, $options);
		}

		public function ImagesGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "images/" . $id . $apiextra, "image", $options);
		}

		public function ImagesActionsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "images/" . $id . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function ImagesRename($id, $newname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "images/" . $id . $apiextra, "image", self::MakeJSONOptions(array("name" => $newname), $options));
		}

		public function ImagesDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "images/" . $id . $apiextra, $options);
		}

		// Image actions.
		public function ImageActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "images/" . $id . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Snapshots.
		public function SnapshotsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function SnapshotsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "snapshots/" . $id . $apiextra, "snapshot", $options);
		}

		public function SnapshotsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "snapshots/" . $id . $apiextra, $options);
		}

		// SSH keys.
		public function SSHKeysList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "account/keys" . $apiextra, "ssh_keys", $numpages, $options);
		}

		public function SSHKeysCreate($name, $publickey, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "account/keys" . $apiextra, "ssh_key", self::MakeJSONOptions(array("name" => $name, "public_key" => $publickey), $options), 201);
		}

		public function SSHKeysGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "account/keys/" . $id . $apiextra, "ssh_key", $options);
		}

		public function SSHKeysRename($id, $newname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "account/keys/" . $id . $apiextra, "ssh_key", self::MakeJSONOptions(array("name" => $newname), $options));
		}

		public function SSHKeysDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "account/keys/" . $id . $apiextra, $options);
		}

		// Regions.
		public function RegionsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "regions" . $apiextra, "regions", $numpages, $options);
		}

		// Sizes.
		public function SizesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "sizes" . $apiextra, "sizes", $numpages, $options);
		}

		// Floating IPs.
		public function FloatingIPsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "floating_ips" . $apiextra, "floating_ips", $numpages, $options);
		}

		public function FloatingIPsCreate($target, $targetid, $apiextra = "", $options = array())
		{
			$target = preg_replace('/[^a-z]/', "_", strtolower(trim($target)));
			if ($target === "droplet")  $target = "droplet_id";

			return $this->RunAPIGetOne("POST", "floating_ips" . $apiextra, "floating_ip", self::MakeJSONOptions(array($target => $targetid), $options), 202);
		}

		public function FloatingIPsGetInfo($ipaddr, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "floating_ips/" . $ipaddr . $apiextra, "floating_ip", $options);
		}

		public function FloatingIPsActionsList($ipaddr, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "floating_ips/" . $ipaddr . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function FloatingIPsDelete($ipaddr, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "floating_ips/" . $ipaddr . $apiextra, $options);
		}

		// Floating IP actions.
		public function FloatingIPActionsByIP($ipaddr, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "floating_ips/" . $ipaddr . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Tags.
		public function TagsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "tags" . $apiextra, "tags", $numpages, $options);
		}

		public function TagsCreate($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "tags" . $apiextra, "tag", self::MakeJSONOptions(array("name" => $tagname), $options), 201);
		}

		public function TagsGetInfo($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "tags/" . $tagname . $apiextra, "tag", $options);
		}

		public function TagsAttach($tagname, $resources, $apiextra = "", $options = array())
		{
			foreach ($resources as $num => $item)
			{
				foreach ($item as $key => $val)  $item[$key] = (string)$val;

				$resources[$num] = $item;
			}

			return $this->RunAPIGetNone("POST", "tags/" . $tagname . "/resources" . $apiextra, self::MakeJSONOptions(array("resources" => $resources), $options));
		}

		public function TagsDetach($tagname, $resources, $apiextra = "", $options = array())
		{
			foreach ($resources as $num => $item)
			{
				foreach ($item as $key => $val)  $item[$key] = (string)$val;

				$resources[$num] = $item;
			}

			return $this->RunAPIGetNone("DELETE", "tags/" . $tagname . "/resources" . $apiextra, self::MakeJSONOptions(array("resources" => $resources), $options));
		}

		public function TagsDelete($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "tags/" . $tagname . $apiextra, $options);
		}

		// Metadata.
		public function MetadataDropletGetInfo($infopath = ".json", $apiextra = "", $options = array())
		{
			return $this->RunMetadataAPI("GET", $infopath, $apiextra, $options);
		}

		// For simple API calls that return a single result.
		public function RunAPIGetOne($method, $apipath, $resultkey, $options = array(), $expected = 200)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, false, $options, $expected);
			if (!$result["success"])  return $result;
			if (!isset($result["data"][0][$resultkey]))  return array("success" => false, "error" => self::DO_Translate("The result key '" . $resultkey . "' does not exist in the data returned by Digital Ocean."), "errorcode" => "missing_result_key", "info" => $result);

			$result[$resultkey] = $result["data"][0][$resultkey];
			if ($resultkey !== "data")  unset($result["data"]);

			return $result;
		}

		// For simple API calls that return a standard list.
		public function RunAPIGetList($method, $apipath, $expectedkey, $numpages = 1, $options = array(), $expected = 200)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, $expectedkey, $options, $expected, $numpages);
			if (!$result["success"])  return $result;

			return $result;
		}

		// For simple API calls that return nothing (mostly deletes).
		public function RunAPIGetNone($method, $apipath, $options = array(), $expected = 204)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, false, $options, $expected);
			if (!$result["success"])  return $result;

			return $result;
		}

		public function GetAPIEndpoint()
		{
			return "https://api.digitalocean.com/v2/";
		}

		public function RunMetadataAPI($method, $apipath, $apiextra = "", $options = array(), $expected = 200)
		{
			return $this->RunAPI("GET", $this->GetMetadataEndpoint() . $apipath . $apiextra, false, $options, $expected);
		}

		public function GetMetadataEndpoint()
		{
			return "http://169.254.169.254/metadata/v1";
		}

		public static function MakeValidID($id)
		{
			return preg_replace('/[^0-9]/', "", $id);
		}

		public static function MakeJSONOptions($jsonoptions, $options)
		{
			unset($options["postvars"]);

			if (!isset($options["headers"]))  $options["headers"] = array();
			$options["headers"]["Content-Type"] = "application/json";

			if (isset($options["body"]))  $options["body"] = array_merge($jsonoptions, $options["body"]);
			else  $options["body"] = json_encode($jsonoptions);

			return $options;
		}

		private static function DO_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		private static function InitSSLOpts($options)
		{
			$result = array_merge(array(
				"ciphers" => "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS",
				"disable_compression" => true,
				"allow_self_signed" => false,
				"verify_peer" => true,
				"verify_depth" => 3,
				"capture_peer_cert" => true,
				"cafile" => str_replace("\\", "/", dirname(__FILE__)) . "/digitalocean_ca.pem",
				"auto_cn_match" => true,
				"auto_sni" => true
			), $options);

			return $result;
		}

		private function RunAPI($method, $url, $expectedkey, $options = array(), $expected = 200, $numpages = 1, $decodebody = true)
		{
			$options2 = array(
				"method" => $method,
				"sslopts" => self::InitSSLOpts(array())
			);
			if ($this->debug)  $options2["debug"] = true;

			$options2 = array_merge($options2, $options);

			if ($this->accesstokens["bearertoken"] !== false)
			{
				if (!isset($options2["headers"]))  $options2["headers"] = array();
				$options2["headers"]["Authorization"] = "Bearer " . $this->accesstokens["bearertoken"];
			}

			$result = array(
				"success" => true,
				"requests_left" => 0,
				"data" => array(),
				"actions" => array()
			);

			do
			{
				$found = false;

				$retries = 3;
				do
				{
					$result2 = $this->web->Process($url, "auto", $options2);
					if (!$result2["success"])  sleep(1);
					$retries--;
				} while ($retries > 0 && !$result2["success"]);

				if (!$result2["success"])  return $result2;

				if ($this->debug)
				{
					echo "------- RAW SEND START -------\n";
					echo $result2["rawsend"];
					echo "------- RAW SEND END -------\n\n";

					echo "------- RAW RECEIVE START -------\n";
					echo $result2["rawrecv"];
					echo "------- RAW RECEIVE END -------\n\n";
				}

				if ($result2["response"]["code"] != $expected)  return array("success" => false, "error" => self::DO_Translate("Expected a %d response from DigitalOcean.  Received '%s'.", $expected, $result2["response"]["line"]), "errorcode" => "unexpected_digitalocean_response", "info" => $result2);

				if (isset($result2["headers"]["Ratelimit-Remaining"]))  $result["requests_left"] = (int)$result2["headers"]["Ratelimit-Remaining"][0];

				if ($decodebody && trim($result2["body"]) !== "")
				{
					$data = json_decode($result2["body"], true);

					if ($data !== false)
					{
						if ($expectedkey !== false)
						{
							if (!isset($data[$expectedkey]))  return array("success" => false, "error" => self::DO_Translate("The key '" . $expectedkey . "' does not exist in the data returned by Digital Ocean."), "errorcode" => "missing_expected_key", "info" => $data);

							foreach ($data[$expectedkey] as $item)  $result["data"][] = $item;
						}
						else
						{
							$result["data"][] = $data;
						}
					}

					if (isset($data["links"]) && isset($data["links"]["pages"]) && isset($data["links"]["pages"]["next"]))
					{
						$url = $data["links"]["pages"]["next"];
						if ($numpages > 0)
						{
							$found = true;

							if ($numpages !== true)  $numpages--;
						}
					}

					if (isset($data["links"]) && isset($data["links"]["actions"]))
					{
						foreach ($data["links"]["actions"] as $item)  $result["actions"][] = $item;
					}
				}

			} while ($found && $numpages);

			return $result;
		}
	}
?>
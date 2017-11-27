<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	class TagFilterNodes
	{
		public $nodes, $nextid;
		private $queries;

		public function __construct()
		{
			$this->nodes = array(
				array(
					"type" => "root",
					"parent" => false,
					"parentpos" => false,
					"children" => array()
				)
			);

			$this->nextid = 1;
			$this->queries = array();
		}

		// Makes a selector suitable for Find() and Filter() by altering or removing rules.  Query is not cached.
		public static function MakeValidSelector($query)
		{
			if (!is_array($query))  $result = \CubicleSoft\TagFilter::ParseSelector($query, true);
			else if (isset($query["success"]) && isset($query["tokens"]))
			{
				$result = $query;
				$result["tokens"] = \CubicleSoft\TagFilter::ReorderSelectorTokens(array_reverse($result["tokens"]), true);
			}
			else
			{
				$result = array("success" => true, "tokens" => \CubicleSoft\TagFilter::ReorderSelectorTokens(array_reverse($query), true));
			}

			// Alter certain CSS3 tokens to equivalent tokens.
			foreach ($result["tokens"] as $num => $rules)
			{
				foreach ($rules as $num2 => $rule)
				{
					if ($rule["type"] === "pseudo-class")
					{
						if ($rule["pseudo"] === "link")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "element", "namespace" => false, "tag" => "a");
						else if ($rule["pseudo"] === "disabled")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "disabled", "cmp" => false);
						else if ($rule["pseudo"] === "enabled")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "enabled", "cmp" => false);
						else if ($rule["pseudo"] === "checked")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "checked", "cmp" => false);
					}
				}
			}

			// Reorder the tokens so that the order is simple for output.
			$tokens = \CubicleSoft\TagFilter::ReorderSelectorTokens(array_reverse($result["tokens"]), true, array("element" => array(), "id" => array(), "class" => array(), "attr" => array(), "pseudo-class" => array(), "pseudo-element" => array()), false);

			// Generate a duplicate-free Find()-safe string.
			$result = array();
			foreach ($tokens as $rules)
			{
				$groups = array();
				$strs = array();
				$rules = array_reverse($rules);
				$y = count($rules);
				for ($x = 0; $x < $y; $x++)
				{
					$str = "";

					if (isset($rules[$x]["not"]) && $rules[$x]["not"])  $str .= ":not(";

					switch ($rules[$x]["type"])
					{
						case "id":  $str .= "#" . $rules[$x]["id"];  $valid = true;  break;
						case "element":  $str .= ($rules[$x]["namespace"] !== false ? $rules[$x]["namespace"] . "|" : "") . strtolower($rules[$x]["tag"]);  $valid = true;  break;
						case "class":  $str .= "." . $rules[$x]["class"];  $valid = true;  break;
						case "attr":  $str .= "[" . ($rules[$x]["namespace"] !== false ? $rules[$x]["namespace"] . "|" : "") . strtolower($rules[$x]["attr"]) . ($rules[$x]["cmp"] !== false ? $rules[$x]["cmp"] . "\"" . str_replace("\"", "\\\"", $rules[$x]["val"]) . "\"" : "") . "]";  $valid = true;  break;
						case "pseudo-class":
						{
							$pc = $rules[$x]["pseudo"];
							$valid = ($pc === "first-child" || $pc === "last-child" || $pc === "only-child" || $pc === "nth-child" || $pc === "nth-last-child" || $pc === "first-of-type" || $pc === "last-of-type" || $pc === "only-of-type" || $pc === "nth-of-type" || $pc === "nth-last-of-type" || $pc === "empty");

							if ($valid && substr($rules[$x]["pseudo"], 0, 4) === "nth-" && (!isset($rules[$x]["a"]) || !isset($rules[$x]["b"])))  $valid = false;

							break;
						}
						case "combine":
						{
							switch ($rules[$x]["combine"])
							{
								case "prev-parent":  $groups[] = implode("", $strs);  $groups[] = ">";  $strs = array();  $valid = true;  break;
								case "any-parent":  $groups[] = implode("", $strs);  $strs = array();  $valid = true;  break;
								case "prev-sibling":  $groups[] = implode("", $strs);  $groups[] = "+";  $strs = array();  $valid = true;  break;
								case "any-prev-sibling":  $groups[] = implode("", $strs);  $groups[] = "~";  $strs = array();  $valid = true;  break;
								default:  $valid = false;
							}

							break;
						}
						default:  $valid = false;  break;
					}

					if (!$valid)  break;

					if (isset($rules[$x]["not"]) && $rules[$x]["not"])  $str .= ")";

					$strs[$str] = $str;
				}

				if ($x == $y)
				{
					if (count($strs))  $groups[] = implode("", $strs);
					$str = implode(" ", $groups);
					$result[$str] = $str;
				}
			}

			return implode(", ", $result);
		}

		public function Find($query, $id = 0, $cachequery = true, $firstmatch = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return array("success" => false, "error" => "Invalid initial ID.", "errorcode" => "invalid_init_id");

			if (isset($this->queries[$query]))  $result = $this->queries[$query];
			else
			{
				if (!is_array($query))  $result = \CubicleSoft\TagFilter::ParseSelector($query, true);
				else if (isset($query["success"]) && isset($result["selector"]) && isset($query["tokens"]))
				{
					$result = $query;
					$result["tokens"] = \CubicleSoft\TagFilter::ReorderSelectorTokens($result["tokens"], true);

					$query = $result["selector"];
				}
				else
				{
					$result = array("success" => true, "tokens" => \CubicleSoft\TagFilter::ReorderSelectorTokens($query, true));

					$cachequery = false;
				}

				if ($cachequery)
				{
					foreach ($this->queries as $key => $val)
					{
						if (count($this->queries) < 25)  break;

						unset($this->queries[$key]);
					}

					$this->queries[$query] = $result;
				}
			}

			if (!$result["success"])  return $result;

			$rules = $result["tokens"];
			$numrules = count($rules);

			$result = array();
			$oftypecache = array();
			$rootid = $id;
			$pos = 0;
			$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
			do
			{
				if (!$pos && $this->nodes[$id]["type"] === "element")
				{
					// Attempt to match a rule.
					for ($x = 0; $x < $numrules; $x++)
					{
						$id2 = $id;
						$y = count($rules[$x]);
						for ($x2 = 0; $x2 < $y; $x2++)
						{
							if (isset($rules[$x][$x2]["namespace"]) && $rules[$x][$x2]["namespace"] !== false && $rules[$x][$x2]["namespace"] !== "*" && (($rules[$x][$x2]["namespace"] === "" && strpos($this->nodes[$id2]["tag"], ":") !== false) || ($rules[$x][$x2]["namespace"] !== "" && strcasecmp(substr($this->nodes[$id2]["tag"], 0, strlen($rules[$x][$x2]["namespace"]) + 1), $rules[$x][$x2]["namespace"] . ":") !== 0)))  $backtrack = true;
							else
							{
								switch ($rules[$x][$x2]["type"])
								{
									case "id":  $backtrack = (!isset($this->nodes[$id2]["attrs"]["id"]) || $this->nodes[$id2]["attrs"]["id"] !== $rules[$x][$x2]["id"]);  break;
									case "element":  $backtrack = (strcasecmp($this->nodes[$id2]["tag"], (isset($rules[$x][$x2]["namespace"]) && $rules[$x][$x2]["namespace"] !== false ? $rules[$x][$x2]["namespace"] . ":" : "") . $rules[$x][$x2]["tag"]) !== 0);  break;
									case "class":  $backtrack = (!isset($this->nodes[$id2]["attrs"]["class"]) || !isset($this->nodes[$id2]["attrs"]["class"][$rules[$x][$x2]["class"]]));  break;
									case "attr":
									{
										$attr = strtolower($rules[$x][$x2]["attr"]);
										if (!isset($this->nodes[$id2]["attrs"][$attr]))  $backtrack = true;
										else
										{
											$val = $this->nodes[$id2]["attrs"][$attr];
											if (is_array($val))  $val = implode(" ", $val);

											switch ($rules[$x][$x2]["cmp"])
											{
												case "=":  $backtrack = ($val !== $rules[$x][$x2]["val"]);  break;
												case "^=":  $backtrack = ($rules[$x][$x2]["val"] === "" || substr($val, 0, strlen($rules[$x][$x2]["val"])) !== $rules[$x][$x2]["val"]);  break;
												case "$=":  $backtrack = ($rules[$x][$x2]["val"] === "" || substr($val, -strlen($rules[$x][$x2]["val"])) !== $rules[$x][$x2]["val"]);  break;
												case "*=":  $backtrack = ($rules[$x][$x2]["val"] === "" || strpos($val, $rules[$x][$x2]["val"]) === false);  break;
												case "~=":  $backtrack = ($rules[$x][$x2]["val"] === "" || strpos($rules[$x][$x2]["val"], " ") !== false || strpos(" " . $val . " ", " " . $rules[$x][$x2]["val"] . " ") === false);  break;
												case "|=":  $backtrack = ($rules[$x][$x2]["val"] === "" || ($val !== $rules[$x][$x2]["val"] && substr($val, 0, strlen($rules[$x][$x2]["val"]) + 1) !== $rules[$x][$x2]["val"] . "-"));  break;
												default:  $backtrack = false;  break;
											}
										}

										break;
									}
									case "pseudo-class":
									{
										// Handle various bits of common code.
										$pid = $this->nodes[$id2]["parent"];
										$pnum = count($this->nodes[$pid]["children"]);

										$nth = (substr($rules[$x][$x2]["pseudo"], 0, 4) === "nth-");
										if ($nth && (!isset($rules[$x][$x2]["a"]) || !isset($rules[$x][$x2]["b"])))  return array("success" => false, "error" => "Pseudo-class ':" . $rules[$x][$x2]["pseudo"] . "(n)' requires an expression for 'n'.", "errorcode" => "missing_pseudo_class_expression");

										if (substr($rules[$x][$x2]["pseudo"], -8) === "-of-type")
										{
											if (!isset($oftypecache[$id2]))
											{
												$types = array();
												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")
													{
														$tag = $this->nodes[$id3]["tag"];
														if (!isset($types[$tag]))  $types[$tag] = 0;

														$oftypecache[$id3] = array("tx" => $types[$tag]);

														$types[$tag]++;
													}
												}

												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")
													{
														$tag = $this->nodes[$id3]["tag"];
														$oftypecache[$id3]["ty"] = $types[$tag];
													}
												}
											}

											$tx = $oftypecache[$id2]["tx"];
											$ty = $oftypecache[$id2]["ty"];
										}

										switch ($rules[$x][$x2]["pseudo"])
										{
											case "first-child":  $backtrack = ($this->nodes[$id2]["parentpos"] !== 0);  break;
											case "last-child":  $backtrack = ($this->nodes[$id2]["parentpos"] !== $pnum - 1);  break;
											case "only-child":  $backtrack = ($pnum !== 1);  break;
											case "nth-child":  $px = $this->nodes[$id2]["parentpos"];  break;
											case "nth-last-child":  $px = $pnum - $this->nodes[$id2]["parentpos"] - 1;  break;
											case "first-of-type":  $backtrack = ($tx !== 0);  break;
											case "last-of-type":  $backtrack = ($tx !== $ty - 1);  break;
											case "only-of-type":  $backtrack = ($ty !== 1);  break;
											case "nth-of-type":  $px = $tx;  break;
											case "nth-last-of-type":  $px = $ty - $tx - 1;  break;
											case "empty":
											{
												$backtrack = false;
												foreach ($this->nodes[$id2]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element" || ($this->nodes[$id3]["type"] === "content" && trim($this->nodes[$id3]["text"]) !== ""))
													{
														$backtrack = true;

														break;
													}
												}

												break;
											}
											default:  return array("success" => false, "error" => "Unknown/Unsupported pseudo-class ':" . $rules[$x][$x2]["pseudo"] . "'.", "errorcode" => "unknown_unsupported_pseudo_class");
										}

										if ($nth)
										{
											// Calculated expression:  a * n + b - 1 = x
											// Solved for n:  n = (x + 1 - b) / a
											// Where 'n' is a non-negative integer.  When 'a' is 0, solve for 'b' instead.
											$pa = $rules[$x][$x2]["a"];
											$pb = $rules[$x][$x2]["b"];

											if ($pa == 0)  $backtrack = ($pb != $px + 1);
											else
											{
												$pn = (($px + 1 - $pb) / $pa);

												$backtrack = ($pn < 0 || $pn - (int)$pn > 0.000001);
											}
										}

										break;
									}
									case "pseudo-element":  return array("success" => false, "error" => "Pseudo-elements are not supported.  Found '::" . $rules[$x][$x2]["pseudo"] . "'.", "errorcode" => "unsupported_selector_type");
									case "combine":
									{
										switch ($rules[$x][$x2]["combine"])
										{
											case "prev-parent":
											case "any-parent":
											{
												$backtrack = ($id2 === $rootid || !$this->nodes[$id2]["parent"]);
												if (!$backtrack)  $id2 = $this->nodes[$id2]["parent"];

												break;
											}
											case "prev-sibling":
											case "any-prev-sibling":
											{
												$backtrack = ($this->nodes[$id2]["parentpos"] == 0);
												if (!$backtrack)  $id2 = $this->nodes[$this->nodes[$id2]["parent"]]["children"][$this->nodes[$id2]["parentpos"] - 1];

												break;
											}
											default:  return array("success" => false, "error" => "Unknown combiner " . $rules[$x][$x2]["pseudo"] . ".", "errorcode" => "unknown_combiner");
										}

										// For unknown parent/sibling combiners such as '~', use the rule stack to allow for backtracking to try another path if a match fails (e.g. h1 p ~ p).
										$rules[$x][$x2]["lastid"] = $id2;

										break;
									}
									default:  return array("success" => false, "error" => "Unknown selector type '" . $rules[$x][$x2]["type"] . "'.", "errorcode" => "unknown_selector_type");
								}
							}

							if (isset($rules[$x][$x2]["not"]) && $rules[$x][$x2]["not"])  $backtrack = !$backtrack;

							// Backtrack through the rule to an unknown parent/sibling combiner.
							if ($backtrack)
							{
								if ($x2)
								{
									for ($x2--; $x2; $x2--)
									{
										if ($rules[$x][$x2]["type"] === "combine")
										{
											if ($rules[$x][$x2]["combine"] === "any-parent")
											{
												$id2 = $rules[$x][$x2]["lastid"];
												if ($id2 !== $rootid && $this->nodes[$id2]["parent"])
												{
													$id2 = $this->nodes[$id2]["parent"];

													break;
												}
											}
											else if ($rules[$x][$x2]["combine"] === "any-prev-sibling")
											{
												$id2 = $rules[$x][$x2]["lastid"];
												if ($this->nodes[$id2]["parentpos"] != 0)
												{
													$id2 = $this->nodes[$this->nodes[$id2]["parent"]]["children"][$this->nodes[$id2]["parentpos"] - 1];

													break;
												}
											}
										}
									}
								}

								if (!$x2)  break;
							}
						}

						// Match found.
						if ($x2 === $y)
						{
							$result[] = $id;

							if ($firstmatch)  return array("success" => true, "ids" => $result);

							break;
						}
					}
				}

				if ($pos >= $maxpos)
				{
					if ($rootid === $id)  break;

					$pos = $this->nodes[$id]["parentpos"] + 1;
					$id = $this->nodes[$id]["parent"];
					$maxpos = count($this->nodes[$id]["children"]);
				}
				else
				{
					$id = $this->nodes[$id]["children"][$pos];
					$pos = 0;
					$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
				}
			} while (1);

			return array("success" => true, "ids" => $result);
		}

		// Filter results from Find() based on a matching query.
		public function Filter($ids, $query, $cachequery = true)
		{
			if (!is_array($ids))  $ids = array($ids);

			// Handle lazy chaining from both Find() and Filter().
			if (isset($ids["success"]))
			{
				if (!$ids["success"])  return $ids;
				if (!isset($ids["ids"]))  return array("success" => false, "error" => "Bad filter input.", "invalid_filter_ids");

				$ids = $ids["ids"];
			}

			$ids2 = array();
			if (is_string($query) && strtolower(substr($query, 0, 10)) === "/contains:")
			{
				$query = substr($query, 10);
				foreach ($ids as $id)
				{
					if (strpos($this->GetPlainText($id), $query) !== false)  $ids2[] = $id;
				}
			}
			else if (is_string($query) && strtolower(substr($query, 0, 11)) === "/~contains:")
			{
				$query = substr($query, 11);
				foreach ($ids as $id)
				{
					if (stripos($this->GetPlainText($id), $query) !== false)  $ids2[] = $id;
				}
			}
			else
			{
				foreach ($ids as $id)
				{
					$result = $this->Find($query, $id, $cachequery, true);
					if ($result["success"] && count($result["ids"]))  $ids2[] = $id;
				}
			}

			return array("success" => true, "ids" => $ids2);
		}

		// Convert all or some of the nodes back into a string.
		public function Implode($id, $options = array())
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return "";

			if (!isset($options["include_id"]))  $options["include_id"] = true;
			if (!isset($options["types"]))  $options["types"] = "element,content,comment";
			if (!isset($options["output_mode"]))  $options["output_mode"] = "html";
			if (!isset($options["post_elements"]))  $options["post_elements"] = array();
			if (!isset($options["no_content_elements"]))  $options["no_content_elements"] = array("script" => true, "style" => true);

			$types2 = explode(",", $options["types"]);
			$types = array();
			foreach ($types2 as $type)
			{
				$type = trim($type);
				if ($type !== "")  $types[$type] = true;
			}

			$result = "";
			$include = (bool)$options["include_id"];
			$rootid = $id;
			$pos = 0;
			$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
			do
			{
				if (!$pos && isset($types[$this->nodes[$id]["type"]]))
				{
					switch ($this->nodes[$id]["type"])
					{
						case "element":
						{
							if ($include || $rootid != $id)
							{
								$result .= "<" . $this->nodes[$id]["tag"];
								foreach ($this->nodes[$id]["attrs"] as $key => $val)
								{
									$result .= " " . $key;

									if (is_array($val))  $val = implode(" ", $val);
									if (is_string($val))  $result .= "=\"" . htmlspecialchars($val) . "\"";
								}
								$result .= (!$maxpos && $options["output_mode"] === "xml" ? " />" : ">");
							}

							break;
						}
						case "content":
						case "comment":
						{
							if (isset($types["element"]) || !isset($options["no_content_elements"][$this->nodes[$this->nodes[$id]["parent"]]["tag"]]))  $result .= $this->nodes[$id]["text"];

							break;
						}
						default:  break;
					}
				}

				if ($pos >= $maxpos)
				{
					if ($maxpos && $this->nodes[$id]["type"] === "element")
					{
						if (($include || $rootid != $id) && isset($types[$this->nodes[$id]["type"]]))  $result .= "</" . $this->nodes[$id]["tag"] . ">";

						if (isset($options["post_elements"][$this->nodes[$id]["type"]]))  $result .= $options["post_elements"][$this->nodes[$id]["type"]];
					}

					if ($rootid === $id)  break;

					$pos = $this->nodes[$id]["parentpos"] + 1;
					$id = $this->nodes[$id]["parent"];
					$maxpos = count($this->nodes[$id]["children"]);
				}
				else
				{
					$id = $this->nodes[$id]["children"][$pos];
					$pos = 0;
					$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
				}
			} while (1);

			return $result;
		}

		// Object-oriented access methods.  Only Get() supports multiple IDs.
		public function Get($id = 0)
		{
			if (is_array($id))
			{
				if (isset($id["success"]) && $id["ids"])  $id = $id["ids"];

				$result = array();
				foreach ($id as $id2)  $result[] = $this->Get($id2);

				return $result;
			}

			return ($id !== false && isset($this->nodes[$id]) ? new \CubicleSoft\TagFilterNode($this, $id) : false);
		}

		public function GetParent($id)
		{
			return ($id !== false && isset($this->nodes[$id]) && isset($this->nodes[$this->nodes[$id]["parent"]]) ? new \CubicleSoft\TagFilterNode($this, $this->nodes[$id]["parent"]) : false);
		}

		public function GetChildren($id, $objects = false)
		{
			if (!isset($this->nodes[$id]) || !isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

			return ($objects ? $this->Get($this->nodes[$id]["children"]) : $this->nodes[$id]["children"]);
		}

		public function GetChild($id, $pos)
		{
			if (!isset($this->nodes[$id]) || !isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

			$pos = (int)$pos;
			$y = count($this->nodes[$id]["children"]);
			if ($pos < 0)  $pos = $y + $pos;
			if ($pos < 0 || $pos > $y - 1)  return false;

			return $this->Get($this->nodes[$id]["children"][$pos]);
		}

		public function GetPrevSibling($id)
		{
			if (!isset($this->nodes[$id]) || $this->nodes[$id]["parentpos"] == 0)  return false;

			return $this->Get($this->nodes[$this->nodes[$id]["parent"]]["children"][$this->nodes[$id]["parentpos"] - 1]);
		}

		public function GetNextSibling($id)
		{
			if ($id === false || !isset($this->nodes[$id]) || $this->nodes[$id]["parentpos"] >= count($this->nodes[$this->nodes[$id]["parent"]]["children"]) - 1)  return false;

			return $this->Get($this->nodes[$this->nodes[$id]["parent"]]["children"][$this->nodes[$id]["parentpos"] + 1]);
		}

		public function GetTag($id)
		{
			return (isset($this->nodes[$id]) && $this->nodes[$id]["type"] === "element" ? $this->nodes[$id]["tag"] : false);
		}

		public function Move($src, $newpid, $newpos)
		{
			$newpid = (int)$newpid;
			if (!isset($this->nodes[$newpid]) || !isset($this->nodes[$newpid]["children"]) || !is_array($this->nodes[$newpid]["children"]))  return false;

			$newpos = (is_bool($newpos) ? count($this->nodes[$newpid]["children"]) : (int)$newpos);
			if ($newpos < 0)  $newpos = count($this->nodes[$newpid]["children"]) + $newpos;
			if ($newpos < 0)  $newpos = 0;
			if ($newpos > count($this->nodes[$newpid]["children"]))  $newpos = count($this->nodes[$newpid]["children"]);

			if ($src instanceof TagFilterNodes)
			{
				if ($src === $this)  return false;

				// Bulk node import.  Doesn't remove source nodes.
				foreach ($src->nodes as $id => $node)
				{
					if ($node["type"] === "element" || $node["type"] === "content" || $node["type"] === "comment")
					{
						$node["parent"] += $this->nextid - 1;

						if (isset($node["children"]) && is_array($node["children"]))
						{
							foreach ($node["children"] as $pos => $id2)  $node["children"][$pos] += $this->nextid - 1;
						}

						$this->nodes[$id + $this->nextid - 1] = $node;
					}
				}

				// Merge root children.
				foreach ($src->nodes[0]["children"] as $pos => $id)
				{
					$this->nodes[$id + $this->nextid - 1]["parent"] = $newpid;
					array_splice($this->nodes[$newpid]["children"], $newpos + $pos, 0, array($id + $this->nextid - 1));
				}

				$this->RealignChildren($newpid, $newpos);

				$this->nextid += $src->nextid - 1;
			}
			else if (is_array($src))
			{
				// Attach the array to the position if it is valid.
				if (!isset($src["type"]))  return false;

				switch ($src["type"])
				{
					case "element":
					{
						if (!isset($src["tag"]) || !isset($src["attrs"]) || !is_array($src["attrs"]) || !isset($src["children"]))  return false;

						$src["tag"] = (string)$src["tag"];
						$src["parent"] = $newpid;

						break;
					}
					case "content":
					case "comment":
					{
						if (!isset($src["text"]) || isset($src["children"]))  return false;

						$src["text"] = (string)$src["text"];

						break;
					}
					default:  return false;
				}

				array_splice($this->nodes[$newpid]["children"], $newpos, 0, array($this->nextid));
				$this->RealignChildren($newpid, $newpos);
				$this->nextid++;
			}
			else if (is_string($src))
			{
				return $this->Move(\CubicleSoft\TagFilter::Explode($src, \CubicleSoft\TagFilter::GetHTMLOptions()), $newpid, $newpos);
			}
			else
			{
				// Reparents an internal id.
				$id = (int)$src;

				if (!$id || !isset($this->nodes[$id]))  return false;

				// Don't allow reparenting to a child node.
				$id2 = $newpid;
				while ($id2)
				{
					if ($id === $id2)  return false;

					$id2 = $this->nodes[$id2]["parent"];
				}

				// Detach.
				array_splice($this->nodes[$this->nodes[$id]["parent"]]["children"], $this->nodes[$id]["parentpos"], 1);
				$this->RealignChildren($this->nodes[$id]["parent"], $this->nodes[$id]["parentpos"]);

				// Attach.
				array_splice($this->nodes[$newpid]["children"], $newpos, 0, array($id));
				$this->RealignChildren($newpid, $newpos);
			}

			return true;
		}

		// When $keepchildren is true, the node's children are moved into the parent of the node being removed.
		public function Remove($id, $keepchildren = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return;

			if (!$id)
			{
				if (!$keepchildren)
				{
					// Reset all nodes.
					$this->nodes = array(
						array(
							"type" => "root",
							"parent" => false,
							"parentpos" => false,
							"children" => array()
						)
					);

					$this->nextid = 1;
				}
			}
			else
			{
				// Detach the node from the parent.
				$pid = $this->nodes[$id]["parent"];
				$pos = $this->nodes[$id]["parentpos"];

				if ($keepchildren)
				{
					// Reparent the children and attach them to the new parent.
					if (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]))
					{
						foreach ($this->nodes[$id]["children"] as $cid)  $this->nodes[$cid]["parent"] = $pid;
						array_splice($this->nodes[$pid]["children"], $pos, 1, $this->nodes[$id]["children"]);
					}
					else
					{
						array_splice($this->nodes[$pid]["children"], $pos, 1);
					}

					$this->RealignChildren($pid, $pos);

					unset($this->nodes[$id]);
				}
				else
				{
					array_splice($this->nodes[$pid]["children"], $pos, 1);

					$this->RealignChildren($pid, $pos);

					// Remove node and all children.
					$rootid = $id;
					$pos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
					do
					{
						if (!$pos)
						{
							$pid = $this->nodes[$id]["parent"];
							$pos = $this->nodes[$id]["parentpos"];

							unset($this->nodes[$id]);
							if ($rootid === $id)  break;

							$id = $pid;
						}
						else
						{
							$id = $this->nodes[$id]["children"][$pos - 1];
							$pos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
						}
					} while (1);
				}
			}
		}

		public function Replace($id, $src, $inneronly = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return false;

			if ($inneronly)
			{
				// Remove children.
				if (!isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

				while (count($this->nodes[$id]["children"]))  $this->Remove($this->nodes[$id]["children"][0]);

				$newpid = $id;
				$newpos = 0;
			}
			else
			{
				$newpid = $this->nodes[$id]["parent"];
				$newpos = $this->nodes[$id]["parentpos"];

				$this->Remove($id);
			}

			return $this->Move($src, $newpid, $newpos);
		}

		public function GetOuterHTML($id, $mode = "html")
		{
			return $this->Implode($id, array("output_mode" => $mode));
		}

		public function SetOuterHTML($id, $src)
		{
			return $this->Replace($id, $src);
		}

		public function GetInnerHTML($id, $mode = "html")
		{
			return $this->Implode($id, array("include_id" => false, "output_mode" => $mode));
		}

		public function SetInnerHTML($id, $src)
		{
			return $this->Replace($id, $src, true);
		}

		public function GetPlainText($id)
		{
			return $this->Implode($id, array("types" => "content", "post_elements" => array("p" => "\n\n", "br" => "\n")));
		}

		public function SetPlainText($id, $src)
		{
			// Convert $src to a string.
			if ($src instanceof TagFilterNodes)
			{
				$src = $src->GetPlainText(0);
			}
			else if (is_array($src))
			{
				$temp = new \CubicleSoft\TagFilterNodes();
				$temp->Move($src, 0, 0);

				$src = $temp->GetPlainText(0);
			}
			else if (!is_string($src))
			{
				$src = $this->GetPlainText((int)$src);
			}

			$src = array(
				"type" => "content",
				"text" => (string)$src,
				"parent" => false,
				"parentpos" => false
			);

			return $this->Replace($id, $src, true);
		}

		private function RealignChildren($id, $pos)
		{
			$y = count($this->nodes[$id]["children"]);
			for ($x = $pos; $x < $y; $x++)  $this->nodes[$this->nodes[$id]["children"][$x]]["parentpos"] = $x;
		}
	}
?>

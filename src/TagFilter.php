<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2017 CubicleSoft.  All Rights Reserved.
	
	class TagFilter
	{
		// Internal callback function for extracting interior content of HTML 'script' and 'style' tags.
		public static function HTMLSpecialTagContentCallback($stack, $final, &$tag, &$content, &$cx, $cy, &$content2, $options)
		{
			if (preg_match('/<\s*\/\s*' . $stack[0]["tag_name"] . '(\s*|\s+.+?)>/is', $content, $matches, PREG_OFFSET_CAPTURE, $cx))
			{
				$pos = $matches[0][1];

				$content2 = substr($content, $cx, $pos - $cx);
				$cx = $pos;
				$tag = true;

				return true;
			}
			else
			{
				if ($final)
				{
					$content2 = substr($content, $cx);
					$cx = $cy;
				}

				return false;
			}
		}

		public static function GetHTMLOptions()
		{
			$result = array(
				"tag_name_map" => array(
					"!doctype" => "DOCTYPE"
				),
				"untouched_tag_attr_keys" => array(
					"doctype" => true,
				),
				"void_tags" => array(
					"DOCTYPE" => true,
					"area" => true,
					"base" => true,
					"bgsound" => true,
					"br" => true,
					"col" => true,
					"embed" => true,
					"hr" => true,
					"img" => true,
					"input" => true,
					"keygen" => true,
					"link" => true,
					"menuitem" => true,
					"meta" => true,
					"param" => true,
					"source" => true,
					"track" => true,
					"wbr" => true
				),
				// Alternate tag internal content rules for specialized tags.
				"alt_tag_content_rules" => array(
					"script" => __CLASS__ . "::HTMLSpecialTagContentCallback",
					"style" => __CLASS__ . "::HTMLSpecialTagContentCallback"
				),
				// Stored as a map for open tag elements.
				// For example, '"address" => array("p" => true)' means:  When an open 'address' tag is encountered,
				// look for an open 'p' tag anywhere (no '_limit') in the tag stack.  Apply a closing '</p>' tag for all matches.
				//
				// If '_limit' is defined as a string or an array, then stack walking stops as soon as one of the specified tags is encountered.
				"pre_close_tags" => array(
					"body" => array("body" => true, "head" => true),

					"address" => array("p" => true),
					"article" => array("p" => true),
					"aside" => array("p" => true),
					"blockquote" => array("p" => true),
					"div" => array("p" => true),
					"dl" => array("p" => true),
					"fieldset" => array("p" => true),
					"footer" => array("p" => true),
					"form" => array("p" => true),
					"h1" => array("p" => true),
					"h2" => array("p" => true),
					"h3" => array("p" => true),
					"h4" => array("p" => true),
					"h5" => array("p" => true),
					"h6" => array("p" => true),
					"header" => array("p" => true),
					"hr" => array("p" => true),
					"menu" => array("p" => true),
					"nav" => array("p" => true),
					"ol" => array("p" => true),
					"pre" => array("p" => true),
					"section" => array("p" => true),
					"table" => array("p" => true),
					"ul" => array("p" => true),
					"p" => array("p" => true),

					"tbody" => array("_limit" => "table", "thead" => true, "tr" => true, "th" => true, "td" => true),
					"tr" => array("_limit" => "table", "tr" => true, "th" => true, "td" => true),
					"th" => array("_limit" => "table", "th" => true, "td" => true),
					"td" => array("_limit" => "table", "th" => true, "td" => true),
					"tfoot" => array("_limit" => "table", "thead" => true, "tbody" => true, "tr" => true, "th" => true, "td" => true),

					"optgroup" => array("optgroup" => true, "option" => true),
					"option" => array("option" => true),

					"dd" => array("_limit" => "dl", "dd" => true, "dt" => true),
					"dt" => array("_limit" => "dl", "dd" => true, "dt" => true),

					"colgroup" => array("colgroup" => true),

					"li" => array("_limit" => array("ul" => true, "ol" => true, "menu" => true, "dir" => true), "li" => true),
				),
				"process_attrs" => array(
					"class" => "classes",
					"href" => "uri",
					"src" => "uri",
					"dynsrc" => "uri",
					"lowsrc" => "uri",
					"background" => "uri",
				),
				"keep_attr_newlines" => false,
				"keep_comments" => false,
				"allow_namespaces" => true,
				"charset" => "UTF-8",
				"output_mode" => "html",
				"lowercase_tags" => true,
				"lowercase_attrs" => true,
			);

			return $result;
		}

		public static function Run($content, $options = array())
		{
			$tfs = new \CubicleSoft\TagFilterStream($options);
			$tfs->Finalize();
			$result = $tfs->Process($content);

			// Clean up output.
			$result = trim($result);
			$result = self::CleanupResults($result);

			return $result;
		}

		public static function CleanupResults($content)
		{
			$result = str_replace("\r\n", "\n", $content);
			$result = str_replace("\r", "\n", $result);
			while (strpos($result, "\n\n\n") !== false)  $result = str_replace("\n\n\n", "\n\n", $result);

			return $result;
		}

		public static function ExplodeTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			if ($open)
			{
				$pid = (count($options["data"]->stackmap) ? $options["data"]->stackmap[0] : 0);

				$options["nodes"]->nodes[$options["nodes"]->nextid] = array(
					"type" => "element",
					"tag" => $tagname,
					"attrs" => $attrs,
					"parent" => $pid,
					"parentpos" => count($options["nodes"]->nodes[$pid]["children"]),
					"children" => (isset($options["void_tags"][$tagname]) ? false : array())
				);

				$options["nodes"]->nodes[$pid]["children"][] = $options["nodes"]->nextid;

				// Append non-void tags to the ID stack.
				if (!isset($options["void_tags"][$tagname]))  array_unshift($options["data"]->stackmap, $options["nodes"]->nextid);

				$options["nodes"]->nextid++;
			}
			else
			{
				array_shift($options["data"]->stackmap);
			}

			return array("keep_tag" => false, "keep_interior" => false);
		}

		public static function ExplodeContentCallback($stack, $result, &$content, $options)
		{
			if ($content === "")  return;

			$type = (substr($content, 0, 5) === "<!-- " ? "comment" : "content");
			$pid = (count($options["data"]->stackmap) ? $options["data"]->stackmap[0] : 0);
			$parentpos = count($options["nodes"]->nodes[$pid]["children"]);

			if ($parentpos && $options["nodes"]->nodes[$options["nodes"]->nodes[$pid]["children"][$parentpos - 1]]["type"] == $type)  $options["nodes"]->nodes[$options["nodes"]->nodes[$pid]["children"][$parentpos - 1]]["text"] .= $content;
			else
			{
				$options["nodes"]->nodes[$options["nodes"]->nextid] = array(
					"type" => $type,
					"text" => $content,
					"parent" => $pid,
					"parentpos" => $parentpos
				);

				$options["nodes"]->nodes[$pid]["children"][] = $options["nodes"]->nextid;

				$options["nodes"]->nextid++;
			}

			$content = "";
		}

		public static function Explode($content, $options = array())
		{
			$options["tag_callback"] = __CLASS__ . "::ExplodeTagCallback";
			$options["content_callback"] = __CLASS__ . "::ExplodeContentCallback";
			$options["nodes"] = new \CubicleSoft\TagFilterNodes();
			$options["data"] = new \stdClass();
			$options["data"]->stackmap = array();

			self::Run($content, $options);

			return $options["nodes"];
		}

		public static function HTMLPurifyTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			if ($open)
			{
				if ($tagname === "script")  return array("keep_tag" => false, "keep_interior" => false);
				if ($tagname === "style")  return array("keep_tag" => false, "keep_interior" => false);

				if (isset($attrs["src"]) && substr($attrs["src"], 0, 11) === "javascript:")  return array("keep_tag" => false, "keep_interior" => false);
				if (isset($attrs["href"]) && substr($attrs["href"], 0, 11) === "javascript:")  return array("keep_tag" => false);

				if (!isset($options["htmlpurify"]["allowed_tags"][$tagname]))  return array("keep_tag" => false);

				if (!isset($options["htmlpurify"]["allowed_attrs"][$tagname]))  $attrs = array();
				else
				{
					// For classes, "class" needs to be specified as an allowed attribute.
					foreach ($attrs as $attr => $val)
					{
						if (!isset($options["htmlpurify"]["allowed_attrs"][$tagname][$attr]))  unset($attrs[$attr]);
					}
				}

				if (isset($options["htmlpurify"]["required_attrs"][$tagname]))
				{
					foreach ($options["htmlpurify"]["required_attrs"][$tagname] as $attr => $val)
					{
						if (!isset($attrs[$attr]))  return array("keep_tag" => false);
					}
				}

				if (isset($attrs["class"]))
				{
					if (!isset($options["htmlpurify"]["allowed_classes"][$tagname]))  unset($attrs["class"]);
					else
					{
						foreach ($attrs["class"] as $class)
						{
							if (!isset($options["htmlpurify"]["allowed_classes"][$tagname][$class]))  unset($attrs["class"][$class]);
						}

						if (!count($attrs["class"]))  unset($attrs["class"]);
					}
				}
			}
			else
			{
				if (isset($options["htmlpurify"]["remove_empty"][substr($tagname, 1)]) && trim($content) === "")  return array("keep_tag" => false);
			}

			return array();
		}

		private static function Internal_NormalizeHTMLPurifyOptions($value)
		{
			if (is_string($value))
			{
				$opts = explode(",", $value);
				$value = array();
				foreach ($opts as $opt)
				{
					$opt = (string)trim($opt);
					if ($opt !== "")  $value[$opt] = true;
				}
			}

			return $value;
		}

		public static function NormalizeHTMLPurifyOptions($purifyopts)
		{
			if (!isset($purifyopts["allowed_tags"]))  $purifyopts["allowed_tags"] = array();
			if (!isset($purifyopts["allowed_attrs"]))  $purifyopts["allowed_attrs"] = array();
			if (!isset($purifyopts["required_attrs"]))  $purifyopts["required_attrs"] = array();
			if (!isset($purifyopts["allowed_classes"]))  $purifyopts["allowed_classes"] = array();
			if (!isset($purifyopts["remove_empty"]))  $purifyopts["remove_empty"] = array();

			$purifyopts["allowed_tags"] = self::Internal_NormalizeHTMLPurifyOptions($purifyopts["allowed_tags"]);
			foreach ($purifyopts["allowed_attrs"] as $key => $val)  $purifyopts["allowed_attrs"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			foreach ($purifyopts["required_attrs"] as $key => $val)  $purifyopts["required_attrs"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			foreach ($purifyopts["allowed_classes"] as $key => $val)  $purifyopts["allowed_classes"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			$purifyopts["remove_empty"] = self::Internal_NormalizeHTMLPurifyOptions($purifyopts["remove_empty"]);

			return $purifyopts;
		}

		public static function HTMLPurify($content, $htmloptions, $purifyopts)
		{
			$htmloptions["tag_callback"] = __CLASS__ . "::HTMLPurifyTagCallback";
			$htmloptions["htmlpurify"] = self::NormalizeHTMLPurifyOptions($purifyopts);

			return self::Run($content, $htmloptions);
		}

		public static function ReorderSelectorTokens($tokens, $splitrules, $order = array("pseudo-element" => array(), "pseudo-class" => array(), "attr" => array(), "class" => array(), "element" => array(), "id" => array()), $endnots = true)
		{
			// Collapse split rules.
			if (count($tokens) && !isset($tokens[0]["type"]) && isset($tokens[0][0]["type"]))
			{
				$tokens2 = array();
				foreach ($tokens as $rules)
				{
					if (count($tokens2))  $tokens2[] = array("type" => "combine", "combine" => "or");
					$rules = array_reverse($rules);
					foreach ($rules as $rule)  $tokens2[] = $rule;
				}

				$tokens = $tokens2;
			}

			$result = array();
			$rules = array();
			$selector = $order;
			foreach ($tokens as $token)
			{
				if ($token["type"] != "combine")  array_unshift($selector[$token["type"]], $token);
				else
				{
					foreach ($selector as $vals)
					{
						foreach ($vals as $token2)
						{
							if (($endnots && $token2["not"]) || (!$endnots && !$token2["not"]))  array_unshift($result, $token2);
						}

						foreach ($vals as $token2)
						{
							if (($endnots && !$token2["not"]) || (!$endnots && $token2["not"]))  array_unshift($result, $token2);
						}
					}

					if (!$splitrules || $token["combine"] != "or")  array_unshift($result, $token);
					else if ($token["combine"] == "or")
					{
						if (count($result))  $rules[] = $result;

						$result = array();
					}

					$selector = $order;
				}
			}

			foreach ($selector as $vals)
			{
				foreach ($vals as $token2)
				{
					if (($endnots && $token2["not"]) || (!$endnots && !$token2["not"]))  array_unshift($result, $token2);
				}

				foreach ($vals as $token2)
				{
					if (($endnots && !$token2["not"]) || (!$endnots && $token2["not"]))  array_unshift($result, $token2);
				}
			}

			if ($splitrules)
			{
				if (count($result))  $rules[] = $result;

				$result = $rules;
			}
			else
			{
				// Ignore a stray group combiner at the end.
				if (count($result) && $result[0]["type"] == "combine" && $result[0]["combine"] == "or")  array_shift($result);
			}

			return $result;
		}

		public static function ParseSelector($query, $splitrules = false)
		{
			// Tokenize query into individual action steps.
			$query = trim($query);
			$tokens = array();
			$lastor = 0;
			$a = ord("A");
			$a2 = ord("a");
			$f = ord("F");
			$f2 = ord("f");
			$z = ord("Z");
			$z2 = ord("z");
			$backslash = ord("\\");
			$hyphen = ord("-");
			$underscore = ord("_");
			$pipe = ord("|");
			$asterisk = ord("*");
			$colon = ord(":");
			$period = ord(".");
			$zero = ord("0");
			$nine = ord("9");
			$cr = ord("\r");
			$nl = ord("\n");
			$ff = ord("\f");
			$cx = 0;
			$cy = strlen($query);
			$state = "next_selector";
			do
			{
				$currcx = $cx;
				$currstate = $state;

				switch ($state)
				{
					case "next_selector":
					{
						// This state is necessary to handle the :not(selector) function.
						$token = array("not" => false);
					}
					case "selector":
					{
						if ($cx >= $cy)  break;

						switch ($query{$cx})
						{
							case "#":
							{
								$token["type"] = "id";
								$state = "ident_name";
								$allownamespace = false;
								$identasterisk = false;
								$allowperiod = false;
								$namespace = false;
								$range = true;
								$ident = "";
								$nextstate = "selector_ident_result";
								$cx++;

								break;
							}
							case ".":
							{
								$token["type"] = "class";
								$state = "ident";
								$allownamespace = false;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";
								$cx++;

								break;
							}
							case "[":
							{
								$token["type"] = "attr";
								$state = "ident";
								$state2 = "attr";
								$allownamespace = true;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";
								$cx++;

								// Find a non-whitespace character.
								while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

								break;
							}
							case ":":
							{
								$cx++;
								if ($cx >= $cy || $query{$cx} != ":")  $token["type"] = "pseudo-class";
								else
								{
									$token["type"] = "pseudo-element";
									$cx++;
								}

								$state = "ident";
								$allownamespace = true;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";

								break;
							}
							case ",":
							case "+":
							case ">":
							case "~":
							case " ":
							case "\r":
							case "\n":
							case "\t":
							case "\f":
							{
								$state = "combine";

								break;
							}
							default:
							{
								$token["type"] = "element";
								$state = "ident";
								$allownamespace = true;
								$identasterisk = true;
								$allowperiod = false;
								$nextstate = "selector_ident_result";

								break;
							}
						}

						break;
					}
					case "selector_ident_result":
					{
						switch ($token["type"])
						{
							case "id":
							{
								$token["id"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "class":
							{
								$token["class"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "element":
							{
								$token["namespace"] = $namespace;
								$token["tag"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "attr":
							{
								if ($state2 == "attr")
								{
									$token["namespace"] = $namespace;
									$token[$state2] = $ident;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

									if ($cx >= $cy || $query{$cx} == "]")
									{
										$token["cmp"] = false;
										$tokens[] = $token;
										$state = ($token["not"] ? "negate_close" : "next_selector");
										$cx++;
									}
									else
									{
										if ($query{$cx} == "=")
										{
											$token["cmp"] = "=";
											$cx++;
										}
										else if ($cx + 1 < $cy && ($query{$cx} == "^" || $query{$cx} == "$" || $query{$cx} == "*" || $query{$cx} == "~" || $query{$cx} == "|") && $query{$cx + 1} == "=")
										{
											$token["cmp"] = substr($query, $cx, 2);
											$cx += 2;
										}
										else
										{
											return array("success" => false, "error" => "Unknown or invalid attribute comparison operator '" . $query{$cx} . "' detected at position " . $cx . ".", "errorcode" => "invalid_attr_compare", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);
										}

										// Find a non-whitespace character.
										while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

										if ($cx < $cy && ($query{$cx} == "\"" || $query{$cx} == "'"))
										{
											$state = "string";
											$endchr = ord($query{$cx});
											$cx++;
										}
										else
										{
											$state = "ident";
											$allownamespace = false;
											$identasterisk = false;
											$allowperiod = false;
										}

										$state2 = "val";
										$nextstate = "selector_ident_result";
									}
								}
								else if ($state2 == "val")
								{
									$token[$state2] = $ident;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

									$tokens[] = $token;
									$state = ($token["not"] ? "negate_close" : "next_selector");

									if ($cx < $cy && $query{$cx} == "]")  $cx++;
								}

								break;
							}
							case "pseudo-class":
							case "pseudo-element":
							{
								$ident = strtolower($ident);

								// Deal with CSS1 and CSS2 compatibility.
								if ($ident === "first-line" || $ident === "first-letter" || $ident === "before" || $ident === "after")  $token["type"] = "pseudo-element";

								if ($token["type"] == "pseudo-class" && $ident == "not")
								{
									if ($token["not"])  return array("success" => false, "error" => "Invalid :not() embedded inside another :not() detected at position " . $cx . ".", "errorcode" => "invalid_not", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);
									if ($cx >= $cy || $query{$cx} != "(")  return array("success" => false, "error" => "Missing '(' detected at position " . $cx . ".", "errorcode" => "invalid_not", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

									unset($token["type"]);
									$token["not"] = true;

									$state = "selector";
									$cx++;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;
								}
								else
								{
									$token["pseudo"] = $ident;

									if ($cx < $cy && $query{$cx} == "(")
									{
										$token["expression"] = "";
										$ident = "";
										$state = "pseudo_expression";
										$cx++;
									}
									else
									{
										$token["expression"] = false;
										$tokens[] = $token;
										$state = ($token["not"] ? "negate_close" : "next_selector");
									}
								}

								break;
							}
						}

						break;
					}
					case "negate_close":
					{
						// Find a non-whitespace character.
						while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

						if ($cx < $cy && $query{$cx} != ")")  return array("success" => false, "error" => "Invalid :not() close character '" . $query{$cx} . "' detected at position " . $cx . ".", "errorcode" => "invalid_negate_close", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

						$cx++;
						$state = "next_selector";

						break;
					}
					case "pseudo_expression":
					{
						$token["expression"] .= $ident;

						// Find a non-whitespace character.
						while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

						if ($cx >= $cy)  break;

						if ($query{$cx} == ")")
						{
							if (substr($token["pseudo"], 0, 4) === "nth-")
							{
								// Convert the expression to an+b syntax.
								$exp = strtolower($token["expression"]);

								if ($exp == "even")  $exp = "2n";
								else if ($exp == "odd")  $exp = "2n+1";
								else
								{
									do
									{
										$currexp = $exp;

										$exp = str_replace(array("++", "+-", "-+", "--"), array("+", "-", "-", "+"), $exp);

									} while ($currexp !== $exp);
								}

								if (substr($exp, 0, 2) == "-n")  $exp = "-1n" . substr($exp, 2);
								else if (substr($exp, 0, 2) == "+n")  $exp = "1n" . substr($exp, 2);
								else if (substr($exp, 0, 1) == "n")  $exp = "1n" . substr($exp, 1);

								$pos = strpos($exp, "n");
								if ($pos === false)
								{
									$token["a"] = 0;
									$token["b"] = (double)$exp;
								}
								else
								{
									$token["a"] = (double)$exp;
									$token["b"] = (double)substr($exp, $pos + 1);
								}

								$token["expression"] = $token["a"] . "n" . ($token["b"] < 0 ? $token["b"] : "+" . $token["b"]);
							}

							$tokens[] = $token;
							$state = ($token["not"] ? "negate_close" : "next_selector");
							$cx++;
						}
						else if ($query{$cx} == "+" || $query{$cx} == "-")
						{
							$ident = $query{$cx};
							$cx++;
						}
						else if ($query{$cx} == "\"" || $query{$cx} == "'")
						{
							$state = "string";
							$endchr = ord($query{$cx});
							$cx++;
						}
						else
						{
							$val = ord($query{$cx});

							$state = ($val >= $zero && $val <= $nine ? "ident_name" : "ident");
							$allownamespace = false;
							$identasterisk = false;
							$allowperiod = ($val >= $zero && $val <= $nine);
							$namespace = false;
							$range = true;
							$ident = "";

							$nextstate = "pseudo_expression";
						}

						break;
					}
					case "string":
					{
						$startcx = $cx;
						$ident = "";

						for (; $cx < $cy; $cx++)
						{
							$val = ord($query{$cx});

							if ($val == $endchr)
							{
								$cx++;

								break;
							}
							else if ($val == $backslash)
							{
								// Escape sequence.
								if ($cx + 1 >= $cy)  $ident .= "\\";
								else
								{
									$cx++;
									$val = ord($query{$cx});

									if (($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine))
									{
										// Unicode (e.g. \0020)
										for ($x = $cx + 1; $x < $cy; $x++)
										{
											$val = ord($query{$x});
											if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
										}

										$num = hexdec(substr($query, $cx, $x - $cx));
										$cx = $x - 1;

										$ident .= \CubicleSoft\TagFilterStream::UTF8Chr($num);

										// Skip one optional \r\n OR a single whitespace char.
										if ($cx + 2 < $cy && $query{$cx + 1} == "\r" && $query{$cx + 2} == "\n")  $cx += 2;
										else if ($cx + 1 < $cy && ($query{$cx + 1} == " " || $query{$cx + 1} == "\r" || $query{$cx + 1} == "\n" || $query{$cx + 1} == "\t" || $query{$cx + 1} == "\f"))  $cx++;
									}
									else
									{
										$ident .= $query{$cx};
									}
								}
							}
							else
							{
								$ident .= $query{$cx};
							}
						}

						$state = $nextstate;

						break;
					}
					case "ident":
					{
						$namespace = false;
						$range = false;

						if ($cx >= $cy)  break;

						if ($query{$cx} != "-")  $ident = "";
						else
						{
							$ident = "-";
							$cx++;
						}

						$state = "ident_name";

						break;
					}
					case "ident_name":
					{
						// Find the first invalid character.
						$startcx = $cx;
						for (; $cx < $cy; $cx++)
						{
							$val = ord($query{$cx});

							if ($val != $period && ($val < $zero || $val > $nine))  $allowperiod = false;

							if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || $val == $underscore || $val > 127)
							{
								$ident .= $query{$cx};
							}
							else if ($allowperiod && $val == $period)
							{
								$allowperiod = false;

								$ident .= ".";
							}
							else if ($val == $hyphen || ($val >= $zero && $val <= $nine))
							{
								// Only allowed AFTER the first character.
								if (!$range)  return array("success" => false, "error" => "Invalid identifier character '" . $query{$cx} . "' detected at position " . $cx . ".", "errorcode" => "invalid_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

								$allowperiod = false;

								$ident .= $query{$cx};
							}
							else if ($val == $backslash)
							{
								// Escape sequence.
								if ($cx + 1 >= $cy)  $ident .= "\\";
								else
								{
									$cx++;
									$val = ord($query{$cx});

									if (($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine))
									{
										// Unicode (e.g. \0020)
										for ($x = $cx + 1; $x < $cy; $x++)
										{
											$val = ord($query{$x});
											if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
										}

										$num = hexdec(substr($query, $cx, $x - $cx));
										$cx = $x - 1;

										$ident .= \CubicleSoft\TagFilterStream::UTF8Chr($num);

										// Skip one optional \r\n OR a single whitespace char.
										if ($cx + 2 < $cy && $query{$cx + 1} == "\r" && $query{$cx + 2} == "\n")  $cx += 2;
										else if ($cx + 1 < $cy && ($query{$cx + 1} == " " || $query{$cx + 1} == "\r" || $query{$cx + 1} == "\n" || $query{$cx + 1} == "\t" || $query{$cx + 1} == "\f"))  $cx++;
									}
									else if ($val != $cr && $val != $nl && $val != $ff)
									{
										$ident .= $query{$cx};
									}
								}
							}
							else if ($allownamespace && $val == $pipe && ($cx + 1 >= $cy || $query{$cx + 1} != "="))
							{
								// Handle namespaces (rare).
								if ($ident != "")
								{
									$namespace = $ident;
									$ident = "";
								}

								$allownamespace = false;
							}
							else if ($val == $asterisk)
							{
								// Handle wildcard (*) characters.
								if ($allownamespace && $cx + 1 < $cy && $query{$cx + 1} == "|")
								{
									// Wildcard namespace (*|).
									$namespace = "*";
									$allownamespace = false;
									$cx++;
								}
								else if ($identasterisk)
								{
									if ($ident != "")  return array("success" => false, "error" => "Invalid identifier wildcard character '*' detected at position " . $cx . ".", "errorcode" => "invalid_wildcard_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

									$ident = "*";
									$cx++;

									break;
								}
								else
								{
									// End of ident.
									break;
								}
							}
							else
							{
								// End of ident.
								break;
							}

							$range = true;
						}

						if ($ident == "")  return array("success" => false, "error" => "Missing or invalid identifier at position " . $cx . ".", "errorcode" => "missing_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

						$state = $nextstate;

						break;
					}
					case "combine":
					{
						$token = array("type" => "combine");

						// Find a non-whitespace character.
						while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;

						if ($cx < $cy)
						{
							switch ($query{$cx})
							{
								case ",":
								{
									$token["combine"] = "or";
									$lastor = count($tokens);
									$cx++;

									break;
								}
								case "+":
								{
									$token["combine"] = "prev-sibling";
									$cx++;

									break;
								}
								case ">":
								{
									$token["combine"] = "prev-parent";
									$cx++;

									break;
								}
								case "~":
								{
									$token["combine"] = "any-prev-sibling";
									$cx++;

									break;
								}
								default:
								{
									$token["combine"] = "any-parent";

									break;
								}
							}

							if (!count($tokens) || $tokens[count($tokens) - 1]["type"] == "combine")  return array("success" => false, "error" => "Invalid combiner '" . $token["type"] . "' detected at position " . $cx . ".", "errorcode" => "invalid_combiner", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

							$tokens[] = $token;

							// Find a non-whitespace character.
							while ($cx < $cy && ($query{$cx} == " " || $query{$cx} == "\t" || $query{$cx} == "\r" || $query{$cx} == "\n" || $query{$cx} == "\f"))  $cx++;
						}

						$state = "next_selector";

						break;
					}
				}
			} while ($currstate !== $state || $currcx !== $cx);

			return array("success" => true, "selector" => $query, "tokens" => self::ReorderSelectorTokens($tokens, $splitrules), "splitrules" => $splitrules);
		}

		public static function GetParentPos($stack, $tagname, $start = 0, $attrs = array())
		{
			$y = count($stack);
			for ($x = $start; $x < $y; $x++)
			{
				if ($stack[$x]["tag_name"] === $tagname)
				{
					$found = true;
					foreach ($attrs as $key => $val)
					{
						if (!isset($stack[$x]["attrs"][$key]))  $found = false;
						else if (is_string($stack[$x]["attrs"][$key]) && is_string($val) && stripos($stack[$x]["attrs"][$key], $val) === false)  $found = false;
						else if (is_array($stack[$x]["attrs"][$key]))
						{
							if (is_string($val))  $val = explode(" ", $val);

							foreach ($val as $val2)
							{
								if ($val2 !== "" && !isset($stack[$x]["attrs"][$key][$val2]))  $found = false;
							}
						}
					}

					if ($found)  return $x;
				}
			}

			return false;
		}
	}
?>

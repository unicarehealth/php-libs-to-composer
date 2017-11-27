<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2017 CubicleSoft.  All Rights Reserved.
	
	// Accessing the data in TagFilterNodes (with an 's') via objects is not the most performance-friendly method of access.
	// The classes TagFilterNode and TagFilterNodeIterator defer method calls to the referenced TagFilterNodes instance.
	// Removed/replaced nodes in the original data will result in undefined behavior with object reuse.
	class TagFilterNode
	{
		private $tfn, $id;

		public function __construct($tfn, $rootid)
		{
			$this->tfn = $tfn;
			$this->id = $rootid;
		}

		public function __get($key)
		{
			return (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]) && isset($this->tfn->nodes[$this->id]["attrs"][$key]) ? $this->tfn->nodes[$this->id]["attrs"][$key] : false);
		}

		public function __set($key, $val)
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (is_array($val))  $this->tfn->nodes[$this->id]["attrs"][$key] = $val;
				else if (is_array($this->tfn->nodes[$this->id]["attrs"][$key]))  $this->tfn->nodes[$this->id]["attrs"][$key][(string)$val] = (string)$val;
				else  $this->tfn->nodes[$this->id]["attrs"][$key] = (string)$val;
			}
		}

		public function __isset($key)
		{
			return (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]) && isset($this->tfn->nodes[$this->id]["attrs"][$key]));
		}

		public function __unset($key)
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))  unset($this->tfn->nodes[$this->id]["attrs"][$key]);
		}

		public function __toString()
		{
			return $this->tfn->GetOuterHTML($this->id);
		}

		public function __debugInfo()
		{
			$result = (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id] : array());
			$result["id"] = $this->id;

			return $result;
		}

		public function ID()
		{
			return $this->id;
		}

		public function Node()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id] : false);
		}

		public function Type()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id]["type"] : false);
		}

		public function Tag()
		{
			return $this->tfn->GetTag($this->id);
		}

		public function AddClass($name, $attr = "class")
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (!isset($this->tfn->nodes[$this->id]["attrs"][$attr]) || !is_array($this->tfn->nodes[$this->id]["attrs"][$attr]))  $this->tfn->nodes[$this->id]["attrs"][$attr] = array();

				$this->tfn->nodes[$this->id]["attrs"][$attr][$name] = $name;
			}
		}

		public function RemoveClass($name, $attr = "class")
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (isset($this->tfn->nodes[$this->id]["attrs"][$attr]) && is_array($this->tfn->nodes[$this->id]["attrs"][$attr]))  unset($this->tfn->nodes[$this->id]["attrs"][$attr][$name]);
			}
		}

		public function Parent()
		{
			return $this->tfn->GetParent($this->id);
		}

		public function ParentPos()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id]["parentpos"] : false);
		}

		// Passing true to this method has the potential to leak RAM.  Passing false is preferred, use with caution.
		public function Children($objects = false)
		{
			return $this->tfn->GetChildren($this->id, $objects);
		}

		public function Child($pos)
		{
			return $this->tfn->GetChild($this->id, $pos);
		}

		public function PrevSibling()
		{
			return $this->tfn->GetPrevSibling($this->id);
		}

		public function NextSibling()
		{
			return $this->tfn->GetNextSibling($this->id);
		}

		public function Find($query, $cachequery = true, $firstmatch = false)
		{
			$result = $this->tfn->Find($query, $this->id, $cachequery, $firstmatch);
			if (!$result["success"])  return $result;

			return new \CubicleSoft\TagFilterNodeIterator($this->tfn, $result["ids"]);
		}

		public function Implode($options = array())
		{
			return $this->tfn->Implode($this->id, $options);
		}

		public function GetOuterHTML($mode = "html")
		{
			return $this->tfn->GetOuterHTML($this->id, $mode);
		}

		// Set functions ruin the object.
		public function SetOuterHTML($src)
		{
			return $this->tfn->SetOuterHTML($this->id, $src);
		}

		public function GetInnerHTML($mode = "html")
		{
			return $this->tfn->GetInnerHTML($this->id, $mode);
		}

		public function SetInnerHTML($src)
		{
			return $this->tfn->SetInnerHTML($this->id, $src);
		}

		public function GetPlainText()
		{
			return $this->tfn->GetPlainText($this->id);
		}

		// Set functions ruin the object.
		public function SetPlainText($src)
		{
			return $this->tfn->SetPlainText($this->id, $src);
		}
	}
?>

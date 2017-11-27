<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2017 CubicleSoft.  All Rights Reserved.
	
	class TagFilterNodeIterator implements Iterator
	{
		private $tfn, $ids, $x, $y;

		public function __construct($tfn, $ids)
		{
			$this->tfn = $tfn;
			$this->ids = $ids;
			$this->x = 0;
			$this->y = count($ids);
		}

		public function rewind()
		{
			$this->x = 0;
		}

		public function valid()
		{
			return ($this->x < $this->y);
		}

		public function current()
		{
			return $this->tfn->Get($this->ids[$this->x]);
		}

		public function key()
		{
			return $this->ids[$this->x];
		}

		public function next()
		{
			$this->x++;
		}

		public function Filter($query, $cachequery = true)
		{
			$result = $this->tfn->Filter($this->ids, $query, $cachequery);
			if (!$result["success"])  return $result;

			return new \CubicleSoft\TagFilterNodeIterator($this->tfn, $result["ids"]);
		}
	}
?>

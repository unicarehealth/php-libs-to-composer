<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft generic database base class.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	class CSDB_PDO_Statement
	{
		private $db, $stmt, $filteropts;

		function __construct($db, $stmt, $filteropts)
		{
			$this->db = $db;
			$this->stmt = $stmt;
			$this->filteropts = $filteropts;
		}

		function __destruct()
		{
			$this->Free();
		}

		function Free()
		{
			if ($this->stmt === false)  return false;

			$this->stmt = false;

			return true;
		}

		function NextRow($fetchtype = \PDO::FETCH_OBJ)
		{
			if ($this->stmt === false && $this->filteropts === false)  return false;

			do
			{
				$fetchnext = false;
				$result = ($this->stmt !== false ? $this->stmt->fetch($this->filteropts === false ? $fetchtype : \PDO::FETCH_OBJ) : false);
				if ($this->filteropts !== false)  $this->db->RunRowFilter($result, $this->filteropts, $fetchnext);
			} while ($result !== false && $fetchnext);

			if ($result === false)  $this->Free();
			else if ($this->filteropts !== false && $fetchtype != \PDO::FETCH_OBJ)
			{
				$result2 = array();
				foreach ($result as $key => $val)
				{
					if ($fetchtype == \PDO::FETCH_NUM || $fetchtype == \PDO::FETCH_BOTH)  $result2[] = $val;
					if ($fetchtype == \PDO::FETCH_ASSOC || $fetchtype == \PDO::FETCH_BOTH)  $result2[$key] = $val;
				}

				$result = $result2;
			}

			return $result;
		}
	}
?>

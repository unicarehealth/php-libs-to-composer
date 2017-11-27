<?php
	namespace CubicleSoft;
?><?php
	// CubicleSoft PHP WebServer class.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// Internal class used to construct file objects to minimize the number of open file handles.
	// Otherwise an attacker could easily consume all available file handles in a single request.
	class WebServer_TempFile
	{
		public $fp, $filename;

		public function __destruct()
		{
			$this->Close();

			@unlink($this->filename);
		}

		public function Open()
		{
			$this->Close();

			$this->fp = @fopen($this->filename, "w+b");

			return $this->fp;
		}

		public function Read($size)
		{
			if ($this->fp === false)  return false;

			$data = @fread($this->fp, $size);
			if ($data === false || feof($this->fp))  $this->Close();
			if ($data === false)  $data = "";

			return $data;
		}

		public function Write($data)
		{
			return fwrite($this->fp, $data);
		}

		public function Close()
		{
			if (is_resource($this->fp))  @fclose($this->fp);

			$this->fp = false;
		}
	}
?>

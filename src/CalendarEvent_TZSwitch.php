<?php
	namespace CubicleSoft;
?><?php
	class CalendarEvent_TZSwitch
	{
		private $origtz, $newtz;

		public function __construct($new)
		{
			$tz = (function_exists("date_default_timezone_get") ? @date_default_timezone_get() : @ini_get("date.timezone"));
			if ($tz == "")  $tz = "UTC";

			$this->origtz = $tz;
			$this->newtz = $new;

			if ($this->origtz != $this->newtz)
			{
				if (function_exists("date_default_timezone_set"))  @date_default_timezone_set($this->newtz);
				else  @ini_set($this->newtz);
			}
		}

		public function __destruct()
		{
			if ($this->origtz != $this->newtz)
			{
				if (function_exists("date_default_timezone_set"))  @date_default_timezone_set($this->origtz);
				else  @ini_set($this->origtz);
			}
		}
	}
?>

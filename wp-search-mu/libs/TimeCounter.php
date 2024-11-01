<?php
/*
	As Per: http://www.ebrueggeman.com/article_php_execution_time.php
	Slightly Modified
*/
class TimeCounter
{
	var $startTime;
	var $endTime;
	
	function TimeCounter($start)
	{
		$this->startTime=0;
		$this->endTime=0;
		
		if ($start) $this->Start();
	}
	function getTimestamp()
	{
		$timeofday = gettimeofday();
		//RETRIEVE SECONDS AND MICROSECONDS (ONE MILLIONTH OF A SECOND)
		//CONVERT MICROSECONDS TO SECONDS AND ADD TO RETRIEVED SECONDS
		//MULTIPLY BY 1000 TO GET MILLISECONDS
		 return 1000*($timeofday['sec'] + ($timeofday['usec'] / 1000000));
	}
	function Start()
	{
		return $this->startTime=$this->getTimestamp();
	}
	function Stop()
	{
		$this->endTime=$this->getTimestamp();
		return $this->GetElapsedTime();
	}
	function GetElapsedTime()
	{
		//RETURN DIFFERECE IN MILLISECONDS
		return number_format(($this->endTime)-($this->startTime), 2);
	}
}
?>
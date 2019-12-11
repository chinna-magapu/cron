<?php
date_default_timezone_set('America/New_York');

Class Logger {
    const logNone = 0;
    const logNormal = 1;
    const logVerbose = 2;
    const logExtraVerbose = 3;

    private $pid = 0;
    private $logHandle;
	private $debugThreshold = self::logNormal;
	private $filename;


    public function __construct($logname, $threshold = self::logNormal) {
		if (php_sapi_name() == 'cli') {
			$this->filename = __DIR__.'/logs/'.$logname;
		} else {
			$this->filename = 'logs/'.$logname;
		}
		$this->logHandle = fopen($this->filename, 'a');
		$this->pid = getmypid();
    }

    public function __destruct() {
		if($this->logHandle !== false) {
			@fclose($this->logHandle);
		}
		$this->logHandle = null;
		$this->filePath = null;
	}

	public function setDebugLevel($level){
		$level = $level > self::logExtraVerbose || $level < 0 ? self::logNormal : $level;
		$this->debugThreshold = self::logNormal;
	}

    // Function writes the argument text and object to a log file if debugLevel is
    // less than or equal to a (non-zero) threshold passed in the arguments
    public function write($text, $debugLevel=self::logNormal, $obj=null) {
		if (!empty($this->logHandle)) {
			date_default_timezone_set('America/New_York');

			if ($debugLevel <= $this->debugThreshold) {
				$callStack = debug_backtrace(false);
				// use top of the stack - the inner script
				$caller = $callStack[0];
				$line = isset($caller['line'])?':'.$caller['line']:'';
				$callerID = basename($caller['file'], '.php') . "({$this->pid})" . $line;

				$utimestamp = microtime(true);
				$timestamp = floor($utimestamp);
				$microsecs = round(($utimestamp - $timestamp) * 1000000);

				$time = date('Y-m-d H:i:s', $timestamp).'.'.$microsecs;
				if ($obj!==null)
					$json = ' ' . json_encode($obj);
				else
					$json = '';

				fwrite($this->logHandle, $time. " [{$callerID}] " . $text . $json . "\n");
			}
		}
    }
}
?>

<?php

/*

Current Version:
- 1.0

Description:
- Provides functions to read from / write to serial port

Change Log:
- V1.0 API
  - php_serial ()
 	- deviceSet ($device)
  - deviceOpen ($device, $mode)
  - deviceClose ()
  - confBaudRate ($baudRate)
  - confParity ($parity)
  - confCharacterLength ($length)
  - confStopBits ($length)
  - confFlowControl ($mode)
  - sendMessage ($str, $waitForReplySec=0)
  - readPort ($count=0, $timeout=5)
  - readPortMultiple ($count=0, $timeout=5)
  - readPortTimed ($timeout=5)
  - readPortDelimited ($byteCount, $byteStart='<', $byteEnd='>', $timeout=5)
  - readPortDelimited_Calla ($byteStart='<', $byteEnd='>', $timeout=5)

*/

define("DEBUG_PHP", 0);
// 1 - print setup DEBUG_PHP messages
// 2 - print timestamps
// 4 - print raw buffer contents
// 8 - print unpacked buffer contents

define ("SERIAL_DEVICE_NOTSET", 0);
define ("SERIAL_DEVICE_SET", 1);
define ("SERIAL_DEVICE_OPENED", 2);

/**
 * Serial port control class
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * @modified by Tiffany Chua <tchua168@ymail.com>
 *
 * @author R�my Sanchez <thenux@gmail.com>
 * @thanks Aur�lien Derouineau for finding how to open serial ports with windows
 * @thanks Alec Avedisyan for help and testing with reading
 * @copyright under GPL 2 licence
 */
class phpSerial
{
	var $_device = null;
	var $_dHandle = null;
	var $_dState = SERIAL_DEVICE_NOTSET;
	var $_buffer = "";

	/**
	 * This var says if buffer should be flushed by sendMessage (true) or manualy (false)
	 *
	 * @var bool
	 */
	var $autoflush = true;

	/**
	 * Constructor. Perform some checks about the OS and setserial
	 *
	 * @return phpSerial
	 */
	function phpSerial ()
	{
		setlocale(LC_ALL, "en_US");

		$sysname = php_uname();

		register_shutdown_function(array($this, "deviceClose"));
	}

	//
	// OPEN/CLOSE DEVICE SECTION -- {START}
	//

	/**
	 * Device set function : used to set the device name/address.
	 * -> OpenWrt: use the device address, like /dev/ttyS0, /dev/USBtty0
	 *
	 * @param string $device the name of the device to be used
	 * @return bool
	 */
	function deviceSet ($device)
	{
		if ($this->_dState !== SERIAL_DEVICE_OPENED)
		{
			if ($this->_exec("sudo stty -F " . $device) === 0)
			{
				$this->_device = $device;
				$this->_dState = SERIAL_DEVICE_SET;
				return true;
			}
			trigger_error("Specified serial port is not valid", E_USER_WARNING);
			return false;
		}
		else
		{
			trigger_error("You must close your device before to set an other one", E_USER_WARNING);
			return false;
		}
	}

	/**
	 * Opens the device for reading and/or writing.
	 *
	 * @param string $mode Opening mode : same parameter as fopen()
	 * @return bool
	 */
	function deviceOpen ($mode = "r+b")
	{
		if ($this->_dState === SERIAL_DEVICE_OPENED)
		{
			trigger_error("The device is already opened", E_USER_NOTICE);
			return true;
		}

		if ($this->_dState === SERIAL_DEVICE_NOTSET)
		{
			trigger_error("The device must be set before to be open", E_USER_WARNING);
			return false;
		}

		if (!preg_match("@^[raw]\+?b?$@", $mode))
		{
			trigger_error("Invalid opening mode : ".$mode.". Use fopen() modes.", E_USER_WARNING);
			return false;
		}

		$this->_dHandle = @fopen($this->_device, $mode);

		if ($this->_dHandle !== false)
		{
			stream_set_blocking($this->_dHandle, 0);
			if (DEBUG_PHP & 1)
			{
				// check if non-blocking stream has been activated - should return 0
				$metadata = stream_get_meta_data($this->_dHandle);
				var_dump($metadata['blocked']);
			}
			$this->_dState = SERIAL_DEVICE_OPENED;
			return true;
		}

		$this->_dHandle = null;
		trigger_error("Unable to open the device", E_USER_WARNING);
		return false;
	}

	/**
	 * Closes the device
	 *
	 * @return bool
	 */
	function deviceClose ()
	{
		if ($this->_dState !== SERIAL_DEVICE_OPENED)
		{
			return true;
		}

		if (fclose($this->_dHandle))
		{
			$this->_dHandle = null;
			$this->_dState = SERIAL_DEVICE_SET;
			return true;
		}

		trigger_error("Unable to close the device", E_USER_ERROR);
		return false;
	}

	//
	// OPEN/CLOSE DEVICE SECTION -- {STOP}
	//

	//
	// CONFIGURE SECTION -- {START}
	//

	/**
	 * Configure the Baud Rate
	 * Possible rates : 110, 150, 300, 600, 1200, 2400, 4800, 9600, 38400,
	 * 57600 and 115200.
	 *
	 * @param int $rate the rate to set the port in
	 * @return bool
	 */
	function confBaudRate ($rate)
	{
		if ($this->_dState !== SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set the baud rate : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$validBauds = array (
			110    => 11,
			150    => 15,
			300    => 30,
			600    => 60,
			1200   => 12,
			2400   => 24,
			4800   => 48,
			9600   => 96,
			19200  => 19,
			38400  => 38400,
			57600  => 57600,
			115200 => 115200
		);

		if (isset($validBauds[$rate]))
		{
			$ret = $this->_exec("stty -F " . $this->_device . " " . (int) $rate, $out);

			if ($ret !== 0)
			{
				trigger_error ("Unable to set baud rate: " . $out[1], E_USER_WARNING);
				return false;
			}
		}
	}

	/**
	 * Configure parity.
	 * Modes : odd, even, none
	 *
	 * @param string $parity one of the modes
	 * @return bool
	 */
	function confParity ($parity)
	{
		if ($this->_dState !== SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set parity : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$args = array(
			"none" => "-parenb",
			"odd"  => "parenb parodd",
			"even" => "parenb -parodd",
		);

		if (!isset($args[$parity]))
		{
			trigger_error("Parity mode not supported", E_USER_WARNING);
			return false;
		}

		$ret = $this->_exec("stty -F " . $this->_device . " " . $args[$parity], $out);

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set parity : " . $out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Sets the length of a character.
	 *
	 * @param int $int length of a character (5 <= length <= 8)
	 * @return bool
	 */
	function confCharacterLength ($int)
	{
		if ($this->_dState !== SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set length of a character : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$int = (int) $int;
		if ($int < 5) $int = 5;
		elseif ($int > 8) $int = 8;

		$ret = $this->_exec("stty -F " . $this->_device . " cs" . $int, $out);

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set character length : " .$out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Sets the length of stop bits.
	 *
	 * @param float $length the length of a stop bit. It must be either 1,
	 * 1.5 or 2. 1.5 is not supported under linux and on some computers.
	 * @return bool
	 */
	function confStopBits ($length)
	{
		if ($this->_dState !== SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set the length of a stop bit : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		if ($length != 1 and $length != 2 and $length != 1.5)
		{
			trigger_error("Specified stop bit length is invalid", E_USER_WARNING);
			return false;
		}

		$ret = $this->_exec("stty -F " . $this->_device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set stop bit length : " . $out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Configures the flow control
	 *
	 * @param string $mode Set the flow control mode. Availible modes :
	 * 	-> "none" : no flow control
	 * 	-> "rts/cts" : use RTS/CTS handshaking
	 * 	-> "xon/xoff" : use XON/XOFF protocol
	 * @return bool
	 */
	function confFlowControl ($mode)
	{
		if ($this->_dState !== SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set flow control mode : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$linuxModes = array(
			"none"     => "clocal -crtscts -ixon -ixoff",
			"rts/cts"  => "-clocal crtscts -ixon -ixoff",
			"xon/xoff" => "-clocal -crtscts ixon ixoff"
		);

		if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
			trigger_error("Invalid flow control mode specified", E_USER_ERROR);
			return false;
		}

		$ret = $this->_exec("stty -F " . $this->_device . " " . $linuxModes[$mode], $out);

		if ($ret === 0) return true;
		else {
			trigger_error("Unable to set flow control : " . $out[1], E_USER_ERROR);
			return false;
		}
	}

	/**
	 * Sets a setserial parameter (cf man setserial)
	 * NO MORE USEFUL !
	 * 	-> No longer supported
	 * 	-> Only use it if you need it
	 *
	 * @param string $param parameter name
	 * @param string $arg parameter value
	 * @return bool
	 */
	function setSetserialFlag ($param, $arg = "")
	{
		if (!$this->_ckOpened()) return false;

		$return = exec ("setserial " . $this->_device . " " . $param . " " . $arg . " 2>&1");

		if ($return{0} === "I")
		{
			trigger_error("setserial: Invalid flag", E_USER_WARNING);
			return false;
		}
		elseif ($return{0} === "/")
		{
			trigger_error("setserial: Error with device file", E_USER_WARNING);
			return false;
		}
		else
		{
			return true;
		}
	}

	//
	// CONFIGURE SECTION -- {STOP}
	//

	//
	// I/O SECTION -- {START}
	//

	/**
	 * Sends a string to the device
	 *
	 * @param string $str string to be sent to the device
	 * @param float $waitForReply time to wait for the reply (in seconds)
	 */
	function sendMessage ($str, $waitForReply = 0)
	{
		global $texec;
		$start = microtime(true);

		$this->_buffer .= $str;

		if ($this->autoflush === true) $this->flush();

		if ($waitForReply > 0)
			usleep((int) ($waitForReply * 1000000));

		$texec += (microtime(true)-$start)*1000;
	}

	/**
	 * Reads a packet of length $count within $timeout sec
	 * - returns the data packet in $content
	 *
	 * @param int $count	   : number of characters to read
	 * @param float $timeout : read timeout in seconds
	 * 
   * @param float $texec (global) : $texec + execution time in milliseconds
	 * 
	 * @return string $content      : packet data
	 */
	function readPort ($count = 0, $timeout = 2)
	{
		global $texec;
		$start = microtime(true);

		if ($this->_dState !== SERIAL_DEVICE_OPENED) {
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}

		$content = "";
		do {
			$content .= fread($this->_dHandle, $count);
			$count = $count - strlen($content);
			if (DEBUG_PHP & 4) {
				printf("Getting data... %s\n", $content);
			}
			if ((microtime(true)-$start) > $timeout) {
				//trigger_error("Timeout!", E_USER_WARNING);
				//return false;
				$content = "Timeout!";
			}
		} while ($count > 0);
		if (DEBUG_PHP & 2) {
			printf("fread took %01.2fms to read %d bytes: %s\n", (microtime(true)-$start)*1000, strlen($content), $content);
		}

		$texec += (microtime(true)-$start)*1000;
		return $content;
	}

	/**
	 * Reads a packet of length $count (if specified) within $timeout sec or
	 * reads everything in the serial buffer, 8192 chars at a time,
	 * until the buffer is empty or the EOF is reached.
	 * - returns the data packet in $content
	 *
	 * @param int $count	   : number of characters to read
	 * @param float $timeout : read timeout in seconds
	 * 
   * @param float $texec (global) : $texec + execution time in milliseconds
	 * 
	 * @return string $content      : packet data
	 */
	function readPortMultiple ($count = 0, $timeout = 2)
	{
		global $texec;
		$start = microtime(true);

		if ($this->_dState !== SERIAL_DEVICE_OPENED) {
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}
		$content = "";
		if ($count > 0) {
			do {
				$n = $count;
				while ($n > 0) {
					$content .= fread($this->_dHandle, $n);
					$n = $n - strlen($content);
					if (DEBUG_PHP & 4) {
						printf("Getting data... %s\n", $content);
					}
					if ((microtime(true)-$start) > $timeout) {
						break 2;
					}
				}
			} while (1);
		}
		else { // $count == 0
			$i = 0;
			do {
				$content .= fread($this->_dHandle, 8192);
			} while (($i += 8192) === strlen($content));
		}
		if (DEBUG_PHP & 2) {
			printf("fread took %01.2fms to read %d bytes: %s\n", (microtime(true)-$start)*1000, strlen($content), $content);
		}

		$texec += (microtime(true)-$start)*1000;
		return $content;
	}

	/**
	 * Reads packets continuously for $timeout sec 
	 * reads 8192 chars at a time or until EOF occurs.
	 * - returns the data packet in $content
	 *
	 * @param float $timeout : read timeout in seconds
	 * 
   * @param float $texec (global) : $texec + execution time in milliseconds
	 * 
	 * @return string $content      : packet data
	 */
	function readPortTimed ($timeout = 10)
	{
		global $texec;
		$start = microtime(true);

		if ($this->_dState !== SERIAL_DEVICE_OPENED) {
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}
		$content = "";
		do {
			$content .= fread($this->_dHandle, 8192);
			if (DEBUG_PHP & 4)
			{
				printf("Getting data... %s\n", $content);
			}
		} while ((microtime(true)-$start) < $timeout);

		$texec += (microtime(true)-$start)*1000;
		return $content;
	}

	/**
	 * Reads a packet delimited by start and end delimiters
   * - packet format: $byteStart(1) $dataPacket(0-n) $byteEnd(1)
   * - times out if packet read is not completed within $timeout seconds
	 * - returns the content of packet, stripped of delimiters, in $dataPacket
   * - this function may discard data before and after delimiters
	 *
	 * @param char $byteStart : start character delimiter
	 * @param char $byteEnd   : end character delimiter
	 * @param float $timeout  : read timeout in seconds
	 * @param int $byteCount  : number of characters to read from serial port in each fread call
	 * 
   * @param float $texec (global) : $texec + execution time in milliseconds
	 * 
	 * @return string $packetIn     : packet data stripped of headers
	 */
	function readPortDelimited ($byteStart = '<', $byteEnd = '>', $timeout = 5, $byteCount = 1024)
	{
		global $texec;
		$start = microtime(true);

		if ($this->_dState !== SERIAL_DEVICE_OPENED) {
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}

		$buffer = "";
		$packetIn = "";
		$done = 0;
		do {
			$buffer .= fread($this->_dHandle, $byteCount);
			if (DEBUG_PHP & 4) {
				printf("Getting data... length = %d, contents = %s\n", strlen($buffer), $buffer);
			}

			for ($i = 0; $i < strlen($buffer); $i++) {
 				$byteIn = unpack("Cvalue", substr($buffer,$i,1));
				if (DEBUG_PHP & 8) {
					//What we are looking for in $buffer -
					//printf("byteStart, byteIn, byteEnd: %b %b %b\n", ord($byteStart), $byteIn["value"], ord($byteEnd));
					//printf("byteStart, byteIn, byteEnd: %X %X %X\n", ord($byteStart), $byteIn["value"], ord($byteEnd));
					printf("byteStart, byteIn, byteEnd: %d %d %d\n", ord($byteStart), $byteIn["value"], ord($byteEnd));
					//printf("byteStart, byteIn, byteEnd: %c %c %c\n", ord($byteStart), $byteIn["value"], ord($byteEnd));
				}
				if ($byteIn["value"] == ord($byteStart)) {
					$packetIn = "";
				}
				else if ($byteIn["value"] == ord($byteEnd)) {
					$done = 1;
					break 1;
				}
				else {
					$packetIn .= chr($byteIn["value"]);
				}
			}

			if ((microtime(true)-$start) > $timeout) {
				//trigger_error("Timeout!", E_USER_WARNING);
				//return false;
				$packetIn = "---Timeout!";
				$done = 1;
			}
		} while ($done == 0);
		if (DEBUG_PHP & 2) {
			printf("fread took %01.2fms to read %d bytes: %s\n", (microtime(true)-$start)*1000, strlen($packetIn), $packetIn);
		}
		$texec += (microtime(true)-$start)*1000;
		return $packetIn;
	}


	/**
	 * Reads a packet delimited by start and end delimiters
   * - packet format: $byteStart(1) $devId(1) $count(1) $cmd(1) $dataPacket(0-n) $byteEnd(1)
   * - reads $count number of characters from the port
   * - times out if $count characters are not read within $timeout seconds
	 * - returns the content of packet, stripped of headers, in $dataPacket
   * 
	 * @param char $byteStart : start character delimiter
	 * @param char $byteEnd   : end character delimiter
	 * @param float $timeout  : read timeout in seconds
	 * 
   * @param float $texec (global) : $texec + execution time in milliseconds
	 * 
	 * @return string $dataPacket   : packet data stripped of headers
	 */
	function readPortDelimited_Calla ($byteStart = '<', $byteEnd = '>', $timeout = 5)
	{
		global $texec;
		
		$start = microtime(true);

		if ($this->_dState !== SERIAL_DEVICE_OPENED) {
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}

		$delim = "";
		$devid = "";
		$count = "";
		$cmd   = "";
		do {
			$delim = fread($this->_dHandle, 1);
		} while (strlen($delim) == 0);
		do {
			$devid = fread($this->_dHandle, 1);
		} while (strlen($devid) == 0);
		do {
			$count = fread($this->_dHandle, 1);
		} while (strlen($count) == 0);
		do {
			$cmd = fread($this->_dHandle, 1);
		} while (strlen($cmd) == 0);

		if (DEBUG_PHP & 8) {
			printf("Delimiter: %d\n", ord($delim));
			printf("Device ID: %d\n", ord($devid));
			printf("ByteCount: %d\n", ord($count));
			printf("Command  : %c\n", ord($cmd));
		}
		if ($delim !== $byteStart) {
			trigger_error("Wrong packet format. Packet does not start with delimiter.", E_USER_WARNING);
			return false;
		}

		$readCount = ord($count)-5;
		$buffer = "";
		while (strlen($buffer) !== $readCount) {
			$buffer .= fread($this->_dHandle, $readCount-strlen($buffer));
			if ((microtime(true)-$start) > $timeout) {
				trigger_error("Timeout!", E_USER_WARNING);
				return false;
			}
		}

		$delim = "";
		do {
			$delim = fread($this->_dHandle, 1);
		} while (strlen($delim) == 0);
		if ($delim !== $byteEnd)
		{
			trigger_error("Wrong packet format. Packet does not end with delimiter.", E_USER_WARNING);
			return false;
		}

		if (DEBUG_PHP & 2) {
			printf("fread took %01.2fms to read %d bytes: %s\n", (microtime(true)-$start)*1000, strlen($buffer), $buffer);
		}

		$dataPacket = "";
		for ($i = 0; $i < strlen($buffer); $i++) {
			$byteIn = unpack("Cvalue", substr($buffer,$i,1));
			$dataPacket .= chr($byteIn["value"]);
		}

		$texec += (microtime(true)-$start)*1000;
		return $dataPacket;
	}


	/**
	 * Flushes the output buffer
	 *
	 * @return bool
	 */
	function flush ()
	{
		if (!$this->_ckOpened()) return false;

		if (fwrite($this->_dHandle, $this->_buffer) !== false)
		{
			$this->_buffer = "";
			return true;
		}
		else
		{
			$this->_buffer = "";
			trigger_error("Error while sending message", E_USER_WARNING);
			return false;
		}
	}

	//
	// I/O SECTION -- {STOP}
	//

	//
	// INTERNAL TOOLKIT -- {START}
	//

	function _ckOpened()
	{
		if ($this->_dState !== SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened", E_USER_WARNING);
			return false;
		}

		return true;
	}

	function _ckClosed()
	{
		if ($this->_dState !== SERIAL_DEVICE_CLOSED)
		{
			trigger_error("Device must be closed", E_USER_WARNING);
			return false;
		}

		return true;
	}

	function _exec($cmd, &$out = null)
	{
		$desc = array(
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);

		$proc = proc_open($cmd, $desc, $pipes);

		$ret = stream_get_contents($pipes[1]);
		$err = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		$retVal = proc_close($proc);

		if (func_num_args() == 2) $out = array($ret, $err);
		return $retVal;
	}

	//
	// INTERNAL TOOLKIT -- {STOP}
	//
}
?>

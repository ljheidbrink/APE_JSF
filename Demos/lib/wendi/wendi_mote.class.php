<?php

//set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/local/www/ape-jsf/Demos/lib');
//include("php_serial/php_serial_wendi.linux.class.php");

define("DEBUG_WENDI", 1);
// 1 - Display device initialization / startup / shutdown status

/**
 * Wendi mote control class
 *
 * @author Tiffany Chua <tchua at uci dot edu>
 */

class wendiMote {

	var $_deviceName 	= '/dev/ttyUSB0';
	var $_serial 			= null;

	/*
	// Constructor
	// - Initialize and open serial port
	*/
	function wendiMote() {
		$this->_serial = new phpSerial;
		if ($this->_serial->deviceSet($this->_deviceName)) {
			$this->_serial->confBaudRate(57600);
			$this->_serial->confParity("none");
			$this->_serial->confCharacterLength(8);
			$this->_serial->confStopBits(1);
			$this->_serial->confFlowControl("none");
			if (DEBUG_WENDI & 1)
				echo 'Device ' . $this->_deviceName . ' is set.<br />';
		}
		else {
//		trigger_error('Cannot set device ' . $this->_deviceName);
			if (DEBUG_WENDI & 1)
				echo 'Cannot set device ' . $this->_deviceName . '<br />';
		}
	}

	function startup() {
	  if ($this->_serial->deviceOpen("r+b")) {
			if (DEBUG_WENDI & 1)
				echo 'Device ' . $this->_deviceName . ' is open.<br />';
			return true;
		}
		else {
			if (DEBUG_WENDI & 1)
				echo 'Cannot open device ' . $this->_deviceName . '<br />';
//			trigger_error('Cannot open serial port ' . $this->_deviceName);
			return false;
		}
	}

	function shutdown() {
		$this->_serial->deviceClose();
		if (DEBUG_WENDI & 1)
			echo 'Device ' . $this->_deviceName . ' is closed.<br />';
	}

	function pingDevice() {
    $message = "<ping>";
    $this->_serial->sendMessage($message);
    $buffer = $this->_serial->readPortDelimited_Calla('<','>');

    if ($buffer !== false) {
      return true;
    }
    else {
      return false;
    }
	}

	function getADCvalue($channel) {
    $message = "<a{$channel}>";
    $this->_serial->sendMessage($message, 0);
    $buffer = $this->_serial->readPortDelimited_Calla('<','>');

    if (strlen($buffer) > 0) {
      $adc = unpack("nvalue", $buffer);
      return $adc["value"];
    }

	}

	function getFileContents() {
    $message = "<f{$filename}>";
    $this->_serial->sendMessage($message, 0);

    $buffer1 = $this->_serial->readPortDelimited_Calla('<','>');
    if (strlen($buffer1) > 0) {
      $arr_len = unpack("Nvalue", $buffer1);
      $len = $arr_len["value"];
//    printf("%d more bytes to read\n", $len);
    }

    $buffer_out = "";
    while ($len > 0) {
      $buffer2 = $this->_serial->readPortDelimited_Calla('<','>');
      if (strlen($buffer2) > 0) {
        $buffer_out .= $buffer2;
        $len = $len - strlen($buffer2);
//      printf("read %d, %d more bytes to read\n", strlen($buffer2), $len);
      }
    }

//    $fp = fopen($filename,'wb');
//    fwrite($fp,$buffer_out);
//    fclose($fp);

    return $buffer_out;
	}

}

?>


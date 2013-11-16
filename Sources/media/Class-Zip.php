<?php
/**
 * This class allows creating zip (archive) files on the fly.
 * From http://olederer.users.phpclasses.org/package/2322-PHP-Create-ZIP-file-archives-and-serve-for-download.html
 *
 * @author Rochak Chauhan (www.rochakchauhan.com), original class, Shitiz Garg and R.-G. Deberdt (modifications)
 * @see Distributed under "General Public License"
 */

class aeva_zipper
{
	var $compressedData = array();
	var $centralDirectory = array(); // central directory
	var $endOfCentralDirectory = "\x50\x4b\x05\x06\x00\x00\x00\x00"; // end of central directory record
	var $oldOffset = 0;

	/**
	 * Function to create the directory where the file(s) will be unzipped
	 *
	 * @param $directoryName string
	 *
	 */

	function addDirectory($directoryName)
	{
		$directoryName = str_replace("\\", '/', $directoryName);

		$feedArrayRow = "\x50\x4b\x03\x04\x0a\x00\x00\x00\x00\x00\x00\x00\x00\x00";
		$feedArrayRow .= pack('V',0);
		$feedArrayRow .= pack('V',0);
		$feedArrayRow .= pack('V',0);
		$feedArrayRow .= pack('v', strlen($directoryName));
		$feedArrayRow .= pack('v', 0);
		$feedArrayRow .= $directoryName;

		$feedArrayRow .= pack('V',0);
		$feedArrayRow .= pack('V',0);
		$feedArrayRow .= pack('V',0);

		$this -> compressedData[] = $feedArrayRow;

		$newOffset = strlen(implode('', $this->compressedData));

		$addCentralRecord = "\x50\x4b\x01\x02\x00\x00\x0a\x00\x00\x00\x00\x00\x00\x00\x00\x00";
		$addCentralRecord .= pack('V',0);
		$addCentralRecord .= pack('V',0);
		$addCentralRecord .= pack('V',0);
		$addCentralRecord .= pack('v', strlen($directoryName));
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('V', 16);
		$addCentralRecord .= pack('V', $this->oldOffset);
		$this->oldOffset = $newOffset;

		$addCentralRecord .= $directoryName;

		$this->centralDirectory[] = $addCentralRecord;
	}

	/**
	 * Function to add file(s) to the specified directory in the archive
	 *
	 * @param $directoryName string
	 *
	 */

	function addFile($data, $directoryName)
	{
		$directoryName = str_replace("\\", '/', $directoryName);

		$feedArrayRow = "\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00";

		$uncompressedLength = strlen($data);
		$compression = crc32($data);
		$gzCompressedData = substr(gzcompress($data), 2, -4);
		$compressedLength = strlen($gzCompressedData);
		$feedArrayRow .= pack('V', $compression);
		$feedArrayRow .= pack('V', $compressedLength);
		$feedArrayRow .= pack('V', $uncompressedLength);
		$feedArrayRow .= pack('v', strlen($directoryName));
		$feedArrayRow .= pack('v', 0);
		$feedArrayRow .= $directoryName;

		$feedArrayRow .= $gzCompressedData;

		$feedArrayRow .= pack('V',$compression);
		$feedArrayRow .= pack('V',$compressedLength);
		$feedArrayRow .= pack('V',$uncompressedLength);

		$this -> compressedData[] = $feedArrayRow;
		$newOffset = strlen(implode('', $this->compressedData));
		$addCentralRecord = "\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00";
		$addCentralRecord .= pack('V', $compression);
		$addCentralRecord .= pack('V', $compressedLength);
		$addCentralRecord .= pack('V', $uncompressedLength);
		$addCentralRecord .= pack('v', strlen($directoryName));
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('v', 0);
		$addCentralRecord .= pack('V', 32);

		$addCentralRecord .= pack('V', $this->oldOffset);

		$addCentralRecord .= $directoryName;

		$this->centralDirectory[] = $addCentralRecord;
	}

	// All three functions below are written by Shitiz Garg (Dragooon).
	function addFileDataToCache($data, $filename, $cache)
	{
		if (empty($this->oldOffset) && file_exists($cache . '_other'))
			list (, $this->oldOffset) = unserialize(@file_get_contents($cache . '_other'));

		$this->addFile($data, $filename);

		$this->oldOffset += strlen($this->compressedData[count($this->compressedData) - 1]);

		$f = @fopen($cache . '_data', file_exists($cache . '_data') ? 'a' : 'w');
		@fwrite($f, $this->compressedData[count($this->compressedData) - 1]);
		@fclose($f);

		@chmod($cache . '_data', 0777);

		$this->compressedData = array();
	}

	// Save it
	function saveFile($cache)
	{
		if (file_exists($cache . '_other'))
		{
			list ($centralDirectory) = unserialize(@file_get_contents($cache . '_other'));
			$this->centralDirectory = array_merge($centralDirectory, $this->centralDirectory);
		}

		@file_put_contents($cache . '_other', serialize(array($this->centralDirectory, $this->oldOffset)));
		@chmod($cache . '_other', 0777);
	}

	// Save as a proper zip file
	function saveAsZip($cache)
	{
		if (!($f = fopen($cache . '_data', 'a+')))
			return false;

		$strlen = 0;

		$f2 = fopen($cache . '_data', 'r');
		while (!feof($f2))
			$strlen += strlen(@fread($f2, 8192));
		fclose($f2);

		if (file_exists($cache . '_other'))
		{
			list ($centralDirectory) = unserialize(@file_get_contents($cache . '_other'));
			$this->centralDirectory = array_merge($centralDirectory, $this->centralDirectory);
		}

		// Finally make it...
		fwrite($f, implode('', $this->centralDirectory));
		fwrite($f, $this->endOfCentralDirectory);
		fwrite($f, pack('v', sizeof($this->centralDirectory)));
		fwrite($f, pack('v', sizeof($this->centralDirectory)));
		fwrite($f, pack('V', strlen(implode('', $this->centralDirectory))));
		fwrite($f, pack('V', $strlen));
		fwrite($f, "\x00\x00");
		fclose($f);
		@unlink($cache . '_other');
	}
}

<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio-video.matriska.php                             //
// module for analyzing Matroska containers                    //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

// from: http://www.matroska.org/technical/specs/index.html
define('EBML_ID_CHAPTERS',                  0x0043A770);
define('EBML_ID_SEEKHEAD',                  0x014D9B74);
define('EBML_ID_TAGS',                      0x0254C367);
define('EBML_ID_INFO',                      0x0549A966);
define('EBML_ID_TRACKS',                    0x0654AE6B);
define('EBML_ID_SEGMENT',                   0x08538067);
define('EBML_ID_ATTACHMENTS',               0x0941A469);
define('EBML_ID_EBML',                      0x0A45DFA3);
define('EBML_ID_CUES',                      0x0C53BB6B);
define('EBML_ID_CLUSTER',                   0x0F43B675);
define('EBML_ID_LANGUAGE',                    0x02B59C);
define('EBML_ID_TRACKTIMECODESCALE',          0x03314F);
define('EBML_ID_DEFAULTDURATION',             0x03E383);
define('EBML_ID_CODECNAME',                   0x058688);
define('EBML_ID_CODECDOWNLOADURL',            0x06B240);
define('EBML_ID_TIMECODESCALE',               0x0AD7B1);
define('EBML_ID_COLOURSPACE',                 0x0EB524);
define('EBML_ID_GAMMAVALUE',                  0x0FB523);
define('EBML_ID_CODECSETTINGS',               0x1A9697);
define('EBML_ID_CODECINFOURL',                0x1B4040);
define('EBML_ID_PREVFILENAME',                0x1C83AB);
define('EBML_ID_PREVUID',                     0x1CB923);
define('EBML_ID_NEXTFILENAME',                0x1E83BB);
define('EBML_ID_NEXTUID',                     0x1EB923);
define('EBML_ID_CONTENTCOMPALGO',               0x0254);
define('EBML_ID_CONTENTCOMPSETTINGS',           0x0255);
define('EBML_ID_DOCTYPE',                       0x0282);
define('EBML_ID_DOCTYPEREADVERSION',            0x0285);
define('EBML_ID_EBMLVERSION',                   0x0286);
define('EBML_ID_DOCTYPEVERSION',                0x0287);
define('EBML_ID_EBMLMAXIDLENGTH',               0x02F2);
define('EBML_ID_EBMLMAXSIZELENGTH',             0x02F3);
define('EBML_ID_EBMLREADVERSION',               0x02F7);
define('EBML_ID_CHAPLANGUAGE',                  0x037C);
define('EBML_ID_CHAPCOUNTRY',                   0x037E);
define('EBML_ID_SEGMENTFAMILY',                 0x0444);
define('EBML_ID_DATEUTC',                       0x0461);
define('EBML_ID_TAGLANGUAGE',                   0x047A);
define('EBML_ID_TAGDEFAULT',                    0x0484);
define('EBML_ID_TAGBINARY',                     0x0485);
define('EBML_ID_TAGSTRING',                     0x0487);
define('EBML_ID_DURATION',                      0x0489);
define('EBML_ID_CHAPPROCESSPRIVATE',            0x050D);
define('EBML_ID_CHAPTERFLAGENABLED',            0x0598);
define('EBML_ID_TAGNAME',                       0x05A3);
define('EBML_ID_EDITIONENTRY',                  0x05B9);
define('EBML_ID_EDITIONUID',                    0x05BC);
define('EBML_ID_EDITIONFLAGHIDDEN',             0x05BD);
define('EBML_ID_EDITIONFLAGDEFAULT',            0x05DB);
define('EBML_ID_EDITIONFLAGORDERED',            0x05DD);
define('EBML_ID_FILEDATA',                      0x065C);
define('EBML_ID_FILEMIMETYPE',                  0x0660);
define('EBML_ID_FILENAME',                      0x066E);
define('EBML_ID_FILEREFERRAL',                  0x0675);
define('EBML_ID_FILEDESCRIPTION',               0x067E);
define('EBML_ID_FILEUID',                       0x06AE);
define('EBML_ID_CONTENTENCALGO',                0x07E1);
define('EBML_ID_CONTENTENCKEYID',               0x07E2);
define('EBML_ID_CONTENTSIGNATURE',              0x07E3);
define('EBML_ID_CONTENTSIGKEYID',               0x07E4);
define('EBML_ID_CONTENTSIGALGO',                0x07E5);
define('EBML_ID_CONTENTSIGHASHALGO',            0x07E6);
define('EBML_ID_MUXINGAPP',                     0x0D80);
define('EBML_ID_SEEK',                          0x0DBB);
define('EBML_ID_CONTENTENCODINGORDER',          0x1031);
define('EBML_ID_CONTENTENCODINGSCOPE',          0x1032);
define('EBML_ID_CONTENTENCODINGTYPE',           0x1033);
define('EBML_ID_CONTENTCOMPRESSION',            0x1034);
define('EBML_ID_CONTENTENCRYPTION',             0x1035);
define('EBML_ID_CUEREFNUMBER',                  0x135F);
define('EBML_ID_NAME',                          0x136E);
define('EBML_ID_CUEBLOCKNUMBER',                0x1378);
define('EBML_ID_TRACKOFFSET',                   0x137F);
define('EBML_ID_SEEKID',                        0x13AB);
define('EBML_ID_SEEKPOSITION',                  0x13AC);
define('EBML_ID_STEREOMODE',                    0x13B8);
define('EBML_ID_PIXELCROPBOTTOM',               0x14AA);
define('EBML_ID_DISPLAYWIDTH',                  0x14B0);
define('EBML_ID_DISPLAYUNIT',                   0x14B2);
define('EBML_ID_ASPECTRATIOTYPE',               0x14B3);
define('EBML_ID_DISPLAYHEIGHT',                 0x14BA);
define('EBML_ID_PIXELCROPTOP',                  0x14BB);
define('EBML_ID_PIXELCROPLEFT',                 0x14CC);
define('EBML_ID_PIXELCROPRIGHT',                0x14DD);
define('EBML_ID_FLAGFORCED',                    0x15AA);
define('EBML_ID_MAXBLOCKADDITIONID',            0x15EE);
define('EBML_ID_WRITINGAPP',                    0x1741);
define('EBML_ID_CLUSTERSILENTTRACKS',           0x1854);
define('EBML_ID_CLUSTERSILENTTRACKNUMBER',      0x18D7);
define('EBML_ID_ATTACHEDFILE',                  0x21A7);
define('EBML_ID_CONTENTENCODING',               0x2240);
define('EBML_ID_BITDEPTH',                      0x2264);
define('EBML_ID_CODECPRIVATE',                  0x23A2);
define('EBML_ID_TARGETS',                       0x23C0);
define('EBML_ID_CHAPTERPHYSICALEQUIV',          0x23C3);
define('EBML_ID_TAGCHAPTERUID',                 0x23C4);
define('EBML_ID_TAGTRACKUID',                   0x23C5);
define('EBML_ID_ATTACHMENTUID',                 0x23C6);
define('EBML_ID_TAGEDITIONUID',                 0x23C9);
define('EBML_ID_TARGETTYPE',                    0x23CA);
define('EBML_ID_TRACKTRANSLATE',                0x2624);
define('EBML_ID_TRACKTRANSLATETRACKID',         0x26A5);
define('EBML_ID_TRACKTRANSLATECODEC',           0x26BF);
define('EBML_ID_TRACKTRANSLATEEDITIONUID',      0x26FC);
define('EBML_ID_SIMPLETAG',                     0x27C8);
define('EBML_ID_TARGETTYPEVALUE',               0x28CA);
define('EBML_ID_CHAPPROCESSCOMMAND',            0x2911);
define('EBML_ID_CHAPPROCESSTIME',               0x2922);
define('EBML_ID_CHAPTERTRANSLATE',              0x2924);
define('EBML_ID_CHAPPROCESSDATA',               0x2933);
define('EBML_ID_CHAPPROCESS',                   0x2944);
define('EBML_ID_CHAPPROCESSCODECID',            0x2955);
define('EBML_ID_CHAPTERTRANSLATEID',            0x29A5);
define('EBML_ID_CHAPTERTRANSLATECODEC',         0x29BF);
define('EBML_ID_CHAPTERTRANSLATEEDITIONUID',    0x29FC);
define('EBML_ID_CONTENTENCODINGS',              0x2D80);
define('EBML_ID_MINCACHE',                      0x2DE7);
define('EBML_ID_MAXCACHE',                      0x2DF8);
define('EBML_ID_CHAPTERSEGMENTUID',             0x2E67);
define('EBML_ID_CHAPTERSEGMENTEDITIONUID',      0x2EBC);
define('EBML_ID_TRACKOVERLAY',                  0x2FAB);
define('EBML_ID_TAG',                           0x3373);
define('EBML_ID_SEGMENTFILENAME',               0x3384);
define('EBML_ID_SEGMENTUID',                    0x33A4);
define('EBML_ID_CHAPTERUID',                    0x33C4);
define('EBML_ID_TRACKUID',                      0x33C5);
define('EBML_ID_ATTACHMENTLINK',                0x3446);
define('EBML_ID_CLUSTERBLOCKADDITIONS',         0x35A1);
define('EBML_ID_CHANNELPOSITIONS',              0x347B);
define('EBML_ID_OUTPUTSAMPLINGFREQUENCY',       0x38B5);
define('EBML_ID_TITLE',                         0x3BA9);
define('EBML_ID_CHAPTERDISPLAY',                  0x00);
define('EBML_ID_TRACKTYPE',                       0x03);
define('EBML_ID_CHAPSTRING',                      0x05);
define('EBML_ID_CODECID',                         0x06);
define('EBML_ID_FLAGDEFAULT',                     0x08);
define('EBML_ID_CHAPTERTRACKNUMBER',              0x09);
define('EBML_ID_CLUSTERSLICES',                   0x0E);
define('EBML_ID_CHAPTERTRACK',                    0x0F);
define('EBML_ID_CHAPTERTIMESTART',                0x11);
define('EBML_ID_CHAPTERTIMEEND',                  0x12);
define('EBML_ID_CUEREFTIME',                      0x16);
define('EBML_ID_CUEREFCLUSTER',                   0x17);
define('EBML_ID_CHAPTERFLAGHIDDEN',               0x18);
define('EBML_ID_FLAGINTERLACED',                  0x1A);
define('EBML_ID_CLUSTERBLOCKDURATION',            0x1B);
define('EBML_ID_FLAGLACING',                      0x1C);
define('EBML_ID_CHANNELS',                        0x1F);
define('EBML_ID_CLUSTERBLOCKGROUP',               0x20);
define('EBML_ID_CLUSTERBLOCK',                    0x21);
define('EBML_ID_CLUSTERBLOCKVIRTUAL',             0x22);
define('EBML_ID_CLUSTERSIMPLEBLOCK',              0x23);
define('EBML_ID_CLUSTERCODECSTATE',               0x24);
define('EBML_ID_CLUSTERBLOCKADDITIONAL',          0x25);
define('EBML_ID_CLUSTERBLOCKMORE',                0x26);
define('EBML_ID_CLUSTERPOSITION',                 0x27);
define('EBML_ID_CODECDECODEALL',                  0x2A);
define('EBML_ID_CLUSTERPREVSIZE',                 0x2B);
define('EBML_ID_TRACKENTRY',                      0x2E);
define('EBML_ID_CLUSTERENCRYPTEDBLOCK',           0x2F);
define('EBML_ID_PIXELWIDTH',                      0x30);
define('EBML_ID_CUETIME',                         0x33);
define('EBML_ID_SAMPLINGFREQUENCY',               0x35);
define('EBML_ID_CHAPTERATOM',                     0x36);
define('EBML_ID_CUETRACKPOSITIONS',               0x37);
define('EBML_ID_FLAGENABLED',                     0x39);
define('EBML_ID_PIXELHEIGHT',                     0x3A);
define('EBML_ID_CUEPOINT',                        0x3B);
define('EBML_ID_CRC32',                           0x3F);
define('EBML_ID_CLUSTERBLOCKADDITIONID',          0x4B);
define('EBML_ID_CLUSTERLACENUMBER',               0x4C);
define('EBML_ID_CLUSTERFRAMENUMBER',              0x4D);
define('EBML_ID_CLUSTERDELAY',                    0x4E);
define('EBML_ID_CLUSTERDURATION',                 0x4F);
define('EBML_ID_TRACKNUMBER',                     0x57);
define('EBML_ID_CUEREFERENCE',                    0x5B);
define('EBML_ID_VIDEO',                           0x60);
define('EBML_ID_AUDIO',                           0x61);
define('EBML_ID_CLUSTERTIMESLICE',                0x68);
define('EBML_ID_CUECODECSTATE',                   0x6A);
define('EBML_ID_CUEREFCODECSTATE',                0x6B);
define('EBML_ID_VOID',                            0x6C);
define('EBML_ID_CLUSTERTIMECODE',                 0x67);
define('EBML_ID_CLUSTERBLOCKADDID',               0x6E);
define('EBML_ID_CUECLUSTERPOSITION',              0x71);
define('EBML_ID_CUETRACK',                        0x77);
define('EBML_ID_CLUSTERREFERENCEPRIORITY',        0x7A);
define('EBML_ID_CLUSTERREFERENCEBLOCK',           0x7B);
define('EBML_ID_CLUSTERREFERENCEVIRTUAL',         0x7D);

class getid3_matroska extends getid3_handler
{
	var $read_buffer_size   = 32768;
	var $hide_clusters      = true;
	var $warnings           = array();
	var $inline_attachments = true;

	function Analyze() {
		$info =& $this->getid3->info;

		// http://www.matroska.org/technical/specs/index.html#EBMLBasics
		$offset = $info['avdataoffset'];
		$EBMLdata = '';
		$EBMLdata_offset = $offset;

		if (!getid3_lib::intValueSupported($info['avdataend'])) {
			$this->getid3->warning('This version of getID3() ['.$this->getid3->version().'] may or may not correctly handle Matroska files larger than '.round(PHP_INT_MAX / 1073741824).'GB');
		}

		while ($offset < $info['avdataend']) {
			if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
				$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
				break;
			}

			$top_element_offset    = $offset;
			$top_element_id        = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
			$top_element_length    = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
			if ($top_element_length === false) {
				$this->getid3->warning('invalid chunk length at '.$top_element_offset);
				$offset = PHP_INT_MAX + 1;
				break;
			}
			$top_element_endoffset = $offset + $top_element_length;
			switch ($top_element_id) {
				case EBML_ID_EBML:
					$info['fileformat'] = 'matroska';
					$info['matroska']['header']['offset'] = $top_element_offset;
					$info['matroska']['header']['length'] = $top_element_length;

					while ($offset < $top_element_endoffset) {
						if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
							$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
							break;
						}
						$element_data = array();
						$element_data_offset     = $offset;
						$element_data['id']      = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
						$element_data['id_name'] = $this->EBMLidName($element_data['id']);
						$element_data['length']     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
						$end_offset              = $offset + $element_data['length'];

						switch ($element_data['id']) {
							case EBML_ID_VOID:
								break;
							case EBML_ID_EBMLVERSION:
							case EBML_ID_EBMLREADVERSION:
							case EBML_ID_EBMLMAXIDLENGTH:
							case EBML_ID_EBMLMAXSIZELENGTH:
							case EBML_ID_DOCTYPEVERSION:
							case EBML_ID_DOCTYPEREADVERSION:
								$element_data['data'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $element_data['length']));
								break;
							case EBML_ID_DOCTYPE:
								$element_data['data'] =                      trim(substr($EBMLdata, $offset - $EBMLdata_offset, $element_data['length']), "\x00");
								break;
							case EBML_ID_CRC32:

								unset($element_data);
								break;
							default:
								$this->getid3->warning('Unhandled track.video element ['.basename(__FILE__).':'.__LINE__.'] ('.$element_data['id'].'::'.$element_data['id_name'].') at '.$element_data_offset);
								break;
						}
						$offset = $end_offset;
						if (!empty($element_data)) {
							$info['matroska']['header']['elements'][] = $element_data;
						}
					}
					break;

				case EBML_ID_SEGMENT:
					$info['matroska']['segment'][0]['offset'] = $top_element_offset;
					$info['matroska']['segment'][0]['length'] = $top_element_length;

					$segment_key = -1;
					while ($offset < $info['avdataend']) {
						if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
							$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
							break;
						}

						$element_data = array();
						$element_data['offset']  = $offset;
						$element_data['id']      = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
						$element_data['id_name'] = $this->EBMLidName($element_data['id']);
						$element_data['length']  = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
						if ($element_data['length'] === false) {
							$this->getid3->warning('invalid chunk length at '.$element_data['offset']);

							$offset = $info['avdataend'];
							break;
						}
						$element_end             = $offset + $element_data['length'];
						switch ($element_data['id']) {
							case false:
								$this->getid3->warning('invalid ID at '.$element_data['offset']);
								$offset = $element_end;
								continue 3;
							default:
								$info['matroska']['segments'][] = $element_data;
								break;
						}
						$segment_key++;

						switch ($element_data['id']) {
							case EBML_ID_SEEKHEAD:
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$seek_entry = array();
									$seek_entry['offset']  = $offset;
									$seek_entry['id']      = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$seek_entry['id_name'] = $this->EBMLidName($seek_entry['id']);
									$seek_entry['length']  = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$seek_end_offset       = $offset + $seek_entry['length'];
									switch ($seek_entry['id']) {
										case EBML_ID_SEEK:
											while ($offset < $seek_end_offset) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$value  =             substr($EBMLdata, $offset - $EBMLdata_offset, $length);
												$offset += $length;
												switch ($id) {
													case EBML_ID_SEEKID:
														$dummy = 0;
														$seek_entry['target_id']   = $this->readEBMLint($value, $dummy);
														$seek_entry['target_name'] = $this->EBMLidName($seek_entry['target_id']);
														break;
													case EBML_ID_SEEKPOSITION:
														$seek_entry['target_offset'] = $element_data['offset'] + getid3_lib::BigEndian2Int($value);
														break;
													case EBML_ID_CRC32:
														unset($seek_entry);
														break;
													default:
														$info['error'][] = 'Unhandled segment ['.basename(__FILE__).':'.__LINE__.'] ('.$id.') at '.$offset;
														break;
												}
											}
											if (!empty($seek_entry)) {
												$info['matroska']['seek'][] = $seek_entry;
											}

											break;
										case EBML_ID_CRC32:

											break;
										default:
											$this->getid3->warning('Unhandled seekhead element ['.basename(__FILE__).':'.__LINE__.'] ('.$seek_entry['id'].'::'.$seek_entry['id_name'].') at '.$offset);
											break;
									}
									$offset = $seek_end_offset;
								}
								break;

							case EBML_ID_TRACKS:
								$info['matroska']['tracks'] = $element_data;
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$track_entry = array();
									$track_entry['offset']  = $offset;
									$track_entry['id']      = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$track_entry['id_name'] = $this->EBMLidName($track_entry['id']);
									$track_entry['length']  = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$track_entry_endoffset  = $offset + $track_entry['length'];
									switch ($track_entry['id']) {
										case EBML_ID_TRACKENTRY:
											while ($offset < $track_entry_endoffset) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$subelement_offset = $offset;
												$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$subelement_idname = $this->EBMLidName($subelement_id);
												$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$subelement_end    = $offset + $subelement_length;
												switch ($subelement_id) {
													case EBML_ID_TRACKNUMBER:
													case EBML_ID_TRACKUID:
													case EBML_ID_TRACKTYPE:
													case EBML_ID_MINCACHE:
													case EBML_ID_MAXCACHE:
													case EBML_ID_MAXBLOCKADDITIONID:
													case EBML_ID_DEFAULTDURATION:
														$track_entry[$subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
														break;

													case EBML_ID_TRACKTIMECODESCALE:
														$track_entry[$subelement_idname] =      getid3_lib::BigEndian2Float(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
														break;

													case EBML_ID_CODECID:
													case EBML_ID_LANGUAGE:
													case EBML_ID_NAME:
													case EBML_ID_CODECNAME:
													case EBML_ID_CODECPRIVATE:
														$track_entry[$subelement_idname] =                             trim(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length), "\x00");
														break;

													case EBML_ID_FLAGENABLED:
													case EBML_ID_FLAGDEFAULT:
													case EBML_ID_FLAGFORCED:
													case EBML_ID_FLAGLACING:
													case EBML_ID_CODECDECODEALL:
														$track_entry[$subelement_idname] = (bool) getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
														break;

													case EBML_ID_VIDEO:
														while ($offset < $subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_subelement_offset = $offset;
															$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
															$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_end    = $offset + $sub_subelement_length;
															switch ($sub_subelement_id) {
																case EBML_ID_PIXELWIDTH:
																case EBML_ID_PIXELHEIGHT:
																case EBML_ID_STEREOMODE:
																case EBML_ID_PIXELCROPBOTTOM:
																case EBML_ID_PIXELCROPTOP:
																case EBML_ID_PIXELCROPLEFT:
																case EBML_ID_PIXELCROPRIGHT:
																case EBML_ID_DISPLAYWIDTH:
																case EBML_ID_DISPLAYHEIGHT:
																case EBML_ID_DISPLAYUNIT:
																case EBML_ID_ASPECTRATIOTYPE:
																	$track_entry[$sub_subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
																	break;
																case EBML_ID_FLAGINTERLACED:
																	$track_entry[$sub_subelement_idname] = (bool) getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
																	break;
																case EBML_ID_GAMMAVALUE:
																	$track_entry[$sub_subelement_idname] =      getid3_lib::BigEndian2Float(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
																	break;
																case EBML_ID_COLOURSPACE:
																	$track_entry[$sub_subelement_idname] =                             trim(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length), "\x00");
																	break;
																default:
																	$this->getid3->warning('Unhandled track.video element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
																	break;
															}
															$offset = $sub_subelement_end;
														}

														if (isset($track_entry[$this->EBMLidName(EBML_ID_CODECID)]) && ($track_entry[$this->EBMLidName(EBML_ID_CODECID)] == 'V_MS/VFW/FOURCC') && isset($track_entry[$this->EBMLidName(EBML_ID_CODECPRIVATE)])) {
															if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio-video.riff.php', __FILE__, false)) {
																$track_entry['codec_private_parsed'] = getid3_riff::ParseBITMAPINFOHEADER($track_entry[$this->EBMLidName(EBML_ID_CODECPRIVATE)]);
															} else {
																$this->getid3->warning('Unable to parse codec private data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio-video.riff.php"');
															}
														}
														break;

													case EBML_ID_AUDIO:
														while ($offset < $subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_subelement_offset = $offset;
															$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
															$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_end    = $offset + $sub_subelement_length;
															switch ($sub_subelement_id) {
																case EBML_ID_CHANNELS:
																case EBML_ID_BITDEPTH:
																	$track_entry[$sub_subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
																	break;
																case EBML_ID_SAMPLINGFREQUENCY:
																case EBML_ID_OUTPUTSAMPLINGFREQUENCY:
																	$track_entry[$sub_subelement_idname] =      getid3_lib::BigEndian2Float(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
																	break;
																case EBML_ID_CHANNELPOSITIONS:
																	$track_entry[$sub_subelement_idname] =                             trim(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length), "\x00");
																	break;
																default:
																	$this->getid3->warning('Unhandled track.audio element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
																	break;
															}
															$offset = $sub_subelement_end;
														}
														break;

													case EBML_ID_CONTENTENCODINGS:
														while ($offset < $subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_subelement_offset = $offset;
															$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
															$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_subelement_end    = $offset + $sub_subelement_length;
															switch ($sub_subelement_id) {
																case EBML_ID_CONTENTENCODING:
																	while ($offset < $sub_subelement_end) {
																		if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																			$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																			break;
																		}
																		$sub_sub_subelement_offset = $offset;
																		$sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
																		$sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_subelement_end    = $offset + $sub_sub_subelement_length;
																		switch ($sub_sub_subelement_id) {
																			case EBML_ID_CONTENTENCODINGORDER:
																			case EBML_ID_CONTENTENCODINGSCOPE:
																			case EBML_ID_CONTENTENCODINGTYPE:
																				$track_entry[$sub_subelement_idname][$sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																				break;
																			case EBML_ID_CONTENTCOMPRESSION:
																				while ($offset < $sub_sub_subelement_end) {
																					if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																						$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																						break;
																					}
																					$sub_sub_sub_subelement_offset = $offset;
																					$sub_sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																					$sub_sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
																					$sub_sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																					$sub_sub_sub_subelement_end    = $offset + $sub_sub_sub_subelement_length;
																					switch ($sub_sub_sub_subelement_id) {
																						case EBML_ID_CONTENTCOMPALGO:
																							$track_entry[$sub_subelement_idname][$sub_sub_subelement_idname][$sub_sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length));
																							break;
																						case EBML_ID_CONTENTCOMPSETTINGS:
																							$track_entry[$sub_subelement_idname][$sub_sub_subelement_idname][$sub_sub_sub_subelement_idname] =                           substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length);
																							break;
																						default:
																							$this->getid3->warning('Unhandled track.contentencodings.contentencoding.contentcompression element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
																							break;
																					}
																					$offset = $sub_sub_sub_subelement_end;
																				}
																				break;

																			case EBML_ID_CONTENTENCRYPTION:
																				while ($offset < $sub_sub_subelement_end) {
																					if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																						$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																						break;
																					}
																					$sub_sub_sub_subelement_offset = $offset;
																					$sub_sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																					$sub_sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
																					$sub_sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																					$sub_sub_sub_subelement_end    = $offset + $sub_sub_sub_subelement_length;
																					switch ($sub_sub_sub_subelement_id) {
																						case EBML_ID_CONTENTENCALGO:
																						case EBML_ID_CONTENTSIGALGO:
																						case EBML_ID_CONTENTSIGHASHALGO:
																							$track_entry[$sub_subelement_idname][$sub_sub_subelement_idname][$sub_sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length));
																							break;
																						case EBML_ID_CONTENTENCKEYID:
																						case EBML_ID_CONTENTSIGNATURE:
																						case EBML_ID_CONTENTSIGKEYID:
																							$track_entry[$sub_subelement_idname][$sub_sub_subelement_idname][$sub_sub_sub_subelement_idname] =                           substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length);
																							break;
																						default:
																							$this->getid3->warning('Unhandled track.contentencodings.contentencoding.contentcompression element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
																							break;
																					}
																					$offset = $sub_sub_sub_subelement_end;
																				}
																				break;

																			default:
																				$this->getid3->warning('Unhandled track.contentencodings.contentencoding element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
																				break;
																		}
																		$offset = $sub_sub_subelement_end;
																	}
																	break;
																default:
																	$this->getid3->warning('Unhandled track.contentencodings element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
																	break;
															}
															$offset = $sub_subelement_end;
														}
														break;

													case EBML_ID_CRC32:

														break;

													default:
														$this->getid3->warning('Unhandled track element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
														break;
												}
												$offset = $subelement_end;
											}
											break;

										case EBML_ID_CRC32:

											$offset = $track_entry_endoffset;
											break;

										default:
											$this->getid3->warning('Unhandled track element ['.basename(__FILE__).':'.__LINE__.'] ('.$track_entry['id'].'::'.$track_entry['id_name'].') at '.$track_entry['offset']);
											$offset = $track_entry_endoffset;
											break;
									}
									$info['matroska']['tracks']['tracks'][] = $track_entry;
								}
								break;

							case EBML_ID_INFO:
								$info_entry = array();
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									switch ($subelement_id) {
										case EBML_ID_CHAPTERTRANSLATEEDITIONUID:
										case EBML_ID_CHAPTERTRANSLATECODEC:
										case EBML_ID_TIMECODESCALE:
											$info_entry[$subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
											break;
										case EBML_ID_DURATION:
											$info_entry[$subelement_idname] =      getid3_lib::BigEndian2Float(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
											break;
										case EBML_ID_DATEUTC:
											$info_entry[$subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
											$info_entry[$subelement_idname.'_unix'] = $this->EBMLdate2unix($info_entry[$subelement_idname]);
											break;
										case EBML_ID_SEGMENTUID:
										case EBML_ID_PREVUID:
										case EBML_ID_NEXTUID:
										case EBML_ID_SEGMENTFAMILY:
										case EBML_ID_CHAPTERTRANSLATEID:
											$info_entry[$subelement_idname] =                             trim(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length), "\x00");
											break;
										case EBML_ID_SEGMENTFILENAME:
										case EBML_ID_PREVFILENAME:
										case EBML_ID_NEXTFILENAME:
										case EBML_ID_TITLE:
										case EBML_ID_MUXINGAPP:
										case EBML_ID_WRITINGAPP:
											$info_entry[$subelement_idname] =                             trim(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length), "\x00");
											$info['matroska']['comments'][strtolower($subelement_idname)][] = $info_entry[$subelement_idname];
											break;
										case EBML_ID_CRC32:

											break;
										default:
											$this->getid3->warning('Unhandled info element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$offset = $subelement_end;
								}
								$info['matroska']['info'][] = $info_entry;
								break;

							case EBML_ID_CUES:
								$cues_entry = array();
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									switch ($subelement_id) {
										case EBML_ID_CUEPOINT:
											$cuepoint_entry = array();
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_CUETRACKPOSITIONS:
														while ($offset < $sub_subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_sub_subelement_offset = $offset;
															$sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
															$sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_end    = $offset + $sub_sub_subelement_length;
															switch ($sub_sub_subelement_id) {
																case EBML_ID_CUETRACK:
																	$cuepoint_entry[$sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																	break;
																default:
																	$this->getid3->warning('Unhandled cues.cuepoint.cuetrackpositions element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_subelement_id.'::'.$sub_sub_subelement_idname.') at '.$sub_sub_subelement_offset);
																	break;
															}
															$offset = $sub_subelement_end;
														}
														break;
													case EBML_ID_CUETIME:
														$cuepoint_entry[$subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;
													default:
														$this->getid3->warning('Unhandled cues.cuepoint element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											$cues_entry[] = $cuepoint_entry;
											$offset = $sub_subelement_end;
											break;

										case EBML_ID_CRC32:

											break;

										default:
											$this->getid3->warning('Unhandled cues element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$offset = $subelement_end;
								}
								$info['matroska']['cues'] = $cues_entry;
								break;

							case EBML_ID_TAGS:
								$tags_entry = array();
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									$tag_entry = array();
									switch ($subelement_id) {
										case EBML_ID_WRITINGAPP:
											$tag_entry[$subelement_idname] = substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length);
											break;
										case EBML_ID_TAG:
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_TARGETS:
														$targets_entry = array();
														while ($offset < $sub_subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_sub_subelement_offset = $offset;
															$sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
															$sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_end    = $offset + $sub_sub_subelement_length;
															switch ($sub_sub_subelement_id) {
																case EBML_ID_TARGETTYPEVALUE:
																	$targets_entry[$sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																	$targets_entry[strtolower($sub_sub_subelement_idname).'_long'] = $this->MatroskaTargetTypeValue($targets_entry[$sub_sub_subelement_idname]);
																	break;
																case EBML_ID_EDITIONUID:
																case EBML_ID_CHAPTERUID:
																case EBML_ID_ATTACHMENTUID:
																case EBML_ID_TAGTRACKUID:
																case EBML_ID_TAGCHAPTERUID:
																	$targets_entry[$sub_sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																	break;
																default:
																	$this->getid3->warning('Unhandled tag.targets element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_subelement_id.'::'.$sub_sub_subelement_idname.') at '.$sub_sub_subelement_offset);
																	break;
															}
															$offset = $sub_sub_subelement_end;
														}
														$tag_entry[$sub_subelement_idname][] = $targets_entry;
														break;

													case EBML_ID_SIMPLETAG:

														$tag_entry[$sub_subelement_idname][] = $this->Handle_EMBL_ID_SIMPLETAG($offset, $sub_subelement_end);
														break;

													case EBML_ID_TARGETTYPE:
														$tag_entry[$sub_subelement_idname] =                           substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length);
														break;

													case EBML_ID_TRACKUID:
														$tag_entry[$sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;

													default:
														$this->getid3->warning('Unhandled tags.tag element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											$offset = $sub_subelement_end;
											break;

										case EBML_ID_CRC32:

											break;

										default:
											$this->getid3->warning('Unhandled tags element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$tags_entry['tags'][] = $tag_entry;
									$offset = $subelement_end;
								}
								$info['matroska']['tags'] = $tags_entry['tags'];
								break;

							case EBML_ID_ATTACHMENTS:
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									switch ($subelement_id) {
										case EBML_ID_ATTACHEDFILE:
											$attachedfile_entry = array();
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_FILEDESCRIPTION:
													case EBML_ID_FILENAME:
													case EBML_ID_FILEMIMETYPE:
														$attachedfile_entry[$sub_subelement_idname] = substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length);
														break;

													case EBML_ID_FILEDATA:
														$attachedfile_entry['data_offset'] = $offset;
														$attachedfile_entry['data_length'] = $sub_subelement_length;
														do {
															if ($this->inline_attachments === false) {

																break;
															}
															if ($this->inline_attachments === true) {

															} elseif (is_int($this->inline_attachments)) {
																if ($this->inline_attachments < $sub_subelement_length) {

																	$this->getid3->warning('attachment at '.$sub_subelement_offset.' is too large to process inline ('.number_format($sub_subelement_length).' bytes)');
																	break;
																}
															} elseif (is_string($this->inline_attachments)) {
																$this->inline_attachments = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $this->inline_attachments), DIRECTORY_SEPARATOR);
																if (!is_dir($this->inline_attachments) || !is_writable($this->inline_attachments)) {

																	$this->getid3->warning('attachment at '.$sub_subelement_offset.' cannot be saved to "'.$this->inline_attachments.'" (not writable)');
																	break;
																}
															}

															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset, $sub_subelement_length)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$attachedfile_entry[$sub_subelement_idname] = substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length);
															if (is_string($this->inline_attachments)) {
																$destination_filename = $this->inline_attachments.DIRECTORY_SEPARATOR.md5($info['filenamepath']).'_'.$attachedfile_entry['data_offset'];
																if (!file_exists($destination_filename) || is_writable($destination_filename)) {
																	file_put_contents($destination_filename, $attachedfile_entry[$sub_subelement_idname]);
																} else {
																	$this->getid3->warning('attachment at '.$sub_subelement_offset.' cannot be saved to "'.$destination_filename.'" (not writable)');
																}
																$attachedfile_entry[$sub_subelement_idname.'_filename'] = $destination_filename;
																unset($attachedfile_entry[$sub_subelement_idname]);
															}
														} while (false);
														break;

													case EBML_ID_FILEUID:
														$attachedfile_entry[$sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;

													default:
														$this->getid3->warning('Unhandled attachment.attachedfile element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											if (!empty($attachedfile_entry[$this->EBMLidName(EBML_ID_FILEDATA)]) && !empty($attachedfile_entry[$this->EBMLidName(EBML_ID_FILEMIMETYPE)]) && preg_match('#^image/#i', $attachedfile_entry[$this->EBMLidName(EBML_ID_FILEMIMETYPE)])) {
												if (($this->inline_attachments === true) || (is_int($this->inline_attachments) && ($this->inline_attachments >= strlen($attachedfile_entry[$this->EBMLidName(EBML_ID_FILEDATA)])))) {
													$attachedfile_entry['data']       = $attachedfile_entry[$this->EBMLidName(EBML_ID_FILEDATA)];
													$attachedfile_entry['image_mime'] = $attachedfile_entry[$this->EBMLidName(EBML_ID_FILEMIMETYPE)];
													$info['matroska']['comments']['picture'][] = array('data'=>$attachedfile_entry['data'], 'image_mime'=>$attachedfile_entry['image_mime'], 'filename'=>(!empty($attachedfile_entry[$this->EBMLidName(EBML_ID_FILENAME)]) ? $attachedfile_entry[$this->EBMLidName(EBML_ID_FILENAME)] : ''));
													unset($attachedfile_entry[$this->EBMLidName(EBML_ID_FILEDATA)], $attachedfile_entry[$this->EBMLidName(EBML_ID_FILEMIMETYPE)]);
												}
											}
											if (!empty($attachedfile_entry['image_mime']) && preg_match('#^image/#i', $attachedfile_entry['image_mime'])) {
												// don't add a second copy of attached images, which are grouped under the standard location [comments][picture]
											} else {
												$info['matroska']['attachments'][] = $attachedfile_entry;
											}
											$offset = $sub_subelement_end;
											break;

										case EBML_ID_CRC32:

											break;

										default:
											$this->getid3->warning('Unhandled tags element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$offset = $subelement_end;
								}
								break;

							case EBML_ID_CHAPTERS:
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									switch ($subelement_id) {
										case EBML_ID_EDITIONENTRY:
											$editionentry_entry = array();
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_EDITIONUID:
														$editionentry_entry[$sub_subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;
													case EBML_ID_EDITIONFLAGHIDDEN:
													case EBML_ID_EDITIONFLAGDEFAULT:
													case EBML_ID_EDITIONFLAGORDERED:
														$editionentry_entry[$sub_subelement_idname] = (bool) getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;
													case EBML_ID_CHAPTERATOM:
														$chapteratom_entry = array();
														while ($offset < $sub_subelement_end) {
															if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																break;
															}
															$sub_sub_subelement_offset = $offset;
															$sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
															$sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
															$sub_sub_subelement_end    = $offset + $sub_sub_subelement_length;
															switch ($sub_sub_subelement_id) {
																case EBML_ID_CHAPTERSEGMENTUID:
																case EBML_ID_CHAPTERSEGMENTEDITIONUID:
																	$chapteratom_entry[$sub_sub_subelement_idname] =                                  substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length);
																	break;
																case EBML_ID_CHAPTERFLAGENABLED:
																case EBML_ID_CHAPTERFLAGHIDDEN:
																	$chapteratom_entry[$sub_sub_subelement_idname] = (bool) getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																	break;
																case EBML_ID_CHAPTERUID:
																case EBML_ID_CHAPTERTIMESTART:
																case EBML_ID_CHAPTERTIMEEND:
																	$chapteratom_entry[$sub_sub_subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
																	break;
																case EBML_ID_CHAPTERTRACK:
																	$chaptertrack_entry = array();
																	while ($offset < $sub_sub_subelement_end) {
																		if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																			$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																			break;
																		}
																		$sub_sub_sub_subelement_offset = $offset;
																		$sub_sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
																		$sub_sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_sub_subelement_end    = $offset + $sub_sub_sub_subelement_length;
																		switch ($sub_sub_sub_subelement_id) {
																			case EBML_ID_CHAPTERTRACKNUMBER:
																				$chaptertrack_entry[$sub_sub_sub_subelement_idname] =        getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length));
																				break;
																			default:
																				$this->getid3->warning('Unhandled chapters.editionentry.chapteratom.chaptertrack element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_sub_subelement_id.'::'.$sub_sub_sub_subelement_idname.') at '.$sub_sub_sub_subelement_offset);
																				break;
																		}
																		$offset = $sub_sub_sub_subelement_end;
																	}
																	$chapteratom_entry[$sub_sub_subelement_idname][] = $chaptertrack_entry;
																	break;
																case EBML_ID_CHAPTERDISPLAY:
																	$chapterdisplay_entry = array();
																	while ($offset < $sub_sub_subelement_end) {
																		if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
																			$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
																			break;
																		}
																		$sub_sub_sub_subelement_offset = $offset;
																		$sub_sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_sub_subelement_idname = $this->EBMLidName($sub_sub_sub_subelement_id);
																		$sub_sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
																		$sub_sub_sub_subelement_end    = $offset + $sub_sub_sub_subelement_length;
																		switch ($sub_sub_sub_subelement_id) {
																			case EBML_ID_CHAPSTRING:
																			case EBML_ID_CHAPLANGUAGE:
																			case EBML_ID_CHAPCOUNTRY:
																				$chapterdisplay_entry[$sub_sub_sub_subelement_idname] =                                  substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_sub_subelement_length);
																				break;
																			default:
																				$this->getid3->warning('Unhandled chapters.editionentry.chapteratom.chapterdisplay element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_sub_subelement_id.'::'.$sub_sub_sub_subelement_idname.') at '.$sub_sub_sub_subelement_offset);
																				break;
																		}
																		$offset = $sub_sub_sub_subelement_end;
																	}
																	$chapteratom_entry[$sub_sub_subelement_idname][] = $chapterdisplay_entry;
																	break;
																default:
																	$this->getid3->warning('Unhandled chapters.editionentry.chapteratom element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_subelement_id.'::'.$sub_sub_subelement_idname.') at '.$sub_sub_subelement_offset);
																	break;
															}
															$offset = $sub_sub_subelement_end;
														}
														$editionentry_entry[$sub_subelement_idname][] = $chapteratom_entry;
														break;
													default:
														$this->getid3->warning('Unhandled chapters.editionentry element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											$info['matroska']['chapters'][] = $editionentry_entry;
											$offset = $sub_subelement_end;
											break;
										default:
											$this->getid3->warning('Unhandled chapters element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$offset = $subelement_end;
								}
								break;

							case EBML_ID_VOID:
								$void_entry = array();
								$void_entry['offset'] = $offset;
								$info['matroska']['void'][] = $void_entry;
								break;

							case EBML_ID_CLUSTER:
								$cluster_entry = array();
								while ($offset < $element_end) {
									if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
										$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
										break;
									}
									$subelement_offset = $offset;
									$subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_idname = $this->EBMLidName($subelement_id);
									$subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
									$subelement_end    = $offset + $subelement_length;
									switch ($subelement_id) {
										case EBML_ID_CLUSTERTIMECODE:
										case EBML_ID_CLUSTERPOSITION:
										case EBML_ID_CLUSTERPREVSIZE:
											$cluster_entry[$subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $subelement_length));
											break;

										case EBML_ID_CLUSTERSILENTTRACKS:
											$cluster_silent_tracks = array();
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_CLUSTERSILENTTRACKNUMBER:
														$cluster_silent_tracks[] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;
													default:
														$this->getid3->warning('Unhandled clusters.silenttracks element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											$cluster_entry[$subelement_idname][] = $cluster_silent_tracks;
											$offset = $sub_subelement_end;
											break;

										case EBML_ID_CLUSTERBLOCKGROUP:
											$cluster_block_group = array('offset'=>$offset);
											while ($offset < $subelement_end) {
												if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
													$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
													break;
												}
												$sub_subelement_offset = $offset;
												$sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_idname = $this->EBMLidName($sub_subelement_id);
												$sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
												$sub_subelement_end    = $offset + $sub_subelement_length;
												switch ($sub_subelement_id) {
													case EBML_ID_CLUSTERBLOCK:
														$cluster_block_data = array();
														$cluster_block_data['tracknumber'] = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
														$cluster_block_data['timecode'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 2));
														$offset += 2;
														// unsure whether this is 1 octect or 2 octets? (http://matroska.org/technical/specs/index.html#block_structure)
														$cluster_block_data['flags_raw'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
														$offset += 1;
														//$cluster_block_data['flags']['reserved1'] =      (($cluster_block_data['flags_raw'] & 0xF0) >> 4);
														$cluster_block_data['flags']['invisible'] = (bool) (($cluster_block_data['flags_raw'] & 0x08) >> 3);
														$cluster_block_data['flags']['lacing']    =        (($cluster_block_data['flags_raw'] & 0x06) >> 1);
														//$cluster_block_data['flags']['reserved2'] =      (($cluster_block_data['flags_raw'] & 0x01) >> 0);
														$cluster_block_data['flags']['lacing_type'] = $this->MatroskaBlockLacingType($cluster_block_data['flags']['lacing']);
														if ($cluster_block_data['flags']['lacing'] != 0) {
															$cluster_block_data['lace_frames'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
															$offset += 1;
															if ($cluster_block_data['flags']['lacing'] != 2) {
																$cluster_block_data['lace_frames'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
																$offset += 1;
															}
														}
														if (!isset($info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']])) {
															$info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']]['offset'] = $offset;
															$info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']]['length'] = $subelement_length;
														}
														$cluster_block_group[$sub_subelement_idname] = $cluster_block_data;
														break;

													case EBML_ID_CLUSTERREFERENCEPRIORITY:
													case EBML_ID_CLUSTERBLOCKDURATION:
														$cluster_block_group[$sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length));
														break;

													case EBML_ID_CLUSTERREFERENCEBLOCK:
														$cluster_block_group[$sub_subelement_idname] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_subelement_length), false, true);
														break;

													default:
														$this->getid3->warning('Unhandled clusters.blockgroup element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_subelement_id.'::'.$sub_subelement_idname.') at '.$sub_subelement_offset);
														break;
												}
												$offset = $sub_subelement_end;
											}
											$cluster_entry[$subelement_idname][] = $cluster_block_group;
											$offset = $sub_subelement_end;
											break;

										case EBML_ID_CLUSTERSIMPLEBLOCK:
											// http://www.matroska.org/technical/specs/index.html#simpleblock_structure
											$cluster_block_data = array();
											$cluster_block_data['tracknumber'] = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
											$cluster_block_data['timecode'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 2));
											$offset += 2;
											$cluster_block_data['flags_raw'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
											$offset += 1;
											$cluster_block_data['flags']['keyframe']    = (($cluster_block_data['flags_raw'] & 0x80) >> 7);
											$cluster_block_data['flags']['reserved1']   = (($cluster_block_data['flags_raw'] & 0x70) >> 4);
											$cluster_block_data['flags']['invisible']   = (($cluster_block_data['flags_raw'] & 0x08) >> 3);
											$cluster_block_data['flags']['lacing']      = (($cluster_block_data['flags_raw'] & 0x06) >> 1);
											$cluster_block_data['flags']['discardable'] = (($cluster_block_data['flags_raw'] & 0x01));

											if ($cluster_block_data['flags']['lacing'] > 0) {
												$cluster_block_data['lace_frames'] = 1 + getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
												$offset += 1;
												if ($cluster_block_data['flags']['lacing'] != 0x02) {
													// *This is not used with Fixed-size lacing as it is calculated automatically from (total size of lace) / (number of frames in lace).
													$cluster_block_data['lace_frame_size'] = getid3_lib::BigEndian2Int(substr($EBMLdata, $offset, 1));
													$offset += 1;
												}
											}

											if (!isset($info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']])) {
												$info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']]['offset'] = $offset;
												$info['matroska']['track_data_offsets'][$cluster_block_data['tracknumber']]['length'] = $subelement_length;
											}
											$cluster_block_group[$sub_subelement_idname] = $cluster_block_data;
											break;

										default:
											$this->getid3->warning('Unhandled cluster element ['.basename(__FILE__).':'.__LINE__.'] ('.$subelement_id.'::'.$subelement_idname.' ['.$subelement_length.' bytes]) at '.$subelement_offset);
											break;
									}
									$offset = $subelement_end;
								}
								$info['matroska']['cluster'][] = $cluster_entry;

								if (isset($info['matroska']['info']) && is_array($info['matroska']['info'])) {
									if (isset($info['matroska']['tracks']['tracks']) && is_array($info['matroska']['tracks']['tracks'])) {
										break 2;
									}
								}
								break;

							default:
								if ($element_data['id_name'] == dechex($element_data['id'])) {
									$info['error'][] = 'Unhandled segment ['.basename(__FILE__).':'.__LINE__.'] ('.$element_data['id'].') at '.$element_data_offset;
								} else {
									$this->getid3->warning('Unhandled segment ['.basename(__FILE__).':'.__LINE__.'] ('.$element_data['id'].'::'.$element_data['id_name'].') at '.$element_data['offset']);
								}
								break;
						}
						$offset = $element_end;
					}
					break;

				default:
					$info['error'][] = 'Unhandled chunk ['.basename(__FILE__).':'.__LINE__.'] ('.$top_element_id.') at '.$offset;
					break;
			}
			$offset = $top_element_endoffset;
		}

		if (isset($info['matroska']['info']) && is_array($info['matroska']['info'])) {
			foreach ($info['matroska']['info'] as $key => $infoarray) {
				if (isset($infoarray['Duration'])) {

					$info['playtime_seconds'] = $infoarray['Duration'] * ((isset($infoarray['TimecodeScale']) ? $infoarray['TimecodeScale'] : 1000000) / 1000000000);
					break;
				}
			}
		}

		if (isset($info['matroska']['tags']) && is_array($info['matroska']['tags'])) {
			foreach ($info['matroska']['tags'] as $key => $infoarray) {
				$this->ExtractCommentsSimpleTag($infoarray);
			}
		}

		if (isset($info['matroska']['tracks']['tracks']) && is_array($info['matroska']['tracks']['tracks'])) {
			foreach ($info['matroska']['tracks']['tracks'] as $key => $trackarray) {
				$track_info = array();
				if (isset($trackarray['FlagDefault'])) {
					$track_info['default'] = $trackarray['FlagDefault'];
				}
				switch (isset($trackarray['TrackType']) ? $trackarray['TrackType'] : '') {
					case 1:
						if (!empty($trackarray['PixelWidth']))                     { $track_info['resolution_x']  =                                    $trackarray['PixelWidth'];                     }
						if (!empty($trackarray['PixelHeight']))                    { $track_info['resolution_y']  =                                    $trackarray['PixelHeight'];                    }
						if (!empty($trackarray['DisplayWidth']))                   { $track_info['display_x']     =                                    $trackarray['DisplayWidth'];                   }
						if (!empty($trackarray['DisplayHeight']))                  { $track_info['display_y']     =                                    $trackarray['DisplayHeight'];                  }
						if (!empty($trackarray['DefaultDuration']))                { $track_info['frame_rate']    =                 round(1000000000 / $trackarray['DefaultDuration'], 3);            }
						if (!empty($trackarray['CodecID']))                        { $track_info['dataformat']    = $this->MatroskaCodecIDtoCommonName($trackarray['CodecID']);                       }
						if (!empty($trackarray['codec_private_parsed']['fourcc'])) { $track_info['fourcc']        =                                    $trackarray['codec_private_parsed']['fourcc']; }
						$info['video']['streams'][] = $track_info;
						if (isset($track_info['resolution_x']) && empty($info['video']['resolution_x'])) {
							foreach ($track_info as $key => $value) {
								$info['video'][$key] = $value;
							}
						}
						break;
					case 2:
						if (!empty($trackarray['CodecID']))           { $track_info['dataformat']      = $this->MatroskaCodecIDtoCommonName($trackarray['CodecID']);          }
						if (!empty($trackarray['SamplingFrequency'])) { $track_info['sample_rate']     =                                    $trackarray['SamplingFrequency']; }
						if (!empty($trackarray['Channels']))          { $track_info['channels']        =                                    $trackarray['Channels'];          }
						if (!empty($trackarray['BitDepth']))          { $track_info['bits_per_sample'] =                                    $trackarray['BitDepth'];          }
						if (!empty($trackarray['Language']))          { $track_info['language']        =                                    $trackarray['Language'];          }
						switch (isset($trackarray[$this->EBMLidName(EBML_ID_CODECID)]) ? $trackarray[$this->EBMLidName(EBML_ID_CODECID)] : '') {
							case 'A_PCM/INT/LIT':
							case 'A_PCM/INT/BIG':
								$track_info['bitrate'] = $trackarray['SamplingFrequency'] * $trackarray['Channels'] * $trackarray['BitDepth'];
								break;

							case 'A_AC3':
								if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.ac3.php', __FILE__, false)) {
									if (isset($info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'])) {
										$getid3_temp = new getID3();
										$getid3_temp->openfile($this->getid3->filename);
										$getid3_temp->info['avdataoffset'] = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'];
										$getid3_ac3 = new getid3_ac3($getid3_temp);
										$getid3_ac3->Analyze();
										unset($getid3_temp->info['ac3']['GETID3_VERSION']);
										$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info['ac3'];
										if (!empty($getid3_temp->info['error'])) {
											foreach ($getid3_temp->info['error'] as $newerror) {
												$this->getid3->warning('getid3_ac3() says: ['.$newerror.']');
											}
										}
										if (!empty($getid3_temp->info['warning'])) {
											foreach ($getid3_temp->info['warning'] as $newerror) {
												$this->getid3->warning('getid3_ac3() says: ['.$newerror.']');
											}
										}
										if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
											foreach ($getid3_temp->info['audio'] as $key => $value) {
												$track_info[$key] = $value;
											}
										}
										unset($getid3_temp, $getid3_ac3);
									} else {
										$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because $info[matroska][track_data_offsets]['.$trackarray['TrackNumber'].'][offset] not set');
									}
								} else {
									$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio.ac3.php"');
								}
								break;

							case 'A_DTS':
								if (isset($info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'])) {
									$dts_offset = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'];

									fseek($this->getid3->fp, $dts_offset, SEEK_SET);
									$magic_test = fread($this->getid3->fp, 8);
									for ($i = 0; $i < 4; $i++) {
										// look to see if DTS "magic" is here, if so adjust offset by that many bytes
										if (substr($magic_test, $i, 4) == "\x7F\xFE\x80\x01") {
											$dts_offset += $i;
											break;
										}
									}
									if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.dts.php', __FILE__, false)) {
										$getid3_temp = new getID3();
										$getid3_temp->openfile($this->getid3->filename);
										$getid3_temp->info['avdataoffset'] = $dts_offset;
										$getid3_dts = new getid3_dts($getid3_temp);
										$getid3_dts->Analyze();
										unset($getid3_temp->info['dts']['GETID3_VERSION']);
										$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info['dts'];
										if (!empty($getid3_temp->info['error'])) {
											foreach ($getid3_temp->info['error'] as $newerror) {
												$this->getid3->warning('getid3_dts() says: ['.$newerror.']');
											}
										}
										if (!empty($getid3_temp->info['warning'])) {
											foreach ($getid3_temp->info['warning'] as $newerror) {
												$this->getid3->warning('getid3_dts() says: ['.$newerror.']');
											}
										}
										if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
											foreach ($getid3_temp->info['audio'] as $key => $value) {
												$track_info[$key] = $value;
											}
										}
										unset($getid3_temp, $getid3_dts);
									} else {
										$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio.dts.php"');
									}
								} else {
									$this->getid3->warning('Unable to parse audio data for track "'.$trackarray['TrackNumber'].'" in ['.basename(__FILE__).':'.__LINE__.'] data offset is unknown');
								}
								break;

							case 'A_AAC':
								$this->getid3->warning('This version of getID3() [v'.$this->getid3->version().'] has problems parsing AAC audio in Matroska containers ['.basename(__FILE__).':'.__LINE__.']');
								if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.aac.php', __FILE__, false)) {
									$getid3_temp = new getID3();
									$getid3_temp->openfile($this->getid3->filename);
									$getid3_temp->info['avdataoffset'] = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'];
									$getid3_aac = new getid3_aac($getid3_temp);
									$getid3_aac->Analyze();
									unset($getid3_temp->info['aac']['GETID3_VERSION']);
									if (!empty($getid3_temp->info['audio']['dataformat'])) {
										$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info['aac'];
										if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
											foreach ($getid3_temp->info['audio'] as $key => $value) {
												$track_info[$key] = $value;
											}
										}
									} else {
										$this->getid3->warning('Failed to parse '.$trackarray[$this->EBMLidName(EBML_ID_CODECID)].' audio data ['.basename(__FILE__).':'.__LINE__.']');
									}
									unset($getid3_temp, $getid3_aac);
								} else {
									$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio.aac.php"');
								}
								break;

							case 'A_MPEG/L3':
								if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.mp3.php', __FILE__, false)) {
									$getid3_temp = new getID3();
									$getid3_temp->openfile($this->getid3->filename);
									$getid3_temp->info['avdataoffset'] = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'];
									$getid3_temp->info['avdataend']    = $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset'] + $info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['length'];
									$getid3_mp3 = new getid3_mp3($getid3_temp);
									$getid3_mp3->allow_bruteforce = true;
									$getid3_mp3->Analyze();
									if (!empty($getid3_temp->info['mpeg'])) {
										unset($getid3_temp->info['mpeg']['GETID3_VERSION']);
										$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']] = $getid3_temp->info['mpeg'];
										if (!empty($getid3_temp->info['error'])) {
											foreach ($getid3_temp->info['error'] as $newerror) {
												$this->getid3->warning('getid3_mp3() says: ['.$newerror.']');
											}
										}
										if (!empty($getid3_temp->info['warning'])) {
											foreach ($getid3_temp->info['warning'] as $newerror) {
												$this->getid3->warning('getid3_mp3() says: ['.$newerror.']');
											}
										}
										if (isset($getid3_temp->info['audio']) && is_array($getid3_temp->info['audio'])) {
											foreach ($getid3_temp->info['audio'] as $key => $value) {
												$track_info[$key] = $value;
											}
										}
									} else {
										$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because getid3_mp3::Analyze failed at offset '.$info['matroska']['track_data_offsets'][$trackarray['TrackNumber']]['offset']);
									}
									unset($getid3_temp, $getid3_mp3);
								} else {
									$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio.mp3.php"');
								}
								break;

							case 'A_VORBIS':
								if (isset($trackarray['CodecPrivate'])) {

									$found_vorbis = false;
									for ($vorbis_offset = 1; $vorbis_offset < 16; $vorbis_offset++) {
										if (substr($trackarray['CodecPrivate'], $vorbis_offset, 6) == 'vorbis') {
											$vorbis_offset--;
											$found_vorbis = true;
											break;
										}
									}
									if ($found_vorbis) {
										if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.ogg.php', __FILE__, false)) {
											$oggpageinfo['page_seqno'] = 0;

											$getid3_temp = new getID3();
											$getid3_temp->openfile($this->getid3->filename);
											$getid3_ogg = new getid3_ogg($getid3_temp);
											$getid3_ogg->ParseVorbisPageHeader($trackarray['CodecPrivate'], $vorbis_offset, $oggpageinfo);
											$vorbis_fileinfo = $getid3_temp->info;
											unset($getid3_temp, $getid3_ogg);

											if (isset($vorbis_fileinfo['audio'])) {
												$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']]['audio'] = $vorbis_fileinfo['audio'];
											}
											if (isset($vorbis_fileinfo['ogg'])) {
												$info['matroska']['track_codec_parsed'][$trackarray['TrackNumber']]['ogg']   = $vorbis_fileinfo['ogg'];
											}
											if (!empty($vorbis_fileinfo['error'])) {
												foreach ($vorbis_fileinfo['error'] as $newerror) {
													$this->getid3->warning('getid3_ogg() says: ['.$newerror.']');
												}
											}
											if (!empty($vorbis_fileinfo['warning'])) {
												foreach ($vorbis_fileinfo['warning'] as $newerror) {
													$this->getid3->warning('getid3_ogg() says: ['.$newerror.']');
												}
											}
											if (isset($vorbis_fileinfo['audio']) && is_array($vorbis_fileinfo['audio'])) {
												foreach ($vorbis_fileinfo['audio'] as $key => $value) {
													$track_info[$key] = $value;
												}
											}
											if (!empty($vorbis_fileinfo['ogg']['bitrate_average'])) {
												$track_info['bitrate'] = $vorbis_fileinfo['ogg']['bitrate_average'];
											} elseif (!empty($vorbis_fileinfo['ogg']['bitrate_nominal'])) {
												$track_info['bitrate'] = $vorbis_fileinfo['ogg']['bitrate_nominal'];
											}
											unset($vorbis_fileinfo);
											unset($oggpageinfo);
										} else {
											$this->getid3->warning('Unable to parse audio data ['.basename(__FILE__).':'.__LINE__.'] because cannot include "module.audio.ogg.php"');
										}
									} else {
									}
								} else {
								}
								break;

							default:
								$this->getid3->warning('Unhandled audio type "'.(isset($trackarray[$this->EBMLidName(EBML_ID_CODECID)]) ? $trackarray[$this->EBMLidName(EBML_ID_CODECID)] : '').'"');
								break;
						}

						$info['audio']['streams'][] = $track_info;
						if (isset($track_info['dataformat']) && empty($info['audio']['dataformat'])) {
							foreach ($track_info as $key => $value) {
								$info['audio'][$key] = $value;
							}
						}
						break;
					default:

						break;
				}
			}
		}

		if ($this->hide_clusters) {

			if (isset($info['matroska']['segments']) && is_array($info['matroska']['segments'])) {
				foreach ($info['matroska']['segments'] as $key => $segmentsarray) {
					if ($segmentsarray['id'] == EBML_ID_CLUSTER) {
						unset($info['matroska']['segments'][$key]);
					}
				}
			}
			if (isset($info['matroska']['seek']) && is_array($info['matroska']['seek'])) {
				foreach ($info['matroska']['seek'] as $key => $seekarray) {
					if ($seekarray['target_id'] == EBML_ID_CLUSTER) {
						unset($info['matroska']['seek'][$key]);
					}
				}
			}
		}

		if (!empty($info['video']['streams'])) {
			$info['mime_type'] = 'video/x-matroska';
		} elseif (!empty($info['audio']['streams'])) {
			$info['mime_type'] = 'audio/x-matroska';
		} elseif (isset($info['mime_type'])) {
			unset($info['mime_type']);
		}

		foreach ($this->warnings as $key => $value) {
			$info['warning'][] = $value;
		}

		return true;
	}

///////////////////////////////////////

	function EnsureBufferHasEnoughData(&$EBMLdata, &$offset, &$EBMLdata_offset, $min_data=1024) {
		if (!getid3_lib::intValueSupported($offset + $this->read_buffer_size)) {
			$offset = PHP_INT_MAX + 1;
			return false;
		}
		if (($offset - $EBMLdata_offset) >= (strlen($EBMLdata) - $min_data)) {
			fseek($this->getid3->fp, $offset, SEEK_SET);
			$EBMLdata_offset = ftell($this->getid3->fp);
			$EBMLdata = fread($this->getid3->fp, max($min_data, $this->read_buffer_size));
			if ((strlen($EBMLdata) == 0) && feof($this->getid3->fp)) {
				$this->errors[] = 'EnsureBufferHasEnoughData() ran out of file at offset '.$offset;
				return false;
			}
		}
		return true;
	}

	function readEBMLint(&$string, &$offset, $dataoffset=0) {
		$actual_offset = $offset - $dataoffset;
		if (!getid3_lib::intValueSupported($offset + $this->read_buffer_size)) {
			$this->getid3->warning('aborting readEBMLint() because $offset larger than '.round(PHP_INT_MAX / 1073741824).'GB');
			return false;
		} elseif ($actual_offset >= strlen($string)) {
			$this->getid3->warning('$actual_offset > $string in readEBMLint($string['.strlen($string).'], '.$offset.', '.$dataoffset.')');
			return false;
		} elseif ($actual_offset < 0) {
			$this->getid3->warning('$actual_offset < 0 in readEBMLint($string['.strlen($string).'], '.$offset.', '.$dataoffset.')');
			return false;
		}
		$first_byte_int = ord($string{$actual_offset});
		if (0x80 & $first_byte_int) {
			$length = 1;
		} elseif (0x40 & $first_byte_int) {
			$length = 2;
		} elseif (0x20 & $first_byte_int) {
			$length = 3;
		} elseif (0x10 & $first_byte_int) {
			$length = 4;
		} elseif (0x08 & $first_byte_int) {
			$length = 5;
		} elseif (0x04 & $first_byte_int) {
			$length = 6;
		} elseif (0x02 & $first_byte_int) {
			$length = 7;
		} elseif (0x01 & $first_byte_int) {
			$length = 8;
		} else {
			$this->getid3->warning('invalid EBML integer (leading 0x00) at '.$offset);
			$offset = PHP_INT_MAX + 1;
			return false;
		}
		$int_value = $this->EBML2Int(substr($string, $actual_offset, $length));
		$offset += $length;
		return $int_value;
	}

	static function EBML2Int($EBMLstring) {
		// http://matroska.org/specs/

		$first_byte_int = ord($EBMLstring{0});
		if (0x80 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x7F);
		} elseif (0x40 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x3F);
		} elseif (0x20 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x1F);
		} elseif (0x10 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x0F);
		} elseif (0x08 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x07);
		} elseif (0x04 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x03);
		} elseif (0x02 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x01);
		} elseif (0x01 & $first_byte_int) {
			$EBMLstring{0} = chr($first_byte_int & 0x00);
		} else {
			return false;
		}
		return getid3_lib::BigEndian2Int($EBMLstring);
	}

	static function EBMLdate2unix($EBMLdatestamp) {

		return round(($EBMLdatestamp / 1000000000) + 978307200);
	}

	function ExtractCommentsSimpleTag($SimpleTagArray) {
		$info =& $this->getid3->info;
		if (!empty($SimpleTagArray[$this->EBMLidName(EBML_ID_SIMPLETAG)])) {
			foreach ($SimpleTagArray[$this->EBMLidName(EBML_ID_SIMPLETAG)] as $SimpleTagKey => $SimpleTagData) {
				if (!empty($SimpleTagData[$this->EBMLidName(EBML_ID_TAGNAME)]) && !empty($SimpleTagData[$this->EBMLidName(EBML_ID_TAGSTRING)])) {
					$info['matroska']['comments'][strtolower($SimpleTagData[$this->EBMLidName(EBML_ID_TAGNAME)])][] = $SimpleTagData[$this->EBMLidName(EBML_ID_TAGSTRING)];
				}
				if (!empty($SimpleTagData[$this->EBMLidName(EBML_ID_SIMPLETAG)])) {
					$this->ExtractCommentsSimpleTag($SimpleTagData);
				}
			}
		}
		return true;
	}

	function Handle_EMBL_ID_SIMPLETAG(&$offset, $sub_subelement_end) {
		$simpletag_entry = array();
		while ($offset < $sub_subelement_end) {
			if (!$this->EnsureBufferHasEnoughData($EBMLdata, $offset, $EBMLdata_offset)) {
				$this->getid3->error('EnsureBufferHasEnoughData() failed at offset '.$offset);
				break;
			}
			$sub_sub_subelement_offset = $offset;
			$sub_sub_subelement_id     = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
			$sub_sub_subelement_idname = $this->EBMLidName($sub_sub_subelement_id);
			$sub_sub_subelement_length = $this->readEBMLint($EBMLdata, $offset, $EBMLdata_offset);
			$sub_sub_subelement_end    = $offset + $sub_sub_subelement_length;
			switch ($sub_sub_subelement_id) {
				case EBML_ID_TAGNAME:
				case EBML_ID_TAGLANGUAGE:
				case EBML_ID_TAGSTRING:
				case EBML_ID_TAGBINARY:
					$simpletag_entry[$sub_sub_subelement_idname] =                                  substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length);
					break;
				case EBML_ID_SIMPLETAG:
					$simpletag_entry[$sub_sub_subelement_idname][] = $this->Handle_EMBL_ID_SIMPLETAG($offset, $sub_sub_subelement_end);
					break;
				case EBML_ID_TAGDEFAULT:
					$simpletag_entry[$sub_sub_subelement_idname] = (bool) getid3_lib::BigEndian2Int(substr($EBMLdata, $offset - $EBMLdata_offset, $sub_sub_subelement_length));
					break;

				default:
					$this->getid3->warning('Unhandled tag.simpletag element ['.basename(__FILE__).':'.__LINE__.'] ('.$sub_sub_subelement_id.'::'.$sub_sub_subelement_idname.') at '.$sub_sub_subelement_offset);
					break;
			}
			$offset = $sub_sub_subelement_end;
		}
		return $simpletag_entry;
	}

	static function MatroskaTargetTypeValue($target_type) {
		// http://www.matroska.org/technical/specs/tagging/index.html
		static $MatroskaTargetTypeValue = array();
		if (empty($MatroskaTargetTypeValue)) {
			$MatroskaTargetTypeValue[10] = 'A: ~ V:shot';
			$MatroskaTargetTypeValue[20] = 'A:subtrack/part/movement ~ V:scene';
			$MatroskaTargetTypeValue[30] = 'A:track/song ~ V:chapter';
			$MatroskaTargetTypeValue[40] = 'A:part/session ~ V:part/session';
			$MatroskaTargetTypeValue[50] = 'A:album/opera/concert ~ V:movie/episode/concert';
			$MatroskaTargetTypeValue[60] = 'A:edition/issue/volume/opus ~ V:season/sequel/volume';
			$MatroskaTargetTypeValue[70] = 'A:collection ~ V:collection';
		}
		return (isset($MatroskaTargetTypeValue[$target_type]) ? $MatroskaTargetTypeValue[$target_type] : $target_type);
	}

	static function MatroskaBlockLacingType($lacingtype) {
		// http://matroska.org/technical/specs/index.html#block_structure
		static $MatroskaBlockLacingType = array();
		if (empty($MatroskaBlockLacingType)) {
			$MatroskaBlockLacingType[0x00] = 'no lacing';
			$MatroskaBlockLacingType[0x01] = 'Xiph lacing';
			$MatroskaBlockLacingType[0x02] = 'fixed-size lacing';
			$MatroskaBlockLacingType[0x03] = 'EBML lacing';
		}
		return (isset($MatroskaBlockLacingType[$lacingtype]) ? $MatroskaBlockLacingType[$lacingtype] : $lacingtype);
	}

	static function MatroskaCodecIDtoCommonName($codecid) {
		// http://www.matroska.org/technical/specs/codecid/index.html
		static $MatroskaCodecIDlist = array();
		if (empty($MatroskaCodecIDlist)) {
			$MatroskaCodecIDlist['A_AAC']            = 'aac';
			$MatroskaCodecIDlist['A_AAC/MPEG2/LC']   = 'aac';
			$MatroskaCodecIDlist['A_AC3']            = 'ac3';
			$MatroskaCodecIDlist['A_DTS']            = 'dts';
			$MatroskaCodecIDlist['A_FLAC']           = 'flac';
			$MatroskaCodecIDlist['A_MPEG/L1']        = 'mp1';
			$MatroskaCodecIDlist['A_MPEG/L2']        = 'mp2';
			$MatroskaCodecIDlist['A_MPEG/L3']        = 'mp3';
			$MatroskaCodecIDlist['A_PCM/INT/LIT']    = 'pcm';
			$MatroskaCodecIDlist['A_PCM/INT/BIG']    = 'pcm';
			$MatroskaCodecIDlist['A_QUICKTIME/QDMC'] = 'quicktime';
			$MatroskaCodecIDlist['A_QUICKTIME/QDM2'] = 'quicktime';
			$MatroskaCodecIDlist['A_VORBIS']         = 'vorbis';
			$MatroskaCodecIDlist['V_MPEG1']          = 'mpeg';
			$MatroskaCodecIDlist['V_THEORA']         = 'theora';
			$MatroskaCodecIDlist['V_REAL/RV40']      = 'real';
			$MatroskaCodecIDlist['V_REAL/RV10']      = 'real';
			$MatroskaCodecIDlist['V_REAL/RV20']      = 'real';
			$MatroskaCodecIDlist['V_REAL/RV30']      = 'real';
			$MatroskaCodecIDlist['V_QUICKTIME']      = 'quicktime';
			$MatroskaCodecIDlist['V_MPEG4/ISO/AP']   = 'mpeg4';
			$MatroskaCodecIDlist['V_MPEG4/ISO/ASP']  = 'mpeg4';
			$MatroskaCodecIDlist['V_MPEG4/ISO/AVC']  = 'h264';
			$MatroskaCodecIDlist['V_MPEG4/ISO/SP']   = 'mpeg4';
		}
		return (isset($MatroskaCodecIDlist[$codecid]) ? $MatroskaCodecIDlist[$codecid] : $codecid);
	}

	static function EBMLidName($value) {
		static $EBMLidList = array();
		if (empty($EBMLidList)) {
			$EBMLidList[EBML_ID_ASPECTRATIOTYPE]            = 'AspectRatioType';
			$EBMLidList[EBML_ID_ATTACHEDFILE]               = 'AttachedFile';
			$EBMLidList[EBML_ID_ATTACHMENTLINK]             = 'AttachmentLink';
			$EBMLidList[EBML_ID_ATTACHMENTS]                = 'Attachments';
			$EBMLidList[EBML_ID_ATTACHMENTUID]              = 'AttachmentUID';
			$EBMLidList[EBML_ID_AUDIO]                      = 'Audio';
			$EBMLidList[EBML_ID_BITDEPTH]                   = 'BitDepth';
			$EBMLidList[EBML_ID_CHANNELPOSITIONS]           = 'ChannelPositions';
			$EBMLidList[EBML_ID_CHANNELS]                   = 'Channels';
			$EBMLidList[EBML_ID_CHAPCOUNTRY]                = 'ChapCountry';
			$EBMLidList[EBML_ID_CHAPLANGUAGE]               = 'ChapLanguage';
			$EBMLidList[EBML_ID_CHAPPROCESS]                = 'ChapProcess';
			$EBMLidList[EBML_ID_CHAPPROCESSCODECID]         = 'ChapProcessCodecID';
			$EBMLidList[EBML_ID_CHAPPROCESSCOMMAND]         = 'ChapProcessCommand';
			$EBMLidList[EBML_ID_CHAPPROCESSDATA]            = 'ChapProcessData';
			$EBMLidList[EBML_ID_CHAPPROCESSPRIVATE]         = 'ChapProcessPrivate';
			$EBMLidList[EBML_ID_CHAPPROCESSTIME]            = 'ChapProcessTime';
			$EBMLidList[EBML_ID_CHAPSTRING]                 = 'ChapString';
			$EBMLidList[EBML_ID_CHAPTERATOM]                = 'ChapterAtom';
			$EBMLidList[EBML_ID_CHAPTERDISPLAY]             = 'ChapterDisplay';
			$EBMLidList[EBML_ID_CHAPTERFLAGENABLED]         = 'ChapterFlagEnabled';
			$EBMLidList[EBML_ID_CHAPTERFLAGHIDDEN]          = 'ChapterFlagHidden';
			$EBMLidList[EBML_ID_CHAPTERPHYSICALEQUIV]       = 'ChapterPhysicalEquiv';
			$EBMLidList[EBML_ID_CHAPTERS]                   = 'Chapters';
			$EBMLidList[EBML_ID_CHAPTERSEGMENTEDITIONUID]   = 'ChapterSegmentEditionUID';
			$EBMLidList[EBML_ID_CHAPTERSEGMENTUID]          = 'ChapterSegmentUID';
			$EBMLidList[EBML_ID_CHAPTERTIMEEND]             = 'ChapterTimeEnd';
			$EBMLidList[EBML_ID_CHAPTERTIMESTART]           = 'ChapterTimeStart';
			$EBMLidList[EBML_ID_CHAPTERTRACK]               = 'ChapterTrack';
			$EBMLidList[EBML_ID_CHAPTERTRACKNUMBER]         = 'ChapterTrackNumber';
			$EBMLidList[EBML_ID_CHAPTERTRANSLATE]           = 'ChapterTranslate';
			$EBMLidList[EBML_ID_CHAPTERTRANSLATECODEC]      = 'ChapterTranslateCodec';
			$EBMLidList[EBML_ID_CHAPTERTRANSLATEEDITIONUID] = 'ChapterTranslateEditionUID';
			$EBMLidList[EBML_ID_CHAPTERTRANSLATEID]         = 'ChapterTranslateID';
			$EBMLidList[EBML_ID_CHAPTERUID]                 = 'ChapterUID';
			$EBMLidList[EBML_ID_CLUSTER]                    = 'Cluster';
			$EBMLidList[EBML_ID_CLUSTERBLOCK]               = 'ClusterBlock';
			$EBMLidList[EBML_ID_CLUSTERBLOCKADDID]          = 'ClusterBlockAddID';
			$EBMLidList[EBML_ID_CLUSTERBLOCKADDITIONAL]     = 'ClusterBlockAdditional';
			$EBMLidList[EBML_ID_CLUSTERBLOCKADDITIONID]     = 'ClusterBlockAdditionID';
			$EBMLidList[EBML_ID_CLUSTERBLOCKADDITIONS]      = 'ClusterBlockAdditions';
			$EBMLidList[EBML_ID_CLUSTERBLOCKDURATION]       = 'ClusterBlockDuration';
			$EBMLidList[EBML_ID_CLUSTERBLOCKGROUP]          = 'ClusterBlockGroup';
			$EBMLidList[EBML_ID_CLUSTERBLOCKMORE]           = 'ClusterBlockMore';
			$EBMLidList[EBML_ID_CLUSTERBLOCKVIRTUAL]        = 'ClusterBlockVirtual';
			$EBMLidList[EBML_ID_CLUSTERCODECSTATE]          = 'ClusterCodecState';
			$EBMLidList[EBML_ID_CLUSTERDELAY]               = 'ClusterDelay';
			$EBMLidList[EBML_ID_CLUSTERDURATION]            = 'ClusterDuration';
			$EBMLidList[EBML_ID_CLUSTERENCRYPTEDBLOCK]      = 'ClusterEncryptedBlock';
			$EBMLidList[EBML_ID_CLUSTERFRAMENUMBER]         = 'ClusterFrameNumber';
			$EBMLidList[EBML_ID_CLUSTERLACENUMBER]          = 'ClusterLaceNumber';
			$EBMLidList[EBML_ID_CLUSTERPOSITION]            = 'ClusterPosition';
			$EBMLidList[EBML_ID_CLUSTERPREVSIZE]            = 'ClusterPrevSize';
			$EBMLidList[EBML_ID_CLUSTERREFERENCEBLOCK]      = 'ClusterReferenceBlock';
			$EBMLidList[EBML_ID_CLUSTERREFERENCEPRIORITY]   = 'ClusterReferencePriority';
			$EBMLidList[EBML_ID_CLUSTERREFERENCEVIRTUAL]    = 'ClusterReferenceVirtual';
			$EBMLidList[EBML_ID_CLUSTERSILENTTRACKNUMBER]   = 'ClusterSilentTrackNumber';
			$EBMLidList[EBML_ID_CLUSTERSILENTTRACKS]        = 'ClusterSilentTracks';
			$EBMLidList[EBML_ID_CLUSTERSIMPLEBLOCK]         = 'ClusterSimpleBlock';
			$EBMLidList[EBML_ID_CLUSTERTIMECODE]            = 'ClusterTimecode';
			$EBMLidList[EBML_ID_CLUSTERTIMESLICE]           = 'ClusterTimeSlice';
			$EBMLidList[EBML_ID_CODECDECODEALL]             = 'CodecDecodeAll';
			$EBMLidList[EBML_ID_CODECDOWNLOADURL]           = 'CodecDownloadURL';
			$EBMLidList[EBML_ID_CODECID]                    = 'CodecID';
			$EBMLidList[EBML_ID_CODECINFOURL]               = 'CodecInfoURL';
			$EBMLidList[EBML_ID_CODECNAME]                  = 'CodecName';
			$EBMLidList[EBML_ID_CODECPRIVATE]               = 'CodecPrivate';
			$EBMLidList[EBML_ID_CODECSETTINGS]              = 'CodecSettings';
			$EBMLidList[EBML_ID_COLOURSPACE]                = 'ColourSpace';
			$EBMLidList[EBML_ID_CONTENTCOMPALGO]            = 'ContentCompAlgo';
			$EBMLidList[EBML_ID_CONTENTCOMPRESSION]         = 'ContentCompression';
			$EBMLidList[EBML_ID_CONTENTCOMPSETTINGS]        = 'ContentCompSettings';
			$EBMLidList[EBML_ID_CONTENTENCALGO]             = 'ContentEncAlgo';
			$EBMLidList[EBML_ID_CONTENTENCKEYID]            = 'ContentEncKeyID';
			$EBMLidList[EBML_ID_CONTENTENCODING]            = 'ContentEncoding';
			$EBMLidList[EBML_ID_CONTENTENCODINGORDER]       = 'ContentEncodingOrder';
			$EBMLidList[EBML_ID_CONTENTENCODINGS]           = 'ContentEncodings';
			$EBMLidList[EBML_ID_CONTENTENCODINGSCOPE]       = 'ContentEncodingScope';
			$EBMLidList[EBML_ID_CONTENTENCODINGTYPE]        = 'ContentEncodingType';
			$EBMLidList[EBML_ID_CONTENTENCRYPTION]          = 'ContentEncryption';
			$EBMLidList[EBML_ID_CONTENTSIGALGO]             = 'ContentSigAlgo';
			$EBMLidList[EBML_ID_CONTENTSIGHASHALGO]         = 'ContentSigHashAlgo';
			$EBMLidList[EBML_ID_CONTENTSIGKEYID]            = 'ContentSigKeyID';
			$EBMLidList[EBML_ID_CONTENTSIGNATURE]           = 'ContentSignature';
			$EBMLidList[EBML_ID_CRC32]                      = 'CRC32';
			$EBMLidList[EBML_ID_CUEBLOCKNUMBER]             = 'CueBlockNumber';
			$EBMLidList[EBML_ID_CUECLUSTERPOSITION]         = 'CueClusterPosition';
			$EBMLidList[EBML_ID_CUECODECSTATE]              = 'CueCodecState';
			$EBMLidList[EBML_ID_CUEPOINT]                   = 'CuePoint';
			$EBMLidList[EBML_ID_CUEREFCLUSTER]              = 'CueRefCluster';
			$EBMLidList[EBML_ID_CUEREFCODECSTATE]           = 'CueRefCodecState';
			$EBMLidList[EBML_ID_CUEREFERENCE]               = 'CueReference';
			$EBMLidList[EBML_ID_CUEREFNUMBER]               = 'CueRefNumber';
			$EBMLidList[EBML_ID_CUEREFTIME]                 = 'CueRefTime';
			$EBMLidList[EBML_ID_CUES]                       = 'Cues';
			$EBMLidList[EBML_ID_CUETIME]                    = 'CueTime';
			$EBMLidList[EBML_ID_CUETRACK]                   = 'CueTrack';
			$EBMLidList[EBML_ID_CUETRACKPOSITIONS]          = 'CueTrackPositions';
			$EBMLidList[EBML_ID_DATEUTC]                    = 'DateUTC';
			$EBMLidList[EBML_ID_DEFAULTDURATION]            = 'DefaultDuration';
			$EBMLidList[EBML_ID_DISPLAYHEIGHT]              = 'DisplayHeight';
			$EBMLidList[EBML_ID_DISPLAYUNIT]                = 'DisplayUnit';
			$EBMLidList[EBML_ID_DISPLAYWIDTH]               = 'DisplayWidth';
			$EBMLidList[EBML_ID_DOCTYPE]                    = 'DocType';
			$EBMLidList[EBML_ID_DOCTYPEREADVERSION]         = 'DocTypeReadVersion';
			$EBMLidList[EBML_ID_DOCTYPEVERSION]             = 'DocTypeVersion';
			$EBMLidList[EBML_ID_DURATION]                   = 'Duration';
			$EBMLidList[EBML_ID_EBMLMAXIDLENGTH]            = 'EBMLMaxIDLength';
			$EBMLidList[EBML_ID_EBMLMAXSIZELENGTH]          = 'EBMLMaxSizeLength';
			$EBMLidList[EBML_ID_EBMLREADVERSION]            = 'EBMLReadVersion';
			$EBMLidList[EBML_ID_EBMLVERSION]                = 'EBMLVersion';
			$EBMLidList[EBML_ID_EDITIONENTRY]               = 'EditionEntry';
			$EBMLidList[EBML_ID_EDITIONFLAGDEFAULT]         = 'EditionFlagDefault';
			$EBMLidList[EBML_ID_EDITIONFLAGHIDDEN]          = 'EditionFlagHidden';
			$EBMLidList[EBML_ID_EDITIONFLAGORDERED]         = 'EditionFlagOrdered';
			$EBMLidList[EBML_ID_EDITIONUID]                 = 'EditionUID';
			$EBMLidList[EBML_ID_FILEDATA]                   = 'FileData';
			$EBMLidList[EBML_ID_FILEDESCRIPTION]            = 'FileDescription';
			$EBMLidList[EBML_ID_FILEMIMETYPE]               = 'FileMimeType';
			$EBMLidList[EBML_ID_FILENAME]                   = 'FileName';
			$EBMLidList[EBML_ID_FILEREFERRAL]               = 'FileReferral';
			$EBMLidList[EBML_ID_FILEUID]                    = 'FileUID';
			$EBMLidList[EBML_ID_FLAGDEFAULT]                = 'FlagDefault';
			$EBMLidList[EBML_ID_FLAGENABLED]                = 'FlagEnabled';
			$EBMLidList[EBML_ID_FLAGFORCED]                 = 'FlagForced';
			$EBMLidList[EBML_ID_FLAGINTERLACED]             = 'FlagInterlaced';
			$EBMLidList[EBML_ID_FLAGLACING]                 = 'FlagLacing';
			$EBMLidList[EBML_ID_GAMMAVALUE]                 = 'GammaValue';
			$EBMLidList[EBML_ID_INFO]                       = 'Info';
			$EBMLidList[EBML_ID_LANGUAGE]                   = 'Language';
			$EBMLidList[EBML_ID_MAXBLOCKADDITIONID]         = 'MaxBlockAdditionID';
			$EBMLidList[EBML_ID_MAXCACHE]                   = 'MaxCache';
			$EBMLidList[EBML_ID_MINCACHE]                   = 'MinCache';
			$EBMLidList[EBML_ID_MUXINGAPP]                  = 'MuxingApp';
			$EBMLidList[EBML_ID_NAME]                       = 'Name';
			$EBMLidList[EBML_ID_NEXTFILENAME]               = 'NextFilename';
			$EBMLidList[EBML_ID_NEXTUID]                    = 'NextUID';
			$EBMLidList[EBML_ID_OUTPUTSAMPLINGFREQUENCY]    = 'OutputSamplingFrequency';
			$EBMLidList[EBML_ID_PIXELCROPBOTTOM]            = 'PixelCropBottom';
			$EBMLidList[EBML_ID_PIXELCROPLEFT]              = 'PixelCropLeft';
			$EBMLidList[EBML_ID_PIXELCROPRIGHT]             = 'PixelCropRight';
			$EBMLidList[EBML_ID_PIXELCROPTOP]               = 'PixelCropTop';
			$EBMLidList[EBML_ID_PIXELHEIGHT]                = 'PixelHeight';
			$EBMLidList[EBML_ID_PIXELWIDTH]                 = 'PixelWidth';
			$EBMLidList[EBML_ID_PREVFILENAME]               = 'PrevFilename';
			$EBMLidList[EBML_ID_PREVUID]                    = 'PrevUID';
			$EBMLidList[EBML_ID_SAMPLINGFREQUENCY]          = 'SamplingFrequency';
			$EBMLidList[EBML_ID_SEEK]                       = 'Seek';
			$EBMLidList[EBML_ID_SEEKHEAD]                   = 'SeekHead';
			$EBMLidList[EBML_ID_SEEKID]                     = 'SeekID';
			$EBMLidList[EBML_ID_SEEKPOSITION]               = 'SeekPosition';
			$EBMLidList[EBML_ID_SEGMENTFAMILY]              = 'SegmentFamily';
			$EBMLidList[EBML_ID_SEGMENTFILENAME]            = 'SegmentFilename';
			$EBMLidList[EBML_ID_SEGMENTUID]                 = 'SegmentUID';
			$EBMLidList[EBML_ID_SIMPLETAG]                  = 'SimpleTag';
			$EBMLidList[EBML_ID_CLUSTERSLICES]              = 'ClusterSlices';
			$EBMLidList[EBML_ID_STEREOMODE]                 = 'StereoMode';
			$EBMLidList[EBML_ID_TAG]                        = 'Tag';
			$EBMLidList[EBML_ID_TAGBINARY]                  = 'TagBinary';
			$EBMLidList[EBML_ID_TAGCHAPTERUID]              = 'TagChapterUID';
			$EBMLidList[EBML_ID_TAGDEFAULT]                 = 'TagDefault';
			$EBMLidList[EBML_ID_TAGEDITIONUID]              = 'TagEditionUID';
			$EBMLidList[EBML_ID_TAGLANGUAGE]                = 'TagLanguage';
			$EBMLidList[EBML_ID_TAGNAME]                    = 'TagName';
			$EBMLidList[EBML_ID_TAGTRACKUID]                = 'TagTrackUID';
			$EBMLidList[EBML_ID_TAGS]                       = 'Tags';
			$EBMLidList[EBML_ID_TAGSTRING]                  = 'TagString';
			$EBMLidList[EBML_ID_TARGETS]                    = 'Targets';
			$EBMLidList[EBML_ID_TARGETTYPE]                 = 'TargetType';
			$EBMLidList[EBML_ID_TARGETTYPEVALUE]            = 'TargetTypeValue';
			$EBMLidList[EBML_ID_TIMECODESCALE]              = 'TimecodeScale';
			$EBMLidList[EBML_ID_TITLE]                      = 'Title';
			$EBMLidList[EBML_ID_TRACKENTRY]                 = 'TrackEntry';
			$EBMLidList[EBML_ID_TRACKNUMBER]                = 'TrackNumber';
			$EBMLidList[EBML_ID_TRACKOFFSET]                = 'TrackOffset';
			$EBMLidList[EBML_ID_TRACKOVERLAY]               = 'TrackOverlay';
			$EBMLidList[EBML_ID_TRACKS]                     = 'Tracks';
			$EBMLidList[EBML_ID_TRACKTIMECODESCALE]         = 'TrackTimecodeScale';
			$EBMLidList[EBML_ID_TRACKTRANSLATE]             = 'TrackTranslate';
			$EBMLidList[EBML_ID_TRACKTRANSLATECODEC]        = 'TrackTranslateCodec';
			$EBMLidList[EBML_ID_TRACKTRANSLATEEDITIONUID]   = 'TrackTranslateEditionUID';
			$EBMLidList[EBML_ID_TRACKTRANSLATETRACKID]      = 'TrackTranslateTrackID';
			$EBMLidList[EBML_ID_TRACKTYPE]                  = 'TrackType';
			$EBMLidList[EBML_ID_TRACKUID]                   = 'TrackUID';
			$EBMLidList[EBML_ID_VIDEO]                      = 'Video';
			$EBMLidList[EBML_ID_VOID]                       = 'Void';
			$EBMLidList[EBML_ID_WRITINGAPP]                 = 'WritingApp';
		}
		return (isset($EBMLidList[$value]) ? $EBMLidList[$value] : dechex($value));
	}

}
?>
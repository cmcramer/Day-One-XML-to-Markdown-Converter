<!DOCTYPE html>
<html lang="en">
     <head>
          <title>XML to MD Converter</title>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
     </head>
     <body>
     
     
<?php 
/**
 * 
 * Notes:
 * 	- this only works for the original DayOne app which stored entries as xml files
 * 	- extension of entries must be changed from .doentry to .xml, use cli or automater
 * 	- Destination folder  must have write permissions
 * 
 */

$path = __dir__.'/';
//relative path to your php file where the DayOne Entries are located.
$dirsource = 'Journal_dayone/entries';
$dirdest = 'md-converted';
$headerEnclosedBy = array('[',']');

define('NICE_DATE_FORMAT', 'H:i j M Y');
define('FILENAME_DATE_FORMAT', 'Y-m-d_His');
define('TITLE_HEADER_LEVEL', '##');
define('HEADER_IS_FILE_NAME', false);

$xmlfiles = scandir($path.$dirsource);
//print_r($xmlfiles);
foreach ($xmlfiles as $file) {
	$fileparts = pathinfo($file);
	$fullfile = $path.$dirsource.'/'.$file;
	$fileCount = 0;
	//print_r($fullfile);
	if (file_exists($fullfile) && $fileparts['extension'] == 'xml') {
		//load xml
		$xml = simplexml_load_file($fullfile);
		$arrXml = parseDict($xml->dict);
		//var_dump($arrXml);
		date_default_timezone_set($arrXml['Time Zone']);
		
		$fulldestpath = $path.$dirdest;
		$result = markdownFile($arrXml, $fulldestpath, $headerEnclosedBy);
		
		$fileCount++;
		echo "Wrote file #{$fileCount}: {$result}<br>";
		
		//only write one file for now
		//exit;
	}
}

echo "<h4>Done ({$fileCount} files written)</h4>";

function resetKey() {
	return 'unset';
}
	
/** returns array **/
function parseDict($dict) {
	$arrXml = array();
	$key = resetKey();
	$subDictNum = 0;
	foreach($dict->children() as $value) {
		switch ($value->getName()) {
			case 'key' :
				$key = (string)$value;
				break;
			case 'dict' :
				$arrXml[$key] = parseDict($dict->children()->dict[$subDictNum++]);
				$key = resetKey();
				break;
			case 'integer' :
				$arrXml[$key] = intval($value);
				$key = resetKey();
				break;
			case 'real' :
				$arrXml[$key] = floatval($value);
				$key = resetKey();
				break;
			case 'false' :
				$arrXml[$key] = 0;
				$key = resetKey();
				break;
			case 'true' :
				$arrXml[$key] = 1;
				$key = resetKey();
				break;
			case 'array' :
				$tagArray = array();
				foreach ($dict->array->children() as $value) {
					$tagArray[] = (string) $value;
				}
				$arrXml[$key] = $tagArray;
				$key = resetKey();
				break;
			case 'string' :
			case 'date' :
			default :
				$arrXml[$key] = (string)$value;
				$key = resetKey();
		}
	}
	
	return $arrXml;
}

function niceDate($strTime, $format='j M Y @ H:i') {
		$time = strtotime($strTime);
		return date($format, $time);
}



function markdownFile($arrXml, $fulldestpath, $headerEnclosedBy) {
	$mdFilename = niceDate($arrXml['Creation Date'], FILENAME_DATE_FORMAT). '.md';
	$content = $arrXml['Entry Text'];
	//try to get headline
	$headerText = '';
	if (substr($content, 0, 1) == $headerEnclosedBy[0]) {
		$endPos = strpos($content, $headerEnclosedBy[1]);
		$headerText = substr($content, 1, $endPos-1);
		//set file name to header text
		if (HEADER_IS_FILE_NAME) {
			$mdFilename = alphanumericWithSpaces($headerText) . '.md';
		}
		$content = trim(substr($content, $endPos+1));
	}
	
	$strTags = implode(', ', $arrXml['Tags']);
	
// 		echo '<h2>Date Raw: ' .$arrXml['Creation Date'] . '</h2>';
// 		echo '<h2>Filename: ' .$mdFilename . '</h2>';
// 		echo '<h2>Date: ' . niceDate($arrXml['Creation Date'], NICE_DATE_FORMAT) . '</h2>';
// 		echo '<h2>Sunrise: ' . niceDate($arrXml['Weather']['Sunrise Date'], NICE_DATE_FORMAT) . '</h2>';
// 		echo '<h2>Sunset: ' . niceDate($arrXml['Weather']['Sunset Date'], NICE_DATE_FORMAT) . '</h2>';
// 		echo '<h2>Header: ' .$headerText . '</h2>';
// 		echo '<p>String: ' .nl2br($content) . '</p>';
	
	$headerLevel = TITLE_HEADER_LEVEL;
	$visibility = number_format($arrXml['Weather']['Visibility KM'], 1);
	$niceDate = niceDate($arrXml['Creation Date'], NICE_DATE_FORMAT);
	$starred = '';
	if ($arrXml['Starred'] == 1) {
		$starred = '![Star](images/star.png)';
	}
	$strMd = <<<EOT
{$headerLevel}{$headerText}
{$starred} *{$niceDate}* _{$strTags}_

{$content}

	{$arrXml['Location']['Place Name']}, {$arrXml['Location']['Locality']}, {$arrXml['Location']['Administrative Area']}, {$arrXml['Location']['Country']} {$arrXml['Weather']['Fahrenheit']}F/{$arrXml['Weather']['Celsius']} {$arrXml['Weather']['Description']}
	
	Latitude: {$arrXml['Location']['Latitude']} Longitude: {$arrXml['Location']['Longitude']}
	Sunrise: {$arrXml['Weather']['Sunrise Date']}   Sunset: {$arrXml['Weather']['Sunset Date']}
	Pressure: {$arrXml['Weather']['Pressure MB']}MB Humidity: {$arrXml['Weather']['Relative Humidity']}
	Visibility: {$visibility}KM   Windchill: {$arrXml['Weather']['Wind Chill Celsius']}C
	
	Creation Date UTC: {$arrXml['Creation Date']}
	UUID: {$arrXml['UUID']}
	
		
EOT;
	
	//file path
	$fulldestfile = $fulldestpath.'/'.$mdFilename;
	$filetime = strtotime($arrXml['Creation Date']);
	//create and write file
	file_put_contents($fulldestfile, $strMd);
	//set file time to creation date
	touch($fulldestfile, $filetime);
	
	return $fulldestfile;
}

/*
 * @description Allows spaces, but other non-alphanumeric characters are stripped.
 */
function alphanumericWithSpaces($str) {
        $strSafe = preg_replace("/[^A-Za-z0-9 ]/", '', $str);
        return $strSafe;
}
?>


	</body>
</html>

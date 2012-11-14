<?php 
//
//	file: sync.php
//
//	coder: moenk
//
//	purpose: 	recurses through $geodatapath to find all xml-files and loads them to $xml,
//				namespaces are quick and dirty replaced, then parser is called for data insertion
//

include "conf/config.php";
require_once "dbauth.php";
$username=$_SESSION['username'];

// if admin, sync all directories - if not, just the user's home directory
/*
if ($username=="admin") { 
	$dirname=$geodatapath;
} else {
*/
	$dirname=$geodatapath.strtolower(ereg_replace("[^A-Za-z0-9]","_",$username)."/");
/*
}
*/

$id = intval($_GET['id']);
$title="Sync metadata records";
include "header.php";
include "navigation.php";
include "main1.php";
print "<h3>Syncing metadata from geodata in ".$dirname."</h3>";

$peer=-1; // hardcoded value for local data
$it = new RecursiveDirectoryIterator($dirname);
global $dateiname;
foreach(new RecursiveIteratorIterator($it) as $dateiname) {
  if (strtolower(substr($dateiname,-4))==".xml") {
    print "<p>File: ".$dateiname.": ";
    $md5file=md5($dateiname);
    $xml_string=file_get_contents($dateiname);
    $xml_string = str_replace('gmd:','gmd_',$xml_string);
    $xml_string = str_replace('gco:','gco_',$xml_string);
    $xml=simplexml_load_string($xml_string);
	global $grs;
	global $title;	// now the title of the layer
	global $metaid;
	global $format;
    include "parser.php";

// create a mapfile if this is a shapefile an we have a umn-mapserver installed
	if (($mapserverurl!="") && ($grs!="") && (($format=="Shapefile") or ($format=="Raster-Dataset"))) {
		$path_parts = pathinfo($dateiname);
		// this is a local file, we need a link to its directory for download, ignoring meta from xml
		$linkage="WWW:DOWNLOAD-1.0-http--download ".$domainroot.$path_parts['dirname'];
		$shapefile=substr($dateiname,0,-4);
		$mapfile=substr($dateiname,0,-8).".map";
		if (!$handle = fopen($mapfile,'w')) {
			echo "Cannot open file ($mapfile)";
			die();
		}
	  
if ($format=="Shapefile") $map='MAP
  NAME "'.$title.'"
  STATUS ON
  SIZE 800 600
  EXTENT -180 -90 180 90 
  IMAGECOLOR 255 255 255

  WEB
    IMAGEPATH "/var/www/gdi.geo.hu-berlin.de/files/"
    IMAGEURL "/files/"
    METADATA
      "wms_title"     		"'.$title.' WMS" 
      "wms_onlineresource" 	"'.$mapserverurl.'?map='.$mapfile.'&"   
      "wms_srs"       		"EPSG:4326 EPSG:4269 EPSG:3978 EPSG:3857"  
      "wms_enable_request" 	"*"   
	  "wfs_title"          	"'.$title.' WFS" 
      "wfs_onlineresource" 	"'.$mapserverurl.'?map='.$mapfile.'&" 
      "wfs_srs"            	"EPSG:4326 EPSG:4269 EPSG:3978 EPSG:3857" 
      "wfs_abstract"       	"This text describes my WFS service." 
      "wfs_enable_request" 	"*"  
    END
  END

  PROJECTION
    "init='.strtolower($grs).'"
  END

  LAYER
    NAME "'.$title.'"
    TYPE POLYGON 
    STATUS ON 
	DUMP ON
    DATA "'.$shapefile.'"
    METADATA
      "wms_title"    "'.$title.'"   
      "wfs_title"    "'.$title.'"   
      "wfs_srs"           "'.strtolower($grs).'" 
      "gml_include_items" "all" 
      "gml_featureid"     "ID" 
      "wfs_enable_request" "*"
    END
    PROJECTION
      "init='.strtolower($grs).'"
    END
    CLASS
      NAME "World"
      STYLE
        COLOR 255 255 192
        OUTLINECOLOR 128 128 128
      END
    END
  END # Layer

END # Map File
';

if ($format=="Raster-Dataset") $map='MAP
  NAME "'.$title.'"
  STATUS ON
  SIZE 800 600
  EXTENT -180 -90 180 90 
  IMAGECOLOR 255 255 255

  WEB
    IMAGEPATH "/var/www/gdi.geo.hu-berlin.de/files/"
    IMAGEURL "/files/"
    METADATA
      "wms_title"     		"'.$title.' WMS" 
      "wms_onlineresource" 	"'.$mapserverurl.'?map='.$mapfile.'&"   
      "wms_srs"       		"EPSG:4326 EPSG:4269 EPSG:3978 EPSG:3857"  
      "wms_enable_request" 	"*"   
    END
  END

  PROJECTION
    "init='.strtolower($grs).'"
  END

  LAYER
    NAME "'.$title.'"
    DATA "'.$shapefile.'"
	TYPE RASTER
	STATUS ON
	END
   
  END
  
END
';

	  fwrite($handle,$map);
	  fclose($handle);
	  print "<p>Mapfile created: ".$mapfile;
	  // update record with the new WMS/WFS getcapa url
	  $wms=$mapserverurl."?map=".$mapfile."&service=wms&version=1.1.1&request=GetCapabilities";
	  $wfs=$mapserverurl."?map=".$mapfile."&service=wfs&version=1.1.0&request=GetCapabilities";
/*
	  $sql="update metadata set wms='".$wms."', linkage=concat(linkage,' OGC:WMS-1.1.1-http-get-capabilities ".$wms."',' OGC:WFS-1.1.0-http-get-capabilities ".$wfs."'), keywords=concat(keywords,', WMS, WFS') where uuid='".$metaid."';";
*/
      $linkage.=" OGC:WMS-1.1.1-http-get-capabilities ".$wms." OGC:WFS-1.1.0-http-get-capabilities ".$wfs;
	  $sql="update metadata set wms='".$wms."', linkage='".$linkage."' where uuid='".$metaid."';";
	  $results = mysql_query($sql);  
	  if($results) { 
		print ", updated GetCapabilties for WMS and WFS.</p>"; 
	  } else { 
		die('Invalid query: '.mysql_error()); 
	  }
	}  
  }
  print "<p>\n"; // next file please
}
include "main2.php";
include "footer.php";
?>

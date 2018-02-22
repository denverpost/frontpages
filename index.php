<?php

error_reporting( E_ALL );
ini_set('display_startup_errors',1);
ini_set('display_errors',1);

$error_out = false;

function ftp_is_dir($ftp, $dir) {
    $pushd = ftp_pwd($ftp);
    if ($pushd !== false && @ftp_chdir($ftp, $dir)) {
        ftp_chdir($ftp, $pushd);   
        return true;
    }
    return false;
} 

if(isset($_FILES["image"])) {
	if ($_FILES["image"]["error"] > 0) {
		if ( $_FILES["image"]["error"] == 4 ) {
			// No image file was uploaded
			$error_out = '<div data-alert class="alert-box warning">No Image was chosen to be uploaded</div>'; 
		} else {
			// Another error occurred
			$error_out = '<div data-alert class="alert-box warning">Error Code: ' . $_FILES["image"]["error"] . '</div>';
		}
	} else {
		require('constants.php');
		$conn_id = ftp_connect($FTP_SERVER) or die("Couldn't connect to $ftp_server");
		ftp_login($conn_id,$FTP_USER_NAME,$FTP_USER_PASS);
		ftp_pasv($conn_id, TRUE);
		$selectedDate = explode("/", $_POST["date"]);
		$month    = $selectedDate[0];
		$day      = $selectedDate[1];
		$year     = $selectedDate[2];
		// location the file will be put into
		$location = "/".$year."/".$month."/";
		// rename the file to today's date
		$_FILES["image"]["name"] = $year."-".$month."-".$day; 

		if (!ftp_is_dir($conn_id, $FTP_DIRECTORY."/".$year."/")) {
			// if the year folder does not exist, create it
			ftp_mkdir($conn_id, $FTP_DIRECTORY."/".$year);
		} 
		if (!ftp_is_dir($conn_id, $FTP_DIRECTORY."/".$year."/".$month."/")) {
			// if the month folder does not exist, create it
			ftp_mkdir($conn_id, $FTP_DIRECTORY."/".$year."/".$month);
		}
		
		if (($_FILES["image"]["type"]=="application/pdf") || ($_FILES["image"]["type"]=="image/jpeg")) {
			$extension = ".pdf";
			if($_FILES["image"]["type"]=="image/jpeg") {
				$extension = "-original.jpg";
			}
			// drop original file in current folder for imagick to use
			move_uploaded_file($_FILES["image"]["tmp_name"], $_FILES["image"]["name"].$extension); 
			$im = new imagick();
			$im->setResolution(72,72);
			$im->readimage($_FILES["image"]["name"].$extension);
			$im->setImageFormat('jpg');
			$im->scaleImage(350,0);
			$im->writeImage($_FILES["image"]["name"].".jpg"); // Create the smaller jpg version in current folder
			$im->clear();

			if (ftp_put($conn_id, $FTP_DIRECTORY.$location.$_FILES["image"]["name"].$extension, $_FILES["image"]["name"].$extension, FTP_BINARY)) {
				$error_out = '<div data-alert class="alert-box success">Original file uploaded!</div>';
				}
			else { $error_out = '<div data-alert class="alert-box alert">ERROR: The original file failed to upload!</div>'; }
			if (ftp_put($conn_id, $FTP_DIRECTORY.$location.$_FILES["image"]["name"].".jpg", $_FILES["image"]["name"].".jpg", FTP_BINARY)) {
				
				$filename = '"'.$_FILES["image"]["name"].'"';
				$json_a = explode("[", file_get_contents("frontpages.json"));
				$json_b = explode("]", $json_a[1]);
				$json_c = explode(",", $json_b[0]);

				if (!in_array($filename, $json_c)) {
					if ($filename > $json_c[0]) { // date is today, add to beginning
						array_unshift($json_c, $filename);
						}
					else {
						for($i=0;$i<sizeof($json_c);$i++) {
							$i2 = $i+1;
							if($i2<sizeof($json_c)) {
								if(($json_c[$i] > $filename)&&($json_c[$i2] < $filename)) { // date is somewhere in between, find the right spot and drop it in
									array_splice($json_c, $i2, 0, $filename);
									break;
									}
								}
							else { // date is older than anything in array, add to end
								array_push($json_c, $filename);
								break; // prevents infinite loop
								}
							}
						}

					$newString = "frontPages({arr:[";
					for($i=0;$i<sizeof($json_c);$i++) {
						$newString .= $json_c[$i];
						if( $i < sizeof($json_c)-1 ) { $newString .= ","; }
						}

					$newString .= "]})";

					file_put_contents("frontpages.json", $newString);
					ftp_put($conn_id, $FTP_DIRECTORY."/frontpages.json", "frontpages.json", FTP_BINARY);
					}
				$error_out = '<div data-alert class="alert-box success">.jpg created and uploaded!</div>';
				}
			else { $error_out = '<div data-alert class="alert-box alert">ERROR: The .jpg file failed to upload!</div>'; }

			unlink($_FILES["image"]["name"].".jpg");     // delete the jpg file in current folder
			unlink($_FILES["image"]["name"].$extension); // delete the pdf file in current folder
			}

		else { $error_out = '<div data-alert class="alert-box alert">File must be a PDF or JPG file!<br> This file is: ' . $_FILES["image"]["type"] . '</div>'; }
		ftp_close($conn_id);
	}
}
?>

<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Denver Post Front Pages Uploader</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="//extras.denverpost.com/transgender/css/normalize.css" />
    <link rel="stylesheet" href="//extras.denverpost.com/transgender/css/foundation.min.css" />
    <link href='https://fonts.googleapis.com/css?family=Noticia+Text:400,700,400italic,700italic|PT+Sans:400,700,400italic,700italic|PT+Sans+Narrow:400,700' rel='stylesheet' type='text/css'>
    <link rel="shortcut icon" href="https://plus.denverpost.com/favicon.ico" type="image/x-icon" />
	<link rel="stylesheet" href="assets/jquery-ui.css">
	<script src="assets/jquery-latest.min.js"></script>
	<script src="assets/jquery-ui.js"></script>
	<style type="text/css">
		div#radios {
			padding-left: 4em;
		}
		div#radios input {
			margin-bottom: .4em;
		}
		div#radios {
			line-height:1.5
		}
		button.ui-datepicker-trigger {
			background-color:rgba(0,100,255,0.25);
			height:28px;
			width:28px;
			padding:0;
		}
		button.ui-datepicker-trigger img {
			width:100%;
		}
		div#uploadHere {
			margin:1.5em 0;
		}
		div#uploadHere input {
			padding-left: 4em;
		}
		#submitButton {

		}
		p.grey {
			line-height: 2;
			padding-left: 5em;
			font-size: .85em;
			color:rgba(0,0,0,0.6);
		}
		fieldset {
			padding:1em 2em 0;
		}
	</style>
	<script>
		var date       = new Date();
		var curr_year  = date.getFullYear();
		var curr_month = date.getMonth()+1; if(curr_month<10) curr_month='0'+curr_month;
		var curr_day   = date.getDate(); if(curr_day<10) curr_day='0'+curr_day;
		var sel_year   = curr_year;
		var sel_month  = curr_month;
		var sel_day    = curr_day;
		var today      = curr_month+"/"+curr_day+"/"+curr_year;
		var newdate = new Date(curr_year,curr_month,curr_day);
		newdate.setDate(newdate.getDate() + 1);
		var nd        = new Date(newdate);
		var tom_year  = nd.getFullYear();
		var tom_month = nd.getMonth(); if(tom_month<10) tom_month='0'+tom_month;
		var tom_day   = nd.getDate();  if(tom_day<10) tom_day='0'+tom_day;
		var tomorrow  = tom_month+"/"+tom_day+"/"+tom_year;
		function dateFieldChange(){
			var d = $('#dateField').val();
			if (d.indexOf("-") != -1) { d = d.replace(/\-/g,'/') }
			if (!$.isNumeric(d.substring(0,1))) {
				d = d.replace(",", "").replace("th", "").replace("rd", "");
				d2 = d.split(' ');
				switch(d2[0].substring(0,3).toLowerCase()){
					case "jan": sel_month='01'; break; case "feb": sel_month='02'; break; case "mar": sel_month='03'; break; case "apr": sel_month='04'; break;
					case "may": sel_month='05'; break; case "jun": sel_month='06'; break; case "jul": sel_month='07'; break; case "aug": sel_month='08'; break;
					case "sep": sel_month='09'; break; case "oct": sel_month='10'; break; case "nov": sel_month='11'; break; case "dec": sel_month='12'; break;
					}
				sel_day  = parseInt(d2[1]); if(sel_day<10)         { sel_day='0'+sel_day; }
				sel_year = parseInt(d2[2]); if(sel_year.length<3)  { sel_year='20'+sel_year; }
				$('#datepicker, #dateField, #custom').val(sel_month+"/"+sel_day+"/"+sel_year);
				}
			else { $('#datepicker, #datepicker, #custom').val(d); }
			var nm = parseInt(sel_month)-1;
			if(new Date(sel_year,nm,sel_day) > new Date()) { $('#datepicker, #dateField, #custom').val(curr_month+"/"+curr_day+"/"+curr_year); updateImage(); }
			}

		function populateInput() {
			$('#dateField, #custom').val($('#datepicker').val());
			$('#custom').val($('#datepicker').val());
			$('#message').remove();
		}
	</script>

</head>
<body style="margin:0;">

<section id="main" style="margin:0;">
	<div style="background:#e1e1e1;border-bottom:10px solid #b1b1b1;padding:2em 0;margin:0 0 2em;text-align:center;">
        <h1>Denver Post Front Pages uploader</h1>
    </div>

	<div class="row">
        <div class="large-9 large-centered columns">
			<form action="" id="upLoadImage" name='upLoadImage' method='post' enctype="multipart/form-data">
				<fieldset>
					<div class="row">
						<div id="errorOut">
							<?php echo $error_out ?>
						</div>
					</div>
					<h5>This front page is for:</h5>
					<div class="row">
				        <div class="large-6 columns">
							<div id="radios">
								<input type="radio" name="date" id="today" value="today" checked> Today<br />
								<input type="radio" name="date" id="tomorrow" value="tomorrow"> Tomorrow<br />
								<input type="radio" name="date" id="custom" value="custom" onchange="populateInput()"> Custom date:
							</div>
						</div>
				        <div class="large-6 columns">
							<form name="chooseDateForm" id="chooseDateForm">
							   <input type="text" id="dateField" onchange="dateFieldChange()">
							   <input type="hidden" id="datepicker" onchange="populateInput()">
							</form>
						</div>
					</div>
					<div class="row">
				        <div class="large-12 columns">
							<div id="uploadHere">
								<h5>Image to upload:</h5> <input name="image" type="file">
								<p class="grey"><em>This should the <strong>unlocked</strong> PDF from dp_stor. <br />If the PDF file is too large, you can <a href="https://smallpdf.com/compress-pdf">use Smallpdf to optimize it</a> before uploading!</em></p>
							</div>
						</div>
					</div>
					<div class="row">
						<input class="button large-12" id="submitButton" type="submit" name="submit" value="Upload the PDF" onclick="populateInput();">
					</div>
				</form>
			</fieldset>
		</div>
	</div>
</section>

<script>
$(function(){
	$('#datepicker').datepicker({
		inline: true,
		nextText: '&rarr;',
		prevText: '&larr;',
		showOtherMonths: true,
		dateFormat: 'mm/dd/yy',
		dayNamesMin: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
		showOn: "button",
		buttonImage: "https://extras.denverpost.com/frontpages/calendar.svg",
		buttonImageOnly: false,
	});
	$('#datepicker, #dateField').val(sel_month+"/"+sel_day+"/"+sel_year);
	$('#chooseDateForm').submit(function () { return false; });
	$('#today').val(today);
	$('#tomorrow').val(tomorrow);
	$('.ui-datepicker-trigger, #dateField').click(function(){
		$('#today, #tomorrow').prop('checked', false);
		$('#custom').prop('checked', true);
		});
});
</script>

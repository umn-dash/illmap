<?php

// Geocodes addresses for April 2014 ILL data
// Part of VISUALIZING INTERLIBRARY LOAN DATA project
// This script written and used by Steven Braun (sbraun@umn.edu)

// Script adapted specifically for PHP/MySQL interface from a script written by
// Kevin Dyke (kevindyke@umn.edu)


ini_set('auto_detect_line_endings',true);

// Open files with addresses
$borrowingFile = file_get_contents("output/borrowing_allTransactions.json");
$lendingFile = file_get_contents("output/lending_allTransactions.json");

$borrowing = json_decode($borrowingFile,true);
$lending = json_decode($lendingFile,true);

$dataPointers = array("Lending" => "lending",
					  "Borrowing" => "borrowing");

$geocodeArray = array();
$totalCounter = 0;
$newCounter = 0;
$totalRecordCount = count($lending) + count($borrowing);
$fields = array("Address1","Address2","Address3","Address4");
$output = fopen("output/address_geocodes.csv","w");


foreach($dataPointers as $type => $pointer) {
	$data = $$pointer;
	foreach($data as $record => $recordInfo) {
		print ++$totalCounter . "/" . $totalRecordCount . "\n";

		$lender = $recordInfo['Lender'];
		$lenderCode = $lender['LendingLibrary'];
		$addressNumber = $lender['LenderAddressNumber'];

		if(!array_key_exists($lenderCode, $geocodeArray)) {
			print "\t" . ++$counter . "\n";
			$addressArray = array();
			$outputData = array();
			$formattedAddress = "";
			$lat = "";
			$lon = "";
			foreach($lender as $key => $value) {
				$outputData[] = $value;
				if(in_array($key,$fields)) {
					if(trim($value) !== "") {
						$addressArray[] = trim($value);
					}
				}
			}
			$addressString = implode(',',$addressArray);
			$geocodeArray[$lenderCode] = array("addressString" => $addressString,
											   "lat" => null,
											   "lon" => null);


			// Open CURL request to query Google Maps API, obtain lat/lon coordinates
			$url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($addressString);

			$openCurl = curl_init();

			curl_setopt_array($openCurl, array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HEADER => 0,
				CURLOPT_URL => $url
			));

			$result = curl_exec($openCurl);

			if($result === false) {
				$status = "CURLERROR";
			} else {
				$data = json_decode($result,true);
				$formattedAddress = $data["results"][0]["formatted_address"];
				$lat = $data["results"][0]["geometry"]["location"]["lat"];
				$lon = $data["results"][0]["geometry"]["location"]["lng"];
				$status = $data["status"];
			}
			$outputData[] = $status;
			$outputData[] = $lat;
			$outputData[] = $lon;
			$outputData[] = $formattedAddress;

			fputcsv($output,$outputData);

		}
	}
}



?>
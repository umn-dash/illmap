<!-- VISUALIZING INTERLIBRARY LOAN DATA

Description: This script provides a template describing how to make an animated map in D3.js
that shows interlibrary loan transactions into and out of a university library.

Origin:
	This script written by Steven Braun (sbraun@umn.edu)
	Project Collaborators:
		Steven Braun (sbraun@umn.edu)
		Kevin Dyke (kevindyke@umn.edu)
		Meghan Lafferty (mlaffert@umn.edu)
		Amy Neeser (nees0017@umn.edu)
		Emily Riha (emilymr@umn.edu)
		Justin Schell (schel115@umn.edu)
		
	Project presented at 
		2014 Digital Library Federation Forum in Atlanta, GA
		October 27, 2014
		By Amy Neeser, Justin Schell
	
	Script cleaned for sharing 12/1/2014
	
-->

<!DOCTYPE html>
<head>
	<title>Visualizing ILL Transactions in the University of Minnesota Libraries</title>
	<meta charset="utf-8">
	
	<!-- Load relevant libraries -->
    <script src="inc/d3.min.js"></script>
    <script src="inc/topojson.v1.min.js"></script>
    <script src="inc/jquery-1.10.2.min.js"></script>

	<!-- Specify style -->
	<style>

	html {
		margin:0px;
		padding: 0px;
		overflow:hidden;
	}

	svg {
		z-index:100;
		position: absolute;
		top: 0px;
		left: 0px;
		background-color: #e3e3e3;
		border: none;
	}

	path {
		fill: gray;
		stroke: #ffffff;
		stroke-width: .5px;
	}

	.line_lending {
		stroke-width:1px;
		stroke: red;
		
	}

	.line_borrowing {
		stroke-width:1px;
		stroke: blue;
	}

	.circle_lending {
		stroke-width: 1px;
		stroke: red;
		fill: red;
		cursor:default;
	}

	.circle_borrowing {
		stroke-width: 1px;
		stroke: blue;
		fill: blue;
		cursor:default;

	}

	.borrowingBox {
		fill: blue;
	}

	.lendingBox {
		fill: red;
	}


	#umnLabel {
		font-family: Cambria, Georgia, serif;		
		font-size: 32px;
		color: #000;	
	}

	#umnSublabel {
		font-size: 16px;
		color: #000;
		font-family: Cambria, Georgia, serif;
	}

	#bottomBar {
		fill: #fff;
		opacity:0.5;
	}

	#statisticsBox {
		fill: #fff;
		opacity:0.5;
	}

	#descriptionBox {
		fill: #fff;
		opacity:0.5;
	}

	#bottomPanel {
		width: 100%;
		height: 60px;
		position: absolute;
		bottom: 0px;
		left: 0px;
		margin:0px;
		padding:0px 50px 0px 75px;
		margin-top: -100px;
		z-index:500;
		box-sizing:border-box;
	}

	#slider {
		width:100%;
		height: 100%;
		margin:0px;
		padding:0px;
		box-sizing:border-box;
	}

	#timeSlider {
		width: 100%;
		height: 50px;
		margin:0px;
		padding:0px;
		box-sizing:border-box;
	}

	#controlButton {
		position: absolute;
		left: 10px;
		bottom:22px;
		width: 25px;
		height: 25px;
		z-index:1000;
		cursor:pointer;
	}

	#restartButton {
		position: absolute;
		left: 40px;
		bottom:22px;
		width: 25px;
		height: 25px;
		z-index:1000;
		cursor:pointer;
	}


	.axis path,
	.axis line {
		fill: none;
		stroke: black;
		shape-rendering: crispEdges;
	}

	.axis text {
		font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;		
		font-size: 12px;
		font-weight: bold;
	}		

	.statisticsBoxLabel {
		font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
		font-size:13px;
		color: #000;
	}

	.openLabel {
		font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;		
		font-size:16px;
		color: #000;
		font-weight:bold;
	}

	#descriptionContentContainer {
		font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;		
		font-size:12px;
		z-index:1500;
		position: absolute;
		overflow-y: auto;
		overflow-x:hidden;
		padding:5px 10px 5px 15px;
		box-sizing: border-box;
		text-align:justify;
		line-height:150%;
	}
	
	#infoBox {
		font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;		
		font-size:15px;
		font-weight:bold;
		z-index:1500;
		position: absolute;
		background-color:rgba(255, 255, 255, 0.5);
		padding:7px;
	}

	</style>
</head>
<body>
        
<?php
    
    // Specify time zone to properly display date
	date_default_timezone_set("America/Chicago");
	
    // Data to create visulization is stored in a database; establish connection
    include(/*DATABASE CONFIGURATION FILE*/);

    $con = mysqli_connect($dbhost,$dbuser,$dbpw,$dbname);
	
    /* EDIT STEVEN BRAUN 2015-08-10 10:56 AM CST
    In newer versions of PHP, json_encode() may fail on improperly encoded UTF text queried from MySQL.
    To correct for this, add mysqli_char_set() to specify that text read through MySQL should be read as UTF-8 (or otherwise)
    */
    mysqli_set_charset($con,"utf8");

	// Create an array of statuses that indicate when requests open and close; these are statuses
	// that come from the data itself and will be used to generate the visualization    
    $statusArray = array("initiateStatuses" => array("Submitted by Customer","Submitted via Lending Web","Imported from OCLC","Imported from DOCLINE"),"completeStatuses" => array("Checked Out to Customer","Request Finished","Delivered to Web","Item Shipped"));
    $statusList = "'" . implode("','",$statusArray["initiateStatuses"]) . "','" . implode("','",$statusArray["completeStatuses"]) . "'";
    
    // Get data on events -- i.e., when requests are opened and closed.
    // We specify TWO distinct arrays here:
    // eventsArray: stores data on transaction EVENTS (i.e., when a transaction opens and closes)
    // transactionsArray: stores data on the transactions themselves (i.e., name and location of lending library)
    $eventsArray = array();
    $transactionsArray = array();
    $sql = "SELECT id,eventTime,eventDescription,transactionNumber FROM aprilTracking_cleaned WHERE eventDescription IN ($statusList) ORDER BY eventTime ASC";
    $result = mysqli_query($con,$sql);
    
    // Loop through the result of the query to the database where all the data is stored
    while($row = mysqli_fetch_array($result)) {
    	$record_timestamp = strtotime($row['eventTime']);
    	
    	// Here, we will round all transaction events to the closest quarter hour -- this is how
    	// the visualization will loop through events to display them on the map
    	$minutesFactor = (date("i",$record_timestamp) % 15)*60 + (date("s",$record_timestamp) % 60);
    	$timestamp = $record_timestamp - ($minutesFactor);
    	$event = $row['eventDescription'];
    	$transactionNumber = $row['transactionNumber'];

		if(!array_key_exists($transactionNumber,$transactionsArray)) {

			// Add the transaction to an array of data about the transactions
			$transactionsArray[$transactionNumber] = array("transactionNumber" => $transactionNumber, 
														   "transactionOpen" => null,
														   "transactionClose" => null,
														   "type" => null, 
														   "libraryName" => null, 
														   "lat" => null, 
														   "lon" => null, 
														   "address1" => null, 
														   "address2" => null, 
														   "address3" => null, 
														   "address4" => null
														   );

		}    	
		if(in_array($event,$statusArray["initiateStatuses"])) {
			// Update when the transaction opens
			$transactionsArray[$transactionNumber]["transactionOpen"] = $timestamp;
		} else if(in_array($event,$statusArray["completeStatuses"])) {
			// Update when the transaction closes
			$transactionsArray[$transactionNumber]["transactionClose"] = $timestamp;
		}
    
    }

    // Now get data on the individual transactions themselves
    foreach($transactionsArray as $transactionNumber => $data) {
    	if(is_null($data["transactionOpen"]) || is_null($data["transactionClose"])) {
    		unset($transactionsArray[$transactionNumber]);
    	} else { 	
			$sql = "SELECT processType,lender,statusDate,status FROM aprilTransactions_cleaned WHERE transactionNumber = '$transactionNumber'";
			$result = mysqli_query($con,$sql);
			$obj = mysqli_fetch_object($result);
			$lenderCode = $obj->lender;
			$processType = $obj->processType;
			if(!empty($lenderCode) && !is_null($lenderCode) && $lenderCode !== "internet") {
				$lenderSql = "SELECT libraryName,lat,lon,address1,address2,address3,address4 FROM aprilLenders_cleaned WHERE lenderCode = '$lenderCode'";
				$lenderResult = mysqli_query($con,$lenderSql);
				if(mysqli_num_rows($lenderResult) == 0) {
					unset($transactionsArray[$transactionNumber]);			
				} else {
				
					// Get important data about each transaction, including transaction type (borrowing, lending)
					// and borrowing/lending library name
					
					$obj = mysqli_fetch_object($lenderResult);
					$transactionsArray[$transactionNumber]["type"] = $processType;
					$transactionsArray[$transactionNumber]["libraryName"] = $obj->libraryName;
					$transactionsArray[$transactionNumber]["lat"] = $obj->lat;
					$transactionsArray[$transactionNumber]["lon"] = $obj->lon;
					$transactionsArray[$transactionNumber]["address1"] = $obj->address1;
					$transactionsArray[$transactionNumber]["address2"] = $obj->address2;
					$transactionsArray[$transactionNumber]["address3"] = $obj->address3;
					$transactionsArray[$transactionNumber]["address4"] = $obj->address4;

					// Add events to eventsArray
			
					if(!array_key_exists($data["transactionOpen"],$eventsArray)) {
						$eventsArray[$data["transactionOpen"]] = array();
					}
					$eventsArray[$data["transactionOpen"]][] = array("eventDescription" => "transactionOpen","transactionNumber" => $transactionNumber);

					if(!array_key_exists($data["transactionClose"],$eventsArray)) {
						$eventsArray[$data["transactionClose"]] = array();
					}
					$eventsArray[$data["transactionClose"]][] = array("eventDescription" => "transactionClose","transactionNumber" => $transactionNumber);
				}		

			} else {
				unset($transactionsArray[$transactionNumber]);
			}
		}
	}

    ksort($eventsArray);
    
    $transactionCounts = array("borrowing" => 0,"lending" => 0);
    $maxValue = 0;
    foreach($eventsArray as $time => $events) {
    	foreach($events as $key => $event) {
    		$eventType = $event["eventDescription"];
    		$transactionNumber = $event["transactionNumber"];
    		$processType = $transactionsArray[$transactionNumber]["type"];

    		if($eventType === "transactionClose") {
    			$transactionCounts[$processType] = $transactionCounts[$processType] - 1;
    		} else if($eventType === "transactionOpen") {
    			$transactionCounts[$processType] = $transactionCounts[$processType] + 1;    		
    		}
    	}
    	if(max($transactionCounts) > $maxValue) {
    		$maxValue = max($transactionCounts);
    	}
    }
	    
	// Load the data that creates the map (country outlines)
    $world = file_get_contents("data/world_110m2.json");
    
    // Specify the start time for the looping period: April 1, 2014
    $startTime = strtotime("2014-04-01 00:00:00");
    
    // Specify the end time for the looping period: May 1, 2014
    $endTime = strtotime("2014-05-01 00:00:00");
    
    // Some HTML to create the dynamic start/stop/pause button and time slider
	print "<div id='bottomPanel'>";
	print "<div id='controlButton'><img src='inc/play.png'></div>";
	print "<div id='restartButton'><img src='inc/restart.png'></div>";

	print "<div id='slider'><input readonly type='range' value=" . $startTime . " min=" . $startTime . " max=" . $endTime . " step=" . (15*60) . " id='timeSlider'></div>";
	print "</div>";  
    
	
	// End working in PHP. Now jumping to JavaScript to work with D3

?>
    
<script>

	//////////////////////////////////////////////////////////
	// Set global variables /////////////////////////////////
	////////////////////////////////////////////////////////

	// Pull some variables from PHP into JavaScript for D3 to use
	world = <?php echo $world; ?>;
	startTime = <?php echo $startTime; ?>;
	endTime = <?php echo $endTime; ?>;
	transactionCountMax = <?php echo $maxValue; ?>;

	var getEvent;
	var loopThrough;
	var lines, circles;
	
	// Specify the center lat/lon for University of Minnesota (needed when mapping a lending transaction)
	var umn = {lat: 44.974810, lon: -93.227642};
	
	// Dummy variable to initialize the animation start
	var runStatus = -1;

	// Some display parameters
	var width = window.innerWidth,
		height = window.innerHeight,
		rotate = 90,        // so that [-60, 0] becomes initial center of projection
		maxlat = 75;        // clip northern and southern poles (infinite in mercator)

	// Append SVG to body
	svg = d3.selectAll('body')
		.append('svg')
		.attr('width',width)
		.attr('height',height);

	// Specify some scales
	var transactionCountScale = d3.scale.linear()
		.domain([0,transactionCountMax])
		.range([1,150]);
		
	var timeScale = d3.time.scale()
		.domain([startTime*1000,endTime*1000])
		.rangeRound([75,width-50]);


	// Specify time axis
	var timeAxis = d3.svg.axis()
				.scale(timeScale)
				.orient("bottom")
				.ticks(d3.time.day,1)
				.tickFormat(d3.time.format("%-m/%-d"));

	// track last translation and scale event we processed -- this isn't needed if the map is static (which this version is)
	var tlast = [0,0], 
		slast = null;


	//////////////////////////////////////////////////////////
	// Define functions /////////////////////////////////////
	////////////////////////////////////////////////////////
	
	// A function to move elements to the front of the SVG canvas (this is useful when creating
	// many vector elements that are likely to overlap each other)
	d3.selection.prototype.moveToFront = function() {
		return this.each(function(){
			this.parentNode.appendChild(this);
		});
	};

	// A function to draw the map; this is more important if the map is draggable (this one isn't)
	function redraw() {
		if (d3.event) { 
			var scale = d3.event.scale,
				t = d3.event.translate;                
	
			// if scaling changes, ignore translation (otherwise touch zooms are weird)
			if (scale != slast) {
				projection.scale(scale);
			} else {
				var dx = t[0]-tlast[0],
					dy = t[1]-tlast[1],
					yaw = projection.rotate()[0],
					tp = projection.translate();
	
				// use x translation to rotate based on current scale
				projection.rotate([yaw+360.*dx/width*scaleExtent[0]/scale, 0, 0]);
				// use y translation to translate projection, clamped by min/max
				var b = mercatorBounds(projection, maxlat);
				if (b[0][1] + dy > 0) dy = -b[0][1];
				else if (b[1][1] + dy < height) dy = height-b[1][1];
				projection.translate([tp[0],tp[1]+dy]);
			}
			slast = scale;
			tlast = t;
		}
		svg.selectAll('path')       // re-project path data
			.attr('d', path);	
	}
	
	// find the top left and bottom right of current projection
	function mercatorBounds(projection, maxlat) {
		var yaw = projection.rotate()[0],
			xymax = projection([-yaw+180-1e-6,-maxlat]),
			xymin = projection([-yaw-180+1e-6, maxlat]);

		return [xymin,xymax];
	}	

	// A function to format dates and times for the map labels
	function dateLabelFormat(date,type) {
		var thisDate = new Date(date*1000);	// need to express timestamp in milliseconds
		var date = thisDate.getDate();
		var hours = thisDate.getHours();
		var minutes = thisDate.getMinutes();
		var month = thisDate.getMonth() + 1;

		if(type === "date") {
			return month + " " + date + ", 2014";				
		} else if(type === "time") {
			return hours + ":" + minutes;
		} else if(type === "axis") {
			return month + "/" + date;
		}
	}

	// A function that transforms lat/lon coordinates for lending libraries into
	// coordinates that are mapped onto the projection of the visualization
	function getPosition(geometryType,positionType,processType,lat,lon) {
		if(processType === "borrowing") {
			switch(positionType) {
				case "startX":
					return projection([lon,lat])[0];
					break;
				case "startY":
					return projection([lon,lat])[1];
					break;
				case "endX":
					return projection([umn.lon,umn.lat])[0];
					break;
				case "endY":
					return projection([umn.lon,umn.lat])[1];
					break;
			}
		} else if(processType === "lending") {
			switch(positionType) {
				case "startX":
					if(geometryType === "circle") {
						return projection([lon,lat])[0];
					} else if(geometryType === "line") {
						return projection([umn.lon,umn.lat])[0];
					}
					break;
				case "startY":
					if(geometryType === "circle") {
						return projection([lon,lat])[1];
					} else if(geometryType === "line") {
						return projection([umn.lon,umn.lat])[1];
					}

					break;
				case "endX":
					return projection([lon,lat])[0];
					break;
				case "endY":
					return projection([lon,lat])[1];
					break;
			}	
		}
	}

	// The function that actually RUNS the animation -- as a JavaScript setInterval loop
	function runAnimation(timeChecker) {
	
		// The loop that makes the animation run
		// This setInterval function jumps through the data at 15 MINUTE INCREMENTS
		// (this is why we rounded the transaction events above to the nearest quarter hour)
		// This is a good speed -- play around with the parameters to find something that works for you
		loopThrough = setInterval(function() {
			if(timeChecker >= endTime) {
				lendingCountLabel.text(0);
				borrowingCountLabel.text(0);
				clearInterval(loopThrough);
			}
			
			var lendingCount = d3.selectAll(".circle_lending").filter(function() { return d3.select(this).style("fill-opacity") > 0; }).size();
			var borrowingCount = d3.selectAll(".circle_borrowing").filter(function() { return d3.select(this).style("fill-opacity") > 0; }).size();

			lendingBox.transition()
				.duration(100)
				.attr("width",transactionCountScale(lendingCount));
			borrowingBox.transition()
				.duration(100)
				.attr("width",transactionCountScale(borrowingCount));
			
			lendingCountLabel.text(lendingCount);
			borrowingCountLabel.text(borrowingCount);

			// As we loop through the setInterval function, the timeChecker variable updates to a new
			// quarter-hour increment; the timeChecker increment serves as the INDEX to check
			// in the events array			
			d3.select("#timeSlider").property("value",timeChecker);
			if(typeof (getEvent = events[String(timeChecker)]) != 'undefined') {
				
				// Loop through all the events at the given time index
				for(var i = 0; i < getEvent.length; i++) {
					var eventType = getEvent[i]["eventDescription"];
					var getTransactionNumber = String(getEvent[i]["transactionNumber"]);
				
					switch(eventType) {
					
						// If the transaction is opening, pop open the circle
						case "transactionOpen":
							d3.select("#circle_" + getTransactionNumber)
								.moveToFront()
								.transition()
								.duration(500)
								.style("fill-opacity",1)
								.attr("r",15)
								.transition()
								.duration(250)
								.style("fill-opacity",0.2)
								.attr("r",5);
							
							break;
							
						// If the transaction is closing, draw the line to/from the library and pop close the circle
						case "transactionClose":
							thisLine = svg.select("#line_" + getTransactionNumber);
						
							thisLine.transition()
								.duration(250)
								.attr("x2",function(d) {
									return getPosition("line","endX",d.type,d.lat,d.lon);
								})
								.attr("y2",function(d) {
									return getPosition("line","endY",d.type,d.lat,d.lon);											
								})
								.each("end",function(j) {
									d3.select("#line_" + j.transactionNumber)
										.transition()
										.duration(250)
										.attr("x1",function(l) {
											return getPosition("line","endX",l.type,l.lat,l.lon);
										})
										.attr("y1",function(l) {
											return getPosition("line","endY",l.type,l.lat,l.lon);																						
										})
										.each("end",function(l) {
											d3.select("#circle_" + l.transactionNumber)
												.transition()
												.duration(125)
												.attr("cx", function(m) {
													return getPosition("circle","endX",m.type,m.lat,m.lon);
												})
												.attr("cy", function(m) { 
													return getPosition("circle","endY",m.type,m.lat,m.lon);
												})
												.attr("r",0)
												.style("fill-opacity",0);
										});
								});
								
							break;
					}	
					

				}

			}
			timeChecker += 15*60;	// Jump every 15 minutes -- event data is rounded down to every quarter hour

		}, 1);
	}

	// A function that makes it possible to control the animation
	function controlFunction(loopThrough) {
		if(runStatus == 1) {
			clearInterval(loopThrough);
			d3.select("#controlButton").select("img").attr("src","inc/play.png");
			runStatus = 0;
		} else if(runStatus == 0) {
			var loopThrough;
			runStatus = 1;
			timeResume = Number(d3.select("#timeSlider").property("value"));
			d3.select("#controlButton").select("img").attr("src","inc/pause.png");
			runAnimation(timeResume);
		} else if(runStatus == -1) {
			runStatus = 1;
			d3.select("#controlButton").select("img").attr("src","inc/pause.png");
			runAnimation(startTime);
		}
	}

	// A function to initialize the animation; this function initializes all the DATA and VECTOR ELEMENTS
	// necessary to run the animation
	function initializeAnimation() {
	
		// Lines between UMN and other libraries
		var lines = svg.selectAll("line")
			.data(transactionsArray)
			.enter()
			.append("line")
			.attr("class",function(d) {
				return "line_" + d.type;
			})
			.attr("id",function(d) {
				return "line_" + d.transactionNumber;
			})
			.attr("x1", function(d) {
					return getPosition("line","startX",d.type,d.lat,d.lon);
			})
			.attr("y1", function(d) {
					return getPosition("line","startY",d.type,d.lat,d.lon);
			})
			.attr("x2", function(d) {
					return getPosition("line","startX",d.type,d.lat,d.lon);
			})
			.attr("y2", function(d) {
					return getPosition("line","startY",d.type,d.lat,d.lon);
			});

		// Circles representing transactions at libraries
		var circles = svg.selectAll("circle")
			.data(transactionsArray)
			.enter()
			.append("circle")
			.attr("class",function(d) {
				return "circle_" + d.type;
			})
			.attr("id",function(d) {
				return "circle_" + d.transactionNumber;
			})			
			.style("fill-opacity",0)
			.style("stroke-width",0)
			.attr("cx", function(d) {
					return getPosition("circle","startX",d.type,d.lat,d.lon);
			})
			.attr("cy", function(d) {
					return getPosition("circle","startY",d.type,d.lat,d.lon);
			})
			.attr("r", 5) // Default node radius
			.on("mouseover",function(d) {
				if(d3.select(this).style("fill-opacity") > 0) {
					var libName = d["libraryName"];
					var libAddress = d["address1"];
					var coordinates = d3.mouse(this);
					d3.select("#infoBox")
						.style("left",(coordinates[0]+10) + "px")
						.style("top",(coordinates[1]+10) + "px")
						.style("display","block")
						.html(libName + "<br><span style='font-size:11px;font-style:italics;'>" + libAddress + "</span>");
				}
			})
			.on("mouseout",function() {
				d3.select("#infoBox")
					.style("display","none");
			});
	}


	//////////////////////////////////////////////////////////
	// Place world map //////////////////////////////////////
	////////////////////////////////////////////////////////

	// Define the projection
	var projection = d3.geo.mercator()
		.rotate([rotate,0])
		.scale(1)           // we'll scale up to match viewport shortly.
		.translate([width/2, height/2]);

	// set up the scale extent and initial scale for the projection
	var b = mercatorBounds(projection, maxlat),
		s = width/(b[1][0]-b[0][0]),
		scaleExtent = [s, 10*s];

	projection
		.scale(scaleExtent[0]);

	var path = d3.geo.path()
		.projection(projection);
		
	// Draw the map
	svg.selectAll('path')
		.data(topojson.feature(world, world.objects.countries).features)
		.enter().append('path');

	redraw();       // update path data

	//////////////////////////////////////////////////////////
	// Place map features ///////////////////////////////////
	////////////////////////////////////////////////////////

	// These are visual components to the interface
	var bottomBar = svg.append("rect")
		.attr("id","bottomBar")
		.attr("x",0)
		.attr("y",height-60)
		.attr("width",width)
		.attr("height",60);		

	var statisticsContainer = svg.append("g")
		.attr("x",width-200)
		.attr("y",height-160)
		.attr("width",200)
		.attr("height",100)
		
	var statisticsBox = statisticsContainer.append("rect")
		.attr("id","statisticsBox")
		.attr("x",width-200)
		.attr("y",height-160)
		.attr("width",200)
		.attr("height",100);

	var openLabel = statisticsContainer.append("text")
		.attr("class","openLabel")
		.attr("text-anchor","middle")
		.attr("x",width-100)
		.attr("y",height - 140)
		.text("Open Transactions");

	
	var lendingLabel = statisticsContainer.append("text")
		.attr("class","statisticsBoxLabel")
		.attr("x",width-190)
		.attr("y",height - 120)
		.text("Lending");

	var lendingCountLabel = statisticsContainer.append("text")
		.attr("class","statisticsBoxLabel")
		.attr("x",width-190)
		.attr("y",height - 103)
		.text("0");

	var borrowingLabel = statisticsContainer.append("text")
		.attr("class","statisticsBoxLabel")
		.attr("x",width-190)
		.attr("y",height - 80)
		.text("Borrowing");


	var borrowingCountLabel = statisticsContainer.append("text")
		.attr("class","statisticsBoxLabel")
		.attr("x",width-190)
		.attr("y",height - 63)
		.text("0");

	var barHeight = 15;

	var lendingBox = statisticsContainer.append("rect")
		.attr("class","lendingBox")
		.attr("x",width-160)
		.attr("y",height - 115)
		.attr("width",1)
		.attr("height",barHeight);
		
	var borrowingBox = statisticsContainer.append("rect")
		.attr("class","borrowingBox")
		.attr("x",width-160)
		.attr("y",height - 75)
		.attr("width",1)
		.attr("height",barHeight);

	var descriptionContainer = svg.append("g")
		.attr("x",0)
		.attr("y",height-160)
		.attr("width",width-200)
		.attr("height",100);
	
	var descriptionBox = descriptionContainer.append("rect")
		.attr("id","descriptionBox")
		.attr("x",0)
		.attr("y",height-160)
		.attr("width",width-200-5)
		.attr("height",95);
	
	var descriptionContent = d3.select("body").append("div")
		.attr("id","descriptionContentContainer")
		.style("right","205px")
		.style("bottom","60px")
		.style("width",(width - 200 - 400) + "px")
		.style("height","100px");
		
	descriptionContent.html("It is undisputed that libraries have undergone significant transformation brought about by a changing landscape of digital services and the ways in which patrons interface with those services. As a result, libraries and their users have increasingly been forced to recognize the ways in which their collections extend beyond the physical stacks of the walls in which they are contained to demonstrate the added value and impact they provide to patrons. One consequence of this accordingly has been that new definitions of what constitutes \"collections\" have charged libraries with the task of reconceptualizing themselves as living, breathing, dynamic ecosystems. A larger question remains to be addressed, however: how can these changing definitions, as well as the measures of impact and value they carry, be best expressed and communicated?<br><br>This animation shows a \"flight map\" of all completed interlibrary loan transactions coming into and going out of the University of Minnesota Libraries for <b>April 2014</b>. Each new circle represents the opening of a new transaction; those colored <span style='font-weight:bold;color:red;'>red</span> indicate lending transactions while those colored <span style='font-weight:bold;color:blue;'>blue</span> indicate borrowing transactions. A circle stays on the map until the request has been fulfilled and the transaction has been closed, at which point a line appears showing where the requested item has been sent to or received from. The animation can be paused and restarted using the controls to the left of the time slider.<br><br>This project is the result of a joint effort of Steven Braun (<i>sbraun@umn.edu</i>), Meghan Lafferty (<i>mlaffert@umn.edu</i>), Amy Neeser (<i>nees0017@umn.edu</i>), Justin Schell (<i>schel115@umn.edu</i>), Emily Riha (<i>emilymr@umn.edu</i>), and Kevin Dyke (<i>kevindyke@umn.edu</i>) in the University of Minnesota Libraries. This animation was created in D3.js by Steven Braun. [Last edited 10-26-2014 for the 2014 Digital Library Federation Forum in Atlanta, GA.]");
	
	var umnLabel = descriptionContainer.append("text")
		.attr("id", "umnLabel")
		.attr("x", 2)  
		.attr("y", height-130)
		.attr("text-anchor", "start") 
		.text("Interlibrary Loan, Visualized");	

	var umnSublabel = descriptionContainer.append("text")
		.attr("id", "umnSublabel")
		.attr("x", 5)  
		.attr("y", height-105)
		.attr("text-anchor", "start") 
		.text("in the University of Minnesota Libraries");	

	var infoBox = d3.select("body")
		.append("div")
		.attr("id","infoBox")
		.style("display","none");
		
	svg.append("g")
		.attr("class","axis")
		.call(timeAxis)
		.attr("transform","translate(0," + (height-30) + ")");
		
	d3.select("#controlButton")
		.select("img")
		.on("click",function() {
			controlFunction(loopThrough);
		});

	d3.select("#restartButton")
		.select("img")
		.on("click",function() {
			clearInterval(loopThrough);
			d3.select("#controlButton").select("img")
				.attr("src","inc/play.png");
			runStatus = 0;
			d3.selectAll("circle")
				.remove();
			d3.selectAll("line")
				.remove();
			initializeAnimation();
			runStatus = 1;
			d3.select("#controlButton").select("img")
				.attr("src","inc/pause.png");
			runAnimation(startTime);
		});
		
	
	//////////////////////////////////////////////////////////
	// Load ILL data ////////////////////////////////////////
	////////////////////////////////////////////////////////
		
	// Load the data, initialize the animation, and go!
	events = <?php echo json_encode($eventsArray,true); ?>;
	transactions = <?php echo json_encode($transactionsArray,true); ?>;
	transactionsArray = [];
	for(var d in transactions) {
		transactionsArray.push(transactions[d]);
	}

	initializeAnimation();			
				
</script>
</body>
</html>

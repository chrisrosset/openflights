<?php

session_start();
$uid = $_SESSION["uid"];
$export = $_GET["export"] ?? false;
if ($export) {
    if (!$uid || empty($uid)) {
        exit("You must be logged in to export.");
    }
    if ($export == "export" || $export == "backup") {
        header("Content-type: text/csv; charset=utf-8");
        header("Content-disposition: attachment; filename=\"openflights-$export-" . date("Y-m-d") . ".csv\"");
    }
    if ($export == "export" || $export == "gcmap") {
        $trid = $_GET["trid"];
        $alid = $_GET["alid"];
        $year = $_GET["year"];
        $apid = $_GET["id"];
    }
    // else export everything unfiltered
} else {
    header("Content-type: text/html; charset=utf-8");

    $apid = $_POST["id"] ?? ($_GET["id"] ?? "");
    $trid = $_POST["trid"] ?? null;
    $alid = $_POST["alid"] ?? null;
    $user = $_POST["user"] ?? null;
    $year = $_POST["year"] ?? null;
    $fid = $_POST["fid"] ?? null;
}

include 'helper.php';
include 'filter.php';
include 'db_pdo.php';
include 'greatcircle.php';

$units = $_SESSION["units"];

// Logged in?
if (!$uid || empty($uid)) {
    // Viewing an "open" user's flights, or an "open" flight?
    // (will be previously set in map.php)
    $uid = $_SESSION["openuid"];
    if ($uid && !empty($uid)) {
        // Yes we are, so check if we're limited to a single trip
        $openTrid = $_SESSION["opentrid"];
        if ($openTrid) {
            if ($openTrid != $trid) {
                // Naughty naughty, back to demo mode
                $uid = 1;
            }
        }
        // No limit, do nothing
    } else {
        // Nope, default to demo mode
        $uid = 1;
    }
}

$params = [];
$route = false;

// Special handling of "route" apids in form R<apid>,<coreid>
// <apid> is user selection, <coreid> is ID of airport map is centered around
$type = substr($apid, 0, 1);
if ($type == "R" || $type == "L") {
    $route = true;
    $ids = explode(',', substr($apid, 1));
    $apid = $ids[0];
    $coreid = $ids[1];
    $params['apid'] = $apid;
    if ($type == "L") {
        if ($coreid == "") {
            $match = "r.alid=:apid"; // all routes on $alid
        } else {
            $params['coreid'] = $coreid;
            $match = "r.src_apid=:coreid AND r.alid=:apid"; // flight from $coreid on $alid only
        }
    } else {
        if ($apid == $coreid) {
            $match = "r.src_apid=:apid"; // all flights from $apid
        } else {
            $params['coreid'] = $coreid;
            $match = "r.src_apid=:coreid AND r.dst_apid=:apid"; // flight from $coreid to $apid only
        }
        // Airline filter on top of airport
        if ($alid) {
            $params['alid'] = $alid;
            $match .= " AND r.alid=:alid";
        }
    }
    $sql = "SELECT s.x AS sx,s.y AS sy,s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.x AS dx,d.y AS dy,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,l.iata as code, '-' as src_date, '-' as src_time, '-' as distance, '-:-' AS duration, '' as seat, '' as seat_type, '' as class, '' as reason, r.equipment AS name, '' as registration,rid AS fid,l.alid,'' AS note,NULL as trid,'N' AS opp,NULL as plid,l.iata AS al_iata,l.icao AS al_icao,l.name AS al_name,'F' AS mode,codeshare,stops FROM airports AS s,airports AS d, airlines AS l,routes AS r WHERE $match AND r.src_apid=s.apid AND r.dst_apid=d.apid AND r.alid=l.alid";
} else {
    // List of all this user's flights
    $params['uid'] = $uid;
    $sql = "SELECT s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,f.code,f.src_date,src_time,distance,DATE_FORMAT(duration, '%H:%i') AS duration,seat,seat_type,class,reason,p.name,registration,fid,l.alid,note,trid,opp,f.plid,l.iata AS al_iata,l.icao AS al_icao,l.name AS al_name,f.mode AS mode FROM airports AS s,airports AS d, airlines AS l,flights AS f LEFT JOIN planes AS p ON f.plid=p.plid WHERE f.uid=:uid AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.alid=l.alid";

    // ...filtered by airport (optional)
    if ($apid && $apid != 0) {
        $params['apid'] = $apid;
        $sql = $sql . " AND (s.apid=:apid OR d.apid=:apid)";
    }
}

// Add filters, if any
switch ($export) {
    case "export":
    case "gcmap":
        // Full filter only for user flight searches
        if (!$route) {
            $sql = $sql . getFilterString($dbh, $_GET);
        }
        break;

    case "backup":
        // do nothing;
        break;

    default:
        // Full filter only for user flight searches
        if (!$route) {
            $sql = $sql . getFilterString($dbh, $_POST);
        }
        break;
}
if ($fid && $fid != "0") {
    $params['fid'] = $fid;
    $sql = $sql . " AND fid= :fid";
}

// And sort order
if ($route) {
    if ($type == "R") {
        $sql .= " ORDER BY d.iata ASC";
    } else {
        $sql .= " ORDER BY s.iata,d.iata ASC";
    }
} else {
    $sql .= " ORDER BY src_date DESC, src_time DESC";
}

// Execute!
$sth = $dbh->prepare($sql);
if (!$sth->execute($params)) {
    die('Error;Query ' . print_r($_GET, true) . ' caused database error ' . $sql . ', ' . $sth->errorInfo()[0]);
}

$flights = Array();

while ($row = $sth->fetch()) {
    $note = $row["note"];

    if ($route) {
        $row["distance"] = gcPointDistance(
            array("x" => $row["sx"], "y" => $row["sy"]),
            array("x" => $row["dx"], "y" => $row["dy"])
        );
        $row["duration"] = gcDuration($row["distance"]);
        $row["code"] = $row["al_name"] . " (" . $row["code"] . ")";
        $note = "";
        if ($row["stops"] == "0") {
            $note = "Direct";
        } else {
            $note = $row["stops"] . " stops";
        }
        if ($row["codeshare"] == "Y") {
            $note = "Codeshare";
        }
    }

    $src_apid = $row["src_apid"];
    $src_code = format_apcode2($row["src_iata"], $row["src_icao"]);

    $dst_apid = $row["dst_apid"];
    $dst_code = format_apcode2($row["dst_iata"], $row["dst_icao"]);

    $al_code = format_alcode($row["al_iata"], $row["al_icao"], $row["mode"]);

    if ($row["opp"] == 'Y') {
        $tmp = $src_apid;
        $src_apid = $dst_apid;
        $dst_apid = $tmp;

        $tmp = $src_code;
        $src_code = $dst_code;
        $dst_code = $tmp;
    }

    array_push($flights, Array(
        "src_code" => $src_code,
        "src_apid" => $src_apid,
        "src_date" => $row["src_date"],
        "src_time" => $row["src_time"],
        "dst_code" => $dst_code,
        "dst_apid" => $dst_apid,
        "code" => $row["code"],
        "al_name" => $row["al_name"],
        "al_code" => $al_code,
        "distance" => $row["distance"],
        "duration" => $row["duration"],
        "seat" => $row["seat"],
        "seat_type" => $row["seat_type"],
        "class" => $row["class"],
        "reason" => $row["reason"],
        "name" => $row["name"],
        "trid" => $row["trid"],
        "mode" => $row["mode"],
        "note" => $note,
        "registration" => $row["registration"],
        "fid" => $row["fid"],
        "alid" => $row["alid"],
        "plid" => $row["plid"]
    ));
}


// Format-specific data manipulation and output
if ($export == "gcmap") {

    // list of city pairs when doing gcmap export.
    $airport_pair_set = array_reduce($flights, function($carry, $item) {
        $pair = $item["src_code"] . "-" . $item["dst_code"];
        $carry[$pair] = $pair; // Using a map as a set
        return $carry;
    });

    $url_pairs = urlencode(implode(",", $airport_pair_set));

    // Output the redirect URL.
    header("Location: http://www.gcmap.com/mapui?P=" . $url_pairs . "&MS=bm");
} else if ($export == "export" || $export == "backup") {

    $rows = array(
        // Start with byte-order mark to try to clue Excel into realizing that this is UTF-8
        "\xEF\xBB\xBFDate,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_OID,To_OID,Airline_OID,Plane_OID"
    );

    function quote_string($str) { return "\"" . $str . "\""; }

    foreach ($flights as $f) {

        $src_time = $f["src_time"];

        // Pad time with space if it's known
        if ($src_time) {
            $src_time = " " . $src_time;
        } else {
            $src_time = "";
        }

        array_push($rows, implode(",", array(
            $f["src_date"],
            $src_time,
            $f["src_code"],
            $f["dst_code"],
            $f["code"],
            $f["al_name"],
            $f["distance"],
            $f["duration"],
            $f["seat"],
            $f["seat_type"],
            $f["class"],
            $f["reason"],
            $f["name"],
            $f["registration"],
            $f["trid"],
            quote_string($f["note"]),
            $f["src_apid"],
            $f["dst_apid"],
            $f["alid"],
            $f["plid"]
        )));
    }

    print implode("\r\n", $rows);
} else if ($export == "json") {
    print(json_encode($flights));
} else {
    // Filter out any carriage returns or tabs
    $note = str_replace(array("\n", "\r", "\t"), "", $note);

    // Convert mi to km if units=K *and* we're not loading a single flight
    if ($units == "K" && (!$fid || $fid == "0")) {
        $row["distance"] = round($row["distance"] * KM_PER_MILE);
    }

    $lines = array_map(function($f) {
        return implode("\t", array(
            $f["src_code"] ?? "",          // $src_code,
            $f["src_apid"] ?? "",          // $src_apid,
            $f["dst_code"] ?? "",          // $dst_code,
            $f["dst_apid"] ?? "",          // $dst_apid,
            $f["code"] ?? "",              // $row["code"],
            $f["src_date"] ?? "",          // $row["src_date"],
            $f["distance"] ?? "",          // $row["distance"],
            $f["duration"] ?? "",          // $row["duration"],
            $f["seat"] ?? "",              // $row["seat"],
            $f["seat_type"] ?? "",         // $row["seat_type"],
            $f["class"] ?? "",             // $row["class"],
            $f["reason"] ?? "",            // $row["reason"],
            $f["fid"] ?? "",               // $row["fid"],
            $f["name"] ?? "",              // $row["name"],
            $f["registration"] ?? "",      // $row["registration"],
            $f["alid"] ?? "",              // $row["alid"],
            $f["note"] ?? "",              // $note,
            $f["trid"] ?? "",              // $row["trid"],
            $f["plid"] ?? "",              // $row["plid"],
            $f["al_code"] ?? "",           // $al_code,
            $f["src_time"] ?? "",          // $row["src_time"],
            $f["mode"] ?? ""));            // $row["mode"]
    }, $flights);

    print(implode("\n", $lines));
}

<?php

include 'helper.php';
include 'locale.php';
include 'db_pdo.php';

function valid_name($name) {
    return $name != null and $name != "";
}

function valid_privacy($privacy) {
    return in_array($privacy, ["Y", "N", "O"]);
}

function trip_put($uid) {
    global $dbh;

    $data = json_decode(file_get_contents("php://input"), true);

    $name = $data["name"] ?? null;
    $privacy = $data["privacy"] ?? 'N'; // default to private
    $url = $data["url"] ?? "";

    if (!valid_name($name) or !valid_privacy($privacy)) {
        respond_with_json(400, ['message' => 'Invalid request.']);
    }

    // Note: the API uses `privacy` but the database field is `public`.
    $sth = $dbh->prepare("INSERT INTO trips(name,url,public,uid) VALUES(?,?,?,?)");
    $success = $sth->execute([$name, $url, $privacy, $uid]);

    if ($success) {
        respond_with_json(200, [
            "trid" => (int) $dbh->lastInsertId(),
            "name" => $name,
            "url" => $url,
            "privacy" => $privacy
        ]);
    } else {
        respond_with_json(500, ['message' => 'Failed to save trip.']);
    }
}

function trip_get($uid) {
    global $dbh;

    $trid = $_GET["trid"] ?? null;
    $name = $data["name"] ?? null;
    $privacy = $data["privacy"] ?? 'N'; // default to private
    $url = $data["url"] ?? "";

    if (!is_numeric($trid)) {
        respond_with_json(400, ['message' => 'Invalid request.']);
    }

    $sth = $dbh->prepare("SELECT name, url, public FROM trips WHERE trid=? AND uid=?");
    $sth->execute([$trid, $uid]);

    $row = $sth->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        respond_with_json(200, [
            "trid" => (int) $trid,
            "name" => $row["name"],
            "url" => $row["url"],
            "privacy" => $row["public"]
        ]);
    } else {
        respond_with_json(404, ['message' => 'No matching trip found.']);
    }
}

function trip_post($uid) {
    global $dbh;

    $trid = $_POST["trid"] ?? null;
    $name = $data["name"] ?? null;
    $privacy = $data["privacy"] ?? 'N'; // default to private
    $url = $data["url"] ?? "";

    if (!is_numeric($trid) or !valid_name($name) or !valid_privacy($privacy)) {
        respond_with_json(400, ['message' => 'Invalid request.']);
    }

    $sth = $dbh->prepare("UPDATE trips SET name=?, url=?, public=? WHERE uid=? AND trid=?");
    $success = $sth->execute([$name, $url, $privacy, $uid, $trid]);

    if ($success) {
        respond_with_json(200, [
            "trid" => (int) $trid,
            "name" => $name,
            "url" => $url,
            "privacy" => $privacy
        ]);
    } else {
        respond_with_json(404, ['message' => 'No matching trip found.']);
    }
}

function trip_delete($uid) {
    global $dbh;

    parse_str($_SERVER['QUERY_STRING'], $data);
    $trid = $data["trid"] ?? null;

    if (!is_numeric($trid)) {
        respond_with_json(400, ['message' => 'Invalid request.', delete => $data]);
    }

    // Assign its flights to null and delete trip
    $dbh->beginTransaction();

    $sth = $dbh->prepare("UPDATE flights SET trid=NULL WHERE trid=? AND uid=?");
    $sth->execute([$trid, $uid]);

    $sth = $dbh->prepare("DELETE FROM trips WHERE trid=? AND uid=?");
    $sth->execute([$trid, $uid]);

    if ($sth->rowCount() == 1) {
        $dbh->commit();
        respond_with_json(200, ['message' => 'Trip successfully deleted.']);
    } else {
        $dbh->rollback();
        respond_with_json(400, ['message' => 'No matching trip found.']);
    }
}

function bad_method() {
    respond_with_json(400, ['message' => 'Invalid request.']);
}

// Let's check the session before we do anything else.
$uid = 1;
// $uid = $_SESSION["uid"];
// if (!$uid || empty($uid)) {
//     respond_with_json(401, _("Your session has timed out, please log in again."));
// }

$method = $_SERVER['REQUEST_METHOD'];
$handlers = ['GET' => 'trip_get', 'PUT' => 'trip_put', 'POST' => 'trip_post', 'DELETE' => 'trip_delete'];

// The legacy API uses `POST` for everything and specifies the operation type
// through the `type` parameter which is not used in the new API. If those
// two conditions are not met, this is a new request and we dispatch it to
// the new implementation. Otherwise, fall through to the legacy API.
if ($method != 'POST' or ($_POST['type'] ?? null) != null) {
    error_log($method);
    ($handlers[$method] ?? 'bad_method')($uid);
    exit();
}

$type = $_POST["type"];
$name = $_POST["name"];
$url = $_POST["url"];
$trid = $_POST["trid"];
$privacy = $_POST["privacy"];

if ($type != "NEW" && (!$trid or $trid == 0)) {
    die('0;Trip ID ' . $trid . ' invalid');
}

switch ($type) {
    case "NEW":
        // Create new trip
        $sth = $dbh->prepare("INSERT INTO trips(name,url,public,uid) VALUES(?,?,?,?)");
        $success = $sth->execute([$name, $url, $privacy, $uid]);
        break;

    case "EDIT":
        // Edit existing trip
        $sth = $dbh->prepare("UPDATE trips SET name=?, url=?, public=? WHERE uid=? AND trid=?");
        $success = $sth->execute([$name, $url, $privacy, $uid, $trid]);
        break;

    case "DELETE":
        // Assign its flights to null and delete trip
        $sth = $dbh->prepare("UPDATE flights SET trid=NULL WHERE trid=? AND uid=?");
        if (!$sth->execute([$trid, $uid])) {
            die('0;Operation on trip ' . $name . ' failed.');
        }

        $sth = $dbh->prepare("DELETE FROM trips WHERE trid=? AND uid=?");
        $success = $sth->execute([$trid, $uid]);
        break;

    default:
        die('0;Unknown operation ' . $type);
}

if (!$success) {
    die('0;Operation on trip ' . $name . ' failed.');
}
if ($sth->rowCount() != 1) {
    die("0;No matching trip found");
}

switch ($type) {
    case "NEW":
        $trid = $dbh->lastInsertId();
        printf("1;%s;" . _("Trip successfully created"), $trid);
        break;

    case "DELETE":
        printf("100;%s;" . _("Trip successfully deleted"), $trid);
        break;

    default:
        printf("2;%s;" . _("Trip successfully edited."), $trid);
        break;
}

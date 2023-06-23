#!/usr/bin/env bash
#set -x

SERVER="http://localhost:8008"
USERNAME="user"
PASSWORD="pass"

md5()
{
    printf "${1}" | md5sum --zero | cut -f1 -d' '
}

generate_challenge()
{
    md5 "$(date)"
}

# $1 username
# $2 password
# $3 challenge
generate_passhash()
{
    #echo $1
    #echo $2
    #echo $3
    local user_lower=$(printf "${1}" | tr '[:upper:]' '[":lower:"]')

    local pwname="${2}${user_lower}"
    local legacy_pwname="${2}${1}"

    pwname=$(md5 "${pwname}")
    local legacy_hash_pwname=$(md5 "${legacy_pwname}")

    pwname="${3}${pwname}"
    legacy_hash_pwname="${3}${legacy_hash_pwname}"

    pwname=$(md5 "${pwname}")
    legacy_hash_pwname=$(md5 "${legacy_hash_pwname}")

    printf "%s" $pwname
}

#JAR=$(mktemp)
JAR="./jar.txt"

curl "${SERVER}" \
    --cookie-jar ${JAR} \
    --silent \
    > /dev/null

cat $JAR

curl "${SERVER}/php/map.php" \
    -X POST \
    --http1.1 \
    --cookie ${JAR} \
    --cookie-jar ${JAR} \
    -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/114.0' \
    -H 'Accept: */*' \
    -H 'Accept-Language: en-US,en;q=0.5' \
    -H 'Accept-Encoding: gzip, deflate, br' \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    -H "Origin: ${SERVER}" \
    -H 'Connection: keep-alive' \
    -H "Referer: ${SERVER}/" \
    -H 'Sec-Fetch-Dest: empty' \
    -H 'Sec-Fetch-Mode: cors' \
    -H 'Sec-Fetch-Site: same-origin' \
    --data-raw 'user=0&trid=0&alid=&year=&param=true' \
    --verbose \
    --output "output.bin"

file output.bin

exit 0

CHALLENGE=$(cat challenge.txt)
PWHASH="$(generate_passhash $USERNAME $PASSWORD $CHALLENGE)"

curl "${SERVER}/php/login.php" \
    --cookie ${JAR} \
    --cookie-jar ${JAR} \
    --silent \
    -H 'Accept: */*' -H 'Accept-Language: en-US,en;q=0.5' -H 'Accept-Encoding: gzip, deflate, br' \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    -H 'Host: localhost:8008' \
    -H 'Origin: http://localhost:8008' \
    -H 'Connection: keep-alive' \
    -H 'Referer: http://localhost:8008/' \
    -H 'Sec-Fetch-Dest: empty' \
    -H 'Sec-Fetch-Mode: cors' \
    -H 'Sec-Fetch-Site: same-origin' \
    --output /dev/null \
    --data-raw "name=${USERNAME}&pw=${PWHASH}&lpw=${PWHASH}&challenge=${CHALLENGE}" \

echo -----------------

curl "${SERVER}/php/flights.php?export=json" \
    --cookie ${JAR} \
    --cookie-jar ${JAR} \
    --silent \
    --output - \
    > output.json

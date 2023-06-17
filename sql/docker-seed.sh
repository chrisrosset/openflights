#!/bin/sh

# We mount the source code under /openflights in docker-compose to gain
# access to the data/ files referenced by load-data.sql.
cd /openflights

mysql --local-infile=1 -u root -D flightdb2 <sql/load-data.sql

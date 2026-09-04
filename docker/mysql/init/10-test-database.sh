#!/bin/sh
# The test suite runs against "<database>_test": in the test environment Doctrine
# appends that suffix by itself (dbname_suffix in config/packages/doctrine.yaml).
#
# MYSQL_DATABASE creates the main database and grants the user rights on it alone,
# so without this the test run dies on "Access denied to database shortener_test".
# Runs only while the data volume is being initialised, which is why the same two
# statements had to be applied by hand to the volume that already existed.
set -e

mysql --protocol=socket -u root -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_test\`;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_test\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL

#!/bin/bash
########################
# Check if the provided argument is a boolean or is the string 'yes/true'
# Arguments:
#   $1 - Value to check
# Returns:
#   Boolean
#########################
function is_boolean_yes() {
    local -r bool="${1:-}"
    # comparison is performed without regard to the case of alphabetic characters
    shopt -s nocasematch
    if [[ "$bool" = 1 || "$bool" =~ ^(yes|true)$ ]]; then
        true
    else
        false
    fi
}

function version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1"
}

function migrateDatabase() {
    TARGET_VERSION="$(echo ${DOLI_VERSION} | cut -d. -f1).$(echo ${DOLI_VERSION} | cut -d. -f2).0"
    echo "Schema update is required ..."
    echo "Dumping Database into /var/www/dolidock/documents/dump.sql ..."
    echo "Grant Process to ${DOLI_DB_USER}"
    mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "GRANT PROCESS ON *.* TO ${DOLI_DB_USER};"
    mysqldump -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} >/var/www/dolidock/documents/dump.sql
    r=${?}
    if [[ ${r} -ne 0 ]]; then
        echo "Dump failed ... Aborting migration ..."
        return ${r}
    fi
    echo "Remove the grant Process to ${DOLI_DB_USER}"
    mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "REVOKE PROCESS ON *.* FROM ${DOLI_DB_USER};"

    FROM_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
    echo "Dump done ... Starting Migration from ${FROM_VERSION} to ${TARGET_VERSION}..."
    echo "" >/var/www/dolidock/documents/migration_error.html
    pushd /var/www/dolidock/html/install >/dev/null
    rm -f /var/www/dolidock/documents/install.lock
    php upgrade.php ${FROM_VERSION} ${TARGET_VERSION} >>/var/www/dolidock/documents/migration_error.html 2>&1 &&
        php upgrade2.php ${FROM_VERSION} ${TARGET_VERSION} >>/var/www/dolidock/documents/migration_error.html 2>&1 &&
        php step5.php ${FROM_VERSION} ${TARGET_VERSION} >>/var/www/dolidock/documents/migration_error.html 2>&1
    r=$?
    echo "" >/var/www/dolidock/documents/install.lock && chown www-data:www-data /var/www/dolidock/html/conf/conf.php && chmod 400 /var/www/dolidock/html/conf/conf.php
    popd >/dev/null

    if [[ ${r} -ne 0 ]]; then
        echo "Migration failed ... Restoring DB ... check file /var/www/dolidock/documents/migration_error.html for more info on error ..."
        mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} </var/www/dolidock/documents/dump.sql
        echo "DB Restored ..."
        return ${r}
    else
        echo "Migration successful ... Enjoy !!"
    fi

    return 0
}

function automigrate() {
    FROM_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
    if version_gt ${DOLI_VERSION} ${FROM_VERSION}; then
        migrateDatabase
    else
        echo "Schema update is not required DB=${FROM_VERSION}, APP=${DOLI_VERSION}... Enjoy !!"
    fi
}

echo "For manual migration, run the following command:"
echo "source /usr/local/bin/migrate2 && automigrate"
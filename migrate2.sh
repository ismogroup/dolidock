#!/bin/bash


DOLI_ACTUAL_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")

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

########################
# Check if the provided versions are greater than the other
# Arguments:
#   $1 - Version to compare
#   $2 - Version to compare
# Returns: 1 if the first version is lower than the second, 0 otherwise

function version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1"
}

########################
# grant PROCESS to the $DOLI_DB_USER
function grantProcess(){
    echo "Grant Process to ${DOLI_DB_USER}"
    mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "GRANT PROCESS ON *.* TO ${DOLI_DB_USER};"
}

########################
# revoke PROCESS to the $DOLI_DB_USER
function revokeProcess(){
    echo "Remove the grant Process to ${DOLI_DB_USER}"
    mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "REVOKE PROCESS ON *.* FROM ${DOLI_DB_USER};"
}

########################
# Dump the database to a file
# Arguments:
#   $1 - Dump file name (optional) default to $WORKDIR/documents/dump.sql
# Returns:
#   None
function dumpDatabase(){
    DUMPNAME=$1
    if [[ -z ${DUMPNAME} ]]; then
        DUMPNAME=$WORKDIR/documents/dump.sql
    fi
    echo "Dumping $DUMPNAME ..."
    grantProcess
    mysqldump -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} >$WORKDIR/documents/$DUMPNAME
    r=${?}
    if [[ ${r} -ne 0 ]]; then
        echo "Dump failed ... Aborting migration ..."
        return ${r}
    fi
    revokeProcess
}

########################
# Restore the database from a file
# Arguments:
#   $1 - Dump file name (optional) default to $WORKDIR/documents/dump.sql
# Returns:
#   None
function restoreDatabase(){
    DUMPNAME=$1
    if [[ -z ${DUMPNAME} ]]; then
        DUMPNAME=$WORKDIR/documents/dump.sql
    fi
    # if dump file is compressed then decompress it to a temp file
    if [[ ${DUMPNAME} == *.gz ]]; then
        TEMPFILE=$(mktemp).sql
        echo "Decompressing $DUMPNAME ..."
        gunzip -c $DUMPNAME > ${TEMPFILE}
        r=${?}
        if [[ ${r} -ne 0 ]]; then
            echo "Decompression failed ... Aborting migration ..."
            return ${r}
        fi
        DUMPNAME=${TEMPFILE}
    fi
    if [[ ${DUMPNAME} == *.zip ]]; then
        TEMPDIR=$(mktemp -d).sql
        echo "Decompressing $DUMPNAME ..."
        unzip -o $DUMPNAME -d ${TEMPDIR}
        r=${?}
        if [[ ${r} -ne 0 ]]; then
            echo "Decompression failed ... Aborting migration ..."
            return ${r}
        fi
        DUMPNAME=${TEMPDIR}/*.sql
    fi
    if [[ ${DUMPNAME} == *.bz2 ]]; then
        TEMPFILE=$(mktemp).sql
        echo "Decompressing $DUMPNAME ..."
        bunzip2 -c $DUMPNAME > ${TEMPFILE}
        r=${?}
        if [[ ${r} -ne 0 ]]; then
            echo "Decompression failed ... Aborting migration ..."
            return ${r}
        fi
        DUMPNAME=${TEMPFILE}
    fi
    if [[ ${DUMPNAME} == *.sql ]]; then
        echo "Restoring Database from $DUMPNAME ..."
        grantProcess
        mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <${DUMPNAME}
        r=${?}
        if [[ -z ${TEMPFILE} ]]; then
            rm -f ${TEMPFILE}
        fi
        if [[ ${r} -ne 0 ]]; then
            echo "Restore failed ... Aborting migration ..."
            return ${r}
        fi
        revokeProcess
    else
        echo "Unsupported file format ... Aborting migration ..."
        return 1
    fi
    return 0
}

########################
# Migrate the database to the current version
# Arguments:
#   None
# Returns:
#   None
function migrateDatabase() {
    TARGET_VERSION="$(echo ${DOLI_VERSION} | cut -d. -f1).$(echo ${DOLI_VERSION} | cut -d. -f2).0"
    echo "Schema update is required ..."
    dumpDatabase dump.sql
    r=${?}
    if [[ ${r} -ne 0 ]]; then
        return ${r}
    fi
    mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "REVOKE PROCESS ON *.* FROM ${DOLI_DB_USER};"

    FROM_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
    echo "Dump done ... Starting Migration from ${FROM_VERSION} to ${TARGET_VERSION}..."
    echo "" >$WORKDIR/documents/migration_error.html
    pushd $WORKDIR/html/install >/dev/null
    rm -f $WORKDIR/documents/install.lock
    php upgrade.php ${FROM_VERSION} ${TARGET_VERSION} 2>&1 | tee -a $WORKDIR/documents/migration_error.html | sed -e 's/<[^>]*>//g' -e '/^<!--.*$/d' -e '/^.*-->$/d' -e '/^$/d' &&
        php upgrade2.php ${FROM_VERSION} ${TARGET_VERSION} 2>&1 | tee -a $WORKDIR/documents/migration_error.html | sed -e 's/<[^>]*>//g' -e '/^<!--.*$/d' -e '/^.*-->$/d' -e '/^$/d' &&
        php step5.php ${FROM_VERSION} ${TARGET_VERSION} 2>&1 | tee -a $WORKDIR/documents/migration_error.html | sed -e 's/<[^>]*>//g' -e '/^<!--.*$/d' -e '/^.*-->$/d' -e '/^$/d'
    r=$?
    echo "" >$WORKDIR/documents/install.lock && chown www-data:www-data $WORKDIR/html/conf/conf.php && chmod 400 $WORKDIR/html/conf/conf.php
    popd >/dev/null

    if [[ ${r} -ne 0 ]]; then
        echo "Migration failed ... Restoring DB ... check file $WORKDIR/documents/migration_error.html for more info on error ..."
        mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <$WORKDIR/documents/dump.sql
        echo "DB Restored ..."
        return ${r}
    else
        echo "Migration successful ... Enjoy !!"
    fi

    return 0
}

########################
# Run upgrade.php / upgrade2.php / step5.php from an install directory
# Arguments: $1 install dir, $2 from version, $3 to version
# Returns: 0 on success
function _runUpgradeScripts() {
    local DIR=$1 FROM=$2 TO=$3 rc=0
    pushd "$DIR" >/dev/null
    for step in upgrade upgrade2 step5; do
        echo "=== ${step}.php ${FROM} ${TO}"
        php ${step}.php ${FROM} ${TO} 2>&1 | tee -a $WORKDIR/documents/migration_error.html | sed -e 's/<[^>]*>//g' -e '/^<!--.*$/d' -e '/^.*-->$/d' -e '/^$/d' | tail -n 3
        rc=${PIPESTATUS[0]}
        if [[ ${rc} -ne 0 ]]; then
            popd >/dev/null
            return ${rc}
        fi
    done
    popd >/dev/null
    return 0
}

########################
# Migrate through intermediate major versions that are NOT in this image (Dolibarr must be upgraded one
# major version at a time: 22 -> 23 -> 24). For each tag given, the official tarball is downloaded, run
# with a copy of conf.php, then the image's own version is applied (migrateDatabase). The database is
# dumped first and restored if any step fails.
# Usage: migrateStepwise 23.0.4        (image = 24.x : 22.x -> 23.0.0 -> 24.0.0)
#        migrateStepwise 23.0.4 24.0.2 (several intermediate tags, in order)
function migrateStepwise() {
    if [[ $# -lt 1 ]]; then
        echo "Usage: migrateStepwise <intermediate-tag> [<intermediate-tag> ...]  (ex: migrateStepwise 23.0.4)"
        return 1
    fi
    local SAFE=pre-stepwise-$(date +%Y%m%d-%H%M%S).sql
    dumpDatabase ${SAFE} || return $?
    echo "Safety dump: $WORKDIR/documents/${SAFE}"
    echo "" >$WORKDIR/documents/migration_error.html
    local TAG TO FROM TREE
    for TAG in "$@"; do
        TO="$(echo ${TAG} | cut -d. -f1).$(echo ${TAG} | cut -d. -f2).0"
        FROM=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
        if ! version_gt ${TO} ${FROM}; then
            echo "Step ${TAG}: database already at ${FROM}, nothing to do."
            continue
        fi
        echo "##### Intermediate step: ${FROM} -> ${TO} with Dolibarr ${TAG}"
        TREE=/tmp/code-${TAG}
        rm -rf ${TREE} && mkdir -p ${TREE} || return 1
        if ! curl -fLsS https://github.com/Dolibarr/dolibarr/archive/${TAG}.tar.gz | tar -C ${TREE} -xz; then
            echo "Download of Dolibarr ${TAG} failed ... Aborting (database untouched)."
            return 1
        fi
        cp $WORKDIR/html/conf/conf.php ${TREE}/dolibarr-${TAG}/htdocs/conf/conf.php
        sed -i "s#'$WORKDIR/html'#'${TREE}/dolibarr-${TAG}/htdocs'#" ${TREE}/dolibarr-${TAG}/htdocs/conf/conf.php
        rm -f $WORKDIR/documents/install.lock
        _runUpgradeScripts ${TREE}/dolibarr-${TAG}/htdocs/install ${FROM} ${TO}
        if [[ $? -ne 0 ]]; then
            echo "Intermediate step ${TAG} failed ... Restoring DB from ${SAFE} ..."
            restoreDatabase $WORKDIR/documents/${SAFE}
            echo "" >$WORKDIR/documents/install.lock && chown www-data:www-data $WORKDIR/documents/install.lock && chmod 400 $WORKDIR/documents/install.lock
            return 1
        fi
        rm -rf ${TREE}
    done
    echo "##### Final step with the image version ${DOLI_VERSION}"
    migrateDatabase
}

########################
# Migrate the database to the current version if required
function automigrate() {
    FROM_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
    if version_gt ${DOLI_VERSION} ${FROM_VERSION}; then
        migrateDatabase
    else
        echo "Schema update is not required DB=${FROM_VERSION}, APP=${DOLI_VERSION}... Enjoy !!"
    fi
}

########################
# Open a mysql shell to the database
function mysql_shell() {
    mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME}
}
echo "Migration script loaded ..."
echo "   available commands:"
echo "   - dumpDatabase [dumpfile.sql] : Dump the database to a file"
echo "   - restoreDatabase [dumpfile.sql] : Restore the database from a file can be a .sql, .gz, .bz2 or .zip file"
echo "   - migrateDatabase : Migrate the database to the current version (ONE major version at a time!)"
echo "   - migrateStepwise <tag> [<tag>...] : Migrate through intermediate majors not in this image (ex: migrateStepwise 23.0.4 for 22.x -> 24.x)"
echo "   - automigrate : Migrate the database to the current version if required"
echo "   - mysql_shell : Open a mysql shell to the database"
echo ""
echo "For manual migration, run the following command:"
echo "source /usr/local/bin/migrate2 && automigrate"
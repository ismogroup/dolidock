#!/bin/bash

source /usr/local/bin/migrate2

# usage: get_env_value VAR [DEFAULT]
#    ie: get_env_value 'XYZ_DB_PASSWORD' 'example'
# (will allow for "$XYZ_DB_PASSWORD_FILE" to fill in the value of
#  "$XYZ_DB_PASSWORD" from a file, especially for Docker's secrets feature)
function get_env_value() {
  local varName="${1}"
  local fileVarName="${varName}_FILE"
  local defaultValue="${2:-}"

  if [ "${!varName:-}" ] && [ "${!fileVarName:-}" ]; then
    echo >&2 "error: both ${varName} and ${fileVarName} are set (but are exclusive)"
    exit 1
  fi

  local value="${defaultValue}"
  if [ "${!varName:-}" ]; then
    value="${!varName}"
  elif [ "${!fileVarName:-}" ]; then
    value="$(<"${!fileVarName}")"
  fi

  echo ${value}
  exit 0
}

function initDolibarr() {
  local CURRENT_UID=$(id -u www-data)
  local CURRENT_GID=$(id -g www-data)
  usermod -u ${WWW_USER_ID} www-data
  groupmod -g ${WWW_GROUP_ID} www-data

  if [[ ! -d /var/www/dolidock/documents ]]; then
    echo "[INIT] => create volume directory /var/www/dolidock/documents ..."
    mkdir -p /var/www/documents
  fi

  echo "[INIT] => update PHP Config ..."
  cat >${PHP_INI_DIR}/conf.d/dolibarr-php.ini <<EOF
date.timezone = ${PHP_INI_DATE_TIMEZONE}
sendmail_path = /usr/sbin/sendmail -t -i
memory_limit = ${PHP_INI_MEMORY_LIMIT}
EOF

  if [[ ! -f /var/www/dolidock/html/conf/conf.php ]]; then
    UNIQUE_ID=$(echo "$DOLI_URL_ROOT" | sha1sum | cut -d ' ' -f 1)
    echo "[INIT] => update Dolibarr Config ..."
    cat >/var/www/dolidock/html/conf/conf.php <<EOF
<?php
\$dolibarr_main_prod="1";
\$dolibarr_main_url_root='${DOLI_URL_ROOT}';
\$dolibarr_main_document_root='/var/www/dolidock/html';
\$dolibarr_main_url_root_alt='/custom';
\$dolibarr_main_document_root_alt='/var/www/dolidock/html/custom';
\$dolibarr_main_data_root='/var/www/dolidock/documents';
\$dolibarr_main_db_host='${DOLI_DB_HOST}';
\$dolibarr_main_db_port='${DOLI_DB_HOST_PORT}';
\$dolibarr_main_db_name='${DOLI_DB_NAME}';
\$dolibarr_main_db_prefix='llx_';
\$dolibarr_main_db_user='${DOLI_DB_USER}';
\$dolibarr_main_db_pass='${DOLI_DB_PASSWORD}';
\$dolibarr_main_db_type='${DOLI_DB_TYPE}';
\$dolibarr_main_authentication='${DOLI_AUTH}';
\$dolibarr_main_instance_unique_id='${UNIQUE_ID}';
EOF
    if [[ ${DOLI_AUTH} =~ .*ldap.* ]]; then
      echo "[INIT] => update Dolibarr Config with LDAP entries ..."
      cat >>/var/www/dolidock/html/conf/conf.php <<EOF
\$dolibarr_main_auth_ldap_host='${DOLI_LDAP_HOST}';
\$dolibarr_main_auth_ldap_port='${DOLI_LDAP_PORT}';
\$dolibarr_main_auth_ldap_version='${DOLI_LDAP_VERSION}';
\$dolibarr_main_auth_ldap_servertype='${DOLI_LDAP_SERVER_TYPE}';
\$dolibarr_main_auth_ldap_login_attribute='${DOLI_LDAP_LOGIN_ATTRIBUTE}';
\$dolibarr_main_auth_ldap_dn='${DOLI_LDAP_DN}';
\$dolibarr_main_auth_ldap_filter='${DOLI_LDAP_FILTER}';
\$dolibarr_main_auth_ldap_admin_login='${DOLI_LDAP_BIND_DN}';
\$dolibarr_main_auth_ldap_admin_pass='${DOLI_LDAP_BIND_PASS}';
\$dolibarr_main_auth_ldap_debug='${DOLI_LDAP_DEBUG}';
EOF
    fi
  fi

  echo "[INIT] => update ownership for file in Dolibarr Config ..."
  chown www-data:www-data /var/www/dolidock/html/conf/conf.php
  if [[ ${DOLI_DB_TYPE} == "pgsql" && ! -f /var/www/dolidock/documents/install.lock ]]; then
    chmod 600 /var/www/dolidock/html/conf/conf.php
  else
    chmod 400 /var/www/dolidock/html/conf/conf.php
  fi

  if [[ ${CURRENT_UID} -ne ${WWW_USER_ID} || ${CURRENT_GID} -ne ${WWW_GROUP_ID} ]]; then
    # Refresh file ownership cause it has changed
    echo "[INIT] => As UID / GID have changed from default, update ownership for files in /var/ww ..."
    chown -R www-data:www-data /var/www
  else
    # Reducing load on init : change ownership only for volumes declared in docker
    echo "[INIT] => update ownership for files in /var/www/dolidock/documents ..."
    chown -R www-data:www-data /var/www/dolidock/documents
  fi
}

function waitForDataBase() {
  r=1

  while [[ ${r} -ne 0 ]]; do
    mysql -u ${DOLI_DB_USER} --protocol tcp -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} --connect-timeout=5 -e "status" >/dev/null 2>&1
    r=$?
    if [[ ${r} -ne 0 ]]; then
      echo "Waiting that SQL database is up ..."
      sleep 2
    fi
  done
}

function lockInstallation() {
  touch /var/www/dolidock/documents/install.lock
  chown www-data:www-data /var/www/dolidock/documents/install.lock
  chmod 400 /var/www/dolidock/documents/install.lock
}

function initializeDatabase() {
  for fileSQL in /var/www/dolidock/html/install/mysql/tables/*.sql; do
    if [[ ${fileSQL} != *.key.sql ]]; then
      echo "Importing table from $(basename ${fileSQL}) ..."
      sed -i 's/--.*//g;' ${fileSQL} # remove all comment
      mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <${fileSQL}
    fi
  done

  for fileSQL in /var/www/dolidock/html/install/mysql/tables/*.key.sql; do
    echo "Importing table key from $(basename ${fileSQL}) ..."
    sed -i 's/--.*//g;' ${fileSQL}
    mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <${fileSQL} >/dev/null 2>&1
  done

  for fileSQL in /var/www/dolidock/html/install/mysql/functions/*.sql; do
    echo "Importing $(basename ${fileSQL}) ..."
    sed -i 's/--.*//g;' ${fileSQL}
    mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <${fileSQL} >/dev/null 2>&1
  done

  for fileSQL in /var/www/dolidock/html/install/mysql/data/*.sql; do
    echo "Importing data from $(basename ${fileSQL}) ..."
    sed -i 's/--.*//g;' ${fileSQL}
    mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} <${fileSQL} >/dev/null 2>&1
  done

  echo "Create SuperAdmin account ..."
  pass_crypted=$(echo -n ${DOLI_ADMIN_PASSWORD} | md5sum | awk '{print $1}')
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "INSERT INTO llx_user (entity, login, pass_crypted, lastname, admin, statut) VALUES (0, '${DOLI_ADMIN_LOGIN}', '${pass_crypted}', 'SuperAdmin', 1, 1);" >/dev/null 2>&1

  echo "Set some default const ..."
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "DELETE FROM llx_const WHERE name='MAIN_VERSION_LAST_INSTALL';" >/dev/null 2>&1
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "DELETE FROM llx_const WHERE name='MAIN_NOT_INSTALLED';" >/dev/null 2>&1
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "DELETE FROM llx_const WHERE name='MAIN_LANG_DEFAULT';" >/dev/null 2>&1
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "INSERT INTO llx_const(name,value,type,visible,note,entity) values('MAIN_VERSION_LAST_INSTALL', '${DOLI_VERSION}', 'chaine', 0, 'Dolibarr version when install', 0);" >/dev/null 2>&1
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "INSERT INTO llx_const(name,value,type,visible,note,entity) VALUES ('MAIN_LANG_DEFAULT', 'auto', 'chaine', 0, 'Default language', 1);" >/dev/null 2>&1
  mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "INSERT INTO llx_const(name,value,type,visible,note,entity) VALUES ('SYSTEMTOOLS_MYSQLDUMP', '/usr/bin/mysqldump', 'chaine', 0, '', 0);" >/dev/null 2>&1

  echo "Grant RELOAD to ${DOLI_DB_USER}"
  echo "GRANT RELOAD ON *.* TO ${DOLI_DB_USER};" | mysql -u root -p${MYSQL_ROOT_PASSWORD} -h ${DOLI_DB_HOST} >/dev/null 2>&1
}

function run() {
  initDolibarr
  echo "Current Version is : ${DOLI_VERSION}"

  if [[ ${DOLI_INSTALL_AUTO} -eq 1 && ${DOLI_CRON} -ne 1 && ! -f /var/www/dolidock/documents/install.lock && ${DOLI_DB_TYPE} != "pgsql" ]]; then
    waitForDataBase

    mysql -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1" >/tmp/lastinstall.result 2>&1
    r=$?
    if [[ ${r} -ne 0 ]]; then
      if ! is_boolean_yes "${DOLI_INIT_FROM_S3}"; then
        echo "Initializing database from scratch ..."
        initializeDatabase
      else
        /usr/local/bin/initfrom-s3
        FROM_VERSION=$(mysql -N -u ${DOLI_DB_USER} -p${DOLI_DB_PASSWORD} -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} ${DOLI_DB_NAME} -e "SELECT Q.LAST_INSTALLED_VERSION FROM (SELECT INET_ATON(CONCAT(value, REPEAT('.0', 3 - CHAR_LENGTH(value) + CHAR_LENGTH(REPLACE(value, '.', ''))))) as VERSION_ATON, value as LAST_INSTALLED_VERSION FROM llx_const WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE') and entity=0) Q ORDER BY VERSION_ATON DESC LIMIT 1")
        if version_gt ${DOLI_VERSION} ${FROM_VERSION} ; then
          migrateDatabase
        else
          echo "Schema update is not required DB=${FROM_VERSION}, APP=${DOLI_VERSION}... Enjoy !!"
        fi
      fi

    else
      INSTALLED_VERSION=$(grep -v LAST_INSTALLED_VERSION /tmp/lastinstall.result)
      echo "Last installed Version is : ${INSTALLED_VERSION}"
      if [[ "$(echo ${INSTALLED_VERSION} | cut -d. -f1)" -lt "$(echo ${DOLI_VERSION} | cut -d. -f1)" ]]; then
        migrateDatabase
      else
        echo "Schema update is not required ... Enjoy !!"
      fi
    fi

    if [[ ${DOLI_VERSION} != "develop" ]]; then
      lockInstallation
    fi
  fi
}

DOLI_DB_USER=$(get_env_value 'DOLI_DB_USER' 'doli')
DOLI_DB_PASSWORD=$(get_env_value 'DOLI_DB_PASSWORD' 'doli_pass')
DOLI_ADMIN_LOGIN=$(get_env_value 'DOLI_ADMIN_LOGIN' 'admin')
DOLI_ADMIN_PASSWORD=$(get_env_value 'DOLI_ADMIN_PASSWORD' 'admin')

#create php session dir if not exists
mkdir -p /var/www/dolidock/documents/sessions
#clean old sesssions
rm -rf /var/www/dolidock/documents/sessions/*

run

set -e

if [[ ${DOLI_CRON} -eq 1 ]]; then
  echo "PATH=\$PATH:/usr/local/bin" >/etc/cron.d/dolibarr
  echo "*/5 * * * * root /bin/su www-data -s /bin/sh -c '/var/www/scripts/cron/cron_run_jobs.php ${DOLI_CRON_KEY} ${DOLI_CRON_USER}' > /proc/1/fd/1 2> /proc/1/fd/2" >>/etc/cron.d/dolibarr
  cron -f
  exit 0
fi

if [[ -n ${BACKUP_FROM} ]]; then
  echo "PATH=\$PATH:/usr/local/bin" >/etc/cron.d/autobackup
  echo "0 0 * * 0 root /usr/local/bin/autobackup" >>/etc/cron.d/autobackup
  cron
fi

if [ "${1#-}" != "$1" ]; then
  set -- apache2-foreground "$@"
fi

exec "$@"

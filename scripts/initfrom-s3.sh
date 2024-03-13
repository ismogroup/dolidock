#!/bin/bash
BACKUP_DIR="/var/www/dolidock/documents/admin/backup"
FILESTOR_DIR=/var/www/dolidock/documents

# test if variables DOLI_DB_HOST, DOLI_DB_USER, MYSQL_ROOT_PASSWORD, DOLI_DB_PASSWORD are set
if [ -z "${DOLI_DB_HOST}" ] || [ -z "${DOLI_DB_USER}" ] || [ -z "${MYSQL_ROOT_PASSWORD}" ] || [ -z "${DOLI_DB_PASSWORD}" ]; then
    echo "MYSQL_ROOT_PASSWORD, DOLI_DB_HOST, DOLI_DB_USER, DOLI_DB_PASSWORD are not set"
    exit 1
fi
# default database port is 5432
DOLI_DB_HOST_PORT=${DOLI_DB_HOST_PORT:-3306}

# Test if we can connect to database with mysql
if ! mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} -e "SHOW DATABASES;" > /dev/null 2>&1; then
    echo "Could not connect to the database"
    exit 1
fi

# test if variables S3_BUCKET, S3_ACCESS_KEY, S3_SECRET_KEY, S3_ENDPOINT, S3_REGION, S3_PATH are set
if [ -z "${S3_BUCKET}" ] || [ -z "${S3_ACCESS_KEY}" ] || [ -z "${S3_SECRET_KEY}" ] || [ -z "${S3_ENDPOINT}" ] || [ -z "${S3_PATH}" ]; then
    echo "S3_BUCKET, S3_ACCESS_KEY, S3_SECRET_KEY, S3_ENDPOINT, S3_REGION, S3_PATH are not set"
    exit 1
fi

# test if mc is installed
if [ -z "$(which mc)" ]; then
    echo "mc is not installed"
    exit 1
fi

#  Test if mc alias s3backup exists
if [ -z "$(mc alias list | grep s3backup)" ]; then
    echo "s3backup alias not found"
    echo "create s3backup alias"
    mc alias set s3backup ${S3_ENDPOINT} ${S3_ACCESS_KEY} ${S3_SECRET_KEY}
fi

# create a backup directory
mkdir -p ${BACKUP_DIR}

# Find latest backup file in s3 if S3_DOLIDOCK_FILE is not set
if [ -z "${S3_DOLIDOCK_FILE}" ]; then
    LATEST_BACKUP=$(mc ls s3backup/${S3_BUCKET}/${S3_PATH} | sort -r | head -n 1 | awk '{print $6}')
else
    LATEST_BACKUP=${S3_DOLIDOCK_FILE}
fi
mc cp s3backup/${S3_BUCKET}/${S3_PATH}/${LATEST_BACKUP} ${BACKUP_DIR}/${LATEST_BACKUP}

# If the backup extension is .enc, decrypt it
if [ "${LATEST_BACKUP##*.}" == "enc" ]; then
    LATEST_BACKUP_BASE=$(echo $LATEST_BACKUP | sed 's/.enc//')
    DECRYPT=""
    if [ -n "$CRYPTOKEN" ]; then
        echo "Archive is encrypted, decrypting"
        DECRYPT="-pass pass:$CRYPTOKEN"
    else
        echo "CRYPTOKEN is not set but backup file is encrypted"
        exit 1
    fi
    openssl aes-256-cbc -a -d -md sha256 $DECRYPT -in ${BACKUP_DIR}/${LATEST_BACKUP} -out - >${BACKUP_DIR}/${LATEST_BACKUP_BASE}
    rm -f ${BACKUP_DIR}/${LATEST_BACKUP}
    LATEST_BACKUP=${LATEST_BACKUP_BASE}
fi
# Unzip the backup file to /tmp
TMP_DIR=$(mktemp -d)
tar -C ${TMP_DIR} -xJf ${BACKUP_DIR}/${LATEST_BACKUP}




# Restore the database from the dump
echo "Restore the database from the dump this may take a while..."
mysql -h ${DOLI_DB_HOST} -P ${DOLI_DB_HOST_PORT} -u root -p${MYSQL_ROOT_PASSWORD} < ${TMP_DIR}/backup-dolly.sql

echo "Restore filestore"
# Remove the filestore directory
mkdir -p ${FILESTOR_DIR}
cp -r ${TMP_DIR}/documents/* ${FILESTOR_DIR}/
rm -rf ${TMP_DIR}
rm -f ${BACKUP_DIR}/${LATEST_BACKUP}

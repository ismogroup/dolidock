# Dolidock: Enhanced Dolibarr for Kubernetes

[![Publish image to docker Hub](https://github.com/ismogroup/dolidock/actions/workflows/publish-docker-hub.yml/badge.svg)](https://hub.docker.com/r/ismogroup/dolidock)

Streamlined Dolibarr ERP & CRM deployment with advanced features for Kubernetes environments.

## Table of Contents

- [Dolidock: Enhanced Dolibarr for Kubernetes](#dolidock-enhanced-dolibarr-for-kubernetes)
  - [Table of Contents](#table-of-contents)
  - [Overview](#overview)
    - [What is Dolibarr?](#what-is-dolibarr)
  - [Features](#features)
    - [Docker Image Enhancements](#docker-image-enhancements)
    - [Kubernetes Stack Components](#kubernetes-stack-components)
  - [Deployment Options](#deployment-options)
    - [Prerequisites](#prerequisites)
    - [Quick Start with Helm](#quick-start-with-helm)
    - [Deploying on Okteto](#deploying-on-okteto)
    - [Deploying on Other Kubernetes Clusters](#deploying-on-other-kubernetes-clusters)
  - [Configuration](#configuration)
    - [Environment Variables](#environment-variables)
    - [Email Configuration](#email-configuration)
    - [S3 Backup and Restore](#s3-backup-and-restore)
  - [Maintenance Operations](#maintenance-operations)
    - [Database Migration](#database-migration)
    - [Updating Dolibarr](#updating-dolibarr)
    - [Backup and Restore](#backup-and-restore)
  - [Advanced Features](#advanced-features)
    - [SMTPd with DKIM Signing](#smtpd-with-dkim-signing)
    - [Dolirate Integration](#dolirate-integration)
    - [Crontab-UI](#crontab-ui)
    - [PhpMyAdmin Access](#phpmyadmin-access)
  - [Known Issues](#known-issues)
  - [Building Locally](#building-locally)

## Overview

Dolidock is an enhanced Docker image for Dolibarr ERP & CRM, optimized for Kubernetes deployments. It provides automatic database initialization, migration capabilities, automated backups, and S3 integration for reliable data management.

### What is Dolibarr?

Dolibarr ERP & CRM is a modern software package to manage your organization's activity (contacts, suppliers, invoices, orders, stocks, agenda, etc.).

> [Learn more about Dolibarr](https://github.com/dolibarr/dolibarr)

## Features

### Docker Image Enhancements

- Latest MySQL libraries from Oracle/MySQL
- Support for bzip2 compression for backup
- Horizontal scaling with shared PHP sessions (tested up to 4 replicas)
- PHP Memcached support for improved performance
- Includes all Dolicloud/DoliMods modules
- Multi-architecture support (linux/amd64 and linux/arm64)
- Automatic database migration
- Automatic email backups
- S3 bucket restoration

### Kubernetes Stack Components

- Built-in Postfix server with DKIM signing and Cloudflare DDNS (scalable)
- Built-in Memcached server for performance
- Built-in phpMyAdmin for database management
- Built-in cron server with web UI and cloud commander
- Cloudflare tunnel support

## Deployment Options

### Prerequisites

Before deploying, you'll need:

- Kubernetes cluster access
- Helm (for Helm chart deployment)
- S3-compatible storage (optional, for backups)
- Cloudflare account (for DKIM and DNS features)

### Quick Start with Helm

```sh
helm repo add highcanfly https://helm-repo.highcanfly.club/
helm repo update
helm install --create-namespace --namespace=dolidock dolidock highcanfly/dolidock \
        --values your-values.yaml
```

Create a `values.yaml` file based on this template:

```yaml
dolidock:
  image:
    tag: 21.0.1.0
  allowedSenderDomains: "example.org"
  doliAdminPassword: "strong-password"
  doliDbPassword: "strong-db-password"
  mysqlRootPassword: "strong-root-password"
  hostname: erp.example.org
  doliUrlRoot: https://erp.example.org
  
  # Email backup configuration
  backupFrom: "no-reply@example.org"
  backupTo: "admin@example.org"
  autobackupJob: true

  # S3 restoration configuration (optional)
  s3Bucket: "your-bucket"
  s3Path: "backup-path"
  s3Endpoint: "https://your-s3-endpoint"
  s3AccessKey: "your-access-key"
  s3SecretKey: "your-secret-key"
  s3Region: "your-region"
  s3Cryptoken: "your-encryption-key"
  doliInitFromS3: "false"  # Set to "true" to restore from S3 on startup
  
  # DKIM configuration
  dkimSelector: dkim
  dkimPrivateKey: "----BEGIN PRIVATE KEY-----|your-key-here|-----END PRIVATE KEY-----"
```

### Deploying on Okteto

For development environments, Okteto provides a straightforward deployment option:

1. Get your Okteto Kubernetes credentials
2. Set required environment variables
3. Deploy using kubectl:

```sh
envsubst < k8s.yml | kubectl apply --kubeconfig okteto-kube.config -f -
```

> **Note:** Set DOLIDOCK_REPLICAS=1 for initial installation, then scale up afterward.

### Deploying on Other Kubernetes Clusters

For other Kubernetes clusters:

1. Adjust the namespace variable (OKETO_NS) to match your target namespace
2. Modify the Ingress configuration in k8s.yml to match your cluster's ingress controller
3. Apply with kubectl using your cluster's configuration

## Configuration

### Environment Variables

Key environment variables and their descriptions:

| Variable | Description | Example |
|----------|-------------|---------|
| DOLI_ADMIN_LOGIN | Dolibarr admin username | administrator |
| DOLI_ADMIN_PASSWORD | Dolibarr admin password | strongpassword |
| DOLI_DB_USER | Database username | doliuser |
| DOLI_DB_PASSWORD | Database password | dbpassword |
| DOLI_DB_NAME | Database name | dolibarr |
| MYSQL_ROOT_PASSWORD | MySQL root password | rootpassword |
| DOLI_INIT_FROM_S3 | Enable init from S3 | true |
| BACKUPFROM | Email address for backups | <no-reply@example.org> |
| BACKUPTO | Recipient of backup emails | <admin@example.org> |

### Email Configuration

The integrated email server supports:

1. DKIM signing for improved deliverability
2. Cloudflare DDNS for SPF record validation
3. Automatic TLS certificate management via Let's Encrypt

To generate a DKIM key:

```sh
openssl genrsa -out /dev/stdout 2048 | tr '\n' '|' | sed 's/.$//'
```

Generate the DNS record value for your _domainkey TXT record:

```sh
echo $DKIM_PRIVATE_KEY | tr '|' '\n' | openssl rsa -pubout 2> /dev/null | sed -e '1d' -e '$d' | tr -d '\n' | echo "v=DKIM1; h=sha256; k=rsa; s=email; p=$(</dev/stdin)"
```

### S3 Backup and Restore

To initialize Dolibarr from an S3 backup:

1. Set `DOLI_INIT_FROM_S3=true`
2. Configure the following variables:

| Variable | Description |
|----------|-------------|
| S3_BUCKET | S3 bucket name |
| S3_ACCESS_KEY | S3 access key |
| S3_SECRET_KEY | S3 secret key |
| S3_ENDPOINT | S3 endpoint URL |
| S3_REGION | S3 region |
| S3_PATH | Path in bucket |
| S3_DOLIDOCK_FILE | Specific backup file (optional) |
| CRYPTOKEN | Decryption password |

The system will automatically find the latest backup file if S3_DOLIDOCK_FILE is not specified.

## Maintenance Operations

### Database Migration

The docker image includes a powerful database migration script with several functions:

```sh
# Connect to a pod
kubectl exec -it [pod-name] -- bash

# Load migration functions
source /usr/local/bin/migrate2

# Available commands:
dumpDatabase [filename.sql]  # Dump database to file
restoreDatabase filename.sql  # Restore from SQL file (.sql, .gz, .bz2, .zip)
migrateDatabase  # Manual migration
automigrate  # Automatic migration if needed
mysql_shell  # Open MySQL shell
```

### Updating Dolibarr

To update an existing installation:

1. Find your pod: `kubectl get pods`
2. Connect to the pod: `kubectl exec -it dolidock-pod-name -- bash`
3. Remove the lock file: `rm /var/www/dolidock/documents/install.lock`
4. Restart the cluster and follow the UI instructions
5. After updating, recreate the lock file: `echo "" > /var/www/dolidock/documents/install.lock`

A helper script is available: upgrade-helper.sh

### Backup and Restore

Automatic backups can be configured via email or to an S3 bucket. Manual backups can be performed using the migration script:

```sh
source /usr/local/bin/migrate2 && dumpDatabase my-backup.sql
```

## Advanced Features

### SMTPd with DKIM Signing

The integrated Postfix server provides:

- DKIM signature for improved email deliverability
- Automatic DNS updates via Cloudflare API
- Let's Encrypt certificate integration

Required Cloudflare setup:

1. Create an API token with DNS Edit permissions
2. Configure A, TXT (SPF), and DKIM records as described in the configuration section

### Dolirate Integration

[Dolirate](https://github.com/ismogroup/dolirate) automatically updates currency exchange rates in Dolibarr. Access via:

```web
http://dolirate:3000/updaterates
```

### Crontab-UI

The integrated [Crontab-UI](https://github.com/highcanfly-club/crontab-ui) provides a web interface for managing scheduled tasks:

- URL: <https://crontabui-NAMESPACE.cloud.okteto.net>
- Default credentials: Set via BASIC_AUTH_USER and BASIC_AUTH_PWD

### PhpMyAdmin Access

Access the database via phpMyAdmin:

- URL: <https://admin-NAMESPACE.cloud.okteto.net>
- Login with root and MYSQL_ROOT_PASSWORD

## Known Issues

- Users and Groups module may not be automatically active. Enable it manually.
- Some UI warnings may appear in the frontend.
- DOLI_DB_USER may need RELOAD privilege granted manually.

## Building Locally

While the repository uses GitHub Actions for builds, you can build locally:

```sh
docker login --username=ismogroup
docker buildx create --use
docker buildx build --push --platform linux/amd64,linux/arm64 --tag ismogroup/busybox:1.37.0-php-8.3-apache --tag ismogroup/busybox:latest -f Dockerfile.busybox .
docker buildx build --push --platform linux/amd64,linux/arm64 --tag ismogroup/dolidock:21.0.1.4 --tag ismogroup/dolidock:latest  .
```

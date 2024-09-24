[![Publish image to docker Hub](https://github.com/ismogroup/dolidock/actions/workflows/publish-docker-hub.yml/badge.svg)](https://hub.docker.com/r/ismogroup/dolidock)

- [1. Dolibarr on Docker](#1-dolibarr-on-docker)
  - [1.1. What is Dolibarr ?](#11-what-is-dolibarr-)
  - [1.2. What differs ?](#12-what-differs-)
    - [1.2.1. Docker image](#121-docker-image)
    - [1.2.2. Docker Compose stack / Kubernetes](#122-docker-compose-stack--kubernetes)
  - [1.3. How to ?](#13-how-to-)
  - [1.4. Initializing Dolibarr Database from a File in an S3 Bucket](#14-initializing-dolibarr-database-from-a-file-in-an-s3-bucket)
  - [1.5. Deploy on Okteto](#15-deploy-on-okteto)
  - [1.6. Deploy on other Kubernetes cluster](#16-deploy-on-other-kubernetes-cluster)
  - [1.7. DKIM key and \_domainkey record](#17-dkim-key-and-_domainkey-record)
  - [1.8. SMTPd server](#18-smtpd-server)
  - [1.9. Dolirate](#19-dolirate)
  - [1.10. Crontab-UI](#110-crontab-ui)
  - [1.11. PhpMyAdmin](#111-phpmyadmin)
  - [1.12. Known issues](#112-known-issues)
  - [1.13. Update](#113-update)
- [2. Helm Chart](#2-helm-chart)

# 1. Dolibarr on Docker

Docker image for Dolibarr 19.0.3 with auto installer on first boot.

## 1.1. What is Dolibarr ?

Dolibarr ERP & CRM is a modern software package to manage your organization's activity (contacts, suppliers, invoices, orders, stocks, agenda, ...).

> [More information](https://github.com/dolibarr/dolibarr)

## 1.2. What differs ?

### 1.2.1. Docker image

- Use latest MySql libraries from Oracle/MySql
- Supports bzip2 compression for backup
- Can be scaled up (php session are shared) / tested up to 4 replicas
- php-memcached
- Contains all Dolicloud/DoliMods module
- linux/amd64 and linux/arm64 platform (on arm db client is MariaDB, Mysql on amd64 )

### 1.2.2. Docker Compose stack / Kubernetes

- builtin Postfix server with dkim signing and Cloudflare DDNS (scalable)
- builin memcached server
- builtin phpMyAdmin server
- builtin cron server with web ui and cloud commander

## 1.3. How to ?

The simplest use is to use docker compose…
It is designed for Cloudflare, it automatically updates a Cloudflare DNS zone. You need to get your ZoneID and generate a token (api key) for editing your dns zone.  
You must also create a CLOUDFLARE_DNS_RECORDS host in Cloudflare UI before first run.  
All this are for having a valid POSTFIX_HOSTNAME dns record (for SPF) and a Letsencrypt certificate.  
for creating a DKIM key simply issue:  

```sh
openssl genrsa -out /dev/stdout 2048
```

And replace all newline by |  

You finally need to define some environment variables:  

```sh
#!/bin/bash
NAMESPACE="oktetons"
FQDN_DOLISTOCK="erp-oketons.cloud.okteto.net"
DOLI_ADMIN_LOGIN="administrator"
DOLI_ADMIN_PASSWORD="strongpassword"
DOLI_DB_USER="adbuser"
DOLI_DB_PASSWORD="anotherpassword"
DOLI_DB_NAME="dolismo"
MYSQL_ROOT_PASSWORD="averystrongpassword"
DKIM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----|MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA|rxSPI0KqL9mH2JyWFexZziV3RuE7DIf+IFVPLsrxSrfsZqYOFuBamfPVLVHNx+Ma|dbDPH+KzOc5sMNDkLebWg+qddpTm6Zy0mUACRbFijF1TjPRiwnpEpScGUSS+Cs8U|Coe+cQBuoTsIHpowYjVbps4=|-----END PRIVATE KEY-----"
DKIM_SELECTOR="dkimselector"
ALLOWED_SENDER_DOMAINS="example.org"
CLOUDFLARE_API_KEY="iTndzksdfjkJHsldkzjenLLDg8Ca7MYx2"
CLOUDFLARE_ZONE_ID="721d168a07e873d73ec452713fcbb03f"
CLOUDFLARE_DNS_RECORDS="smtp.example.org"
POSTFIX_HOSTNAME="smtp.example.org"
BASIC_AUTH_USER="admin"
BASIC_AUTH_PWD="latestpassword"
SMTPD_REPLICAS="2"
DOLIDOCK_REPLICAS="3"
```

## 1.4. Initializing Dolibarr Database from a File in an S3 Bucket

If you want to initialize the Dolibarr database from a file in an S3 bucket, you need to set the environment variable `DOLI_INIT_FROM_S3=true` in the Dolibarr container.

The `initfrom-s3.sh` script will be executed, which uses the following environment variables:

| Variable | Description | Helm Value Path |
| --- | --- | --- |
| `S3_BUCKET` | The name of the S3 bucket where the backup file is located. | `dolidock.s3Bucket` |
| `S3_ACCESS_KEY` | The access key to access the S3 bucket | `dolidock.s3AccessKey` |
| `S3_SECRET_KEY` | The secret key to access the S3 bucket. | `dolidock.s3SecretKey` |
| `S3_ENDPOINT` | The endpoint of the S3 service. | `dolidock.s3Endpoint` |
| `S3_REGION` | The region of the S3 service. | `dolidock.s3Region` |
| `S3_PATH` | The path in the S3 bucket where the backup file is located. | `dolidock.s3Path` |
| `S3_DOLIDOCK_FILE` | The name of the backup file. If this variable is not set, the script will use the most recent file in the specified path. | Not applicable |
| `CRYPTOKEN` | The password to decrypt the backup file. | `dolidock.s3Cryptoken` |
| `DOLI_INIT_FROM_S3` | If "true" initialize from S3 | `doliInitFromS3` |

The script first checks if all necessary variables are set. Then, it checks if the `mc` tool (MinIO Client) is installed and if the `s3backup` alias is set. If the alias is not set, the script sets it.

Next, the script creates a backup directory, finds the most recent backup file in the S3 bucket (or uses the file specified by `S3_DOLI_FILE`), copies it to the backup directory, and if the backup file has the `.enc` extension, decrypts it using the password in `CRYPTOKEN` variable.

Finally, the script restores the database from the dump and the filestore from the backup.

## 1.5. Deploy on Okteto

For developping I'm using Okteto free tier.  
If you have an Okteto account, retrieve the kube config (settings Kubernetes credentials), and if you have all the required environment variables
|Variable|Sample value|Description|
|----|----|----|
|NAMESPACE|oktetons|Kubernetes namespace|
|FQDN_DOLISTOCK|erp-oketons.cloud.okteto.net|FQDN of the main url|
|DOLI_ADMIN_LOGIN|administrator|Dolibarr SuperAdmin|
|DOLI_ADMIN_PASSWORD|strongpassword|SuperAdmin password|
|DOLI_DB_USER|adbuser|Owner of Dolibarr database|
|DOLI_DB_PASSWORD|anotherpassword|Dolibarr database user password|
|DOLI_DB_NAME|dolismo|Database name|
|MYSQL_ROOT_PASSWORD|averystrongpassword|Mysql root password|
|DKIM_PRIVATE_KEY|openssl genrsa -out /dev/stdout 2048 \| tr '\n' '\|' \| sed 's/.$//'|DKIM private key with all newline replaced with \||
|DKIM_SELECTOR|dkimselector|The name of the dkim selector in your dns|
|ALLOWED_SENDER_DOMAINS|example.org|Allower sender domain|
|CLOUDFLARE_API_KEY|iTndzksdfjkJHsldkzjenLLDg8Ca7MYx2|Cloudfare API token with DNS Edit right on the dns zone|
|CLOUDFLARE_ZONE_ID|721d168a07e873d73ec452713fcbb03f|Cloudflare DNS zone ID|
|CLOUDFLARE_DNS_RECORDS|smtp.example.org|Cloudflare DNS record (must exists)|
|POSTFIX_HOSTNAME|smtp.example.org|Probably same as CLOUDFLARE_DNS_RECORDS|
|BASIC_AUTH_USER|admin|Crontab-UI login|
|BASIC_AUTH_PWD|latestpassword|Crontab-UI password|
|SMTPD_REPLICAS|2|smtp server number of replicas|
|DOLIDOCK_REPLICAS|3|Dolibarr number of replicas must be 1 for install and can be any after |  

Remember that replicas must be se to 1 for installation.  

```sh
envsubst < k8s.yml | kubectl apply --kubeconfig okteto-kube.config -f -
```

It will deploy a cluster with a web frontend, a [Postfix server with DKIM signing](https://github.com/ismogroup/docker-smtp-relay), an Oracle MySql 8 server, a phpmyadmin web server, a [Dolirate container](https://github.com/ismogroup/dolirate) and a [crontabui server](https://github.com/highcanfly-club/crontab-ui).  
Ingress hosts are:  
| URL | Use |  
| ---------------------------------------- | ---------- |  
| <https://admin-NAMESPACE.cloud.okteto.net> | phpMyAdmin |  
| https://FQDN_DOLISTOCK | Dolibarr |  
| <https://crontabui-$NAMESPACE.cloud.okteto.net> | CrontabUI |  

Note that php sessions are stored in the dolidock-data PVC so sessions are shared across replicas for scalability. It is tested without any problem up to 4 replicas.  

## 1.6. Deploy on other Kubernetes cluster

If you have a valid kube.config you probably just need to adapt the OKETO_NS variable to your namespace.  
For example Azure Kubernetes needs "default".  
Also you may need to replace the Ingress in the k8s.yml for adapting to the available Ingress service.  

## 1.7. DKIM key and _domainkey record

for creating the DKIM private key simply generate it with openssl and store it as DKIM_PRIVATE_KEY

```sh
openssl genrsa -out /dev/stdout 2048 | tr '\n' '|' | sed 's/.$//'
```

When you have a valid DKIM_PRIVATE_KEY environment variable you can compute your _domainkey record

```sh
echo $DKIM_PRIVATE_KEY | tr '|' '\n' | openssl rsa -pubout 2> /dev/null | sed -e '1d' -e '$d' | tr -d '\n' | echo "v=DKIM1; h=sha256; k=rsa; s=email; p=$(</dev/stdin)"
```

## 1.8. SMTPd server

In the Docker docker-compose.yml and the Kubernetes k8s.yml a Postfix server is deployed.  
It contains a DKIM signer, a DDNS update script for Cloudflare DNS and a Letsencrypt autorenew certificate.  
For having it runnig you need a [Cloudflare](https://dash.cloudflare.com/login) zone (the free account is sufficient).  
If you don't own a domain you can find free subdomains on internet accepting Cloudflare as a subdomain dns.  
While you have a running Cloudflare zone,

- look at your zone id and store it as CLOUDFLARE_ZONE_ID .
- In Cloudflare overview, page hit [Get your API token](https://dash.cloudflare.com/profile/api-tokens) and issue a token with DNS Edit right on your zone. Store this token as CLOUDFLARE_API_KEY  
- In the Cloudflare DNS view, create a A record pointing to any IP address (it will be updated automatically) and store the full fqdn record in CLOUDFLARE_DNS_RECORDS  
- In the Cloudflare DNS view, create a TXT record with name "@"" and value "v=spf1 a:CLOUDFLARE_DNS_RECORDS ~all"
- In the Cloudflare DNS view, create a TXT record with name "DKIM_SELECTOR._domainkey" and the value you computed previously with your DKIM_PRIVATE_KEY variable
With that the SMTPd container will check automatically your public ip adress, publish it at Cloudflare. So all outgoing emails will come for a valid MX host (the spf record) and will be signed with a valid dkim key. This is important for spam checking.

## 1.9. Dolirate

If needed a [Dolirate](https://github.com/ismogroup/dolirate) container is deployed.
Dolirate is a simple Express server, making a GET request to <http://dolirate/updaterates> will automatically fetch the currency exchange rates needed and update Dolibarr.  

## 1.10. Crontab-UI

A custom [Crontab-ui](https://github.com/highcanfly-club/crontab-ui) is deployed.  
On Okteto the url is <https://crontabui-OKETO_NS.cloud.okteto.net>  
If needed you can add some cron task inside.  
For example for updating the needed exchange rates in Dolibarr:

```sh
/usr/bin/curl http://dolirate:3000/updaterates
```

## 1.11. PhpMyAdmin

A phpMyAdmin official image is deployed.
On Okteto the url is <https://admin-OKETO_NS.cloud.okteto.net>  
you can log in with root and MYSQL_ROOT_PASSWORD

## 1.12. Known issues

I don't know why but the Users and Groups active is not automatically active. Just enable it.  
Some warnings may appear in the frontend.  
DOLI_DB_USER may not have RELOAD privilege on database, open a shell to the mysql container and grant it the privilege if needed.

## 1.13. Update

- From a terminal find a dolidock pod name `kubectl --kubeconfig kube.config get pods`

```sh
NAME                          READY   STATUS    RESTARTS   AGE
crontabui-d5cb45588-jlbg7     1/1     Running   0          9d
dolidock-6c4d67c96c-klbr2     1/1     Running   0          9d
dolidock-6c4d67c96c-ljds7     1/1     Running   0          9d
dolidock-6c4d67c96c-njnc7     1/1     Running   0          9d
dolirate-757d6bff67-wc8mp     1/1     Running   0          9d
memcached-5855c7d6bf-smd24    1/1     Running   0          9d
mysql-794ff5f6fc-2mwzf        1/1     Running   0          9d
phpmyadmin-5fd9b8bfcb-l4dkl   1/1     Running   0          9d
smtpd-7fddb75dcb-s9j79        1/1     Running   0          9d
```

- connect to the pod terminal `kubectl --kubeconfig kube.config exec -it dolidock-6c4d67c96c-klbr2  -- /bin/bash`
- remove install.lock `rm /var/www/dolidock/documents/install.lock`
- restart the cluster and follow the ui
- at the end if needed reconnect to a pod and create a new install.lock

```sh
echo "" > /var/www/dolidock/documents/install.lock
```

A simple `/upgrade-helper.sh` can help

# 2. Helm Chart

```sh
helm repo add highcanfly https://helm-repo.highcanfly.club/
helm repo update
helm install --create-namespace --namespace=dolidock dolidock highcanfly/dolidock \
        --values _values.yaml
```

```yaml
dolidock:
  image: 
    tag: 19.0.1.0
  allowedSenderDomains: "example.org"
  apiLayerKey: pc4d67c96cc4d67c96cTGH5qwbY
  # cloudflareApiKey: ViCgLwjv4soP55Mn
  # cloudflareDnsRecords: smtp.example.org
  # cloudflareZoneId: "E+OstPbqGs26JhgdhJVF"
  doliAdminPassword: "c4d67c96c"
  doliDbPassword: "c4d67c96c"
  mysqlRootPassword: "c4d67c96c"
  postfixHostname: smtp-example
  hostname: erp.example.org
  doliUrlRoot: https://erp.example.org
  adminHostname: admin-erp.example.org
  crontabuiHostname: crontabui-derp.example.org
  s3Bucket: "master"
  s3Path: "random-s3-path"
  s3Endpoint: "https://random-s3-endpoint.com"
  s3AccessKey: "random-access-key"
  s3SecretKey: "random-secret-key"
  s3Region: "random-region"
  s3Cryptoken: "random-cryptoken"
  doliInitFromS3: "false"
  dkimSelector: dkim
  dkimPrivateKey: "----BEGIN PRIVATE KEY-----|MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA|rxSPI0KqL9mH2JyWFexZziV3RuE7DIf+IFVPLsrxSrfsZqYOFuBamfPVLVHNx+Ma|dbDPH+KzOc5sMNDkLebWg+qddpTm6Zy0mUACRbFijF1TjPRiwnpEpScGUSS+Cs8U|Coe+cQBuoTsIHpowYjVbps4=|-----END PRIVATE KEY-----"
mysql:
  image:
    repository: mysql
    tag: 8
crontabui:
  enabled: false
smtpd:
  useCloudflareDDNS: "0"
  useLetsEncrypt: "0"
  relayHost: "[smtp.gmail.com]:587"
ingress:
  ingressClassName: nginx
  tls:
    enabled: true
    certIssuer: cert-issuer
cloudflared:
  replicaCount: 1
  autoscaling:
    enabled: false
  probe:
    enabled: true
  enabled: true
  image:
      repository: highcanfly/net-tools
      tag: 1.2.4
  TunnelID: your-tunnel-id
  credentials: {"AccountTag":"your-account-tag","TunnelSecret":"your-tunnel-secret","TunnelID":"your-tunnel-id"}
  config: |
    tunnel: your-tunnel-name
    credentials-file: /etc/cloudflared/creds/credentials.json
    metrics: 0.0.0.0:2000
    no-autoupdate: true
    ingress:
      - hostname: your-hostname-1
        service: http://dolidock:80
      - hostname: your-hostname-2
        service: http://phpmyadmin
      - service: http_status:404
    cert: |
        -----BEGIN PRIVATE KEY-----
        your-private-key
        -----END PRIVATE KEY-----
        -----BEGIN CERTIFICATE-----
        your-certificate
        -----END CERTIFICATE-----
        -----BEGIN ARGO TUNNEL TOKEN-----
        your-argo-tunnel-token
        -----END ARGO TUNNEL TOKEN-----
```

Okteto can reuse the same PVC, use --set smtpd.useDolidockPVC=true

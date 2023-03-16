[![Publish image to docker Hub](https://github.com/ismogroup/dolidock/actions/workflows/publish-docker-hub.yml/badge.svg)](https://hub.docker.com/r/ismogroup/dolidock)
# Dolibarr on Docker

Docker image for Dolibarr 17.0.0 with auto installer on first boot.

## What is Dolibarr ?

Dolibarr ERP & CRM is a modern software package to manage your organization's activity (contacts, suppliers, invoices, orders, stocks, agenda, ...).

> [More information](https://github.com/dolibarr/dolibarr)

## What differs ?
### Docker image
- Use latest MySql libraries from Oracle/MySql
- Supports bzip2 compression for backup
- Can be scaled up (php session are shared) / tested up to 4 replicas
- php-memcached
- Contains all Dolicloud/DoliMods module
- linux/amd64 and linux/arm64 platform (on arm db client is MariaDB, Mysql on amd64 )

### Docker Compose stack / Kubernetes
- builtin Postfix server with dkim signing and Cloudflare DDNS (scalable)
- builin memcached server
- builtin phpMyAdmin server
- builtin cron server with web ui and cloud commander


## How to ?

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
OKTETO_NS="oktetons"
OKTETO_FQDN_DOLISTOCK="erp-oketons.cloud.okteto.net"
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


## Deploy on Okteto

For developping I'm using Okteto free tier.  
If you have an Okteto account, retrieve the kube config (settings Kubernetes credentials), and if you have all the required environment variables 
|Variable|Sample value|Description| 
|----|----|----|
|OKTETO_NS|oktetons|Kubernetes namespace|
|OKTETO_FQDN_DOLISTOCK|erp-oketons.cloud.okteto.net|FQDN of the main url|
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
| https://admin-OKTETO_NS.cloud.okteto.net | phpMyAdmin |  
| https://OKTETO_FQDN_DOLISTOCK | Dolibarr |  
| https://crontabui-$OKTETO_NS.cloud.okteto.net | CrontabUI |  
Note that php sessions are stored in the dolidock-data PVC so sessions are shared across replicas for scalability. It is tested without any problem up to 4 replicas.  

## Deploy on other Kubernetes cluster
If you have a valid kube.config you probably just need to adapt the OKETO_NS variable to your namespace.  
For example Azure Kubernetes needs "default".  
Also you may need to replace the Ingress in the k8s.yml for adapting to the available Ingress service.  

## DKIM key and _domainkey record
for creating the DKIM private key simply generate it with openssl and store it as DKIM_PRIVATE_KEY
```sh
openssl genrsa -out /dev/stdout 2048 | tr '\n' '|' | sed 's/.$//'
```
When you have a valid DKIM_PRIVATE_KEY environment variable you can compute your _domainkey record
```sh
echo $DKIM_PRIVATE_KEY | tr '|' '\n' | openssl rsa -pubout 2> /dev/null | sed -e '1d' -e '$d' | tr -d '\n' | echo "v=DKIM1; h=sha256; k=rsa; s=email; p=$(</dev/stdin)"
```

## SMTPd server
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

## Dolirate
If needed a [Dolirate](https://github.com/ismogroup/dolirate) container is deployed. 

## Crontab-UI
A custom [Crontab-ui](https://github.com/highcanfly-club/crontab-ui) is deployed.  
On Okteto the url is https://crontabui-OKETO_NS.cloud.okteto.net  
If needed you can add some cron task inside.  
For example for updating the needed exchange rates in Dolibarr:
```sh
/usr/bin/curl http://dolirate:3000/updaterates
```

## PhpMyAdmin
A phpMyAdmin official image is deployed.
On Okteto the url is https://admin-OKETO_NS.cloud.okteto.net  
you can log in with root and MYSQL_ROOT_PASSWORD

## Known issues

I don't know why but the Users and Groups active is not automatically active. Just enable it.  
Some warnings may appear in the frontend.  
DOLI_DB_USER may not have RELOAD privilege on database, open a shell to the mysql container and grant it the privilege if needed.

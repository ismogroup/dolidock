# Dolibarr on Docker

Docker image for Dolibarr 17.0.0 with auto installer on first boot.

## What is Dolibarr ?

Dolibarr ERP & CRM is a modern software package to manage your organization's activity (contacts, suppliers, invoices, orders, stocks, agenda, ...).

> [More information](https://github.com/dolibarr/dolibarr)

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
MYSQL_PASSWORD="anotherone"
DKIM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----|MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA|rxSPI0KqL9mH2JyWFexZziV3RuE7DIf+IFVPLsrxSrfsZqYOFuBamfPVLVHNx+Ma|dbDPH+KzOc5sMNDkLebWg+qddpTm6Zy0mUACRbFijF1TjPRiwnpEpScGUSS+Cs8U|Coe+cQBuoTsIHpowYjVbps4=|-----END PRIVATE KEY-----"
DKIM_SELECTOR="dkimselector"
ALLOWED_SENDER_DOMAINS="example.org"
CLOUDFLARE_API_KEY="iTndzksdfjkJHsldkzjenLLDg8Ca7MYx2"
CLOUDFLARE_ZONE_ID="721d168a07e873d73ec452713fcbb03f"
CLOUDFLARE_DNS_RECORDS="smtp.example.org"
POSTFIX_HOSTNAME="smtp.example.org"
BASIC_AUTH_USER="admin"
BASIC_AUTH_PWD="latestpassword"
```


## Deploy on Okteto

For developping I'm using Okteto free tier.  
If you have an Okteto account, retrieve the kube config (settings Kubernetes credentials), and if you have all the required environment variables issue:  
```sh
envsubst < k8s.yml | kubectl apply --kubeconfig .vscode/okteto-kube.config -f -
```
It will deploy a cluster with 3 web frontend, an Oracle MySql 8 server, a phpmyadmin web server and a crontabui server.  
Ingress hosts are:  
| URL | Use |  
| ---------------------------------------- | ---------- |  
| https://admin-OKTETO_NS.cloud.okteto.net | phpMyAdmin |  
| https://OKTETO_FQDN_DOLISTOCK | Dolibarr |  
| https://crontabui-$OKTETO_NS.cloud.okteto.net | CrontabUI |  

## Known issues

I don't know why but the Users and Groups active is not automatically active. Just enable it.  
Some warnings may appear in the frontend.  

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
DKIM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----|MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDLkJAAw7tffhHu|yXW5kfN1IBvbfrFV9ZyavzrMMeh/WhZ4T7zto5x3n1KsTtHdYYV5f7O4i92QrXev|mCQjzawOI2o2OQ0uNSe7M8/ySYmyz8LvfLcA1/vRKluJ/+0WKvr2Rz5VkjTeH3qv|mJGjrFNvwkjzYPw1nzy+Vd9u/RLO0MHToLvCr9cOByJi4w9sj/nbusi83dEwfkW0|qiypWgbCK9ylLJfhJvV/kXlBFIjHog8WMJ6VLWF1SFCUB1wU2PBi1QKBgHBb|eb8qG6cWvFsEpqhIsxNV3N1Fv9B/+eETrKwLnR0hQpsg5jQGgfLaKWjmOBciZcpI|w30aIWRzNhA065YgWc6+8QbuWcbak6J2DA2eHaAgMuWqIztkalcN5eHLu+De/W2C|qN45lCsb8ZpXNUsuUm3cqgH3CaXd0mm6UtnWroqxAoGAY4FK7yt4Y+Y6MVx3kKUO|rxSPI0KqL9mH2JyWFexZziV3RuE7DIf+IFVPLsrxSrfsZqYOFuBamfPVLVHNx+Ma|dbDPH+KzOc5sMNDkLebWg+qddpTm6Zy0mUACRbFijF1TjPRiwnpEpScGUSS+Cs8U|Coe+cQBuoTsIHpowYjVbps4=|-----END PRIVATE KEY-----"
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

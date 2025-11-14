# Documentation for developpers

Our developer workstation is debian based, so if you are on an other system please make PR to update our building workflow :)

## Set up

### Prerequisites for Debian stable (12.9)

* Install composer (apt install composer)
* Get php-scoper from https://github.com/humbug/php-scoper/releases/tag/0.18.11
* Install php-scoper.phar to /usr/local/bin
* Get phpstan from https://github.com/phpstan/phpstan/releases/1.12.15
* Move phpstan.phar to /usr/local/bin

### project depends

To import depends you have to use composer command like:

`composer i`

### project code scoper

That point may be the more difficult "first step" if you want to rebuild facturx module from sources,
please take your time to understand and learn that part.

Due to the fact of dolibarr way of life againts composer we have to "scope" our code into namespaces
(maybe one day composer will be into dolibarr like in prestashop one day ?
https://devdocs.prestashop-project.org/8/modules/concepts/composer/).

That process is the same for tons of php projects like for example https://github.com/dartmoon-io/prestashop-module

Please have a look at official php-scoper repository https://github.com/humbug/php-scoper

And some other documentation about that sort of process (but your favorite web search engine could help):
- https://tomasvotruba.com/blog/how-to-scope-your-php-tool-in-10-steps/
- https://tomasvotruba.com/blog/why-do-we-scope-php-tools
- https://phpmagazine.net/2021/02/php-scoper-get-your-code-ready-for-packaging.html
- .../...

## Build zip

Please use the `make` command because there is a Makefile :

`make zip`

## Makefile

if you have to adapt Makefile to your system please copy Makefile.dist into Makefile.local and modify the .local one

if you want to update only commands (like php) plase make a Makefile.localvars file with (for example)

```
  PHPCMD = php
  COMPOSERCMD = composer
  PHPSTANCMD = /usr/local/bin/phpstan.phar
```

## Other

Some doc is on the wiki https://inligit.fr/cap-rel/dolibarr/plugin-facturx/-/wikis/home

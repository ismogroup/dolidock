#!/usr/bin/make
# makefile for cap-rel laravel projects
#
SHELL = bash
MODULE = facturx

#then you could set up your own php version and phpstan/composer commands
ifneq ("$(wildcard Makefile.localvars)","")
  $(info using Makefile.localvars)
  include Makefile.localvars
else
  PHPCMD = php7.3
  COMPOSERCMD = /usr/local/bin/composer.phar
  PHPSTANCMD = phpstan.phar
endif

#then you could override make stuff
ifneq ("$(wildcard Makefile.local)","")
  $(info using Makefile.local)
  include Makefile.local
else
  $(info using common Makefile)
  include Makefile.dist
endif

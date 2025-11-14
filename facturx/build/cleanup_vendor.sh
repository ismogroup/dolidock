#!/bin/bash
# clean up vendor folder before zip
rm -rf ../vendor/bin
rm -rf ../vendor/barryvdh
for fic in phpcs.xml phpstan.neon psalm.xml .github build tests phpstan doc tutorial docs
do
  find ../vendor -name ${fic} -exec rm -rf "{}" \;
done
echo "Cleanup done"

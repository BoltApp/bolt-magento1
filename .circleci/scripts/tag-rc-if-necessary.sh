#!/usr/bin/env bash

set -e
set -u

PREVRC=$(git for-each-ref --sort=-creatordate --format="%(refname:short)|%(creatordate:unix)" refs/tags/* | grep "0-rc|" | head -n 1)

taggedDate=$(echo $PREVRC | cut -d"|" -f2)
threeWeekDate=$(date --date "20 days ago" +"%s")

if [[ ${taggedDate} -gt ${threeWeekDate} ]]; then
  OLDTAGNAME=$(echo $PREVRC | cut -d"|" -f1)
  NEWTAGNAME=$(echo $OLDTAGNAME | awk -F. '{print $1 "." $2+1 ".0-rc"}')
  echo "Tagging $NEWTAGNAME"
else
  echo "No rc tag this week"
  circleci-agent step halt
fi

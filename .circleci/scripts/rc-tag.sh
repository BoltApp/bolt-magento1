#!/usr/bin/env bash

set -e
set -u
set -x

PREVRC=$(git for-each-ref --sort=-creatordate --format="%(refname:short) | %(creatordate)" refs/tags/* | grep "0-rc " | head -n 1)

TAGDATE=$(echo $PREVRC | cut -d"|" -f2)
taggedDate=$(date --date "$TAGDATE" +'%s')
threeWeekDate=$(date --date "21 days ago" +'%s')

if [[ ${taggedDate} -lt ${threeWeekDate} ]];
then
  export TAGTHISWEEK=true
else
  export TAGTHISWEEK=false
fi

TAG=$(echo $PREVRC | cut -d"|" -f1)
export NEWTAGNAME=$(echo $TAG | awk -F. '{print $1 "." $2+1 ".0-rc"}')

echo $TAGTHISWEEK
echo $NEWTAGNAME
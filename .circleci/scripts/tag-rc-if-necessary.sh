#!/usr/bin/env bash

set -e
set -u
set -x

PREVRC=$(git for-each-ref --sort=-creatordate --format="%(refname:short)|%(creatordate:short)" refs/tags/* | grep "0-rc|" | head -n 1)

TAGDATE=$(echo $PREVRC | cut -d"|" -f2)
taggedDate=$(date --date "$TAGDATE" +"%s")
threeWeekDate=$(date --date "21 days ago" +"%s")

if [[ ${taggedDate} -lt ${threeWeekDate} ]]
then
  OLDTAGNAME=$(echo $PREVRC | cut -d"|" -f1)
  NEWTAGNAME=$(echo $OLDTAGNAME | awk -F. '{print $1 "." $2+1 ".0-rc"}')

  SLACK_MENTIONS="<@aden>"
  curl -X POST -H "Content-type: application/json" --data '{
    "attachments": [{
        "text": "$NEWTAGNAME $SLACK_MENTIONS",
        "color": "#58a359"
    }]
  }' https://hooks.slack.com/services/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
fi
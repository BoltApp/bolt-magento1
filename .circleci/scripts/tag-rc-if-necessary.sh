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

  mkdir -p /tmp/swissknife/
  wget -P /tmp/swissknife/ -qi https://github.com/aktau/github-release/releases/download/v0.7.2/linux-amd64-github-release.tar.bz2
  tar xjf /tmp/swissknife/*.tar.bz2 -C /tmp/swissknife
  /tmp/swissknife/bin/linux/amd64/github-release release --security-token "$GITHUB_TOKEN" --user "$CIRCLE_PROJECT_USERNAME" --repo "$CIRCLE_PROJECT_REPONAME" --tag "$NEWTAGNAME"

  SLACK_MENTIONS="<@aden>"
  curl -X POST -H "Content-type: application/json" --data "{
    \"attachments\": [{
      \"text\": \"$NEWTAGNAME $SLACK_MENTIONS\",
      \"color\": \"#58a359\"
    }]
  }" $SLACK_MAGENTO1_WEBHOOK
fi
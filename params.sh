#!/usr/bin/env bash

# include base default params
. ./params-default.sh

# include base user params
. ./params-base.sh

# include base project params
APP_JIRA_PROJECT=${jira_project}

if [ -f "./params-base-${jira_project}.sh" ]; then
  . "./params-base-${jira_project}.sh"
else
  check_error 3 'There is related configuration file for this project. PLease describe it in file: '"\n${__dir}/params-base-${jira_project}.sh"
fi

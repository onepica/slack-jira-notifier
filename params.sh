#!/usr/bin/env bash

# include base default params
. ./params-default.sh

# include base user params
. ./params-base.sh

# include base project params
APP_JIRA_PROJECT=${jira_project}
. "./params-base-${jira_project}.sh"

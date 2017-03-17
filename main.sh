#!/usr/bin/env bash

#################################################################################
# Slack Task notifier
# $ bash ./slack-new-task.sh TYPE TASK TASK_SUMMARY [EXTRA_MESSAGE]
#################################################################################
# Please edit params in files
#   - params-base.sh
#   - params-base-{JIRA_KEY}.sh
#################################################################################

set -o pipefail
set -o errexit
set -o nounset
#set -o xtrace

# Set magic variables for current file & dir
__dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
__file="${__dir}/$(basename "${BASH_SOURCE[0]}")"
readonly __dir __file
cd ${__dir}

#################################################################################

#################################################################################

if test -z "${1:-}"; then
  echo 'error: Set JIRA key as first argument.'
  exit 3
fi

task_no=${1}; shift

if test $(echo "${task_no}" | grep '-'); then
  jira_project=$(echo ${task_no} | tr '-' ' ' | awk '{print $1}')
  task_no=$(echo ${task_no} | tr '-' ' ' | awk '{print $2}')
else
  echo 'error: Set full JIRA key.'
  exit 3
fi

. ./params.sh

#################################################################################
# Code
#################################################################################

check_error () {
  local status=${1}
  shift
  if [ '0' != "${status}" ]; then
    echo "$@" > /dev/stderr
    exit ${status}
  fi
}

check_php() {
  local bin php_zip_url
  bin=${1}

  if test ! -f "${1}" && ! type "${1}" 2>&1 > /dev/null; then
    echo 'Downloading PHP binaries...'
    php_zip_url='http://windows.php.net/downloads/'$(curl -s http://windows.php.net/download/ | grep -o 'releases/php-.*-x86.zip' | head -1 2>&1)

    mkdir $(dirname ${bin}) -p
    curl -s ${php_zip_url} --output $(dirname ${bin})/php_downloaded.zip
    unzip $(dirname ${bin})/php_downloaded.zip -d $(dirname ${bin}) > /dev/null
    echo 'Good. PHP binaries have been downloaded.'

    # Set up php.ini file
    rm -rf $(dirname ${bin})/php.ini
    cp $(dirname ${bin})/php.ini-production $(dirname ${bin})/php.ini

    cat ${__dir}/php-default.ini >> $(dirname ${bin})/php.ini
  fi

  if ! ${bin} -i | grep curl > /dev/null; then
    check_error 2 'error: PHP does not have enabled cURL module.'
  fi
}

# task_field FIELD_KEY
task_field() {
  ${php_bin} -f ./read-task.php "${JIRA_USERNAME}:${JIRA_PASSWORD}" "${JIRA_URL}" "${task_key}" ${1}
}

if test -z "${php_bin:-}"; then
  php_bin=${__dir}'/php/php.exe'
  if type php 2>&1 > /dev/null; then
    # define global PHP
    php_bin='php'
  fi
fi

escape_quotes_for_post () {
  echo $@ | sed -r 's|"|\\\x22|g'
}
check_php ${php_bin}

task_sprint=''
task_assignee=''
task_reporter=${APP_MESSAGE_USER}

task_key="${APP_JIRA_PROJECT}-${task_no}"
slack_footer_icon=${APP_SLACK_FOOTER_ICON_URL}

if [ -n "${JIRA_USERNAME}" ] && [ -n "${JIRA_PASSWORD}" ]; then
  printf 'Requesting JIRA issue...'
##
# Debug JIRA issue schema
#  ${php_bin} -f ./read-task.php "${JIRA_USERNAME}:${JIRA_PASSWORD}" "${JIRA_URL}" "${task_key}"; exit 55

  task_sprint=$(task_field 'sprint')
  task_assignee=$(task_field 'assignee')
  task_reporter=$(task_field 'reporter')

  if [ -z "${task_sprint}" ]; then
      task_sprint='Backlog'
  fi

  task_type=$(task_field 'issuetype')
  task_summary=$(task_field 'summary')
  echo 'OK'
else
  echo 'notice: JIRA issue cannot be requested.'
  task_type=${1}; shift
  task_summary=${1}; shift
fi

slack_text=${1:-}

if [ -n "${APP_MESSAGE_CC:-}" ]; then
    slack_text="\n"${APP_MESSAGE_CC}
fi
task_url=${JIRA_URL}'/browse/'${APP_JIRA_PROJECT}'-'${task_no}

if [ "${task_type}" == 'Bug' ]; then
  attach_color='#E11'
elif [ "${task_type}" == 'Story' ]; then
  attach_color='#EE1'
elif [ "${task_type}" == 'Task' ]; then
  attach_color='#11E'
elif [ "${task_type}" == 'Question' ]; then
  attach_color='#69F5FF'
elif [ "${task_type}" == 'Issue' ]; then
  attach_color='#911'
elif [ "${task_type}" == 'SubTask' ] || [ "${task_type}" == 'Sub-Task Task' ]; then
  attach_color='#CCC'
else
  echo 'error: Unknown issue type: '${task_type}
  echo 'note: Please use one of following: Bug, Story, Task, SubTask, Issue, Question'
  exit 3
fi

slack_footer_text=$(
  escape_quotes_for_post \
    "$(printf "${APP_FOOTER_TEXT:-'JIRA %s. Posted by %s'}" \
        "${task_type}" \
        "${APP_MESSAGE_USER}"
      )"
)

# disable parsing asterisks, or set -f
set -o noglob
slack_format='payload={
  "username": "JIRA",
  "channel": "'${APP_SLACK_CHANNEL}'",
  "text": "'$(escape_quotes_for_post "${slack_text}")'",
  "as_user": "true",
  "link_names": "true",
  "icon_emoji": "'${APP_SLACK_ICON_EMOJI}'",
  "attachments": [
    {
      "fallback": "",
      "color": "'${attach_color}'",
      "pretext": "",
      "author_name": "'${task_reporter}' -> '${task_assignee}'",
      "title": "'${task_key}'",
      "title_link": "'${task_url}'",
      "text": "'$(escape_quotes_for_post "${task_summary}")'",
      "fields": [
        {
          "title": "Type",
          "value": "'$(escape_quotes_for_post "${task_type}")'",
          "short": true
        },
        {
          "title": "Sprint",
          "value": "'$(escape_quotes_for_post "${task_sprint}")'",
          "short": true
        }
      ],
      "image_url": "",
      "thumb_url": "",
      "footer": "'${slack_footer_text}'",
      "footer_icon": "'${slack_footer_icon}'",
      "ts": '$(date +%s)'
    }
  ]
}'
set +o noglob

printf 'Posting the message to slack...'
result=$(curl -s --data-urlencode \
  "${slack_format}" \
  ${APP_SLACK_WEBHOOK})
if [ 'ok' != "${result}" ]; then
  echo 'FAILED'
  check_error 2 'error: Cannot post message.'"\n"'Response: '
else
  echo 'OK'
fi

# Slack JIRA Issue notification

## Installation

### Download GitBash console
https://github.com/git-for-windows/git/releases/download/v2.12.0.windows.1/PortableGit-2.12.0-64-bit.7z.exe

Let's say it will be in path `d:/Git/`

### Run GitBash console

Press `CTRL + R` and run `d:/Git/bin/git.exe`.
 
### Fetch the project

```
$ git clone https://github.com/onepica/slack-jira-notifier /d/slack-jira-notifier
```
And enter into project directory
```
$ cd /d/slack-jira-notifier
```

### Set config files
*JIRA Credentials*

Create file `params-base.sh` in any editor with content:
```shell
#!/usr/bin/env bash

JIRA_USERNAME='u.sername'
JIRA_PASSWORD='secret123'
APP_MESSAGE_USER='John Doe'
APP_SLACK_WEBHOOK='https://hooks.slack.com/services/ZZZZZZ/XXXXXX/YYYYYYYYYYYYYYYY'
```

*Project set up*
Create file `params-base-EXAMPLE.sh` (where `EXAMPLE` is your real project target JIRA key) 
in any editor with content:
```shell
#!/usr/bin/env bash

# Slack channel for posting messages
APP_SLACK_CHANNEL='#EXAMPLE-gen'

# JIRA project key
APP_JIRA_PROJECT='EXAMPLE'

# Add users for default mentioning. Either @username or @here or @channel
# See: https://get.slack.help/hc/en-us/articles/202009646-Make-an-announcement
#APP_MESSAGE_CC='//cc @here'
```

## Post JIRA issue
### Run in GitBash console
So, just run:
```shell
$ bin/slack-jira-task EXAMPLE-123
```
You may post a message with additional comment (`@channel` will mention all people in a channel):
```shell
$ bin/slack-jira-task EXAMPLE-123 '"Hey team, we got to *REOPEN* this issue. :wink:\n//cc @hannah_rest"'
```
![alt tag](https://raw.githubusercontent.com/onepica/slack-jira-notifier/master/doc/example-post.jpg)

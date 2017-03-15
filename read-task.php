<?php
$username = 'a.roslik';
$password = 'qweqwe1';

class JiraTask
{
    public function __construct($username, $password, $jiraUrl, $task)
    {
        $this->username = $username;
        $this->password = $password;
        $this->jiraUrl  = $jiraUrl;
        $this->task     = $task;
    }

    public function fetchIssueField($field)
    {
        switch ($field) {
            case 'issuetype':
            case 'type':
                return $this->fetchIssue()['fields']['issuetype']['name'];
                break;

            case 'type_icon_url':
                return $this->fetchIssue()['fields']['issuetype']['iconUrl'];
                break;

            case 'sprint':
                if (empty($this->fetchIssue()['fields']['customfield_10600'])) {
                    return '';
                }
                preg_match(
                    '/name=([^,]+)/',
                    $this->fetchIssue()['fields']['customfield_10600'][0],
                    $matches
                );

                return $matches[1] ?: '';
                break;

            case 'assignee':
                if (empty($this->fetchIssue()['fields']['assignee']['displayName'])) {
                    return '';
                }
                return $this->fetchIssue()['fields']['assignee']['displayName'];

            case 'reporter':
                if (empty($this->fetchIssue()['fields']['reporter']['displayName'])) {
                    return '';
                }
                return $this->fetchIssue()['fields']['reporter']['displayName'];

            default:
                if (!isset($this->fetchIssue()['fields'][$field])) {
                    throw new Exception('No such field.');
                }
                return $this->fetchIssue()['fields'][$field];
        }
    }

    /**
     * Fetch issue
     */
    public function fetchIssue()
    {
        if ($this->hasCache()) {
            return json_decode($this->readCache(), JSON_OBJECT_AS_ARRAY);
        }

        $ch      = curl_init();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $this->getApiUrl());
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result  = curl_exec($ch);
        $chError = curl_error($ch);

        curl_close($ch);
        if ($chError) {
            throw new Exception("cURL Error: $chError");
        }

        $this->writeCache((string)$result);

        return json_decode($result, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * @return bool
     */
    protected function hasCache()
    {
        return is_file($this->getCacheFilePath());
    }

    /**
     * @return string
     */
    protected function getCacheFile()
    {
        return md5($this->getApiUrl()) . '.json';
    }

    /**
     * @return string
     */
    protected function getApiUrl()
    {
        return $this->jiraUrl . '/rest/api/2/issue/' . $this->task . '?fields=summary,issuetype,assignee';
    }

    /**
     * @return string
     */
    protected function readCache()
    {
        return file_get_contents($this->getCacheFilePath());
    }

    /**
     * @return string
     */
    protected function getCacheFilePath()
    {
        return __DIR__ . '/cache/' . $this->getCacheFile();
    }

    /**
     * @param string $data
     * @return bool
     * @throws Exception
     */
    protected function writeCache($data)
    {
        if (!is_dir(__DIR__ . '/cache/') && !mkdir(__DIR__ . '/cache/', '755', true)) {
            throw new Exception('Cannot create directory "./cache"');
        }

        return file_put_contents($this->getCacheFilePath(), $data);
    }
}

list($username, $password) = explode(':', $_SERVER['argv'][1]);

try {
    $api = new JiraTask(
        $username, $password,
        $_SERVER['argv'][2],
        $_SERVER['argv'][3]
    );
    if (isset($_SERVER['argv'][4])) {
        echo $api->fetchIssueField($_SERVER['argv'][4]);
    } else {
        var_export($api->fetchIssue());
    }
} catch (\Exception $e) {
    echo 'error:' . $e->getMessage() . PHP_EOL;
    exit(9);
}





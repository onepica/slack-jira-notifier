<?php

/**
 * API class for fetching info about a JIRA issues
 */
class JiraTask
{
    /**
     * Custom field codes
     *
     * @var array
     */
    protected $fieldCodes = [
        'sprint' => 'customfield_10600',
    ];

    public function __construct($username, $password, $jiraUrl, $task, $fieldCodes = [])
    {
        $this->username = $username;
        $this->password = $password;
        $this->jiraUrl  = $jiraUrl;
        $this->task     = $task;

        $this->fieldCodes = $fieldCodes + $this->fieldCodes;
    }

    /**
     * Fetch issue field
     *
     * @param $field
     * @return string
     */
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
                if ($this->readField('sprint')) {
                    return '';
                }
                preg_match(
                    '/name=([^,]+)/',
                    $this->fetchIssue()['fields']['customfield_10600'][0],
                    $matches
                );

                return $matches && $matches[1] ? $matches[1] : '';
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
                return $this->readField($field);
        }
    }

    /**
     * Fetch issue
     *
     * @return array
     * @throws Exception
     */
    public function fetchIssue()
    {
        if ($this->hasCache()) {
            return json_decode($this->readCache(), JSON_OBJECT_AS_ARRAY);
        }

        $result = $this->requestIssue();

        $result && $this->writeCache((string)$result);

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
    protected function getCacheFilePath()
    {
        return __DIR__ . '/cache/' . $this->getCacheFile();
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
        return $this->jiraUrl . '/rest/api/2/issue/' . $this->task 
            . '?fields=summary,issuetype,assignee,reporter,'
            . $this->fieldCodes['sprint'];
    }

    /**
     * Read cached data
     *
     * @return string
     */
    protected function readCache()
    {
        return file_get_contents($this->getCacheFilePath());
    }

    /**
     * Request issue data via JIRA API
     *
     * @return string
     * @throws Exception
     */
    protected function requestIssue()
    {
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

        return $result;
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

    /**
     * @param string $code
     * @return mixed
     */
    protected function readField($code)
    {
        // try to load via associated alias in $this->fieldCodes
        if (isset($this->fieldCodes[$code])
            && isset($this->fetchIssue()['fields'][$this->fieldCodes[$code]])
        ) {
            $this->fetchIssue()['fields'][$this->fieldCodes[$code]];
        }

        return isset($this->fetchIssue()['fields'][$code])
            ? $this->fetchIssue()['fields'][$code] : null;
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





<?php
class PingLogger
{

    private $dataKeys = array(
        'ping_time',
        'source',
        'host',
        'packets_transmitted',
        'packets_received',
        'packet_loss_percent',
        'overall_time_ms',
        'min_ms',
        'avg_ms',
        'max_ms',
        'mdev_ms',
    );

    private $config;

    private $conn;

    public function __construct($config)
    {
        $this->config = $config;
    }

    private function initConn()
    {
        if ($this->config->db_enabled) {
            $this->conn = new PDO(
                sprintf('mysql:host=%s;dbname=%s', $this->config->db_host, $this->config->db_name),
                $this->config->db_user,
                $this->config->db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
        }
    }

    public function logPing($host)
    {
        $data = $this->runPing($host);
        $this->persistLog($data);
    }

    private function runPing($host)
    {
        self::log('Ping started');

        $pingTime = time();

        $command = sprintf('ping -c %d %s', $this->config->ping_count, $host);
        
        exec($command, $output, $return);

        $dataStatsString = array_pop($output);
        $dataSummaryString = array_pop($output);

        $data = (object)array();

        $data->ping_time = $pingTime;
        $data->source = $this->config->source;
        $data->host = $host;

        sscanf($dataSummaryString, '%d packets transmitted, %d received, %d%% packet loss, time %dms', $data->packets_transmitted, $data->packets_received, $data->packet_loss_percent, $data->overall_time_ms);
        sscanf($dataStatsString, 'rtt min/avg/max/mdev = %f/%f/%f/%f ms', $data->min_ms, $data->avg_ms, $data->max_ms, $data->mdev_ms);

        foreach ($this->dataKeys as $key) {
            if (!isset($data->{$key}) || is_null($data->{$key})) {
                throw new \Exception(sprintf('Key "%s" requried in data, bus not set!', $key));
            }
        }

        self::log('Ping complete: %s', [http_build_query($data)]);

        return $data;
        
    }

    private function persistLog($data)
    {
        if ($this->config->db_enabled) {
            $this->persistLogLocally($data);
        }

        if ($this->config->remote_enabled) {
            $this->persistLogRemotely($data);
        }
    }

    private function persistLogLocally($data)
    {
        $this->initConn();

        $sql = "
INSERT INTO logs
(ping_time,source,host,packets_transmitted,packets_received,packet_loss_percent,overall_time_ms,min_ms,avg_ms,max_ms,mdev_ms)
VALUES
(%s,'%s','%s',%d,%d,%d,%d,%f,%f,%f,%f)
        ";
        $this->conn->exec(vsprintf($sql, [
            sprintf('FROM_UNIXTIME(%d)', $data->ping_time),
            $data->source,
            $data->host,
            $data->packets_transmitted,
            $data->packets_received,
            $data->packet_loss_percent,
            $data->overall_time_ms,
            $data->min_ms,
            $data->avg_ms,
            $data->max_ms,
            $data->mdev_ms,
        ]));

        self::log('Ping persisted to database');

        return $data;
    }

    private function persistLogRemotely($data)
    {
        $url = sprintf('%s/proxy.php', $this->config->remote_host);
        $postData = http_build_query($data);

        $streamOptions = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded' . "\r\n" . 'Authorization: Basic ' . base64_encode(sprintf('%s:%s', $this->config->auth_user, $this->config->auth_pass)) . "\r\n",
                'content' => $postData,
            ),
        );

        $context = stream_context_create($streamOptions);

        $result = file_get_contents($url, false, $context);

        if (!$result) {
            throw new \Exception(sprintf('Error POSTing data to "%s"', $url));
        }

        self::log('Ping persisted remotely');

        return $data;
    }

    public function receiveRemoteData($data)
    {
        $data = (object)$data;

        if ($this->config->db_enabled) {
            $this->persistLogLocally($data);
            print('OK');
        }
    }

    public function renderStats($params)
    {
        if (!$this->config->db_enabled) {
            return;
        }

        $sourcesHosts = $this->fetchSourcesHosts();

        $data = null;
        if (@$params->source && @$params->host) {
            $fields = $this->dataKeys;
            $data = $this->fetchData($params->source, $params->host);
        }

        require('index.phtml');
    }

    private function fetchSourcesHosts()
    {
        $this->initConn();

        $sql = "
SELECT DISTINCT source, host
FROM logs
ORDER BY source, host
        ";
        $result = $this->conn->query($sql, PDO::FETCH_OBJ);

        $data = array();
        foreach ($result as $row) {
            if (!isset($data[$row->source])) {
                $data[$row->source] = array();
            }
            $data[$row->source][] = $row->host;
        }

        return $data;
    }

    private function fetchData($source, $host)
    {
        $this->initConn();

        $limit = 100;

        $sql = "
SELECT *
FROM logs
WHERE source = '%s' AND host = '%s'
ORDER BY ping_time DESC
LIMIT %d
        ";
        $result = $this->conn->query(sprintf($sql, $source, $host, $limit), PDO::FETCH_OBJ);

        $data = array();
        foreach ($result as $row) {
            $data[] = $row;
        }

        return $data;
    }

    static function log($message, $replacements = [])
    {
        array_unshift($replacements, date('c'));
        $message = '%s ' . $message . PHP_EOL;
        vprintf($message, $replacements);
    }

}

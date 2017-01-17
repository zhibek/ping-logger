<?php
class PingLogger
{

    private $dataFields = array(
        'ping_time' => array(
            'display' => true,
            'title'   => 'Time',
            'format'  => 'formatFieldPingTime',
            'default_sort' => true,
        ),
        'source' => array(
            'display' => false,
        ),
        'host' => array(
            'display' => false,
        ),
        'packets_transmitted' => array(
            'display' => false,
        ),
        'packets_received' => array(
            'display' => false,
        ),
        'packet_loss_percent' => array(
            'display' => true,
            'title'   => 'Packet Loss',
            'format'  => 'formatFieldPacketLossPercent'
        ),
        'overall_time_ms' => array(
            'display' => false,
        ),
        'min_ms' => array(
            'display' => true,
            'title'   => 'Min (ms)',
        ),
        'avg_ms' => array(
            'display' => true,
            'title'   => 'Avg (ms)',
        ),
        'max_ms' => array(
            'display' => true,
            'title'   => 'Max (ms)',
        ),
        'mdev_ms' => array(
            'display' => false,
        ),
    );

    private $ranges = array(
        5 => 'Last 6 hours', // 6 hours / every 5mins = 72 data points
        20 => 'Last 24 hours', // 1 day / every 20mins = 72 data points
        60 => 'Last 3 days', // 3 days / every 60mins = 72 data points
        180 => 'Last 9 days', // 9 days / every 3 hours = 72 data points
        720 => 'Last 36 days', // 36 days / every 12 hours = 72 data points
    );

    private $defaultRange = 5;

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
            $this->conn->exec(sprintf("SET time_zone='%s'", date('P')));
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

        foreach (array_keys($this->dataFields) as $key) {
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

            $ranges = $this->ranges;
            $range = isset($params->range) ? $params->range : $this->defaultRange;
            $fields = $this->formatFields();
            $raw = $this->fetchData($params->source, $params->host, $range);
            list($startTime, $endTime) = $this->formatDataStartEndTimes($raw);

            // default to "chart" mode, unless "table" is selected
            if ('table' !== strtolower(@$params->mode)) {

                $mode = 'chart';
                $data = $this->formatDataChart($raw);

            } else {

                $mode = 'table';
                $data = $this->formatDataTable($raw);

            }

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

    private function fetchData($source, $host, $range)
    {
        $this->initConn();

        $limit = 72; // 6 hours, based on 5 minute resolution

        $sql = "
SELECT (UNIX_TIMESTAMP(ping_time) DIV (%d * 60)) AS ping_time_range, ping_time, AVG(packet_loss_percent) AS packet_loss_percent, MIN(min_ms) AS min_ms, AVG(avg_ms) AS avg_ms, MAX(max_ms) AS max_ms
FROM logs
WHERE source = '%s' AND host = '%s'
GROUP BY ping_time_range
ORDER BY ping_time_range DESC
LIMIT %d
        ";
        $result = $this->conn->query(sprintf($sql, $range, $source, $host, $limit), PDO::FETCH_OBJ);

        $data = array();
        foreach ($result as $row) {
            $data[] = $row;
        }

        return $data;
    }

    private function formatFields()
    {
        $fields = array();

        foreach ($this->dataFields as $field => $info)
        {
            if ($info['display']) {
                $fields[$field] = $info;
            }
        }

        return $fields;
    }

    private function formatDataStartEndTimes($raw)
    {
        if (!count($raw)) {
            return false;
        }

        $startTime = $this->formatFieldPingTime($raw[count($raw)-1]->ping_time);
        $endTime = $this->formatFieldPingTime($raw[0]->ping_time);

        return array($startTime, $endTime);
    }

    private function formatDataTable($raw)
    {
        $data = array();

        foreach ($raw as $row) {
            $rowFormatted = (object) array();
            foreach ($this->dataFields as $field => $info)
            {
                if ($info['display']) {
                    if (isset($info['format'])) {
                        $rowFormatted->{$field} = call_user_func(array($this, $info['format']), $row->{$field});
                    } else {
                        $rowFormatted->{$field} = $row->{$field};
                    }
                }
            }
            $data[] = $rowFormatted;
        }

        return $data;
    }

    private function formatDataChart($raw)
    {
        $fields = array(
            'min_ms' => array(
                'title' => 'Min (ms)',
                'colour' => 'green',
                'y_axis' => 'left',
                'values' => array(),
            ),
            'avg_ms' => array(
                'title' => 'Avg (ms)',
                'colour' => 'blue',
                'y_axis' => 'left',
                'values' => array(),
            ),
            'max_ms' => array(
                'title' => 'Max (ms)',
                'colour' => 'red',
                'y_axis' => 'left',
                'values' => array(),
            ),
            'packet_loss_percent' => array(
                'title' => 'Packet Loss (%)',
                'colour' => 'black',
                'y_axis' => 'right',
                'fill' => false,
                'dash' => array(5),
                'values' => array(),
            ),
        );

        $labels = array();

        $raw = array_reverse($raw);

        foreach ($raw as $row) {

            $labels[] = $this->formatFieldPingTime($row->ping_time);

            foreach ($fields as $field => $info) {
                $fields[$field]['values'][] = $row->{$field};
            }
        }

        $datasets = array();

        foreach ($fields as $field => $info) {
            $datasets[] = (object)array(
                'label' => $info['title'],
                'data' => $info['values'],
                'borderColor' => $info['colour'],
                'yAxisID' => $info['y_axis'],
                'fill' => isset($info['fill']) ? $info['fill'] : true,
                'borderDash' => isset($info['dash']) ? $info['dash'] : array(0),
            );
        }

        $data = (object)array(
            'labels' => $labels,
            'datasets' => $datasets,
        );

        return json_encode($data);
    }

    private function formatFieldPingTime($input)
    {
        return date('Y-m-d H:i', strtotime($input));
    }

    private function formatFieldPacketLossPercent($input)
    {
        return sprintf('%d%%', $input);
    }

    static function log($message, $replacements = [])
    {
        array_unshift($replacements, date('c'));
        $message = '%s ' . $message . PHP_EOL;
        vprintf($message, $replacements);
    }

}

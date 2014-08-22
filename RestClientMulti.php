<?php

/**
 * This library runs multiple rest calls in parallel.
 *
 * @author Shaun Burdick <github@shaunburdick.com>
 * @see    http://github.com/shaunburdick/rest-client
 */
class RestClientMulti
{
    /** @var object cURL multi handle resource */
    protected $cm;

    /** @var array Queue of rest calls to make */
    protected $queue = array();

    /** @var integer Max number of concurrent calls.  0 means all */
    protected $limit = 0;

    /** @var intger Poll Frequency in microseconds */
    protected $pollFrequency = 25000;

    /**
     * Constructor.
     *
     * @throws Exception when it cannot init curl_multi
     */
    public function __construct()
    {
        $this->cm = curl_multi_init();

        if (!$this->cm) {
            throw new Exception('Unable to init curl multi');
        }

        return $this;
    }

    /**
     * Destuctor.
     */
    public function __destruct()
    {
        if ($this->cm) {
            curl_multi_close($this->cm);
        }
    }

    /**
     * Adds a rest client to the queue.
     *
     * @param RestClient|RestClient[] $clients The client(s) to add.
     * @return boolean True if all added or false if error
     */
    public function addClient($clients)
    {
        $retVal = true;

        if (!is_array($clients)) {
            $clients = array($clients);
        }

        foreach ($clients as $client) {
            if ($client instanceof RestClient) {
                $this->queue[] = $client;
            } else {
                return false;
            }
        }

        return $retVal;
    }

    /**
     * Set the max number of concurrent calls.
     *
     * @param integer $max The max number of calls
     * @return RestClientMulti
     */
    public function limit($max)
    {
        $this->limit = (int) $max;

        return $this;
    }

    /**
     * Set the poll frequency.  This is used to lazily save cpu.
     *
     * @param integer $freq The frequency in microseconds
     * @return RestClientMulti
     */
    public function pollFrequency($freq)
    {
        $this->pollFrequency = (int) $freq;

        return $this;
    }

    /**
     * Executes the multi call.
     *
     * @param function $merge A function to merge results.  It must accept an array of results.
     * @return mixed Either an array of results or the result of your merge function
     */
    public function execute($merge = null)
    {
        $results = array();

        if (!empty($this->queue)) {
            $totalCalls = count($this->queue);
            $limit = ($this->limit > 0 && $totalCalls > $this->limit)
                ? $this->limit : ($totalCalls - 1);

            // prime the queue
            for ($i = 0; $i < $limit; $i++) {
                print $this->queue[$i]->getUrl() . "\n";
                curl_multi_add_handle($this->cm, $this->queue[$i]->getHandle());
            }

            $running = true;
            while ($running || $i < ($totalCalls - 1)) {
                // Wait for next completion
                while(($exec = curl_multi_exec($this->cm, $running)) == CURLM_CALL_MULTI_PERFORM) {
                    usleep($this->pollFrequency);
                }

                // If we have stopped, leave loop
                if ($exec !== CURLM_OK) {
                    var_dump($exec);
                    break;
                }

                // Read any completed calls
                while ($done = curl_multi_info_read($this->cm)) {
                    $info = curl_getinfo($done['handle']);
                    if ($info['http_code'] < 400) {
                        $results[] = ($info['http_code'] == 204)
                            ? '' : curl_multi_getcontent($done['handle']);

                        if ($i < $totalCalls -1){
                            curl_multi_add_handle($this->cm, $this->queue[$i++]->getHandle());
                            print $this->queue[$i]->getUrl() . "\n";
                        }

                        curl_multi_remove_handle($this->cm, $done['handle']);
                    }
                }
            };
        }

        return is_callable($merge) ? $merge($results) : $results;
    }
}
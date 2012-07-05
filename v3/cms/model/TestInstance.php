<?php

/*
  Concerto Platform - Online Adaptive Testing Platform
  Copyright (C) 2011-2012, The Psychometrics Centre, Cambridge University

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; version 2
  of the License, and not any of the later versions.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class TestInstance {

    private $r = null;
    private $pipes;
    public $code_execution_halted = false;
    private $last_action_time;
    public $session_id = 0;
    public $is_working = false;
    public $is_data_ready = false;
    public $response = "";
    public $code = "";
    public $close = false;
    public $is_serializing = false;
    public $is_serialized = false;

    public function __construct($session_id = 0) {
        $this->session_id = $session_id;
    }

    public function is_timedout() {
        if (time() - $this->last_action_time > Ini::$r_instances_timeout) {
            if (TestServer::$debug)
                TestServer::log_debug("TestInstance->is_timedout() --- Test instance timedout");
            return true;
        }
        else
            return false;
    }

    public function is_started() {
        if ($this->r == null)
            return false;
        if (is_resource($this->r)) {
            $status = proc_get_status($this->r);
            return $status["running"];
        }
        else
            return false;
    }

    public function start() {
        $env = array();
        if (Ini::$unix_locale != "") {
            $encoding = Ini::$unix_locale;
            $env = array(
                'LANG' => $encoding
            );
        }

        if (TestServer::$debug)
            TestServer::log_debug("TestInstance->start() --- Test instance starting");
        $this->last_action_time = time();
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        include Ini::$path_internal . 'SETTINGS.php';
        $this->r = proc_open(Ini::$path_r_exe . " --vanilla --args " . $db_host . " " . ($db_port != "" ? $db_port : "3306") . " " . $db_user . " " . $db_password . " " . $db_name . " " . $this->session_id . " " . (Ini::$path_mysql_home != "" ? "'" . Ini::$path_mysql_home . "'" : ""), $descriptorspec, $this->pipes, Ini::$path_temp, $env);
        if (is_resource($this->r)) {
            if (TestServer::$debug)
                TestServer::log_debug("TestInstance->start() --- Test instance started");

            if (!stream_set_blocking($this->pipes[1], 0)) {
                if (TestServer::$debug) {
                    TestServer::log_debug("TestInstance->read() --- Error: (stream_set_blocking) #1");
                    break;
                }
            }
            if (!stream_set_blocking($this->pipes[2], 0)) {
                if (TestServer::$debug) {
                    TestServer::log_debug("TestInstance->read() --- Error: (stream_set_blocking) #2");
                    break;
                }
            }

            return true;
        } else {
            if (TestServer::$debug)
                TestServer::log_debug("TestInstance->start() --- Test instance NOT started");
            return false;
        }
    }

    public function stop() {
        if ($this->is_started()) {
            fclose($this->pipes[0]);
            fclose($this->pipes[1]);
            fclose($this->pipes[2]);
            $ret = proc_close($this->r);
            if (TestServer::$debug)
                TestServer::log_debug("TestInstance->stop() --- Test instance closed with: " . $ret);
        }
        return null;
    }

    public function serialize() {
        if (TestServer::$debug)
            TestServer::log_debug("TestInstance->serialize() --- Serializing #" . $this->session_id);
        $session = TestSession::from_mysql_id($this->session_id);

        $this->is_serializing = true;
        $this->send(sprintf("
            save.session('%s')
            ", $session->get_RSession_file_path()));
    }

    public function send($code) {
        if (TestServer::$debug)
            TestServer::log_debug("TestInstance->send() --- Sending " . strlen($code) . " data to test instance");
        $this->last_action_time = time();

        $lines = explode("\n", $code);
        $code = "";
        foreach ($lines as $line) {
            if (trim($line) == "")
                continue;
            $code .= trim($line) . "
                ";
        }
        $this->code = $code;
        $bytes = "";
        if ($this->is_serializing) {
            $bytes = fwrite($this->pipes[0], $code . "
        print('SESSION SERIALIZATION FINISHED')
        ");
        } else {
            $bytes = fwrite($this->pipes[0], $code . "
        print('CODE EXECUTION FINISHED')
        ");
        }
        if (TestServer::$debug)
            TestServer::log_debug("TestInstance->send() --- " . $bytes . " written to test instance");

        if ($this->is_serializing)
            $this->is_serialized = false;
        $this->is_working = true;
        $this->is_data_ready = false;
        $this->response = "";
    }

    public function read() {
        $this->code_execution_halted = false;
        $this->last_action_time = time();

        $result = "";
        $error = "";
        while ($append = fread($this->pipes[1], 4096)) {
            $result.=$append;
        }
        if (strpos($result, '"CODE EXECUTION FINISHED"') !== false) {
            $this->is_data_ready = true;
        }
        if (strpos($result, '"SESSION SERIALIZATION FINISHED"') !== false) {
            $this->is_serialized = true;
        }

        while ($append = fread($this->pipes[2], 4096)) {
            $error.=$append;
        }
        if (strpos($error, 'Execution halted') !== false) {
            $result .= $error;
            $this->code_execution_halted = true;
            $this->is_data_ready = true;
        }

        $this->response.=$result;
        if ($this->is_data_ready) {
            return $this->response;
        }

        return null;
    }

}

?>

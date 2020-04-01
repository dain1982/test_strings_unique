<?php
//https://github.com/dain1982/test_unique_strings.git

// 16:35 - 20:30 (3:55)
// примерно 0:10 - осмысление задания...
// примерно 0:30 - поиск неправильного алгоритма получения уникальных строк
// примерно 1:00 - осмысление факапа и поиск правильного алгоритма получения уникальных строк
// примерно 0:30 - класс DB, необходимая таблица для хранения результатов и тестирование добавления уникальных записей
// примерно 0:20 - написание комментариев (вкл.этот)
// примерно 1:00 - прикручивал phpStorm к gitHub'у (забыл-тупил)
// ну и всякие чаи-кофеи...

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_BASE', 'test');

class DB {
    private $conn;
    function __construct(){
        $this->connect();
    }

    function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_BASE);
        if ($this->conn->connect_errno) {
            die('DB connect error: ' . $this->conn->connect_error);
        } else {
            $this->query('SET NAMES utf8');
        }
    }

    function query($q) {
        $ret = false;
        if (strlen($q) > 0) {
            if (!$ret = $this->conn->query($q)) {
                die('DB query error: ' . $this->conn->error);
            }
        }
        return $ret;
    }

    function escape($str) {
        return $this->conn->escape_string($str);
    }

    function affected() {
        return $this->conn->affected_rows;
    }
}

class Unique_Strings {
    private $db;

    /**
     * regular expression for determining primitive substrings,
     * laying on the deepest level (a substrings without inner brackets)
     * @var string
     */
    private $substring_regex = '/{[^{]*?}/';

    /**
     * delimiter used inside brackets
     * @var string
     */
    private $substring_delimiter = '|';

    /**
     * an array with resulting unique strings
     * @var array
     */
    private $results = [];

    /**
     * DB table name
     * @var string
     */
    private $results_table_name = 'results';

    function __construct(){
        $this->db = new DB();
    }

    /**
     * Main function
     * @param $string string - a string to be processed
     * @return int - count of the unique strings successfully processed
     */
    function run($string)
    {
        $this->getUniqueStrings($string);
        $this->prepareTableForResults();
        $strings_saved = $this->saveResults();
        return $strings_saved;
    }

    /**
     * forms strings from deepest levels to the surface, i.e.:
     * one string 'a{b1|b2{b21|b22}}' transforms into two stings: 'a{b1|b2b21}' and 'a{b1|b2b22}'
     * then it transforms into 'ab1', 'ab2b21', 'ab1', 'ab2b22'
     * (yeah, 'ab1' are duplicates :( shame for me)
     * @param $string
     */
    function getUniqueStrings($string) {
        // find all primitive (deepest) substrings in brackets
        if (preg_match($this->substring_regex, $string, $matches)) {
            foreach ($matches as $match) {
                // clear substring from brackets
                $m = substr(substr($match, 1), 0, -1);
                // split into chunks for unique strings
                $sub_strings = explode($this->substring_delimiter, $m);
                // and get the unique string (for current depth level)
                foreach ($sub_strings as $sub_string) {
                    $s = str_replace($match, $sub_string, $string);
                    // go to the one depth level up of this substring
                    $this->getUniqueStrings($s);
                }
            }
        }
        // otherwise there are no brackets left, so save it in the results (if it wasn't already)
        else if (!in_array($string, $this->results)){
            $this->results[] = $string;
        }
    }

    /**
     * creates table in DB to put results in
     */
    function prepareTableForResults() {
        $sql = '
            CREATE TABLE IF NOT EXISTS `' . $this->results_table_name . '` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `result` text,
                `check_sum` char(32) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `check_sum` (`check_sum`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $this->db->query($sql);
    }

    /**
     * saves previously processed unique strings into DB with a MD5-checksum of each (for data integrity)
     * @return int - count of the unique strings inserted into DB
     */
    function saveResults() {
        $ret = 0;
        if (count($this->results) > 0) {
            $sql_parts = [];
            foreach ($this->results as $result) {
                $checksum = md5($result);
                $sql_parts[] = '(null,"' . $this->db->escape($result) . '","' . $checksum . '")';
            }

            if (count($sql_parts) > 0) {
                $sql = '
                    INSERT INTO `' . $this->results_table_name . '` 
                    VALUES ' . implode(',', $sql_parts) . ' 
                    ON DUPLICATE KEY UPDATE `id` = `id`';
                if ($this->db->query($sql)) {
                    $ret = $this->db->affected();
                }
            }
        }
        return $ret;
    }
}

//$input = '{a1{a|b{c1|c2|c3}}} ... {x|y{z1{z21|z22}}}';
$input = '{Пожалуйста,|Просто|Если сможете,} сделайте так, чтобы это {удивительное|крутое|простое|важное|бесполезное} тестовое предложение {изменялось {быстро|мгновенно|оперативно|правильно} случайным образом|менялось каждый раз}.';

$unique_strings = new Unique_Strings();
$results_count = $unique_strings->run($input);
echo 'unique strings count - ' . $results_count;
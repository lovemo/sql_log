<?php
header('content-type:text/html;charset=utf-8');

set_time_limit(0);
ini_set('memory_limit', -1);

defined('YW_LOG_FILE')         ?: define('YW_LOG_FILE', $argv[0]);
if ($argv[1] == '-h') {
    defined('YW_LOG_HELP')     ?: define('YW_LOG_HELP', $argv[1]);
} else {
    defined('YW_LOG_PATH')     ?: define('YW_LOG_PATH', $argv[1]);
}

defined('YW_LOG_DIR')      ?: define('YW_LOG_DIR',      $argv[2]);
defined('YW_LOG_DAY')      ?: define('YW_LOG_DAY',      $argv[3]);
defined('YW_LOG_DAY_VAL')  ?: define('YW_LOG_DAY_VAL',  $argv[4]);
defined('YW_LOG_TIME')     ?: define('YW_LOG_TIME',     $argv[5]);
defined('YW_LOG_TIME_VAL') ?: define('YW_LOG_TIME_VAL', $argv[6]);

checkParamsValid();

$day = substr(YW_LOG_DAY_VAL, 0, 2);
$files = scanFile(YW_LOG_DIR, YW_LOG_DAY_VAL);
if (empty($files)) {
    exit('没有' . $day . '号的日志');
}

$logResult = getLogResult($files, YW_LOG_TIME_VAL);

if (!empty($logResult)) {
    print_r($logResult);
}

/**
 * 获取日志结果
 * @param array $files   文件地址
 * @param int $timeParam 时间
 * @return array $logResult
 */
function getLogResult ($files, $timeParam = 0) {
    $separate = '--------------';
    $logContent = '';
    $logResult = [];
    foreach ($files as $file) {
        $f = fopen($file, "r");
        $fileName = basename($file);
        while (!feof($f)) {
            $line = fgets($f);
            if (stristr($line, $separate)) {
                if (empty($logContent)) {
                    continue;
                }
                $log = handleLog($logContent, $timeParam);
                if (!empty($log)) {
                    $logResult[$fileName][] = $log;
                }
                $logContent = '';
                continue;
            }
            $logContent .= $line;
        }
        $log = handleLog($logContent, $timeParam);
        $logContent = '';
        if (!empty($log)) {
            $logResult[$fileName][] = $log;
        }
        fclose($f);
    }
    return $logResult;
}

/**
 * 匹配日志信息
 * @param string $content  日志内容
 * @param float  $timeParam 时间
 * @return array $sqlLogArr
 */
function handleLog($content, $timeParam) {
    $preg = '/.*?\[.*?SQL.*?\].*?(SELECT[\s\S]*?)\s\[.*?RunTime:(.*?)s.*?\].*?/i';
    preg_match_all($preg, $content, $match);
    $pregAction = '/\[ (.*?) \] .*? (GET|POST) ([^ ]+)\n/i';
    preg_match_all($pregAction, $content, $matchAction);

    $sqlLogArr = [];
    if (!empty($match[1])) {
        foreach ($match[2] as $key => $value) {
            if ($value >= $timeParam) {
                if (!empty($matchAction[1][0])) {
                    $sqlLogArr['date'] = date('Y-m-d H:i:s', strtotime($matchAction[1][0]));
                    $sqlLogArr['action'] = $matchAction[3][0];
                }
                $sqlLogArr['info'][] = [
                    'sql' => $match[1][$key],
                    'time' => $match[2][$key],
                ];
            }
        }
    }
    return $sqlLogArr;
}

/**
 * 查找文件夹下所有文件
 * @param string $path 文件夹路径
 * @param int    $day  天数
 * @return array $result
 */
function scanFile($path, $day = 0) {
    global $result;
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            if (is_dir($path . '/' . $file)) {
                scanFile($path . '/' . $file);
            } else {
                if (empty($day)) {
                    $result[] = $path . '/' . basename($file);
                }
                if ($day == substr(basename($file), strrpos(basename($file), '.') - 2, 2)) {
                    $result[] = $path . '/' . basename($file);
                }
            }
        }
    }
    return $result;
}

/**
 * 判断参数是否合法
 */
function checkParamsValid() {
    if (!file_exists(YW_LOG_FILE)) {
        exit("Error: 执行文件路径错误");
    }
    if (defined('YW_LOG_HELP') && YW_LOG_HELP == '-h') {
        $info = [
            '  e.g.      php ./sqlLog.php -p path -d day -t time',
            '  -p        日志的目录路径',
            '  -d        指定文件的日期，查看所有输入0',
            '  -t        指定大于SQL的时间，查看所有输入0',
         ];
         $content = implode("\n", $info);
         printf("\n" . $content . "\n\n");
         exit;
    }
    if (defined('YW_LOG_PATH') && YW_LOG_PATH != '-p') {
        exit('Error: 第二个参数是 -p');
    }
    if (!is_dir(YW_LOG_DIR)) {
        exit('Error: 目录不存在');
    }
    if (YW_LOG_DAY != '-d') {
        exit('Error: 第四个参数必须是-d');
    }
    if (!is_numeric(YW_LOG_DAY_VAL)) {
        exit('Error: 第五个参数是某天');
    }
    if (YW_LOG_TIME != '-t') {
        exit('Error: 第六个参数必须是-t');
    }
    if (!is_numeric(YW_LOG_TIME_VAL)) {
        exit('Error: 第七个参数是大于SQL的时间');
    }
}
